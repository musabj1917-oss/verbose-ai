#!/usr/bin/env python3
import json
import math
import urllib.request
import subprocess
import time
import os

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

# 1. Fetch chunks for book 30
res = subprocess.run(["php", "bin/fetch_book30.php"], capture_output=True, text=True)
if res.returncode != 0:
    print("Fetch error:", res.stderr)
    exit(1)

chunks = json.loads(res.stdout)
print(f"Fetched {len(chunks)} chunks for book 30 to re-embed with gemini-embedding-2.")

CACHE_FILE = "data/book30_vecs_cache.json"
cached_vecs = {}
if os.path.exists(CACHE_FILE):
    try:
        with open(CACHE_FILE, "r") as f:
            cached_vecs = json.load(f)
            print(f"Loaded {len(cached_vecs)} cached embeddings.")
    except Exception:
        pass

def embed_batch(texts):
    url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:batchEmbedContents?key={GEMINI_API_KEY}"
    reqs = [{"model": "models/gemini-embedding-2", "content": {"parts": [{"text": t[:8000]}]}, "outputDimensionality": 768} for t in texts]
    payload = json.dumps({"requests": reqs}).encode("utf-8")
    req = urllib.request.Request(url, data=payload, headers={"Content-Type": "application/json"})
    
    for attempt in range(1, 6):
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
            if "429" in str(e) or "Too Many Requests" in str(e):
                print("  Rate limit hit. Sleeping 20s before retry...")
                time.sleep(20)
            else:
                time.sleep(3)
            if attempt == 5:
                raise e

all_embeddings = []
batch_size = 32

for i in range(0, len(chunks), batch_size):
    batch = chunks[i:i+batch_size]
    batch_idx = i // batch_size + 1
    total_batches = (len(chunks) - 1) // batch_size + 1

    # Check if all chunks in this batch are already cached
    batch_cached = True
    batch_vecs = []
    for c in batch:
        cid = str(c["id"])
        if cid in cached_vecs:
            batch_vecs.append(cached_vecs[cid])
        else:
            batch_cached = False
            break

    if batch_cached:
        print(f"Batch {batch_idx}/{total_batches} loaded from cache.")
        all_embeddings.extend(batch_vecs)
        continue

    texts = [c["chunk_text"] for c in batch]
    print(f"Embedding batch {batch_idx}/{total_batches} ({len(texts)} chunks)...")
    vecs = embed_batch(texts)
    all_embeddings.extend(vecs)

    for c, v in zip(batch, vecs):
        cached_vecs[str(c["id"])] = v

    with open(CACHE_FILE, "w") as f:
        json.dump(cached_vecs, f)

    time.sleep(2)  # gentle spacing between batches to prevent 429

print(f"Finished embedding {len(all_embeddings)} chunks for book 30.")

# Write update payload
updates = []
for c, v in zip(chunks, all_embeddings):
    v_str = "[" + ",".join([f"{x:.6f}" for x in v]) + "]"
    updates.append({"id": c["id"], "embedding": v_str})

with open("data/book30_reembed.json", "w") as f:
    json.dump(updates, f)

res2 = subprocess.run(["php", "bin/update_book30.php"], capture_output=True, text=True)
print(res2.stdout)
if res2.stderr:
    print("STDERR:", res2.stderr)
