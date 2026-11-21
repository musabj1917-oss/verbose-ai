# Verbose AI 📖

> **Zero-Hallucination Book Intelligence with Thinking Orbs & Cascading AI Waterfall.**

Verbose AI is a full-stack reading and comprehension platform powered by **PHP 8.5**, **Neon Postgres (pgvector)**, and a **3-tier cascading failover AI client**.

---

### Key Highlights

- **Antigravity Spatial Design:** Glassmorphic, weightless interface with CSS 3D perspective (`1200px`) and live citation inspector.
- **Thinking Orbs Canvas Integration:** Real-time dotted 3D loading indicator ported from `Jakubantalik/thinking-orbs` with 9 agent states:
  - `breathing`: Ambient idle ring.
  - `searching`: Sweeping globe meridian while querying Neon Postgres.
  - `solving`: Rubik scramble/solve cycle while verifying anti-hallucination grounding.
  - `weaving`: 3-strand plaiting while generating the response.
- **Failover Waterfall Cascade:**
  1. **Tier 1:** Google AI Studio (Gemini 1.5 Flash/Pro)
  2. **Tier 2:** xAI Grok (`grok-beta`)
  3. **Tier 3:** OpenRouter (Universal Fallback)
- **Zero Hallucination Guarantee:** Models are strictly bound to verified book chunks with paragraph and chapter citations.
- **Dual Interactive Modes:**
  - **Ask Book:** Answers user queries with source citations.
  - **Quiz Me:** AI comprehension tutor that challenges the reader with deep questions derived from the book.

---

### Running the App Locally

The PHP development server is running at:
```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Visit: `http://127.0.0.1:8080`

### Admin Book Ingestion

Admins can curate and ingest books via the CLI:
```bash
php bin/ingest.php --title "Meditations" --author "Marcus Aurelius" --file ./path/to/book.txt
```
