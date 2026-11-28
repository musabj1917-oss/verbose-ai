#!/usr/bin/env python3
import json
import os
import math
import urllib.request
import subprocess

# 1. Load config
env_vars = {}
with open("/data/data/com.termux/files/home/verbose/.env") as f:
    for line in f:
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        env_vars[k.strip()] = v.strip().strip("'\"")

GEMINI_API_KEY = env_vars.get("GEMINI_API_KEY")
if not GEMINI_API_KEY:
    raise ValueError("GEMINI_API_KEY not found in .env")

CHUNKS_JSON = "/data/data/com.termux/files/home/verbose/data/citizenship_g8_english_chunks.json"
with open(CHUNKS_JSON, "r", encoding="utf-8") as f:
    chunks = json.load(f)

print(f"Loaded {len(chunks)} chunks to embed and ingest.")

# 2. Embed with gemini-embedding-2 in batches of 32
def embed_batch(texts):
    url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:batchEmbedContents?key={GEMINI_API_KEY}"
    reqs = [{"model": "models/gemini-embedding-2", "content": {"parts": [{"text": t[:8000]}]}, "outputDimensionality": 768} for t in texts]
    payload = json.dumps({"requests": reqs}).encode("utf-8")
    req = urllib.request.Request(url, data=payload, headers={"Content-Type": "application/json"})
    
    for attempt in range(1, 4):
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                data = json.loads(resp.read().decode("utf-8"))
            out = []
            for e in data.get("embeddings", []):
                v = e.get("values", [])
                if len(v) != 768:
                    raise ValueError(f"Expected 768 dims, got {len(v)}")
                norm = math.sqrt(sum(x * x for x in v))
                if norm <= 0:
                    norm = 1.0
                out.append([x / norm for x in v])
            return out
        except Exception as e:
            print(f"  Batch embed error (attempt {attempt}): {e}")
            if attempt == 3:
                raise e

all_embeddings = []
batch_size = 32
for i in range(0, len(chunks), batch_size):
    batch = chunks[i:i+batch_size]
    texts = [c["text"] for c in batch]
    print(f"Embedding batch {i//batch_size + 1}/{(len(chunks)-1)//batch_size + 1} ({len(texts)} chunks)...")
    vecs = embed_batch(texts)
    all_embeddings.extend(vecs)

print(f"Successfully computed {len(all_embeddings)} vector embeddings (768-dim, normalized).")

# Prepare data for PHP ingestion script to ensure identical PDO connection and transaction handling
payload_file = "/data/data/com.termux/files/home/verbose/data/english_textbook_ingest_payload.json"
book_meta = {
    "title": "Citizenship Education Grade 8 - Student Textbook (English)",
    "author": "Ministry of Education / Federal Democratic Republic of Ethiopia",
    "description": "Official Grade 8 Student Textbook for Citizenship Education (English curriculum). Covers Unit 1 (Virtue Ethics), Unit 2 (State and Government), Unit 3 (Democracy), and Unit 4 (Constitutionalism). OCR-transcribed from 127-page curriculum scan.",
    "chunks": []
}

for idx, (c, v) in enumerate(zip(chunks, all_embeddings)):
    v_str = "[" + ",".join([f"{x:.6f}" for x in v]) + "]"
    book_meta["chunks"].append({
        "chapter_title": c["chapter_title"],
        "section_number": idx + 1,
        "chunk_text": c["text"],
        "chunk_index": idx + 1,
        "embedding": v_str,
        "pdf_page": c["pdf_page"],
        "printed_page": c["printed_page"],
        "unit_title": c["unit"],
        "is_front_matter": c["is_front_matter"]
    })

with open(payload_file, "w", encoding="utf-8") as f:
    json.dump(book_meta, f, ensure_ascii=False)

print(f"Wrote ingestion payload to {payload_file}. Invoking PHP ingestion...")

php_cmd = """
require 'src/Database.php';
$payload = json_decode(file_get_contents('data/english_textbook_ingest_payload.json'), true);
$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed\n");
    exit(1);
}

// Check if book already exists
$stmt = $pdo->prepare("SELECT id FROM books WHERE title = ? LIMIT 1");
$stmt->execute([$payload['title']]);
$existingId = $stmt->fetchColumn();
if ($existingId) {
    echo "Book already exists with ID: {$existingId}. Updating chunks...\n";
    $pdo->prepare("DELETE FROM book_chunks WHERE book_id = ?")->execute([$existingId]);
    $bookId = $existingId;
    $pdo->prepare("UPDATE books SET chunks_count = ? WHERE id = ?")->execute([count($payload['chunks']), $bookId]);
} else {
    $stmt = $pdo->prepare("INSERT INTO books (title, author, description, chunks_count, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");
    $stmt->execute([$payload['title'], $payload['author'], $payload['description'], count($payload['chunks'])]);
    $bookId = $stmt->fetchColumn();
    echo "Created new book with ID: {$bookId}\n";
}

$chunkStmt = $pdo->prepare("INSERT INTO book_chunks (book_id, chapter_title, section_number, chunk_text, chunk_index, embedding, pdf_page, printed_page, unit_title, is_front_matter) VALUES (?, ?, ?, ?, ?, CAST(? AS vector), ?, ?, ?, ?)");

$pdo->beginTransaction();
foreach ($payload['chunks'] as $c) {
    $chunkStmt->execute([
        $bookId,
        $c['chapter_title'],
        $c['section_number'],
        $c['chunk_text'],
        $c['chunk_index'],
        $c['embedding'],
        $c['pdf_page'],
        $c['printed_page'],
        $c['unit_title'],
        $c['is_front_matter'] ? 1 : 0
    ]);
}
$pdo->commit();
echo "SUCCESS: Ingested {$payload['title']} (Book ID: {$bookId}) with " . count($payload['chunks']) . " chunks into Neon Postgres!\n";
"""

with open("/data/data/com.termux/files/home/verbose/bin/run_ingest.php", "w") as pf:
    pf.write("<?php\n" + php_cmd)

res = subprocess.run(["php", "bin/run_ingest.php"], capture_output=True, text=True)
print(res.stdout)
if res.stderr:
    print("STDERR:", res.stderr)
