"""
Snipe-IT AI RAG - FastAPI Vector Service (UPDATED FIXED VERSION)
"""

import os
import time
from typing import List, Optional
from datetime import datetime
from io import BytesIO

from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

import chromadb

from docx import Document as DocxDocument
from PyPDF2 import PdfReader

# OpenAI (new SDK style recommended, but keeping compatibility safe)
from openai import OpenAI

try:
    import tiktoken
except Exception:
    tiktoken = None


# =========================
# ENV SETUP
# =========================
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")
PERSIST_DIR = os.getenv("CHROMA_PERSIST_DIR", "./chroma_db")

# Initialize OpenAI client lazily so the app can start even if the key is missing.
client = None
if OPENAI_API_KEY:
    try:
        client = OpenAI(api_key=OPENAI_API_KEY)
    except Exception as e:
        print("OpenAI client init error:", e)
        client = None
else:
    print("Warning: OPENAI_API_KEY not set; embedding endpoints will return a clear error instead of crashing the app.")


# =========================
# CHROMA DB (FIXED)
# =========================
try:
    chroma_client = chromadb.PersistentClient(path=PERSIST_DIR)
    collection = chroma_client.get_or_create_collection(name="documents")
except Exception as e:
    print("ChromaDB initialization error:", e)
    chroma_client = None
    collection = None


# =========================
# FASTAPI APP
# =========================
app = FastAPI(title="Snipe-IT AI Vector Service")

origins = os.getenv("ALLOWED_ORIGINS", "*")
origins_list = [o.strip() for o in origins.split(",")] if origins else ["*"]

app.add_middleware(
    CORSMiddleware,
    allow_origins=origins_list,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# =========================
# UTIL: TOKEN COUNT
# =========================
def _num_tokens(text: str, model: str = "text-embedding-3-small") -> int:
    if tiktoken:
        enc = tiktoken.encoding_for_model(model)
        return len(enc.encode(text))
    return len(text.split())


# =========================
# TEXT CHUNKING
# =========================
def chunk_text(text: str, max_tokens: int = 1200) -> List[str]:
    if not text:
        return []

    paragraphs = text.split("\n\n")
    chunks = []
    current = []
    current_len = 0

    for p in paragraphs:
        length = len(p.split())

        if current_len + length > max_tokens:
            chunks.append("\n\n".join(current))
            current = [p]
            current_len = length
        else:
            current.append(p)
            current_len += length

    if current:
        chunks.append("\n\n".join(current))

    return chunks


# =========================
# FILE TEXT EXTRACTION
# =========================
def extract_text_from_upload(file: UploadFile) -> str:
    filename = file.filename or "document"
    ext = filename.split(".")[-1].lower()
    contents = file.file.read()

    bio = BytesIO(contents)

    if ext == "pdf":
        reader = PdfReader(bio)
        return "\n\n".join([(p.extract_text() or "") for p in reader.pages])

    if ext in ("docx", "doc"):
        doc = DocxDocument(bio)
        return "\n\n".join([p.text for p in doc.paragraphs])

    # fallback txt
    try:
        return contents.decode("utf-8")
    except Exception:
        raise HTTPException(status_code=400, detail="Unsupported file type")


# =========================
# REQUEST MODELS
# =========================
class QueryRequest(BaseModel):
    query: str
    top_k: Optional[int] = 5


# =========================
# UPLOAD → VECTOR DB
# =========================
@app.post("/add-doc")
async def add_doc(file: UploadFile = File(...)):
    start = time.time()

    if client is None:
        raise HTTPException(status_code=503, detail="OpenAI client not configured. Set OPENAI_API_KEY to enable embeddings.")
    if collection is None:
        raise HTTPException(status_code=503, detail="ChromaDB not initialized. Check server logs for initialization errors.")

    text = extract_text_from_upload(file)

    if not text.strip():
        raise HTTPException(status_code=400, detail="Empty document")

    chunks = chunk_text(text)

    print(f"[add-doc] Received file={file.filename}; created_chunks={len(chunks)}")

    ids = []
    docs = []
    metas = []

    for i, chunk in enumerate(chunks):
        ids.append(f"{file.filename}_{int(start)}_{i}")
        docs.append(chunk)
        metas.append({
            "source": file.filename,
            "uploaded_at": datetime.utcnow().isoformat(),
            "chunk": i
        })

    # =========================
    # EMBEDDINGS (OPENAI)
    # =========================
    emb_response = client.embeddings.create(
        model="text-embedding-3-small",
        input=docs
    )

    embeddings = [e.embedding for e in emb_response.data]

    print(f"[add-doc] Embeddings generated: {len(embeddings)}")

    # =========================
    # STORE IN CHROMA
    # =========================
    collection.add(
        ids=ids,
        documents=docs,
        metadatas=metas,
        embeddings=embeddings
    )

    print(f"[add-doc] Inserted IDs: {ids}")

    elapsed = time.time() - start

    return {
        "status": "ok",
        "inserted": len(ids),
        "elapsed": round(elapsed, 2)
    }


# =========================
# VECTOR SEARCH
# =========================
@app.post("/query")
async def query(q: QueryRequest):
    if not q.query.strip():
        raise HTTPException(status_code=400, detail="Empty query")

    if client is None:
        raise HTTPException(status_code=503, detail="OpenAI client not configured. Set OPENAI_API_KEY to enable embeddings.")
    if collection is None:
        raise HTTPException(status_code=503, detail="ChromaDB not initialized. Check server logs for initialization errors.")

    emb = client.embeddings.create(
        model="text-embedding-3-small",
        input=[q.query]
    )

    q_emb = emb.data[0].embedding

    print(f"[query] Generated query embedding (len={len(q_emb)}) for query='{q.query}'")

    results = collection.query(
        query_embeddings=[q_emb],
        n_results=q.top_k,
        include=["documents", "metadatas", "distances"]
    )

    docs = results.get("documents", [[]])[0]
    metas = results.get("metadatas", [[]])[0]
    dists = results.get("distances", [[]])[0]

    output = []

    for i in range(len(docs)):
        output.append({
            "document": docs[i],
            "metadata": metas[i] if metas else {},
            "distance": dists[i] if dists else None
        })

    print(f"[query] Returning {len(output)} results for query='{q.query}'")

    return {
        "status": "ok",
        "query": q.query,
        "results": output
    }


# =========================
# RUN SERVER
# =========================
if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        app,
        host=os.getenv("HOST", "0.0.0.0"),
        port=int(os.getenv("PORT", 8000))
    )