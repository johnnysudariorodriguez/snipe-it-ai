"""
Snipe-IT AI RAG - FastAPI Vector Service (CLEAN V2)
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
from openai import OpenAI

from docx import Document as DocxDocument
from PyPDF2 import PdfReader


# =========================
# ENV
# =========================
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")
PERSIST_DIR = os.getenv("CHROMA_PERSIST_DIR", "./chroma_db")


# =========================
# FAIL FAST (IMPORTANT FIX)
# =========================
if not OPENAI_API_KEY:
    raise RuntimeError("OPENAI_API_KEY is missing. Cannot start RAG service.")

client = OpenAI(api_key=OPENAI_API_KEY)


# =========================
# CHROMA DB
# =========================
try:
    chroma_client = chromadb.PersistentClient(path=PERSIST_DIR)
    collection = chroma_client.get_or_create_collection("documents")
except Exception as e:
    raise RuntimeError(f"ChromaDB init failed: {e}")


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

    text = extract_text(file).strip()
    if not text:
        raise HTTPException(status_code=400, detail="Empty document")

    chunks = chunk_text(text)

    embeddings = embed(chunks)

    ids = []
    metas = []

    for i in range(len(chunks)):
        ids.append(f"{file.filename}_{int(start)}_{i}")
        metas.append({
            "source": file.filename,
            "chunk": i,
            "created_at": datetime.utcnow().isoformat()
        })

    collection.add(
        ids=ids,
        documents=chunks,
        embeddings=embeddings,
        metadatas=metas
    )

    return {
        "status": "ok",
        "file": file.filename,
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