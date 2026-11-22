import urllib.request
import urllib.parse
import pypdf
import json
import os
import subprocess

base = "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/"

books = [
    {
        "name": "Citizenship_G8.pdf",
        "url_path": "content/Citizenship/Civic Grade  8  TB & TG/የዜግነት ትምህርት- 8ኛ ክፍል (TG).pdf",
        "title": "የዜግነት ትምህርት 8ኛ ክፍል (Citizenship Education Grade 8)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Citizenship Education Grade 8 in Amharic."
    },
    {
        "name": "Amharic_G8.pdf",
        "url_path": "content/Amharic/Primary/TG/AM_8_TG.pdf",
        "title": "አማርኛ 8ኛ ክፍል (Amharic Language Grade 8)",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Amharic Language Grade 8."
    },
    {
        "name": "ICT_G8.pdf",
        "url_path": "content/ICT/Primary/ICTSBG8.pdf",
        "title": "Information Technology (ICT) Grade 8",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Information Technology Grade 8."
    }
]

tmp_dir = "/data/data/com.termux/files/usr/tmp"
out_dir = "/data/data/com.termux/files/home/verbose/data/extracted_amharic"
os.makedirs(out_dir, exist_ok=True)

for b in books:
    local_pdf = os.path.join(tmp_dir, b["name"])
    print(f"\n==========================================")
    print(f"Downloading: {b['title']}")
    encoded_url = base + "/".join([urllib.parse.quote(seg) for seg in b["url_path"].split("/")])
    print(f"URL: {encoded_url}")

    if not os.path.exists(local_pdf):
        req = urllib.request.Request(encoded_url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=30) as resp, open(local_pdf, "wb") as f:
            f.write(resp.read())
        print(f"Downloaded {os.path.getsize(local_pdf)/(1024*1024):.2f} MB")
    else:
        print("Using cached PDF file.")

    reader = pypdf.PdfReader(local_pdf)
    chunks = []
    print(f"Total pages: {len(reader.pages)}")

    for idx, page in enumerate(reader.pages):
        page_num = idx + 1
        text = page.extract_text() or ""
        text = text.strip()
        lines = [l.strip() for l in text.split("\n") if l.strip()]
        cleaned = "\n".join(lines)
        if len(cleaned) < 40:
            continue

        chunks.append({
            "chapter": f"Page {page_num}",
            "section": page_num,
            "text": f"[{b['title']} - Page {page_num}]\n{cleaned}"
        })

    print(f"Extracted {len(chunks)} chunks.")

    json_path = os.path.join(out_dir, f"{b['name']}.json")
    with open(json_path, "w", encoding="utf-8") as jf:
        json.dump({
            "title": b["title"],
            "author": b["author"],
            "description": b["description"],
            "chunks": chunks
        }, jf, ensure_ascii=False, indent=2)

    print(f"Ingesting into Neon Postgres via ingest_extracted_book.php...")
    cmd = ["php", "/data/data/com.termux/files/home/verbose/bin/ingest_extracted_book.php", json_path]
    res = subprocess.run(cmd, capture_output=True, text=True)
    print(res.stdout)
    if res.stderr:
        print("STDERR:", res.stderr)

print("\nDone ingesting all remaining Grade 8 books!")
