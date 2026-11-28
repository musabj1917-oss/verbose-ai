#!/usr/bin/env python3
import json
import math
import os
import re
import subprocess
import sys
import time
import urllib.request

# Load .env
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

GEEZ_DIGITS = {
    '፩': 1, '፪': 2, '፫': 3, '፬': 4, '፭': 5, '፮': 6, '፯': 7, '፰': 8, '፱': 9,
    '፲': 10, '፳': 20, '፴': 30, '፵': 40, '፶': 50, '፷': 60, '፸': 70, '፹': 80, '፺': 90,
    '፻': 100, '፼': 10000
}

def geez_to_int(s):
    s = s.strip()
    if not s or not all(ch in GEEZ_DIGITS for ch in s):
        return None
    total = 0
    current = 0
    for ch in s:
        val = GEEZ_DIGITS[ch]
        if val >= 100:
            if current == 0:
                current = 1
            total += current * val
            current = 0
        else:
            current += val
    total += current
    return total if total > 0 else None

def detect_printed_page(lines, pdf_page, slug, prev_printed):
    # Inspect last 4 non-empty lines for page number
    tail = [l.strip() for l in lines[-4:] if l.strip()]
    for l in reversed(tail):
        val = None
        if slug == "amharic":
            val = geez_to_int(l)
        if val is None and re.fullmatch(r"\d{1,3}", l):
            val = int(l)
            # For General Science, top header has "Page <PDF_PAGE>", bottom has printed page
            if slug == "general_science" and val == pdf_page and pdf_page > 6:
                val = None
        if val is not None and 1 <= val <= 300:
            # Enforce monotonicity once content pages start (prevents math diagram digits like '1' or '2' being treated as page numbers)
            if prev_printed > 0 and (val < prev_printed or val > prev_printed + 10):
                continue
            return val
    if prev_printed is not None and prev_printed > 0:
        return prev_printed + 1
    return 0

def strip_headers_footers(raw_text, slug, pdf_page, printed_page):
    t = raw_text.replace("\x08", "")
    # Common watermarks
    t = re.sub(r"^.*Ethiofetena\.com.*$", "", t, flags=re.IGNORECASE | re.MULTILINE)
    t = re.sub(r"^.*Fetena\.net.*$", "", t, flags=re.IGNORECASE | re.MULTILINE)

    lines = [l.rstrip() for l in t.splitlines()]
    cleaned = []
    for i, l in enumerate(lines):
        s = l.strip()
        if not s:
            if cleaned and cleaned[-1] != "":
                cleaned.append("")
            continue
        # Strip repeated headers/footers
        if slug == "amharic":
            if s in ["አማርኛ 8ኛ ክፍል", "የተማሪ መጽሐፍ"] or re.fullmatch(r"ምዕራፍ\s+\d+", s):
                continue
            if geez_to_int(s) is not None and i >= len(lines) - 4:
                continue
        elif slug == "social_studies":
            if s in ["Social Studies", "GRADE 8", "GRADE 8 Student Textbook", "Student Textbook"]:
                continue
        elif slug == "general_science":
            if s in ["General Science", "GRADE 8", "Student TextBook", "Student Textbook"]:
                continue
            if re.fullmatch(r"Page\s+\d+", s, re.IGNORECASE) and i < 6:
                continue
        elif slug == "english":
            if s == "Grade 8" and i < 4:
                continue
            if re.match(r"^Unit\s+\d+\s+[A-Za-z\s]+$", s) and i < 4:
                continue
        # Strip trailing standalone printed page number
        if printed_page > 0 and s == str(printed_page) and i >= len(lines) - 3:
            continue
        cleaned.append(s)

    while cleaned and cleaned[-1] == "":
        cleaned.pop()
    return "\n".join(cleaned).strip()

