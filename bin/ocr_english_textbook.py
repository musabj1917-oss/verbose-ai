#!/usr/bin/env python3
import os
import sys
import json
import subprocess
import time
import re

PDF_PATH = "/sdcard/Download/grade 8-citizenship education_fetena_net_3167.pdf"
OUT_JSON = "/data/data/com.termux/files/home/verbose/data/citizenship_g8_english_raw_ocr.json"
TMP_DIR = "/data/data/com.termux/files/usr/tmp/ocr_g8_eng"

os.makedirs(TMP_DIR, exist_ok=True)
os.makedirs(os.path.dirname(OUT_JSON), exist_ok=True)

# Load existing if partially completed
pages_data = {}
if os.path.exists(OUT_JSON):
    try:
        with open(OUT_JSON, "r", encoding="utf-8") as f:
            pages_data = json.load(f)
            print(f"Loaded {len(pages_data)} previously OCR'd pages from cache.")
    except Exception as e:
        print(f"Cache load warning: {e}")

total_pages = 127
start_total = time.time()

for p in range(1, total_pages + 1):
    p_str = str(p)
    if p_str in pages_data and len(pages_data[p_str]["raw_text"]) > 20:
        continue

    t0 = time.time()
    prefix = os.path.join(TMP_DIR, f"page_{p}")
    # Render PDF page to PNG at 150 DPI
    subprocess.run(["pdftoppm", "-png", "-r", "150", "-f", str(p), "-l", str(p), PDF_PATH, prefix], check=True)
    
    # Locate generated PNG file
    candidates = [f for f in os.listdir(TMP_DIR) if f.startswith(f"page_{p}-") and f.endswith(".png")]
    if not candidates:
        print(f"ERROR: No image generated for page {p}")
        continue

    img_path = os.path.join(TMP_DIR, candidates[0])
    res = subprocess.run(["tesseract", img_path, "stdout", "-l", "eng"], capture_output=True, text=True, check=True)
    raw_ocr = res.stdout or ""
    
    # Remove image to save disk
    try:
        os.remove(img_path)
    except OSError:
        pass

    printed_page = p - 7
    is_front = (p <= 7)

    pages_data[p_str] = {
        "pdf_page": p,
        "printed_page": printed_page,
        "is_front_matter": is_front,
        "raw_text": raw_ocr,
        "char_count": len(raw_ocr)
    }

    elapsed = time.time() - t0
    print(f"[{p}/{total_pages}] PDF p.{p} (printed p.{printed_page}): {len(raw_ocr)} chars in {elapsed:.1f}s", flush=True)

    # Save checkpoint every 10 pages
    if p % 10 == 0 or p == total_pages:
        with open(OUT_JSON, "w", encoding="utf-8") as f:
            json.dump(pages_data, f, ensure_ascii=False, indent=2)

print(f"\nCompleted OCR for all {len(pages_data)} pages in {time.time()-start_total:.1f}s.")
with open(OUT_JSON, "w", encoding="utf-8") as f:
    json.dump(pages_data, f, ensure_ascii=False, indent=2)
print(f"Saved full OCR cache to {OUT_JSON}")
