# Verbose AI 📖

> **Zero-Hallucination Book Intelligence with Thinking Orbs & Cascading AI Waterfall.**

Verbose AI is a full-stack reading and comprehension platform powered by **PHP 8.5**, **Neon Postgres (pgvector)**, and a **3-tier cascading failover AI client**.

---

### Key Highlights

- **Antigravity Spatial Design:** Glassmorphic, weightless interface with CSS 3D perspective (`1200px`) and live citation inspector.
- **Vercel Labs vgpu WebGPU Engine:** Hardware-accelerated WebGPU shader visualizer (`vgpu.sh`) with typed WGSL fragment shaders and live particle resonance on the AI model landing page (`/landing`).
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

### Google OAuth 2.0 Setup

Verbose AI supports seamless **Sign in with Google** via Google OAuth 2.0 and Google Identity Services:

1. Create OAuth credentials in [Google Cloud Console](https://console.cloud.google.com/apis/credentials).
2. Set application type to **Web application**.
3. Add Authorized Redirect URI: `http://localhost:8080/auth/google/callback` (or your production URL).
4. Add to `.env`:
   ```bash
   GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=your_client_secret
   GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback
   ```
5. Users can now sign in using the "Continue with Google" button or email/password.
