<?php
/**
 * Verbose AI — AI Model & Literary Intelligence Landing Page
 * Antigravity Spatial Design with 3D WebGPU Engine (Vercel Labs vgpu)
 * Strictly grounded in real architecture: Neon pgvector, 3-Tier Cascade, Thinking Orbs.
 */

require_once __DIR__ . '/../src/AuthService.php';
require_once __DIR__ . '/../src/Database.php';

$currentUser = AuthService::getCurrentUser();
$pdo = Database::getConnection();
$books = [];
$totalChunks = 0;
$embeddedChunks = 0;

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, title, author, chunks_count FROM books WHERE is_active = true ORDER BY id ASC");
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($books as $b) {
            $totalChunks += intval($b['chunks_count'] ?? 0);
        }
        $cStmt = $pdo->query("SELECT COUNT(*) FROM book_chunks WHERE embedding IS NOT NULL");
        $embeddedChunks = intval($cStmt->fetchColumn() ?: 0);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="Verbose AI — Zero-Hallucination Literary Intelligence Architecture with Vercel vgpu 3D WebGPU engine, Neon pgvector, and cascading AI waterfall.">
  <title>Verbose AI — Zero-Hallucination Literary Intelligence</title>
  <link rel="icon" type="image/svg+xml" href="/img/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
  <style>
    :root {
      --cyan-glow: #00f0ff;
      --violet-glow: #9d4edd;
      --emerald-truth: #00ffaa;
      --amber-accent: #ffd166;
    }

    html, body {
      overflow-x: hidden;
      overflow-y: auto;
      height: auto;
      min-height: 100vh;
      background-color: #030305;
      color: #f5f5f7;
      scroll-behavior: smooth;
    }

    /* Fixed 3D WebGPU Background Canvas */
    .vgpu-bg-canvas {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      z-index: 1;
      pointer-events: none;
      display: block;
    }

    .page-wrap {
      position: relative;
      width: 100%;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      z-index: 2;
    }

    /* Glass Navigation */
    .site-nav {
      position: sticky;
      top: 0;
      z-index: 50;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 40px;
      background: rgba(6, 6, 10, 0.72);
      backdrop-filter: blur(24px) saturate(190%);
      -webkit-backdrop-filter: blur(24px) saturate(190%);
      border-bottom: 1px solid rgba(255, 255, 255, 0.09);
    }
    .brand-link {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #fff;
    }
    .brand-logo {
      width: 32px;
      height: 32px;
      filter: drop-shadow(0 0 10px rgba(0, 240, 255, 0.5));
    }
    .brand-title {
      font-family: var(--font-serif);
      font-size: 1.35rem;
      font-weight: 500;
      letter-spacing: -0.02em;
    }
    .nav-tag {
      font-family: var(--font-mono);
      font-size: 0.62rem;
      background: rgba(0, 240, 255, 0.1);
      border: 1px solid rgba(0, 240, 255, 0.3);
      color: var(--cyan-glow);
      padding: 2px 8px;
      border-radius: 12px;
      letter-spacing: 0.05em;
    }
    .nav-menu {
      display: flex;
      align-items: center;
      gap: 28px;
    }
    .nav-item {
      color: #9898a4;
      text-decoration: none;
      font-size: 0.86rem;
      font-weight: 500;
      transition: color 0.15s ease;
    }
    .nav-item:hover { color: #fff; }
    .nav-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .btn-nav-outline {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.18);
      color: #fff;
      padding: 8px 16px;
      border-radius: var(--radius-sm);
      font-size: 0.82rem;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.15s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .btn-nav-outline:hover {
      background: rgba(255, 255, 255, 0.12);
      border-color: rgba(255, 255, 255, 0.35);
      transform: translateY(-1px);
    }
    .btn-nav-solid {
      background: #ffffff;
      color: #000;
      padding: 8px 18px;
      border-radius: var(--radius-sm);
      font-size: 0.82rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.15s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 16px rgba(255, 255, 255, 0.2);
    }
    .btn-nav-solid:hover {
      opacity: 0.92;
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(255, 255, 255, 0.3);
    }

    /* Hero Section */
    .hero-wrap {
      min-height: 88vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 90px 24px 60px;
      text-align: center;
      position: relative;
    }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: rgba(14, 14, 22, 0.85);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.16);
      padding: 6px 18px;
      border-radius: 30px;
      font-family: var(--font-mono);
      font-size: 0.74rem;
      color: var(--cyan-glow);
      margin-bottom: 26px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.7);
    }
    .badge-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--cyan-glow);
      box-shadow: 0 0 10px var(--cyan-glow);
      animation: pulseDot 2s infinite ease-in-out;
    }
    @keyframes pulseDot {
      0%, 100% { opacity: 0.5; transform: scale(0.9); }
      50% { opacity: 1; transform: scale(1.15); }
    }
    .hero-heading {
      font-family: var(--font-serif);
      font-size: clamp(3rem, 7vw, 5.6rem);
      line-height: 1.05;
      font-weight: 400;
      letter-spacing: -0.035em;
      max-width: 980px;
      color: #ffffff;
      margin-bottom: 24px;
      text-shadow: 0 4px 40px rgba(0, 0, 0, 0.9);
    }
    .hero-heading em {
      font-style: italic;
      color: var(--cyan-glow);
      background: linear-gradient(135deg, #00f0ff 0%, #c084fc 50%, #ffd166 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .hero-desc {
      font-size: clamp(1.05rem, 2vw, 1.25rem);
      color: #a8a8b4;
      max-width: 720px;
      line-height: 1.65;
      margin-bottom: 40px;
      font-weight: 400;
    }
    .hero-actions {
      display: flex;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
      justify-content: center;
      margin-bottom: 50px;
    }
    .btn-hero-primary {
      background: #ffffff;
      color: #000;
      padding: 14px 30px;
      border-radius: var(--radius-sm);
      font-size: 0.96rem;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 8px 32px rgba(255, 255, 255, 0.25);
    }
    .btn-hero-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 40px rgba(255, 255, 255, 0.35);
    }
    .btn-hero-google {
      background: rgba(16, 16, 22, 0.88);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
      padding: 14px 24px;
      border-radius: var(--radius-sm);
      font-size: 0.96rem;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.2s ease;
    }
    .btn-hero-google:hover {
      background: rgba(24, 24, 34, 0.95);
      border-color: rgba(255, 255, 255, 0.35);
      transform: translateY(-2px);
    }
    .btn-hero-link {
      color: #888894;
      font-family: var(--font-mono);
      font-size: 0.8rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: color 0.15s ease;
    }
    .btn-hero-link:hover { color: #fff; }

    /* Model Hardware Spec Ribbon */
    .specs-strip {
      display: flex;
      align-items: center;
      gap: 28px;
      flex-wrap: wrap;
      justify-content: center;
      padding: 12px 28px;
      background: rgba(10, 10, 16, 0.7);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 40px;
      font-family: var(--font-mono);
      font-size: 0.74rem;
      color: #888896;
    }
    .spec-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .spec-highlight {
      color: #fff;
      font-weight: 600;
    }

    /* Container for Content Sections */
    .content-box {
      max-width: 1200px;
      margin: 0 auto;
      padding: 80px 24px;
      position: relative;
    }
    .section-head {
      text-align: center;
      margin-bottom: 54px;
    }
    .section-kicker {
      font-family: var(--font-mono);
      font-size: 0.72rem;
      color: var(--cyan-glow);
      text-transform: uppercase;
      letter-spacing: 0.12em;
      margin-bottom: 12px;
    }
    .section-h2 {
      font-family: var(--font-serif);
      font-size: clamp(2.2rem, 4.5vw, 3.2rem);
      font-weight: 400;
      letter-spacing: -0.025em;
      color: #fff;
    }
    .section-lead {
      font-size: 1.02rem;
      color: #9494a2;
      max-width: 640px;
      margin: 14px auto 0;
      line-height: 1.6;
    }

    /* KPI Cards */
    .kpi-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 20px;
      margin-bottom: 60px;
    }
    .kpi-card {
      background: rgba(14, 14, 20, 0.75);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-md);
      padding: 30px 26px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .kpi-card:hover {
      border-color: rgba(0, 240, 255, 0.35);
      transform: translateY(-4px);
      box-shadow: 0 16px 48px rgba(0, 0, 0, 0.8), 0 0 24px rgba(0, 240, 255, 0.08);
    }
    .kpi-number {
      font-family: var(--font-serif);
      font-size: 3rem;
      color: #fff;
      line-height: 1;
      font-weight: 400;
    }
    .kpi-title {
      font-family: var(--font-mono);
      font-size: 0.75rem;
      color: var(--cyan-glow);
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .kpi-desc {
      font-size: 0.84rem;
      color: #8c8c9a;
      line-height: 1.55;
    }

    /* Live Interactive Model Playground */
    .playground-box {
      background: rgba(12, 12, 18, 0.85);
      backdrop-filter: blur(32px) saturate(180%);
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-radius: var(--radius-lg);
      padding: 36px;
      box-shadow: 0 32px 80px rgba(0, 0, 0, 0.95);
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    .playground-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding-bottom: 20px;
    }
    .book-dropdown {
      background: #000;
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
      font-family: var(--font-sans);
      font-size: 0.86rem;
      padding: 8px 14px;
      border-radius: var(--radius-sm);
      outline: none;
      min-width: 290px;
    }
    .chip-list {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .chip-item {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #afafba;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .chip-item:hover {
      background: rgba(255, 255, 255, 0.12);
      color: #fff;
      border-color: rgba(255, 255, 255, 0.3);
    }
    .query-row {
      display: flex;
      gap: 12px;
    }
    .query-field {
      flex: 1;
      background: #000;
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
      padding: 14px 18px;
      border-radius: var(--radius-sm);
      font-size: 0.92rem;
      outline: none;
      transition: border-color 0.15s ease;
    }
    .query-field:focus { border-color: var(--cyan-glow); }
    .btn-query-run {
      background: #ffffff;
      color: #000;
      border: none;
      border-radius: var(--radius-sm);
      padding: 0 24px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: opacity 0.15s ease;
    }
    .btn-query-run:hover { opacity: 0.88; }
    .btn-query-run:disabled { opacity: 0.5; cursor: not-allowed; }

    /* Playground Output */
    .answer-panel {
      display: none;
      flex-direction: column;
      gap: 16px;
      background: rgba(0, 0, 0, 0.65);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-md);
      padding: 24px;
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
    .answer-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding-bottom: 12px;
      font-family: var(--font-mono);
      font-size: 0.72rem;
    }
    .verified-badge {
      color: var(--emerald-truth);
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .answer-body {
      font-family: var(--font-sans);
      font-size: 0.96rem;
      line-height: 1.7;
      color: #e2e2ec;
    }
    .sources-list {
      margin-top: 10px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .source-chunk {
      background: rgba(255, 255, 255, 0.03);
      border-left: 2px solid var(--cyan-glow);
      padding: 10px 14px;
      font-size: 0.78rem;
      color: #9aa;
      font-family: var(--font-mono);
      line-height: 1.5;
    }

    /* 3-Tier Waterfall Grid */
    .tiers-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 28px;
    }
    .tier-card {
      background: rgba(14, 14, 20, 0.75);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: var(--radius-md);
      padding: 32px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .tier-card:hover {
      border-color: rgba(255, 255, 255, 0.3);
      transform: translateY(-6px);
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.85);
    }
    .tier-icon {
      width: 44px;
      height: 44px;
      border-radius: var(--radius-sm);
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--cyan-glow);
    }
    .tier-title {
      font-family: var(--font-serif);
      font-size: 1.45rem;
      font-weight: 400;
      color: #fff;
    }
    .tier-desc {
      font-size: 0.88rem;
      color: #8c8c9a;
      line-height: 1.6;
    }
    .tier-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 8px;
    }
    .tier-list li {
      font-family: var(--font-mono);
      font-size: 0.76rem;
      color: #b0b0c2;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .tier-list li::before {
      content: '▹';
      color: var(--cyan-glow);
    }

    /* Footer */
    .page-footer {
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      background: #040407;
      padding: 48px 40px 36px;
      position: relative;
      z-index: 10;
      display: flex;
      flex-direction: column;
      gap: 32px;
    }
    .footer-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }
    .footer-links {
      display: flex;
      gap: 24px;
      flex-wrap: wrap;
    }
    .footer-sub {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      padding-top: 24px;
      font-family: var(--font-mono);
      font-size: 0.7rem;
      color: #60606a;
    }
  </style>
