#!/usr/bin/env python3
import json
import os
import re
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

RAW_OCR_PATH = "/data/data/com.termux/files/home/verbose/data/citizenship_g8_english_raw_ocr.json"
if not os.path.exists(RAW_OCR_PATH):
    raise FileNotFoundError(f"Raw OCR file not found: {RAW_OCR_PATH}")

with open(RAW_OCR_PATH, "r", encoding="utf-8") as f:
    pages_raw = json.load(f)

print(f"Loaded {len(pages_raw)} pages of OCR data.")

watermark_re = re.compile(r"^.*Fetena\.net.*Educational\s*Resource.*$", re.IGNORECASE | re.MULTILINE)
footer_re = re.compile(r"^.*Citizenship\s+Education\s+Grade\s+8\s+Student\s+textbook.*$", re.IGNORECASE | re.MULTILINE)

def clean_text(raw):
    t = watermark_re.sub("", raw)
    t = footer_re.sub("", t)
    lines = [l.strip() for l in t.split("\n")]
    out = []
    for l in lines:
        if l:
            out.append(l)
        elif out and out[-1] != "":
            out.append("")
    return "\n".join(out).strip()

def get_unit_and_heading(printed_p, text):
    if printed_p <= 0:
        return "Front Matter", "Cover, Title & Table of Contents"
    elif printed_p <= 43:
        unit = "Unit 1: Virtue Ethics"
    elif printed_p <= 85:
        unit = "Unit 2: State and Government"
    elif printed_p <= 119:
        unit = "Unit 3: Democracy"
    else:
        unit = "Unit 4: Constitutionalism"

    # Specific section detection based on printed page and content
    heading = None
    if printed_p == 1:
        heading = "Introduction to Moral and Citizenship Education"
    elif printed_p in [6, 7]:
        heading = "Character-Based Approach to Morality & Nature of Moral Virtue"
    elif printed_p == 8:
        heading = "Ethics and Virtue Ethics (Aristotle & Greek Thinkers)"
    elif printed_p == 9:
        heading = "Steps in Ethical Decision Making"
    elif printed_p in [11, 12]:
        heading = "Categories of Virtue Ethics: Moral and Civic Virtues"
    elif printed_p in [47, 48]:
        heading = "Unitary State Structure (Powers, Functions & Limitations)"
    elif printed_p in [52, 53, 54]:
        heading = "Federal State Structure & FDRE Constitution (Article 51 & 52)"
    elif printed_p in [70, 71, 72]:
        heading = "The Montevideo Convention: Four Components of a State"
    elif printed_p in [108, 109, 110]:
        heading = "Indigenous Democratic Institutions (Gada, Luwa, Awassiya, Kanchi)"
    else:
        # Check first non-empty lines for prominent headings
        for line in text.split("\n")[:3]:
            l = line.strip()
            if re.match(r"^[0-9]\.[0-9].*", l) or re.match(r"^(Activity|Keywords|Summary|Case study|Remember)\b", l, re.I):
                heading = l
                break
        if not heading:
            heading = f"Section (Printed Page {printed_p})"

    return unit, heading

chunks = []
total_pages = len(pages_raw)

for p_num in range(1, total_pages + 1):
    p_str = str(p_num)
    if p_str not in pages_raw:
        continue

    p_data = pages_raw[p_str]
    raw_text = p_data.get("raw_text", "")
    cleaned = clean_text(raw_text)
    if len(cleaned) < 20:
        continue

    printed_p = p_num - 7
    is_front = (p_num <= 7)
    unit_title, section_heading = get_unit_and_heading(printed_p, cleaned)

    # If front matter, single chunk per page
    if is_front:
        header_tag = f"[Front Matter | PDF Page {p_num}]\n"
        chunks.append({
            "pdf_page": p_num,
            "printed_page": printed_p,
            "unit": unit_title,
            "section": section_heading,
            "chapter_title": f"Front Matter - PDF Page {p_num}",
            "text": header_tag + cleaned,
            "is_front_matter": True
        })
        continue

    # Split page into paragraphs
    paragraphs = [p.strip() for p in cleaned.split("\n\n") if p.strip()]

    # If the page contains a numbered list (e.g. 1. ... 2. ... 3. ...), keep it together!
    has_numbered_list = bool(re.search(r"\n1\.\s+[^\n]+\n2\.\s+", cleaned) or re.match(r"^1\.\s+", cleaned))
    
    # Target chunk length: ~1200 - 1800 chars (approx 300-450 tokens)
    # If the whole page text is under 1800 chars or has a numbered list / remember box, keep as 1 chunk!
    if len(cleaned) <= 1800 or has_numbered_list:
        header_tag = f"[{unit_title} | Printed Page {printed_p} (PDF p. {p_num}) | {section_heading}]\n"
        chunks.append({
            "pdf_page": p_num,
            "printed_page": printed_p,
            "unit": unit_title,
            "section": section_heading,
            "chapter_title": f"{unit_title} - Printed Page {printed_p} (PDF p. {p_num}): {section_heading}",
            "text": header_tag + cleaned,
            "is_front_matter": False
        })
    else:
        # Split into 2 chunks with small overlap
        mid = len(paragraphs) // 2
        p1 = "\n\n".join(paragraphs[:mid+1])
        p2 = "\n\n".join(paragraphs[mid:])

        header_tag1 = f"[{unit_title} | Printed Page {printed_p} (PDF p. {p_num}) | {section_heading} (Part 1)]\n"
        chunks.append({
            "pdf_page": p_num,
            "printed_page": printed_p,
            "unit": unit_title,
            "section": section_heading + " (Part 1)",
            "chapter_title": f"{unit_title} - Printed Page {printed_p} (PDF p. {p_num}): {section_heading} (Part 1)",
            "text": header_tag1 + p1,
            "is_front_matter": False
        })

        header_tag2 = f"[{unit_title} | Printed Page {printed_p} (PDF p. {p_num}) | {section_heading} (Part 2)]\n"
        chunks.append({
            "pdf_page": p_num,
            "printed_page": printed_p,
            "unit": unit_title,
            "section": section_heading + " (Part 2)",
            "chapter_title": f"{unit_title} - Printed Page {printed_p} (PDF p. {p_num}): {section_heading} (Part 2)",
            "text": header_tag2 + p2,
            "is_front_matter": False
        })

print(f"Generated {len(chunks)} semantic chunks ({sum(1 for c in chunks if not c['is_front_matter'])} core textbook chunks).")

# Save chunks to json
CHUNKS_JSON = "/data/data/com.termux/files/home/verbose/data/citizenship_g8_english_chunks.json"
with open(CHUNKS_JSON, "w", encoding="utf-8") as f:
    json.dump(chunks, f, ensure_ascii=False, indent=2)
print(f"Saved chunks to {CHUNKS_JSON}")
