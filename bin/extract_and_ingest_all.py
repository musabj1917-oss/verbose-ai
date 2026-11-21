#!/usr/bin/env python3
"""
Verbose AI - Automated Downloader & Ingestion Pipeline
Downloads primary subject curriculum PDFs from Camara Ethiopia MoE repository
and ingests each subject volume into Neon Lakebase Postgres with vector embeddings.
"""

import os
import sys
import json
import urllib.request
import subprocess
import io
import pypdf

SUBJECTS = [
    {
        "subject": "English",
        "title": "English Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for English Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/English/Primary/EnglishSBG8.pdf"
    },
    {
        "subject": "Mathematics",
        "title": "Mathematics Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Mathematics Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Maths/Primary/Amharic/MathSBG8.pdf"
    },
    {
        "subject": "Social Studies",
        "title": "Social Studies Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Social Studies Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Social-Studies/Primary/Amharic/So_St_SB8.pdf"
    },
    {
        "subject": "Information Technology",
        "title": "Information Technology (ICT) Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Information Technology Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/ICT/Primary/ICTSBG8.pdf"
    },
    {
        "subject": "General Science",
        "title": "General Science Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for General Science Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Chemistry/Primary/Afan%20Oromo/Saayinsii%20waliigalaaG8STB.pdf"
    },
    {
        "subject": "Civics & Moral Education",
        "title": "Civics and Ethical Education Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Civics and Ethical Education Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Civics/Primary/Afan%20Oromo/CivEthicalEduSBG8.pdf"
    },
    {
        "subject": "Afaan Oromoo",
        "title": "Afaan Oromoo Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Afaan Oromoo Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Afaan%20Oromoo/Primary/G8_all.pdf"
    },
    {
        "subject": "Amharic",
        "title": "Amharic Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Amharic Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Amharic/Primary/OR_Am_SB_G8.pdf"
    },
    {
        "subject": "Health & Physical Education",
        "title": "Health and Physical Education Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Health & Physical Education Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Health%20and%20Fitness/Primary/Amharic/FJQ%20GRADE8STB.pdf"
    },
    {
        "subject": "Performing and Visual Arts",
        "title": "Performing and Visual Arts Grade 8 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Performing & Visual Arts Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/PVA/Primary/Afan%20Oromo/PVA%20Grade%208%20TB.pdf"
    },
    {
        "subject": "Citizenship Education",
        "title": "Citizenship Education Grade 8 Textbook",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Citizenship Education Grade 8.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Citizenship/Civic%20Grade%20%208%20%20TB%20%26%20TG/%E1%8B%A8%E1%8B%9C%E1%8C%8D%E1%8A%90%E1%89%B5%20%E1%89%B5%E1%88%9D%E1%88%85%E1%88%AD%E1%89%B5-%208%E1%8A%9B%20%E1%8A%AD%E1%8D%8D%E1%88%8D%20%28TG%29.pdf"
    },
    {
        "subject": "Integrated Science",
        "title": "Integrated Science Grade 6 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Integrated Science Grade 6.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Integrated-Science/Primary/Afan%20Oromo/Integrated-Science_SBG6.PDF"
    },
    {
        "subject": "Environmental Science",
        "title": "Environmental Science Grade 6 Student Book",
        "author": "Ministry of Education / Oromia Education Bureau",
        "description": "Primary curriculum textbook for Environmental Science Grade 6.",
        "url": "https://camaraethiopia.org.et/OromiaPrimary/CEE_MoE_Plasma/content/Environmental-Science/Primary/Afan%20Oromo/Environmental-Science_SBG6.pdf"
    }
]

SCRATCH_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "scratch")
os.makedirs(SCRATCH_DIR, exist_ok=True)
TEMP_JSON = os.path.join(SCRATCH_DIR, "current_subject.json")

def process_subject(item, index, total):
    name = item["subject"]
    title = item["title"]
    url = item["url"]
    author = item["author"]
    desc = item["description"]

    print(f"\n========================================================")
    print(f"[{index}/{total}] Processing Subject: {name}")
    print(f"  Title: {title}")
    print(f"  Downloading from: {url}")
    print(f"========================================================")

    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"})
        with urllib.request.urlopen(req, timeout=90) as resp:
            pdf_bytes = resp.read()
        print(f"  Downloaded {len(pdf_bytes) / (1024*1024):.2f} MB. Extracting pages...")

        reader = pypdf.PdfReader(io.BytesIO(pdf_bytes))
        num_pages = len(reader.pages)
        print(f"  Total PDF pages: {num_pages}")

        chunks = []
        for p_idx, page in enumerate(reader.pages):
            page_num = p_idx + 1
            try:
                raw_text = page.extract_text() or ""
            except Exception as pe:
                raw_text = ""

            clean_text = raw_text.strip()
            if len(clean_text) < 25:
                continue

            # If page text is very large (> 1800 chars), split into sub-chunks
            if len(clean_text) > 1800:
                paragraphs = [p.strip() for p in clean_text.split("\n\n") if p.strip()]
                sub_chunk = ""
                part = 1
                for p in paragraphs:
                    if len(sub_chunk) + len(p) > 1500 and len(sub_chunk) > 200:
                        chunks.append({
                            "chapter": f"Page {page_num} Part {part}",
                            "section": page_num,
                            "text": f"[Page {page_num}]\n" + sub_chunk
                        })
                        sub_chunk = p
                        part += 1
                    else:
                        sub_chunk += ("\n\n" if sub_chunk else "") + p
                if sub_chunk:
                    chunks.append({
                        "chapter": f"Page {page_num}" + (f" Part {part}" if part > 1 else ""),
                        "section": page_num,
                        "text": f"[Page {page_num}]\n" + sub_chunk
                    })
            else:
                chunks.append({
                    "chapter": f"Page {page_num}",
                    "section": page_num,
                    "text": f"[Page {page_num}]\n" + clean_text
                })

        print(f"  Extracted {len(chunks)} text chunks from {num_pages} pages.")

        if not chunks:
            print(f"  Warning: No readable text extracted from {title}. Skipping.")
            return False

        # Save to temporary JSON for PHP ingestion
        book_payload = {
            "title": title,
            "author": author,
            "description": desc,
            "chunks": chunks
        }
        with open(TEMP_JSON, "w", encoding="utf-8") as f:
            json.dump(book_payload, f, ensure_ascii=False)

        # Execute PHP ingestion & vector embedding script
        php_script = os.path.join(os.path.dirname(os.path.abspath(__file__)), "ingest_extracted_book.php")
        res = subprocess.run(["php", php_script, TEMP_JSON], capture_output=True, text=True)
        print(res.stdout)
        if res.stderr:
            print("  STDERR:", res.stderr.strip())

        if res.returncode == 0:
            print(f"  Successfully ingested: {title}")
            return True
        else:
            print(f"  Failed ingestion for: {title}")
            return False

    except Exception as e:
        print(f"  Error processing {name}: {e}")
        return False
    finally:
        if os.path.exists(TEMP_JSON):
            try:
                os.remove(TEMP_JSON)
            except OSError:
                pass

def main():
    total = len(SUBJECTS)
    print(f"Starting ingestion of {total} primary curriculum subjects into Verbose AI...")
    success_count = 0
    for idx, item in enumerate(SUBJECTS, 1):
        ok = process_subject(item, idx, total)
        if ok:
            success_count += 1
    print(f"\n========================================================")
    print(f"Ingestion completed! {success_count}/{total} subjects successfully added.")
    print(f"========================================================")

if __name__ == "__main__":
    main()