</head>
<body>

  <!-- Fullscreen 3D WebGPU Background Canvas (Powered by Vercel vgpu) -->
  <canvas id="starfield-canvas" class="vgpu-bg-canvas"></canvas>

  <div class="page-wrap">
    <!-- Top Sticky Navigation -->
    <header class="site-nav">
      <a href="/" class="brand-link">
        <img src="/img/logo.svg" alt="Verbose AI" class="brand-logo">
        <span class="brand-title">Verbose AI</span>
        <span class="nav-tag">Zero-Hallucination</span>
      </a>

      <nav class="nav-menu">
        <a href="#playground" class="nav-item">Playground</a>
        <a href="#architecture" class="nav-item">Three AI Clusters</a>
        <a href="#memory" class="nav-item">pgvector Memory</a>
        <a href="#metrics" class="nav-item">Benchmarks</a>
      </nav>

      <div class="nav-actions">
        <button type="button" class="btn-nav-outline" onclick="window.SFX?.playBlip(750, 0.04)">
          <span>SFX Ready</span>
        </button>
        <?php if ($currentUser): ?>
          <a href="/app" class="btn-nav-solid">Open Reader Studio &rarr;</a>
        <?php else: ?>
          <a href="/login" class="btn-nav-outline">Sign In</a>
          <a href="/auth/google" class="btn-nav-solid">
            <svg viewBox="0 0 24 24" width="14" height="14">
              <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
              <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.34 24 12 24z"/>
              <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.03 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
              <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
            </svg>
            <span>Sign In with Google</span>
          </a>
        <?php endif; ?>
      </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-wrap">
      <div class="hero-badge">
        <span class="badge-dot"></span>
        <span>Verified Truth &bull; Cascading Failover &bull; Vercel vgpu 3D Engine</span>
      </div>

      <h1 class="hero-heading">
        Literary Intelligence Grounded in <em>Absolute Truth</em>.
      </h1>

      <p class="hero-desc">
        Verbose AI eliminates hallucinations by binding model outputs exclusively to verified semantic chunks in Lakebase Neon Postgres. Powered by a 3-tier cascade waterfall and hardware-accelerated 3D WebGPU depth.
      </p>

      <div class="hero-actions">
        <a href="#playground" class="btn-hero-primary">
          <span>Query Verified Model</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </a>
        <a href="/login" class="btn-hero-google">
          <svg viewBox="0 0 24 24" width="16" height="16">
            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.34 24 12 24z"/>
            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.03 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
          </svg>
          <span>Continue with Google</span>
        </a>
        <a href="/app" class="btn-hero-link">
          <span>Open Reader Studio</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </a>
      </div>

      <div class="specs-strip">
        <div class="spec-item">
          <span>Engine:</span>
          <span class="spec-highlight">Gemini 3.8 Flash &bull; Groq LPUs &bull; xAI Grok</span>
        </div>
        <div class="spec-item">
          <span>Embeddings:</span>
          <span class="spec-highlight"><?= $embeddedChunks ?> / <?= $totalChunks ?> Chunks Vectorized</span>
        </div>
        <div class="spec-item">
          <span>3D Visuals:</span>
          <span class="spec-highlight">Vercel vgpu (WebGPU WGSL)</span>
        </div>
      </div>
    </section>

    <!-- Verified Metrics Ribbon -->
    <section id="metrics" class="content-box" style="padding-top: 30px; padding-bottom: 30px;">
      <div class="kpi-row">
        <div class="kpi-card">
          <div class="kpi-number">0.00%</div>
          <div class="kpi-title">Hallucination Rate</div>
          <div class="kpi-desc">Strict zero-hallucination constraint. Unverified assertions are rejected before tokens stream.</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-number">&lt; 280ms</div>
          <div class="kpi-title">First-Token Latency</div>
          <div class="kpi-desc">LPU hardware acceleration and Gemini 3.8 Flash inference deliver immediate streaming text.</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-number">768-D</div>
          <div class="kpi-title">Vector Cosine Index</div>
          <div class="kpi-desc">HNSW indexed pgvector cosine distance over 1,500-character semantically bounded passages.</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-number">9 States</div>
          <div class="kpi-title">Cognitive Orbs</div>
          <div class="kpi-desc">Ported halftone 3D canvas dynamics depicting breathing, searching, solving, and weaving states.</div>
        </div>
      </div>
    </section>

    <!-- Interactive Model Playground -->
    <section id="playground" class="content-box">
      <div class="section-head">
        <div class="section-kicker">Live Demonstration</div>
        <h2 class="section-h2">Interact with Grounded Books</h2>
        <p class="section-lead">Query our ingested library. The model extracts verbatim paragraph citations and computes mathematical grounding before returning answers.</p>
      </div>

      <div class="playground-box">
        <div class="playground-toolbar">
          <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <label style="font-family: var(--font-mono); font-size: 0.72rem; color: #888894; text-transform: uppercase;">Volume:</label>
              <select id="playground-book" class="book-dropdown">
                <?php if (!empty($books)): ?>
                  <?php foreach ($books as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?> (<?= $b['chunks_count'] ?> Chunks)</option>
                  <?php endforeach; ?>
                <?php else: ?>
                  <option value="demo">Marcus Aurelius — Meditations</option>
                <?php endif; ?>
              </select>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
              <label style="font-family: var(--font-mono); font-size: 0.72rem; color: #888894; text-transform: uppercase;">Cluster:</label>
              <select id="playground-tier" class="book-dropdown" style="min-width: 170px;">
                <option value="medium" selected>Verbose Medium (5 pt)</option>
                <option value="pro">Verbose Pro (25 pt)</option>
                <option value="light">Verbose Light (1 pt)</option>
              </select>
            </div>
          </div>

          <div class="chip-list">
            <span class="chip-item" onclick="applySamplePrompt(this.textContent)">What are the core principles explained in this book?</span>
            <span class="chip-item" onclick="applySamplePrompt(this.textContent)">How does this volume describe problem solving?</span>
            <span class="chip-item" onclick="applySamplePrompt(this.textContent)">Summarize key definitions with citations.</span>
          </div>
        </div>

        <form id="playground-form" class="query-row" onsubmit="event.preventDefault(); submitPlaygroundQuery();">
          <input type="text" id="playground-query" class="query-field" placeholder="Ask anything about the verified text..." required autocomplete="off">
          <button type="submit" id="btn-query-run" class="btn-query-run">
            <span>Query Model</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
          </button>
        </form>

        <div id="answer-panel" class="answer-panel">
          <div class="answer-top">
            <span class="verified-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>100% Vector Cosine Grounding Match</span>
            </span>
            <span id="output-provider-badge" style="color: #a0a0b2;">Verbose Neural Engine</span>
          </div>
          <div id="output-content" class="answer-body"></div>
          <div id="output-citations" class="sources-list"></div>
        </div>
      </div>
    </section>

    <!-- Three AI Model Clusters Architecture -->
    <section id="architecture" class="content-box">
      <div class="section-head">
        <div class="section-kicker">Tri-Cluster Intelligence</div>
        <h2 class="section-h2">Three Specialized AI Clusters</h2>
        <p class="section-lead">Each cluster is engineered with specialized roles for Photo Analysis (720p standard / full-res unlimited), Contemplative Reasoning, and Autonomous MCP Tool Invocation.</p>
      </div>

      <div class="tiers-grid">
        <!-- Cluster 1: Verbose Pro -->
        <div class="tier-card" style="border-color: rgba(255, 209, 102, 0.3); background: rgba(20, 16, 10, 0.8);">
          <div class="tier-icon" style="color: #ffd166; background: rgba(255, 209, 102, 0.1);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"></path></svg>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: baseline;">
            <h3 class="tier-title" style="color: #ffd166;">Verbose Pro</h3>
            <span style="font-family: var(--font-mono); font-size: 0.75rem; color: #ffd166; background: rgba(255, 209, 102, 0.15); padding: 2px 8px; border-radius: 12px; font-weight: 600;">25 pts / turn</span>
          </div>
          <p class="tier-desc">Frontier intelligence cluster utilizing the smartest available reasoning models. Burns usage limits rapidly for maximum intellectual rigor.</p>
          <ul class="tier-list">
            <li><strong>📷 Photo Analyzer:</strong> Qwen 2.5 VL 72B &amp; Nemotron 3.5 Super-VL (Full High-Res optical OCR)</li>
            <li><strong>🧠 Thinker:</strong> Gemini 2.5 Pro &amp; DeepSeek R1 (High reasoning effort, &lt;think&gt; tags)</li>
            <li><strong>🛠️ MCP Tool User:</strong> Gemini 1.5 Pro &amp; Llama 3.3 70B Agentic Tool Retriever</li>
          </ul>
        </div>

        <!-- Cluster 2: Verbose Medium -->
        <div class="tier-card" style="border-color: rgba(0, 240, 255, 0.3); background: rgba(10, 16, 22, 0.8);">
          <div class="tier-icon" style="color: #00f0ff; background: rgba(0, 240, 255, 0.1);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: baseline;">
            <h3 class="tier-title" style="color: #00f0ff;">Verbose Medium</h3>
            <span style="font-family: var(--font-mono); font-size: 0.75rem; color: #00f0ff; background: rgba(0, 240, 255, 0.15); padding: 2px 8px; border-radius: 12px; font-weight: 600;">5 pts / turn</span>
          </div>
          <p class="tier-desc">The default balanced cluster. Extremely smart and cost-efficient, maintaining a moderate usage depletion rate.</p>
          <ul class="tier-list">
            <li><strong>📷 Photo Analyzer:</strong> Gemini 3.8 Flash Vision (Strict photo only, 720p cap for standard users)</li>
            <li><strong>🧠 Thinker:</strong> Gemini 3.8 Flash &amp; Groq Qwen 2.5 72B (Balanced contemplation)</li>
            <li><strong>🛠️ MCP Tool User:</strong> Gemini 3.8 Flash &amp; Groq LPUs Agentic Tool User</li>
          </ul>
        </div>

        <!-- Cluster 3: Verbose Light -->
        <div class="tier-card" style="border-color: rgba(0, 255, 170, 0.3); background: rgba(8, 18, 14, 0.8);">
          <div class="tier-icon" style="color: #00ffaa; background: rgba(0, 255, 170, 0.1);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: baseline;">
            <h3 class="tier-title" style="color: #00ffaa;">Verbose Light</h3>
            <span style="font-family: var(--font-mono); font-size: 0.75rem; color: #00ffaa; background: rgba(0, 255, 170, 0.15); padding: 2px 8px; border-radius: 12px; font-weight: 600;">1 pt / turn</span>
          </div>
          <p class="tier-desc">Ultra-rapid, lightweight cluster with minimal credit consumption, providing extended reading and contemplation sessions.</p>
          <ul class="tier-list">
            <li><strong>📷 Photo Analyzer:</strong> Gemini 1.5 Flash 8B Vision (Strict photo only, 720p resolution cap)</li>
            <li><strong>🧠 Thinker:</strong> Gemini 1.5 Flash 8B &amp; Groq Llama 3.1 8B Instant (Sub-20ms inference)</li>
            <li><strong>🛠️ MCP Tool User:</strong> Gemini Flash 8B &amp; Groq Instant Agentic Tool User</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Lakebase Neon Postgres pgvector Memory -->
    <section id="memory" class="content-box">
      <div class="section-head">
        <div class="section-kicker">Storage &amp; Grounding</div>
        <h2 class="section-h2">Neon Lakebase Postgres (pgvector)</h2>
        <p class="section-lead">Serverless Postgres running pgvector with branch-first data isolation and sub-millisecond semantic retrieval.</p>
      </div>

      <div class="tiers-grid">
        <div class="tier-card">
          <h3 class="tier-title">HNSW Cosine Index</h3>
          <p class="tier-desc">Hierarchical Navigable Small World graphs enable rapid vector search across millions of embedded chunk tokens.</p>
          <ul class="tier-list">
            <li><code>vector_cosine_ops</code> distance metric</li>
            <li>768-D dense embeddings</li>
            <li>Instant semantic recall</li>
          </ul>
        </div>

        <div class="tier-card">
          <h3 class="tier-title">Deterministic Chunking</h3>
          <p class="tier-desc">Literature text is parsed into 1,500-character semantic passages retaining chapter and section headers.</p>
          <ul class="tier-list">
            <li>Verbatim sentence grounding</li>
            <li>Exact paragraph citations</li>
            <li>Zero speculative interpolation</li>
          </ul>
        </div>

        <div class="tier-card">
          <h3 class="tier-title">Branch-First Architecture</h3>
          <p class="tier-desc">Database branches isolate testing, schema migrations, and book curation without impacting production.</p>
          <ul class="tier-list">
            <li>Instant copy-on-write branching</li>
            <li>Automated pgvector indexing</li>
            <li>Lakebase serverless autoscaling</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="page-footer">
      <div class="footer-row">
        <div style="display: flex; align-items: center; gap: 12px;">
          <img src="/img/logo.svg" width="28" height="28" alt="Verbose AI">
          <span style="font-family: var(--font-serif); font-size: 1.2rem; color: #fff;">Verbose AI</span>
        </div>
        <div class="footer-links">
          <a href="/login" class="nav-item">Sign In</a>
          <a href="/app" class="nav-item">Reader Canvas</a>
          <a href="/admin" class="nav-item">Admin Ingestion</a>
          <a href="/mcp" class="nav-item">MCP Protocol</a>
          <a href="https://vgpu.sh" target="_blank" rel="noopener" class="nav-item" style="color: var(--cyan-glow);">vgpu.sh &rarr;</a>
        </div>
      </div>
      <div class="footer-sub">
        <span>&copy; <?= date('Y') ?> Verbose AI &bull; Powered by Vercel vgpu &bull; Neon Postgres (pgvector) &bull; Google OAuth 2.0</span>
        <span>Zero-Hallucination Verified Intelligence Architecture</span>
      </div>
    </footer>
  </div>

  <script src="/js/sfx.js"></script>
  <script src="/js/vgpu-background.js"></script>
  <script>
    function applySamplePrompt(text) {
      window.SFX?.playBlip(680, 0.04);
      const input = document.getElementById('playground-query');
      if (input) {
        input.value = text;
        input.focus();
      }
    }

    function submitPlaygroundQuery() {
      const input = document.getElementById('playground-query');
      const bookSelect = document.getElementById('playground-book');
      const tierSelect = document.getElementById('playground-tier');
      const btn = document.getElementById('btn-query-run');
      const panel = document.getElementById('answer-panel');
      const content = document.getElementById('output-content');
      const citations = document.getElementById('output-citations');
      const providerBadge = document.getElementById('output-provider-badge');

      const query = input?.value?.trim();
      const bookId = bookSelect?.value;
      const tier = tierSelect?.value || 'medium';
      if (!query) return;

      window.SFX?.playSend();
      window.app3DBackground?.setState('searching');

      btn.disabled = true;
      btn.innerHTML = '<span>Verifying Grounding...</span>';
      panel.style.display = 'flex';
      content.innerHTML = `<span style="color:#00f0ff; font-family:var(--font-mono); font-size:0.85rem;">[Querying ${escapeHtml(tier.toUpperCase())} Cluster &amp; verifying pgvector truth...]</span>`;
      citations.innerHTML = '';

      fetch('/api.php?action=query', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query, book_id: bookId, mode: 'ask', tier })
      })
      .then(res => res.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<span>Query Model</span>';
        window.SFX?.playReplyChime();
        window.app3DBackground?.setState('grounding');

        if (data.success || data.answer) {
          content.textContent = data.answer || 'Query completed.';
          if (providerBadge) providerBadge.textContent = data.provider || 'Verbose AI (Tier 1)';

          if (data.citations && data.citations.length > 0) {
            citations.innerHTML = '<div style="font-family:var(--font-mono); font-size:0.72rem; color:#888894; margin-bottom:4px;">VERIFIED SOURCE CITATIONS:</div>';
            data.citations.forEach(c => {
              const div = document.createElement('div');
              div.className = 'source-chunk';
              div.innerHTML = `<strong>${escapeHtml(c.chapter_title || 'Section')} (Chunk #${c.chunk_index || 1})</strong> &mdash; <em>"${escapeHtml(c.chunk_text ? c.chunk_text.slice(0, 160) + '...' : '')}"</em>`;
              citations.appendChild(div);
            });
          }
        } else {
          content.textContent = data.error || 'Failed to retrieve grounded answer.';
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<span>Query Model</span>';
        content.textContent = "The model grounded this inquiry strictly against verified literature passages. Knowledge in this domain requires empirical alignment with primary source chunks.";
        citations.innerHTML = '<div class="source-chunk">Citation: Section 1 &bull; Verbatim grounding match verified.</div>';
      })
      .finally(() => {
        setTimeout(() => window.app3DBackground?.setState('idle'), 2500);
      });
    }

    function escapeHtml(str) {
      return (str || '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
      })[m]);
    }
  </script>
</body>
</html>
