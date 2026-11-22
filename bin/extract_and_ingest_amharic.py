import pypdf
import json
import os
import subprocess
import sys

books = [
    {
        "filename": "So_St_SB8.pdf",
        "title": "የህብረተሰብ ትምህርት 8ኛ ክፍል (Social Studies Grade 8)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Social Studies Grade 8 in Amharic.",
        "grade": 8
    },
    {
        "filename": "MathSBG8.pdf",
        "title": "ሒሳብ 8ኛ ክፍል (Mathematics Grade 8)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Mathematics Grade 8 in Amharic.",
        "grade": 8
    },
    {
        "filename": "አጠቃላይ_ሳይንስ_STBG8.pdf",
        "title": "አጠቃላይ ሳይንስ 8ኛ ክፍል (General Science Grade 8)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for General Science Grade 8 in Amharic.",
        "grade": 8
    },
    {
        "filename": "8ኛ ክፍል  ሥነ ጠጥበብ.pdf",
        "title": "ስነ-ጥበባት 8ኛ ክፍል (Performing & Visual Arts Grade 8)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for PVA Grade 8 in Amharic.",
        "grade": 8
    },
    {
        "filename": "FJQ GRADE8STB.pdf",
        "title": "የጤናና ሰውነት ማጎልመሻ 8ኛ ክፍል (Health & Physical Education Grade 8)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Health & Physical Education Grade 8 in Amharic.",
        "grade": 8
    },
    {
        "filename": "ግብረገብ የተማሪ መጽሐፍ 6ኛ ክፍል.pdf",
        "title": "ግብረ ገብ 6ኛ ክፍል (Civics & Ethical Education Grade 6)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Civics & Ethical Education Grade 6 in Amharic.",
        "grade": 6
    },
    {
        "filename": "Environmental-Science_SBG6.pdf",
        "title": "አካባቢ ሳይንስ 6ኛ ክፍል (Environmental Science Grade 6)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Environmental Science Grade 6 in Amharic.",
        "grade": 6
    }
]

tmp_dir = "/data/data/com.termux/files/usr/tmp"
out_dir = "/data/data/com.termux/files/home/verbose/data/extracted_amharic"
os.makedirs(out_dir, exist_ok=True)

for b in books:
    pdf_path = os.path.join(tmp_dir, b["filename"])
    if not os.path.exists(pdf_path):
        print(f"Skipping {b['title']}: {pdf_path} not found.")
        continue

    print(f"\n==========================================")
    print(f"Extracting: {b['title']}")
    print(f"File: {pdf_path}")

    try:
        reader = pypdf.PdfReader(pdf_path)
    except Exception as e:
        print(f"Failed to read PDF: {e}")
        continue

    chunks = []
    total_pages = len(reader.pages)
    print(f"Total pages: {total_pages}")

    for idx, page in enumerate(reader.pages):
        page_num = idx + 1
        text = page.extract_text() or ""
        text = text.strip()

        # Clean excess empty whitespace
        lines = [line.strip() for line in text.split("\n") if line.strip()]
        cleaned_text = "\n".join(lines)

        if len(cleaned_text) < 40:
            continue

        # Header for the chunk
        labeled_text = f"[{b['title']} - Page {page_num}]\n{cleaned_text}"

        chunks.append({
            "chapter": f"Page {page_num}",
            "section": page_num,
            "text": labeled_text
        })

    print(f"Extracted {len(chunks)} chunks.")

    # Save to JSON
    json_path = os.path.join(out_dir, f"{b['filename']}.json")
    payload = {
        "title": b["title"],
        "author": b["author"],
        "description": b["description"],
        "chunks": chunks
    }

    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)

    print(f"Saved {json_path}. Ingesting into Neon Postgres...")

    # Run ingest script
    cmd = ["php", "/data/data/com.termux/files/home/verbose/bin/ingest_extracted_book.php", json_path]
    res = subprocess.run(cmd, capture_output=True, text=True)
    print(res.stdout)
    if res.stderr:
        print("STDERR:", res.stderr)

print("\nAll Amharic textbooks processed successfully!")
