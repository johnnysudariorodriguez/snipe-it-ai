"""
Snipe-IT AI RAG - FastAPI Vector Service (CLEAN V2)
"""

import os
import time
from typing import List, Optional
from datetime import datetime
import uuid
from io import BytesIO

from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

import chromadb
from openai import OpenAI

from docx import Document as DocxDocument
from PyPDF2 import PdfReader


# =========================
# ENV
# =========================
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")
if OPENAI_API_KEY:
    OPENAI_API_KEY = OPENAI_API_KEY.strip().strip('"').strip("'")
    OPENAI_API_KEY = OPENAI_API_KEY.lstrip('= ')

CHROMA_HOST = os.getenv("CHROMA_HOST", "20.195.43.211")
CHROMA_PORT = int(os.getenv("CHROMA_PORT", "8000"))
CHROMA_USE_SSL = os.getenv("CHROMA_USE_SSL", "false").lower() in ("1", "true", "yes")


# =========================
# FAIL FAST (IMPORTANT FIX)
# =========================
if not OPENAI_API_KEY:
    raise RuntimeError("OPENAI_API_KEY is missing. Cannot start RAG service.")

client = OpenAI(api_key=OPENAI_API_KEY)


# =========================
# CHROMA DB (Remote HTTP)
# =========================
print(f"Connecting to ChromaDB at {CHROMA_HOST}:{CHROMA_PORT}")
try:
    chroma_client = chromadb.HttpClient(
        host=CHROMA_HOST,
        port=CHROMA_PORT,
        ssl=CHROMA_USE_SSL
    )
    # test connection by accessing the collection
    collection = chroma_client.get_or_create_collection("documents")
except Exception as e:
    raise RuntimeError(
        f"ChromaDB init failed: {e}. Ensure CHROMA_HOST/CHROMA_PORT are correct and the Chroma server is reachable."
    )


# =========================
# FASTAPI
# =========================
app = FastAPI(title="Snipe-IT AI Vector Service")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# =========================
# REQUEST MODEL
# =========================
class QueryRequest(BaseModel):
    query: str
    top_k: Optional[int] = 5


# =========================
# TEXT EXTRACTION
# =========================
def extract_text(file: UploadFile) -> str:
    ext = file.filename.split(".")[-1].lower()
    data = file.file.read()
    bio = BytesIO(data)

    if ext == "pdf":
        reader = PdfReader(bio)
        return "\n\n".join([p.extract_text() or "" for p in reader.pages])

    if ext in ["docx", "doc"]:
        doc = DocxDocument(bio)
        return "\n\n".join([p.text for p in doc.paragraphs])

    return data.decode("utf-8", errors="ignore")


# =========================
# SIMPLE CHUNKING (IMPROVED)
# =========================
def chunk_text(text: str, size: int = 800) -> List[str]:
    words = text.split()
    chunks = []

    for i in range(0, len(words), size):
        chunks.append(" ".join(words[i:i + size]))

    return chunks


# =========================
# EMBEDDING FUNCTION (SAFE)
# =========================
def embed(texts: List[str]):
    try:
        res = client.embeddings.create(
            model="text-embedding-3-small",
            input=texts
        )
        return [r.embedding for r in res.data]
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Embedding failed: {str(e)}")


# =========================
# ADD DOCUMENT
# =========================
@app.post("/add-doc")
async def add_doc(file: UploadFile = File(...)):
    start = time.time()

    # generate a stable external id for this file upload
    file_id = str(uuid.uuid4())

    text = extract_text(file).strip()
    if not text:
        raise HTTPException(status_code=400, detail="Empty document")

    chunks = chunk_text(text)

    embeddings = embed(chunks)

    ids = []
    metas = []

    for i in range(len(chunks)):
        ids.append(f"{file_id}_{i}")
        metas.append({
            "source": file.filename,
            "file_id": file_id,
            "chunk": i,
            "created_at": datetime.utcnow().isoformat()
        })

    collection.add(
        ids=ids,
        documents=chunks,
        embeddings=embeddings,
        metadatas=metas
    )
    # attempt to persist if client supports it
    try:
        if hasattr(chroma_client, 'persist'):
            chroma_client.persist()
    except Exception:
        # best-effort: don't fail the request if persisting isn't available
        pass

    return {
        "status": "ok",
        "file": file_id,
        "file_name": file.filename,
        "chunks": len(chunks),
        "time": round(time.time() - start, 2)
    }


# =========================
# QUERY VECTOR DB
# =========================
@app.post("/query")
async def query(req: QueryRequest):
    if not req.query.strip():
        raise HTTPException(status_code=400, detail="Empty query")

    query_embedding = embed([req.query])[0]

    results = collection.query(
        query_embeddings=[query_embedding],
        n_results=req.top_k,
        include=["documents", "metadatas", "distances"]
    )

    docs = results["documents"][0]

    return {
        "status": "ok",
        "query": req.query,
        "results": [
            {
                "text": docs[i],
                "meta": results["metadatas"][0][i],
                "distance": results["distances"][0][i]
            }
            for i in range(len(docs))
        ]
    }


# =========================
# HEALTH CHECK
# =========================
@app.get("/health")
def health():
    return {
        "status": "running",
        "openai": OPENAI_API_KEY is not None,
        "chroma": True
    }


class DeleteDocRequest(BaseModel):
    file_id: Optional[str] = None
    file_name: Optional[str] = None


@app.post("/delete-doc")
async def delete_doc(req: DeleteDocRequest):
    if not req.file_id and not req.file_name:
        raise HTTPException(status_code=400, detail="file_id or file_name required")

    deleted_count = 0
    # Try to delete by file_id metadata first
    try:
        if req.file_id:
            # try direct delete by metadata filter
            try:
                collection.delete(where={"file_id": req.file_id})
                deleted_count = 1
            except Exception:
                # fallback: fetch matching ids and delete them
                try:
                    res = collection.get(where={"file_id": req.file_id}, include=["ids"])
                    ids = res.get("ids", [])
                    if ids and len(ids) and isinstance(ids[0], list):
                        ids_list = ids[0]
                    else:
                        ids_list = ids
                    if ids_list:
                        collection.delete(ids=ids_list)
                        deleted_count = len(ids_list)
                except Exception:
                    pass

        # Also attempt deletion by original file name if provided
        if req.file_name:
            try:
                collection.delete(where={"source": req.file_name})
                deleted_count = deleted_count or 1
            except Exception:
                try:
                    res = collection.get(where={"source": req.file_name}, include=["ids"])
                    ids = res.get("ids", [])
                    if ids and len(ids) and isinstance(ids[0], list):
                        ids_list = ids[0]
                    else:
                        ids_list = ids
                    if ids_list:
                        collection.delete(ids=ids_list)
                        deleted_count = deleted_count or len(ids_list)
                except Exception:
                    pass

        try:
            if hasattr(chroma_client, 'persist'):
                chroma_client.persist()
        except Exception:
            pass

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Delete failed: {str(e)}")

    return {"status": "ok", "deleted": deleted_count}