def detect_unit_and_section(text, slug, cur_unit, cur_sec):
    lines = [l.strip() for l in text.splitlines() if l.strip()]
    new_unit = cur_unit
    new_sec = cur_sec

    for idx, l in enumerate(lines[:12]):
        if slug == "amharic":
            m_u = re.match(r"^ምዕራፍ\s+([፩-፼\d]+)(?:\s*[:፡-]?\s*(.*))?$", l)
            if m_u:
                new_unit = l
                if idx + 1 < len(lines) and len(lines[idx+1]) < 60 and not m_u.group(2):
                    new_unit = f"{l}: {lines[idx+1]}"
            elif re.match(r"^(?:ንባብ|ሰዋስው|ቃላት|ጽሕፈት|ማዳመጥ|መናገር)\b", l) and len(l) < 50:
                new_sec = l
        else:
            # English-medium textbooks (Math, Social Studies, General Science, English)
            if re.match(r"^UNIT\s+(?:\d+|ONE|TWO|THREE|FOUR|FIVE|SIX|SEVEN|EIGHT|NINE|TEN)\b", l, re.IGNORECASE) and len(l) < 90:
                if l.upper() == "UNIT" and idx + 1 < len(lines):
                    title_part = lines[idx+2] if idx + 2 < len(lines) else ""
                    new_unit = f"UNIT {lines[idx+1]} {title_part}".strip()
                else:
                    new_unit = l
                new_sec = new_unit
            elif re.match(r"^\d+\.\d+(?:\.\d+)?\s+[A-Za-z].{2,75}$", l):
                new_sec = l
            elif re.match(r"^(?:Lesson\s+\d+|Unit\s+Summary|Summary|Glossary|Review\s+(?:Questions|Exercise)|Remember|Definition)\b", l, re.IGNORECASE) and len(l) < 70:
                new_sec = l

    return new_unit, new_sec

