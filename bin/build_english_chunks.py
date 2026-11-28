#!/usr/bin/env python3
import json
import os
import re

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
        return "Front Matter", "Cover, Title & Table of Contents", 0

    if printed_p <= 43:
        unit = "Unit 1: Virtue Ethics"
        if printed_p in [1, 2]:
            return unit, "1.1. Introduction to Moral and Citizenship Education", 1
        elif printed_p == 3:
            return unit, "Unit 1: Virtue Ethics Overview & Outcomes", 1
        elif printed_p in [4, 5]:
            return unit, "1.1. The concept of virtue ethics", 1
        elif printed_p in [6, 7]:
            return unit, "1.1. Character-Based Approach to Morality & Nature of Moral Virtue", 1
        elif printed_p == 8:
            return unit, "1.1.1. Ethics and virtue ethics (Aristotle & Greek Thinkers)", 1
        elif printed_p == 9:
            return unit, "1.1.1. Steps in Ethical Decision Making", 1
        elif printed_p == 10:
            return unit, "1.2. The qualities and principles of virtue ethics", 1
        elif printed_p == 11:
            return unit, "1.2. Categories of Virtue Ethics: Moral Virtues and Civic Virtues", 1
        elif printed_p in [12, 13, 14]:
            return unit, "1.2.1. Moral virtues: Honesty", 1
        elif printed_p in [15, 16]:
            return unit, "1.2.2. Moral virtues: Courage", 1
        elif printed_p in [17, 18]:
            return unit, "1.2.3. Moral virtues: Compassion", 1
        elif printed_p in [19, 20]:
            return unit, "1.2.4. Moral virtues: Generosity", 1
        elif printed_p in [21, 22]:
            return unit, "1.2.5. Moral virtues: Fairness", 1
        elif printed_p in [23, 24]:
            return unit, "1.2.6. Moral virtues: Self-Discipline", 1
        elif printed_p in [25, 26, 27]:
            return unit, "1.2.7. Moral virtues: Prudence", 1
        elif printed_p == 28:
            return unit, "1.2.8. Civic virtues (Definition and Meaning)", 1
        elif printed_p == 29:
            return unit, "1.2.8. Civic virtues (Manifestations: 1. Paying Fair Taxes)", 1
        elif printed_p == 30:
            return unit, "1.2.8. Civic virtues (Manifestations: 2. Staying informed)", 1
        elif printed_p == 31:
            return unit, "1.2.8. Civic virtues (Manifestations: 3. Participating in public government)", 1
        elif printed_p in [32, 33]:
            return unit, "1.3. Importance of virtue ethics", 1
        elif printed_p in [34, 35, 36, 37]:
            return unit, "1.4. Virtue ethics in daily life", 1
        elif printed_p == 38:
            return unit, "Unit 1 Summary", 1
        elif printed_p == 39:
            return unit, "Unit 1 Glossary", 1
        else:
            return unit, "Unit 1 Review Questions", 1

    elif printed_p <= 85:
        unit = "Unit 2: State and Government"
        if printed_p == 44:
            return unit, "Unit 2 Overview & Outcomes", 2
        elif printed_p in [45, 46]:
            return unit, "2.1. State structures", 2
        elif printed_p in [47, 48, 49, 50, 51]:
            return unit, "2.1.1. Unitary state structure (Powers, Functions & Limitations)", 2
        elif printed_p in [52, 53, 54, 55, 56, 57, 58]:
            return unit, "2.1.2. Federal state structure & FDRE Constitution (Article 51 & 52)", 2
        elif printed_p in [59, 60]:
            return unit, "2.1.3. Confederation (Confederal arrangement & EU example)", 2
        elif printed_p == 61:
            return unit, "2.2. Systems of government", 2
        elif printed_p in [62, 63, 64, 65]:
            return unit, "2.2.1. Parliamentary system of government (Article 74/2 & Removal)", 2
        elif printed_p in [66, 67, 68, 69]:
            return unit, "2.2.2. Presidential systems of government (Impeachment & Separation of Powers)", 2
        elif printed_p in [70, 71, 72]:
            return unit, "2.3. The Montevideo Convention & State vs Government Differences", 2
        elif printed_p in [73, 74, 75, 76]:
            return unit, "2.4. Forms of government in Ethiopia", 2
        elif printed_p in [77, 78, 79, 80]:
            return unit, "2.4.1. Strengths and limitations of different forms of government in Ethiopia", 2
        elif printed_p == 81:
            return unit, "Unit 2 Summary", 2
        elif printed_p == 82:
            return unit, "Unit 2 Glossary", 2
        else:
            return unit, "Unit 2 Review Questions", 2

    elif printed_p <= 119:
        unit = "Unit 3: Democracy"
        if printed_p == 86:
            return unit, "Unit 3 Overview & Outcomes", 3
        elif printed_p in [87, 88, 89, 90]:
            return unit, "3.1. The concept of democracy (Rules, Monitors & Misconceptions)", 3
        elif printed_p in [91, 92, 93, 94, 95, 96, 97, 98, 99]:
            return unit, "3.2. Fundamental Principles of democracy (Popular Sovereignty, Supremacy, Separation of Power, Secularism Art 11, Elections)", 3
        elif printed_p in [100, 101, 102, 103, 104, 105, 106, 107]:
            return unit, "3.3. Values of Democracy", 3
        elif printed_p in [108, 109, 110]:
            return unit, "3.4. Indigenous democratic values and institutions (Gada, Luwa, Awassiya, Kanchi, Seera, Yajoka)", 3
        elif printed_p in [111, 112, 113, 114]:
            return unit, "3.5. The challenges and opportunities of democracy in Ethiopia", 3
        elif printed_p == 115:
            return unit, "Unit 3 Summary", 3
        elif printed_p == 116:
            return unit, "Unit 3 Glossary", 3
        else:
            return unit, "Unit 3 Review Questions", 3

    else:
        unit = "Unit 4: Constitutionalism"
        return unit, "4.1. The meaning of constitution and constitutionalism", 4

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
    unit_title, section_heading, sec_num = get_unit_and_heading(printed_p, cleaned)

    if is_front:
        header_tag = f"Section: Front Matter\n[Front Matter | PDF Page {p_num}]\n\n"
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
    has_numbered_list = bool(re.search(r"\n1\.\s+[^\n]+\n2\.\s+", cleaned) or re.match(r"^1\.\s+", cleaned))

    # Prepend Section Heading and Unit/Page metadata directly to chunk text
    # so every chunk explicitly carries its section number and title in embeddings and text search
    header_tag = f"Section: {section_heading}\n[{unit_title} | Printed Page {printed_p} (PDF p. {p_num}) | {section_heading}]\n\n"

    if len(cleaned) <= 1800 or has_numbered_list:
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
        mid = len(paragraphs) // 2
        p1 = "\n\n".join(paragraphs[:mid+1])
        p2 = "\n\n".join(paragraphs[mid:])

        header_tag1 = f"Section: {section_heading} (Part 1)\n[{unit_title} | Printed Page {printed_p} (PDF p. {p_num}) | {section_heading} (Part 1)]\n\n"
        chunks.append({
            "pdf_page": p_num,
            "printed_page": printed_p,
            "unit": unit_title,
            "section": section_heading + " (Part 1)",
            "chapter_title": f"{unit_title} - Printed Page {printed_p} (PDF p. {p_num}): {section_heading} (Part 1)",
            "text": header_tag1 + p1,
            "is_front_matter": False
        })

        header_tag2 = f"Section: {section_heading} (Part 2)\n[{unit_title} | Printed Page {printed_p} (PDF p. {p_num}) | {section_heading} (Part 2)]\n\n"
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

CHUNKS_JSON = "/data/data/com.termux/files/home/verbose/data/citizenship_g8_english_chunks.json"
with open(CHUNKS_JSON, "w", encoding="utf-8") as f:
    json.dump(chunks, f, ensure_ascii=False, indent=2)
print(f"Saved chunks to {CHUNKS_JSON}")
