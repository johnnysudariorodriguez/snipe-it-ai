# Snipe-IT AI Python Service

FastAPI service for document ingestion and semantic search using ChromaDB and OpenAI embeddings.

Install:

```bash
python -m venv .venv
source .venv/bin/activate  # or .\.venv\Scripts\activate on Windows
pip install -r requirements.txt
```

Run (example):

```bash
export OPENAI_API_KEY=...
uvicorn main:app --host 0.0.0.0 --port 8000
```