def chunk_page_text(text, unit_title, section_heading, pdf_page, printed_page, is_front):
    if is_front:
        header = f"Section: Front Matter\n[Front Matter | PDF Page {pdf_page}]\n\n"
        return [{
            "pdf_page": pdf_page,
            "printed_page": printed_page,
            "unit_title": "Front Matter",
            "section_heading": "Front Matter",
            "chapter_title": f"Front Matter - PDF Page {pdf_page}",
            "chunk_text": header + text,
            "is_front_matter": True
        }]

    header = f"Section: {section_heading}\n[{unit_title} | Printed Page {printed_page} (PDF p. {pdf_page}) | {section_heading}]\n\n"
    chapter_title = f"{unit_title} - Printed Page {printed_page} (PDF p. {pdf_page}): {section_heading}"

    # Keep numbered lists, Remember/Definition boxes, and tables intact if <= 2200 chars
    has_protected_block = bool(
        re.search(r"(?:^|\n)(?:1\.|Remember|Definition|Note|Table\s+\d)", text, re.IGNORECASE)
    )
    if len(text) <= 1800 or (has_protected_block and len(text) <= 2400):
        return [{
            "pdf_page": pdf_page,
            "printed_page": printed_page,
            "unit_title": unit_title,
            "section_heading": section_heading,
            "chapter_title": chapter_title[:240],
            "chunk_text": header + text,
            "is_front_matter": False
        }]

    # Split long pages by paragraphs with small overlap, keeping heading prepended to both
    paras = [p.strip() for p in text.split("\n\n") if p.strip()]
    if len(paras) <= 1:
        paras = [p.strip() for p in text.splitlines() if p.strip()]
    mid = max(1, len(paras) // 2)
    part1 = "\n\n".join(paras[:mid + 1])
    part2 = "\n\n".join(paras[max(0, mid - 1):])

    return [
        {
            "pdf_page": pdf_page,
            "printed_page": printed_page,
            "unit_title": unit_title,
            "section_heading": section_heading + " (Part 1)",
            "chapter_title": (chapter_title + " (Part 1)")[:240],
            "chunk_text": header + part1,
            "is_front_matter": False
        },
        {
            "pdf_page": pdf_page,
            "printed_page": printed_page,
            "unit_title": unit_title,
            "section_heading": section_heading + " (Part 2)",
            "chapter_title": (chapter_title + " (Part 2)")[:240],
            "chunk_text": header + part2,
            "is_front_matter": False
        }
    ]

def embed_batch(texts):
    url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:batchEmbedContents?key={GEMINI_API_KEY}"
    reqs = [{"model": "models/gemini-embedding-2", "content": {"parts": [{"text": t[:8000]}]}, "outputDimensionality": 768} for t in texts]
    payload = json.dumps({"requests": reqs}).encode("utf-8")
    req = urllib.request.Request(url, data=payload, headers={"Content-Type": "application/json"})

    for attempt in range(1, 7):
        try:
            with urllib.request.urlopen(req, timeout=45) as resp:
                data = json.loads(resp.read().decode("utf-8"))
            out = []
            for e in data.get("embeddings", []):
                v = e.get("values", [])
                if len(v) != 768:
                    raise ValueError(f"Expected 768 dims, got {len(v)}")
                norm = math.sqrt(sum(x * x for x in v)) or 1.0
                out.append([x / norm for x in v])
            return out
        except Exception as e:
            wait = 15 * attempt
            print(f"  Batch embed error (attempt {attempt}): {e} — sleeping {wait}s...", flush=True)
            if attempt == 6:
                raise e
            time.sleep(wait)

BOOKS = [
    {
        "slug": "amharic",
        "title": "Amharic (Mother Tongue) Grade 8 - Student Textbook (2007 E.C.)",
        "author": "Ministry of Education / Amhara & Addis Ababa Education Bureau",
        "description": "Official Grade 8 Amharic as Mother Tongue Student Textbook (Old Curriculum, 2007 E.C., 263 pages, 10 chapters).",
        "pdf": "/sdcard/Download/books/grade 8 amharic old curriculum  student textbook_kehulum_com_649f.pdf",
        "first_content_pdf_page": 9
    },
    {
        "slug": "math",
        "title": "Mathematics Grade 8 - Student Textbook (Old Curriculum)",
        "author": "Ministry of Education (FDRE)",
        "description": "Official Grade 8 Mathematics Student Textbook (Old Curriculum, 256 pages, 7 units).",
        "pdf": "/sdcard/Download/books/grade-8-mathematics-old-curriculum--student-textbook-kehulumcom17544857783070.pdf",
        "first_content_pdf_page": 1
    },
    {
        "slug": "social_studies",
        "title": "Social Studies Grade 8 - Student Textbook (2015 E.C.)",
        "author": "Addis Ababa City Administration Education Bureau",
        "description": "Official Grade 8 Social Studies Student Textbook (New Curriculum, 2015 E.C., 213 pages, 7 units).",
        "pdf": "/sdcard/Download/books/grade-8-social-studies-new-curriculum--student-textbook-kehulumcom17544733949fab.pdf",
        "first_content_pdf_page": 7
    },
    {
        "slug": "general_science",
        "title": "General Science Grade 8 - Student Textbook (New Curriculum)",
        "author": "Ministry of Education / Addis Ababa Education Bureau",
        "description": "Official Grade 8 General Science Student Textbook (New Curriculum, 231 pages, 7 units).",
        "pdf": "/sdcard/Download/books/grade 8-general science_kehulumcom_d399.pdf",
        "first_content_pdf_page": 7
    },
    {
        "slug": "english",
        "title": "English for Ethiopia Grade 8 - Student Textbook",
        "author": "Ministry of Education (FDRE)",
        "description": "Official Grade 8 English for Ethiopia Student's Book (New Curriculum, 153 pages, 10 units).",
        "pdf": "/sdcard/Download/8. Final English G 8 Student's Book.pdf",
        "first_content_pdf_page": 6
    }
]

target_slug = sys.argv[1] if len(sys.argv) > 1 else "all"

for b in BOOKS:
    if target_slug != "all" and b["slug"] != target_slug:
        continue

    print(f"\n=== Processing {b['title']} ===", flush=True)
    raw = subprocess.run(["pdftotext", b["pdf"], "-"], capture_output=True, text=True).stdout
    pages = raw.split("\x0c")
    if pages and not pages[-1].strip():
        pages.pop()

    all_chunks = []
    samples = {}
    cur_unit = "Unit 1"
    cur_sec = "Introduction"
    prev_printed = 0

    sample_targets = [b["first_content_pdf_page"], len(pages)//4, len(pages)//2, (3*len(pages))//4, len(pages)-5]

    for idx, p_raw in enumerate(pages):
        pdf_p = idx + 1
        lines = [l.strip() for l in p_raw.splitlines() if l.strip()]
        is_front = pdf_p < b["first_content_pdf_page"]

        if is_front:
            printed_p = 0
        else:
            printed_p = detect_printed_page(lines, pdf_p, b["slug"], prev_printed)
            if printed_p > 0:
                prev_printed = printed_p

        cleaned = strip_headers_footers(p_raw, b["slug"], pdf_p, printed_p)
        if len(cleaned) < 20:
            continue

        if not is_front:
            cur_unit, cur_sec = detect_unit_and_section(cleaned, b["slug"], cur_unit, cur_sec)

        if pdf_p in sample_targets:
            samples[f"pdf_p{pdf_p}_printed_p{printed_p}"] = cleaned[:500]

        page_chunks = chunk_page_text(cleaned, cur_unit, cur_sec, pdf_p, printed_p, is_front)
        all_chunks.extend(page_chunks)

    print(f"Extracted {len(pages)} pages -> {len(all_chunks)} chunks.", flush=True)

    # Save 5 sample pages for inspection
    os.makedirs("/data/data/com.termux/files/home/verbose/data/samples", exist_ok=True)
    with open(f"/data/data/com.termux/files/home/verbose/data/samples/{b['slug']}_samples.json", "w", encoding="utf-8") as sf:
        json.dump(samples, sf, ensure_ascii=False, indent=2)

    # Embed with caching
    cache_path = f"/data/data/com.termux/files/home/verbose/data/embed_cache_{b['slug']}.json"
    cached_vecs = {}
    if os.path.exists(cache_path):
        with open(cache_path, "r") as cf:
            cached_vecs = json.load(cf)

    batch_size = 16
    embeddings = [None] * len(all_chunks)
    to_embed_indices = []
    for i, c in enumerate(all_chunks):
        key = str(i)
        if key in cached_vecs and len(cached_vecs[key]) == 768:
            embeddings[i] = cached_vecs[key]
        else:
            to_embed_indices.append(i)

    print(f"Cached embeddings: {len(all_chunks) - len(to_embed_indices)}/{len(all_chunks)}. Computing {len(to_embed_indices)}...", flush=True)
    for start in range(0, len(to_embed_indices), batch_size):
        idxs = to_embed_indices[start:start+batch_size]
        texts = [all_chunks[i]["chunk_text"] for i in idxs]
        print(f"  Embedding batch {start//batch_size + 1}/{(len(to_embed_indices)-1)//batch_size + 1} ({len(texts)} chunks)...", flush=True)
        vecs = embed_batch(texts)
        for i, v in zip(idxs, vecs):
            embeddings[i] = v
            cached_vecs[str(i)] = v
        with open(cache_path, "w") as cf:
            json.dump(cached_vecs, cf)
        time.sleep(2.5)

    payload = {
        "title": b["title"],
        "author": b["author"],
        "description": b["description"],
        "chunks": []
    }
    for i, (c, v) in enumerate(zip(all_chunks, embeddings)):
        v_str = "[" + ",".join(f"{x:.6f}" for x in v) + "]"
        payload["chunks"].append({
            "chapter_title": c["chapter_title"],
            "section_number": i + 1,
            "chunk_text": c["chunk_text"],
            "chunk_index": i + 1,
            "embedding": v_str,
            "pdf_page": c["pdf_page"],
            "printed_page": c["printed_page"],
            "unit_title": c["unit_title"],
            "is_front_matter": c["is_front_matter"]
        })

    payload_file = f"/data/data/com.termux/files/home/verbose/data/payload_{b['slug']}.json"
    with open(payload_file, "w", encoding="utf-8") as pf:
        json.dump(payload, pf, ensure_ascii=False)

    print(f"Upserting {b['slug']} into Neon Postgres...", flush=True)
    res = subprocess.run(["php", "/data/data/com.termux/files/home/verbose/bin/upsert_book.php", payload_file], capture_output=True, text=True)
    print(res.stdout.strip())
    if res.returncode != 0:
        print("ERROR:", res.stderr)
        sys.exit(1)
