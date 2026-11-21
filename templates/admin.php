<?php
/**
 * Verbose AI - Administrative Console & Document Ingestion Studio
 * Brutalist Monochromatic Edition with pgvector Embeddings & Chunk Inspector
 */

if (empty($_COOKIE['verbose_session'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Config.php';

$pdo = Database::getConnection();
$books = [];
$totalChunks = 0;
$embeddedChunks = 0;
$dbConnected = false;

if ($pdo) {
    $dbConnected = true;
    try {
        $stmt = $pdo->query("SELECT id, title, author, description, chunks_count, created_at FROM books ORDER BY id DESC");
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($books as $b) {
            $totalChunks += intval($b['chunks_count'] ?? 0);
        }
        $cStmt = $pdo->query("SELECT COUNT(*) FROM book_chunks WHERE embedding IS NOT NULL");
        $embeddedChunks = intval($cStmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        error_log("Admin fetch books error: " . $e->getMessage());
    }
}

$vectorPct = $totalChunks > 0 ? round(($embeddedChunks / $totalChunks) * 100, 1) : 100.0;
$currentUser = htmlspecialchars($_COOKIE['verbose_session'] ?? 'Admin');
$geminiConfigured = !empty(Config::get('GEMINI_API_KEY'));
$groqConfigured = !empty(Config::get('GROQ_API_KEY'));
$openrouterConfigured = !empty(Config::get('OPENROUTER_API_KEY'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Verbose AI &mdash; Administrative Console</title>
  <link rel="icon" type="image/svg+xml" href="/img/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
  <style>
    :root {
      --admin-bg: #070707;
      --admin-card-bg: #0d0d0d;
      --admin-hover-bg: #141414;
      --admin-border: #1f1f1f;
      --admin-border-subtle: #171717;
      --admin-border-strong: #333333;
    }

    body.admin-body {
      background-color: var(--admin-bg);
      color: var(--text-main);
      font-family: var(--font-sans);
      min-height: 100vh;
      overflow-x: hidden;
      margin: 0;
      padding: 0;
    }

    .admin-layout {
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px 24px 96px 24px;
      display: flex;
      flex-direction: column;
      gap: 28px;
    }

    /* Top Administrative Masthead */
    .admin-masthead {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 20px;
      background: var(--admin-card-bg);
      border: 1px solid var(--admin-border);
      border-radius: var(--radius-md);
    }

    .admin-brand-group {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .admin-logo-mark {
      width: 28px;
      height: 28px;
      filter: invert(1);
    }

    .admin-brand-titles {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .admin-sys-title {
      font-family: var(--font-mono);
      font-size: 0.72rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: #ffffff;
      font-weight: 600;
    }

    .admin-sys-subtitle {
      font-family: var(--font-serif);
      font-size: 0.95rem;
      font-style: italic;
      color: var(--text-muted);
    }

    .admin-masthead-controls {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .admin-status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--admin-border);
      border-radius: 9999px;
      font-family: var(--font-mono);
      font-size: 0.68rem;
      color: var(--text-muted);
    }

    .status-pulse-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #ffffff;
      box-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
      animation: adminPulse 2s infinite ease-in-out;
    }

    @keyframes adminPulse {
      0%, 100% { opacity: 0.35; transform: scale(0.9); }
      50% { opacity: 1; transform: scale(1.15); }
    }

    .btn-admin-action {
      background: transparent;
      border: 1px solid var(--admin-border);
      color: var(--text-main);
      padding: 7px 14px;
      border-radius: var(--radius-sm);
      font-size: 0.76rem;
      font-family: var(--font-mono);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.15s ease;
      cursor: pointer;
    }

    .btn-admin-action:hover {
      background: var(--admin-hover-bg);
      border-color: #ffffff;
      color: #ffffff;
    }

    /* KPI Metrics Ribbon */
    .admin-kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 16px;
    }

    .admin-kpi-card {
      background: var(--admin-card-bg);
      border: 1px solid var(--admin-border);
      border-radius: var(--radius-md);
      padding: 20px 22px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      position: relative;
      overflow: hidden;
      transition: border-color 0.15s ease;
    }

    .admin-kpi-card:hover {
      border-color: var(--admin-border-strong);
    }

    .admin-kpi-label {
      font-family: var(--font-mono);
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--text-dim);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .admin-kpi-value {
      font-family: var(--font-mono);
      font-size: 1.85rem;
      font-weight: 600;
      color: #ffffff;
      letter-spacing: -0.02em;
    }

    .admin-kpi-sub {
      font-size: 0.72rem;
      color: var(--text-muted);
      line-height: 1.4;
    }

    .progress-track {
      width: 100%;
      height: 4px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 2px;
      overflow: hidden;
      margin-top: 4px;
    }

    .progress-fill {
      height: 100%;
      background: #ffffff;
      transition: width 0.4s ease;
    }

    /* Tabbed Navigation Bar */
    .admin-tabs-nav {
      display: flex;
      gap: 8px;
      border-bottom: 1px solid var(--admin-border);
      padding-bottom: 0;
    }

    .admin-tab-btn {
      background: transparent;
      border: none;
      border-bottom: 2px solid transparent;
      color: var(--text-muted);
      font-family: var(--font-mono);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 10px 18px 12px 18px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.15s ease;
    }

    .admin-tab-btn:hover {
      color: #ffffff;
    }

    .admin-tab-btn.active {
      color: #ffffff;
      border-bottom-color: #ffffff;
      font-weight: 600;
    }

    .admin-tab-badge {
      font-size: 0.65rem;
      padding: 1px 6px;
      border-radius: 9999px;
      background: rgba(255, 255, 255, 0.1);
      color: #ffffff;
    }

    /* Tab Panes */
    .admin-tab-pane {
      display: none;
      flex-direction: column;
      gap: 24px;
      animation: fadeInTab 0.2s ease-out;
    }

    .admin-tab-pane.active {
      display: flex;
    }

    @keyframes fadeInTab {
      from { opacity: 0; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Content Cards */
    .admin-panel-card {
      background: var(--admin-card-bg);
      border: 1px solid var(--admin-border);
      border-radius: var(--radius-md);
      padding: 26px 28px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .admin-panel-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 1px solid var(--admin-border-subtle);
      padding-bottom: 16px;
      gap: 16px;
      flex-wrap: wrap;
    }

    .admin-panel-titles {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .admin-panel-title {
      font-family: var(--font-serif);
      font-size: 1.35rem;
      font-weight: 400;
      color: #ffffff;
    }

    .admin-panel-desc {
      font-size: 0.78rem;
      color: var(--text-muted);
      max-width: 680px;
      line-height: 1.5;
    }

    /* Search & Filter Toolbar */
    .admin-search-toolbar {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .admin-search-box {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #000000;
      border: 1px solid var(--admin-border);
      border-radius: var(--radius-sm);
      padding: 6px 12px;
      width: 260px;
      transition: border-color 0.15s ease;
    }

    .admin-search-box:focus-within {
      border-color: #ffffff;
    }

    .admin-search-input {
      background: transparent;
      border: none;
      outline: none;
      color: #ffffff;
      font-family: var(--font-mono);
      font-size: 0.75rem;
      width: 100%;
    }

    /* Curated Table */
    .admin-table-wrap {
      overflow-x: auto;
      border: 1px solid var(--admin-border);
      border-radius: var(--radius-sm);
      background: #000000;
    }

    .admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.8rem;
      text-align: left;
    }

    .admin-table th {
      background: #0c0c0c;
      font-family: var(--font-mono);
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-dim);
      padding: 12px 14px;
      border-bottom: 1px solid var(--admin-border);
      white-space: nowrap;
    }

    .admin-table td {
      padding: 14px;
      border-bottom: 1px solid var(--admin-border-subtle);
      color: var(--text-main);
      vertical-align: middle;
    }

    .admin-table tr:hover td {
      background: var(--admin-hover-bg);
    }

    .table-book-title {
      font-family: var(--font-serif);
      font-size: 0.98rem;
      color: #ffffff;
      font-weight: 500;
      line-height: 1.3;
    }

    .table-book-meta {
      font-family: var(--font-mono);
      font-size: 0.68rem;
      color: var(--text-dim);
      margin-top: 2px;
    }

    .badge-chunk-stat {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-family: var(--font-mono);
      font-size: 0.68rem;
      padding: 3px 8px;
      border-radius: 4px;
      border: 1px solid var(--admin-border);
      background: rgba(255, 255, 255, 0.04);
      color: var(--text-muted);
      white-space: nowrap;
    }

    .badge-vector-active {
      border-color: rgba(255, 255, 255, 0.3);
      color: #ffffff;
    }

    .btn-row-action {
      background: transparent;
      border: 1px solid var(--admin-border);
      color: var(--text-muted);
      padding: 5px 10px;
      border-radius: var(--radius-sm);
      font-size: 0.72rem;
      font-family: var(--font-mono);
      cursor: pointer;
      transition: all 0.15s ease;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .btn-row-action:hover {
      border-color: #ffffff;
      color: #ffffff;
    }

    .btn-row-delete:hover {
      border-color: #ff4d4d;
      color: #ff4d4d;
    }

    /* Ingestion Studio Drag & Drop */
    .ingest-dropzone {
      border: 2px dashed var(--admin-border-strong);
      border-radius: var(--radius-md);
      padding: 40px 24px;
      text-align: center;
      cursor: pointer;
      background: rgba(255, 255, 255, 0.015);
      transition: all 0.2s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
    }

    .ingest-dropzone:hover, .ingest-dropzone.dragover {
      border-color: #ffffff;
      background: rgba(255, 255, 255, 0.04);
    }

    .dropzone-label {
      font-family: var(--font-mono);
      font-size: 0.82rem;
      color: var(--text-main);
    }

    .dropzone-hint {
      font-size: 0.72rem;
      color: var(--text-dim);
    }

    .ingest-progress-box {
      width: 100%;
      max-width: 500px;
      background: #000000;
      border: 1px solid var(--admin-border);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      display: none;
      flex-direction: column;
      gap: 8px;
      text-align: left;
    }

    .ingest-progress-status {
      font-family: var(--font-mono);
      font-size: 0.72rem;
      color: #ffffff;
      display: flex;
      justify-content: space-between;
    }

    /* Multi-step Ingestion Pipeline */
    .ingest-steps-track {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
      margin-top: 10px;
    }

    .ingest-step-node {
      border: 1px solid var(--admin-border);
      border-radius: 4px;
      padding: 8px 10px;
      background: #000000;
      font-family: var(--font-mono);
      font-size: 0.65rem;
      color: var(--text-dim);
      display: flex;
      flex-direction: column;
      gap: 2px;
      transition: all 0.2s ease;
    }

    .ingest-step-node.active {
      border-color: #ffffff;
      color: #ffffff;
      background: rgba(255, 255, 255, 0.05);
    }

    .ingest-step-node.done {
      border-color: var(--admin-border-strong);
      color: var(--text-muted);
    }

    /* Diagnostics Pipeline Grid */
    .diag-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 16px;
    }

    .diag-card {
      background: #000000;
      border: 1px solid var(--admin-border);
      border-radius: var(--radius-sm);
      padding: 18px 20px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .diag-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: var(--font-mono);
      font-size: 0.7rem;
      text-transform: uppercase;
      color: var(--text-dim);
    }

    .diag-status-badge {
      font-size: 0.62rem;
      padding: 2px 6px;
      border-radius: 3px;
      border: 1px solid var(--admin-border);
      font-family: var(--font-mono);
    }

    .badge-ok {
      border-color: rgba(255, 255, 255, 0.4);
      color: #ffffff;
      background: rgba(255, 255, 255, 0.06);
    }

    .diag-card-title {
      font-family: var(--font-serif);
      font-size: 1.1rem;
      color: #ffffff;
    }

    .diag-card-desc {
      font-size: 0.74rem;
      color: var(--text-muted);
      line-height: 1.5;
    }

    /* Modal / Drawer */
    .admin-modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.85);
      backdrop-filter: blur(4px);
      z-index: 999;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .admin-modal-backdrop.open {
      display: flex;
    }

    .admin-modal-box {
      background: var(--admin-card-bg);
      border: 1px solid var(--admin-border-strong);
      border-radius: var(--radius-md);
      max-width: 760px;
      width: 100%;
      max-height: 85vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      animation: modalScale 0.18s ease-out;
    }

    @keyframes modalScale {
      from { transform: scale(0.96); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }

    .admin-modal-header {
      padding: 18px 22px;
      border-bottom: 1px solid var(--admin-border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #090909;
    }

    .admin-modal-body {
      padding: 20px 24px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .chunk-item-card {
      background: #000000;
      border: 1px solid var(--admin-border);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      transition: border-color 0.15s ease;
    }

    .chunk-item-card:hover {
      border-color: var(--admin-border-strong);
    }

    .chunk-item-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: var(--font-mono);
      font-size: 0.68rem;
      color: var(--text-dim);
    }

    .chunk-item-text {
      font-family: var(--font-mono);
      font-size: 0.74rem;
      color: #e0e0e0;
      line-height: 1.5;
      white-space: pre-wrap;
      max-height: 90px;
      overflow-y: auto;
      border-left: 2px solid var(--admin-border-strong);
      padding-left: 8px;
    }
  </style>
</head>
<body class="admin-body">

  <div class="admin-layout">

    <!-- Masthead -->
    <header class="admin-masthead">
      <div class="admin-brand-group">
        <img src="/img/logo.svg" alt="Verbose AI" class="admin-logo-mark">
        <div class="admin-brand-titles">
          <span class="admin-sys-title">Verbose AI &bull; Admin Console</span>
          <span class="admin-sys-subtitle">Lakebase Postgres Ingestion &amp; Vector Synthesis Engine</span>
        </div>
      </div>
      <div class="admin-masthead-controls">
        <div class="admin-status-indicator">
          <span class="status-pulse-dot"></span>
          <span><?= $dbConnected ? 'Neon Postgres (AWS Oregon)' : 'Database Offline' ?></span>
        </div>
        <a href="/app" class="btn-admin-action" id="nav-btn-reader">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
          <span>Reader Canvas</span>
        </a>
        <a href="/logout" class="btn-admin-action" style="color: var(--text-dim);">Sign Out</a>
      </div>
    </header>

    <!-- Executive KPI Metrics Ribbon -->
    <section class="admin-kpi-grid">
      <div class="admin-kpi-card">
        <div class="admin-kpi-label">
          <span>Curated Volumes</span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
          </svg>
        </div>
        <div class="admin-kpi-value" id="kpi-books-count"><?= count($books) ?></div>
        <div class="admin-kpi-sub">Active volumes grounded in Neon Postgres</div>
      </div>

      <div class="admin-kpi-card">
        <div class="admin-kpi-label">
          <span>Semantic Chunks</span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="8" y1="6" x2="21" y2="6"></line>
            <line x1="8" y1="12" x2="21" y2="12"></line>
            <line x1="8" y1="18" x2="21" y2="18"></line>
            <line x1="3" y1="6" x2="3.01" y2="6"></line>
            <line x1="3" y1="12" x2="3.01" y2="12"></line>
            <line x1="3" y1="18" x2="3.01" y2="18"></line>
          </svg>
        </div>
        <div class="admin-kpi-value" id="kpi-chunks-count"><?= $totalChunks ?></div>
        <div class="admin-kpi-sub">Indexed paragraphs with page marker tracking</div>
      </div>

      <div class="admin-kpi-card">
        <div class="admin-kpi-label">
          <span>pgvector 768-dim Coverage</span>
          <span id="kpi-vector-pct-badge" style="color: #ffffff;"><?= $vectorPct ?>%</span>
        </div>
        <div class="admin-kpi-value" id="kpi-vector-count"><?= $embeddedChunks ?> <span style="font-size: 1rem; color: var(--text-dim); font-weight: normal;">/ <?= $totalChunks ?></span></div>
        <div class="progress-track">
          <div class="progress-fill" id="kpi-vector-progress" style="width: <?= $vectorPct ?>%;"></div>
        </div>
        <div class="admin-kpi-sub" style="display: flex; justify-content: space-between; align-items: center; margin-top: 2px;">
          <span>Gemini gemini-embedding-2 vectors</span>
          <?php if ($embeddedChunks < $totalChunks): ?>
            <button onclick="triggerQuickBackfill()" class="btn-row-action" style="padding: 2px 6px; font-size: 0.65rem;">Backfill Missing</button>
          <?php endif; ?>
        </div>
      </div>

      <div class="admin-kpi-card">
        <div class="admin-kpi-label">
          <span>AI Pipeline Cascade</span>
          <span class="badge-chunks" style="background: rgba(255,255,255,0.06); font-size: 0.6rem;">MULTI-ENGINE</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 4px;">
          <div style="font-family: var(--font-mono); font-size: 0.72rem; color: #ffffff; display: flex; justify-content: space-between;">
            <span>Groq LPU Synthesis</span>
            <span style="color: var(--text-dim);"><?= $groqConfigured ? 'ONLINE' : 'UNSET' ?></span>
          </div>
          <div style="font-family: var(--font-mono); font-size: 0.72rem; color: #ffffff; display: flex; justify-content: space-between;">
            <span>NVIDIA Nemotron VL</span>
            <span style="color: var(--text-dim);"><?= $openrouterConfigured ? 'ONLINE' : 'UNSET' ?></span>
          </div>
          <div style="font-family: var(--font-mono); font-size: 0.72rem; color: #ffffff; display: flex; justify-content: space-between;">
            <span>Gemini Embeddings</span>
            <span style="color: var(--text-dim);"><?= $geminiConfigured ? 'ONLINE' : 'UNSET' ?></span>
          </div>
        </div>
        <div class="admin-kpi-sub">Auto-failover with zero hallucination</div>
      </div>
    </section>

    <!-- Administrative Tab Switcher -->
    <nav class="admin-tabs-nav">
      <button class="admin-tab-btn active" data-tab="tab-library" onclick="switchAdminTab('tab-library')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
        </svg>
        <span>[01] Library &amp; Chunk Inspector</span>
        <span class="admin-tab-badge" id="tab-books-count-badge"><?= count($books) ?></span>
      </button>

      <button class="admin-tab-btn" data-tab="tab-ingest" onclick="switchAdminTab('tab-ingest')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="17 8 12 3 7 8"></polyline>
          <line x1="12" y1="3" x2="12" y2="15"></line>
        </svg>
        <span>[02] Document Ingestion Studio</span>
      </button>

      <button class="admin-tab-btn" data-tab="tab-diagnostics" onclick="switchAdminTab('tab-diagnostics')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
        </svg>
        <span>[03] AI &amp; Vector Diagnostics</span>
      </button>
    </nav>

    <!-- TAB 1: Library & Chunks Inventory -->
    <div id="tab-library" class="admin-tab-pane active">
      <div class="admin-panel-card">
        <div class="admin-panel-header">
          <div class="admin-panel-titles">
            <h2 class="admin-panel-title">Curated Library Inventory</h2>
            <p class="admin-panel-desc">All ingested book volumes currently active in Neon Lakebase Postgres. Inspect semantic chunks, page mappings, or delete volumes.</p>
          </div>
          <div class="admin-search-toolbar">
            <div class="admin-search-box">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--text-dim);">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input type="text" id="library-search-input" class="admin-search-input" placeholder="Search volumes by title..." oninput="filterLibraryTable()">
            </div>
          </div>
        </div>

        <div class="admin-table-wrap">
          <table class="admin-table" id="books-inventory-table">
            <thead>
              <tr>
                <th style="width: 50px;">ID</th>
                <th>Volume Details</th>
                <th>Author</th>
                <th>Description</th>
                <th style="text-align: center;">Chunks</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody id="books-table-body">
              <?php if (empty($books)): ?>
                <tr id="empty-books-row">
                  <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 36px;">
                    No books in library. Switch to the <strong>Ingestion Studio</strong> tab to drop your first volume.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($books as $b): ?>
                  <tr class="book-table-row" id="book-row-<?= $b['id'] ?>" data-title="<?= strtolower(htmlspecialchars($b['title'])) ?>" data-author="<?= strtolower(htmlspecialchars($b['author'] ?? '')) ?>">
                    <td style="font-family: var(--font-mono); color: var(--text-dim);"><?= $b['id'] ?></td>
                    <td>
                      <div class="table-book-title"><?= htmlspecialchars($b['title']) ?></div>
                      <div class="table-book-meta">Added <?= substr($b['created_at'] ?? '', 0, 10) ?> &bull; Lakebase Postgres</div>
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars($b['author'] ?? 'Unknown') ?></td>
                    <td style="color: var(--text-dim); font-size: 0.76rem; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                      <?= htmlspecialchars($b['description'] ?? 'Curated book volume') ?>
                    </td>
                    <td style="text-align: center;">
                      <span class="badge-chunk-stat badge-vector-active">
                        <?= $b['chunks_count'] ?? 0 ?> chunks
                      </span>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <button class="btn-row-action" onclick="openChunkInspector(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['title'])) ?>')">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="11" cy="11" r="8"></circle>
                          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <span>Inspect Chunks</span>
                      </button>
                      <button class="btn-row-action btn-row-delete" onclick="confirmDeleteBook(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['title'])) ?>')">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        <span>Delete</span>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: Document Ingestion Studio -->
    <div id="tab-ingest" class="admin-tab-pane">
      <div class="admin-panel-card">
        <div class="admin-panel-header">
          <div class="admin-panel-titles">
            <h2 class="admin-panel-title">Document &amp; PDF Ingestion Studio</h2>
            <p class="admin-panel-desc">Ingest multi-page PDFs, books, manuscripts, or markdown volumes. Client-side PDF parsing extracts pages and coordinates 768-dimensional Gemini vectorization.</p>
          </div>
        </div>

        <!-- Drag & Drop Zone -->
        <div id="admin-drop-zone" class="ingest-dropzone">
          <input type="file" id="admin-file-input" accept=".pdf,.txt,.md" style="display: none;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: var(--text-muted);">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="12" y1="18" x2="12" y2="12"></line>
            <line x1="9" y1="15" x2="15" y2="15"></line>
          </svg>
          <div class="dropzone-label">Drop PDF, TXT, or Markdown volume here, or <span style="text-decoration: underline; color: #ffffff;">click to browse</span></div>
          <div class="dropzone-hint">Optimized for multi-page textbooks, manuals, and literary texts. Page markers <code>[Page X]</code> preserved.</div>

          <div id="ingest-progress-box" class="ingest-progress-box">
            <div class="ingest-progress-status">
              <span id="ingest-progress-label">Parsing document...</span>
              <span id="ingest-progress-percent">0%</span>
            </div>
            <div class="progress-track">
              <div id="ingest-progress-fill" class="progress-fill" style="width: 0%;"></div>
            </div>
          </div>
        </div>

        <!-- Ingestion Form -->
        <form id="admin-ingest-form" onsubmit="event.preventDefault(); submitAdminIngest();" style="display: flex; flex-direction: column; gap: 18px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label class="form-label" style="font-family: var(--font-mono); font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim);">Volume Title</label>
              <input type="text" id="admin-title" class="form-input" placeholder="e.g. English for Ethiopia Grade 8" required>
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <label class="form-label" style="font-family: var(--font-mono); font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim);">Author / Publisher</label>
              <input type="text" id="admin-author" class="form-input" placeholder="e.g. Ministry of Education">
            </div>
          </div>

          <div style="display: flex; flex-direction: column; gap: 6px;">
            <label class="form-label" style="font-family: var(--font-mono); font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim);">Description / Overview</label>
            <input type="text" id="admin-desc" class="form-input" placeholder="e.g. Official Grade 8 curriculum textbook covering economic activities, calendars, traffic rules, etc.">
          </div>

          <div style="display: flex; flex-direction: column; gap: 6px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <label class="form-label" style="font-family: var(--font-mono); font-size: 0.72rem; text-transform: uppercase; color: var(--text-dim);">Extracted Content &amp; Pages</label>
              <span id="content-word-counter" style="font-family: var(--font-mono); font-size: 0.68rem; color: var(--text-dim);">0 words &bull; ~0 chunks</span>
            </div>
            <textarea id="admin-content" class="form-input" rows="8" placeholder="Extracted PDF text or pasted volume passages will appear here with [Page X] tags..." required style="resize: vertical; font-family: var(--font-mono); font-size: 0.76rem; line-height: 1.55;"></textarea>
          </div>

          <!-- Step Progress Track -->
          <div class="ingest-steps-track" id="ingest-steps-tracker" style="display: none;">
            <div class="ingest-step-node" id="step-node-1">
              <strong>Step 1</strong>
              <span>PDF Extraction</span>
            </div>
            <div class="ingest-step-node" id="step-node-2">
              <strong>Step 2</strong>
              <span>Paragraph Chunking</span>
            </div>
            <div class="ingest-step-node" id="step-node-3">
              <strong>Step 3</strong>
              <span>768-dim Vectors</span>
            </div>
            <div class="ingest-step-node" id="step-node-4">
              <strong>Step 4</strong>
              <span>Neon Postgres Commit</span>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--admin-border-subtle); padding-top: 16px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <input type="checkbox" id="chk-auto-embed" checked style="accent-color: #ffffff; cursor: pointer;">
              <label for="chk-auto-embed" style="font-family: var(--font-mono); font-size: 0.74rem; color: var(--text-muted); cursor: pointer;">
                Embed chunks using Gemini 768-dim vectors during ingestion
              </label>
            </div>
            <div style="display: flex; align-items: center; gap: 14px;">
              <span id="admin-form-msg" style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-dim);"></span>
              <button type="submit" id="btn-admin-submit" class="btn-auth-submit" style="margin-top: 0; padding: 11px 22px; font-size: 0.8rem;">
                Ingest Volume into Neon Postgres
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- TAB 3: Diagnostics & Vectors -->
    <div id="tab-diagnostics" class="admin-tab-pane">
      <div class="admin-panel-card">
        <div class="admin-panel-header">
          <div class="admin-panel-titles">
            <h2 class="admin-panel-title">pgvector &amp; AI Neural Architecture</h2>
            <p class="admin-panel-desc">Real-time telemetry on Lakebase Postgres, vector embeddings coverage, and multi-model AI routing.</p>
          </div>
          <div style="display: flex; gap: 8px;">
            <button class="btn-admin-action" onclick="runSchemaMigrate()">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"></path>
              </svg>
              <span>Verify DB Schema</span>
            </button>
            <button class="btn-admin-action" id="btn-backfill-full" onclick="triggerQuickBackfill()">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
              </svg>
              <span>Backfill Missing Embeddings</span>
            </button>
          </div>
        </div>

        <div class="diag-grid">
          <!-- Neon Lakebase Postgres -->
          <div class="diag-card">
            <div class="diag-card-header">
              <span>Primary Storage</span>
              <span class="diag-status-badge badge-ok">CONNECTED</span>
            </div>
            <div class="diag-card-title">Neon Lakebase Postgres</div>
            <div class="diag-card-desc">Serverless PostgreSQL 16 on AWS us-west-2 (Oregon). Scaled pooling, instant branching, and transactional isolation.</div>
            <div style="font-family: var(--font-mono); font-size: 0.68rem; color: var(--text-dim); margin-top: auto; border-top: 1px solid var(--admin-border); padding-top: 8px;">
              HNSW Cosine Index &bull; vector(768)
            </div>
          </div>

          <!-- Gemini Embeddings -->
          <div class="diag-card">
            <div class="diag-card-header">
              <span>Embedding Engine</span>
              <span class="diag-status-badge badge-ok"><?= $geminiConfigured ? 'READY' : 'UNCONFIGURED' ?></span>
            </div>
            <div class="diag-card-title">Gemini gemini-embedding-2</div>
            <div class="diag-card-desc">768-dimensional normalized unit vectors for semantic document retrieval with sub-50ms cosine similarity searches.</div>
            <div style="font-family: var(--font-mono); font-size: 0.68rem; color: var(--text-dim); margin-top: auto; border-top: 1px solid var(--admin-border); padding-top: 8px;">
              Batch Limit: 32 chunks &bull; Dim: 768
            </div>
          </div>

          <!-- Groq LPUs -->
          <div class="diag-card">
            <div class="diag-card-header">
              <span>Inference Engine</span>
              <span class="diag-status-badge badge-ok"><?= $groqConfigured ? 'READY' : 'UNCONFIGURED' ?></span>
            </div>
            <div class="diag-card-title">Groq LPUs &bull; Qwen 3.8 27B</div>
            <div class="diag-card-desc">Ultra-fast hardware token inference. Synthesizes grounded answers strictly based on verified book citations.</div>
            <div style="font-family: var(--font-mono); font-size: 0.68rem; color: var(--text-dim); margin-top: auto; border-top: 1px solid var(--admin-border); padding-top: 8px;">
              Cadence: ~300 tokens/sec &bull; Temperature 0.2
            </div>
          </div>

          <!-- NVIDIA Nemotron VL -->
          <div class="diag-card">
            <div class="diag-card-header">
              <span>Optical Vision Model</span>
              <span class="diag-status-badge badge-ok"><?= $openrouterConfigured ? 'READY' : 'UNCONFIGURED' ?></span>
            </div>
            <div class="diag-card-title">Nemotron 3.5 Super VL</div>
            <div class="diag-card-desc">Dedicated multimodal vision model for manuscript scanning, optical transcription, and visual page analysis.</div>
            <div style="font-family: var(--font-mono); font-size: 0.68rem; color: var(--text-dim); margin-top: auto; border-top: 1px solid var(--admin-border); padding-top: 8px;">
              Reasoning Suppressed &bull; Dropdown Fallback
            </div>
          </div>
        </div>

        <!-- Telemetry Log Window -->
        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px;">
          <span style="font-family: var(--font-mono); font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim);">Live Telemetry Log</span>
          <div id="diag-console-log" style="background: #000000; border: 1px solid var(--admin-border); border-radius: var(--radius-sm); padding: 12px 14px; font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-muted); min-height: 90px; line-height: 1.6;">
            [Ready] Verbose AI Admin Console initialized.<br>
            [Database] Connected to Neon Lakebase Postgres (AWS Oregon).<br>
            [Vector] <?= $embeddedChunks ?> of <?= $totalChunks ?> chunks vectorized (<?= $vectorPct ?>%).
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Modal 1: Chunk Inspector Drawer -->
  <div id="chunk-modal" class="admin-modal-backdrop">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <div>
          <h3 id="modal-chunk-title" style="font-family: var(--font-serif); font-size: 1.15rem; color: #ffffff; margin: 0;">Volume Chunks</h3>
          <span id="modal-chunk-sub" style="font-family: var(--font-mono); font-size: 0.68rem; color: var(--text-dim);">Inspecting digested pages</span>
        </div>
        <button class="btn-row-action" onclick="closeChunkInspector()">&times; Close</button>
      </div>
      <div class="admin-modal-body" id="modal-chunks-list">
        <div style="text-align: center; color: var(--text-muted); font-family: var(--font-mono); font-size: 0.75rem; padding: 24px;">
          Loading volume chunks from database...
        </div>
      </div>
    </div>
  </div>

  <!-- Modal 2: Delete Confirmation Modal -->
  <div id="delete-modal" class="admin-modal-backdrop">
    <div class="admin-modal-box" style="max-width: 440px;">
      <div class="admin-modal-header">
        <h3 style="font-family: var(--font-serif); font-size: 1.15rem; color: #ff5555; margin: 0;">Delete Book Volume</h3>
        <button class="btn-row-action" onclick="closeDeleteModal()">&times;</button>
      </div>
      <div class="admin-modal-body" style="gap: 12px;">
        <p style="font-size: 0.85rem; color: var(--text-main); margin: 0; line-height: 1.5;">
          Are you sure you want to permanently delete <strong id="delete-book-name" style="color: #ffffff;"></strong> from Neon Postgres?
        </p>
        <p style="font-size: 0.74rem; color: var(--text-dim); margin: 0; line-height: 1.4;">
          All associated book chunks, page index markers, and 768-dim embeddings will be permanently removed.
        </p>
        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
          <button class="btn-row-action" onclick="closeDeleteModal()">Cancel</button>
          <button class="btn-row-action btn-row-delete" id="btn-confirm-delete" onclick="executeDeleteBook()">Confirm Delete</button>
        </div>
      </div>
    </div>
  </div>

  <script src="/js/sfx.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>
    if (window.pdfjsLib) {
      window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    let pendingDeleteBookId = null;

    // Tab Management
    function switchAdminTab(tabId) {
      window.SFX?.playBlip(750, 0.03);
      document.querySelectorAll('.admin-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-tab') === tabId);
      });
      document.querySelectorAll('.admin-tab-pane').forEach(pane => {
        pane.classList.toggle('active', pane.id === tabId);
      });
    }

    // Filter Table
    function filterLibraryTable() {
      const q = document.getElementById('library-search-input').value.toLowerCase().trim();
      const rows = document.querySelectorAll('.book-table-row');
      rows.forEach(row => {
        const title = row.getAttribute('data-title') || '';
        const author = row.getAttribute('data-author') || '';
        const match = title.includes(q) || author.includes(q);
        row.style.display = match ? '' : 'none';
      });
    }

    // Word counter for content input
    const contentInput = document.getElementById('admin-content');
    const wordCounter = document.getElementById('content-word-counter');
    contentInput?.addEventListener('input', () => {
      const text = contentInput.value.trim();
      const words = text ? text.split(/\s+/).length : 0;
      const estChunks = Math.max(1, Math.ceil(text.length / 1400));
      wordCounter.textContent = `${words} words \u2022 ~${estChunks} semantic chunks`;
    });

    // Ingestion File Drop & PDF Extraction
    const dropZone = document.getElementById('admin-drop-zone');
    const fileInput = document.getElementById('admin-file-input');
    const progressBox = document.getElementById('ingest-progress-box');
    const progressLabel = document.getElementById('ingest-progress-label');
    const progressPercent = document.getElementById('ingest-progress-percent');
    const progressFill = document.getElementById('ingest-progress-fill');
    const titleInput = document.getElementById('admin-title');
    const authorInput = document.getElementById('admin-author');
    const descInput = document.getElementById('admin-desc');
    const btnSubmit = document.getElementById('btn-admin-submit');
    const formMsg = document.getElementById('admin-form-msg');
    const stepsTracker = document.getElementById('ingest-steps-tracker');

    dropZone?.addEventListener('click', () => {
      window.SFX?.playBlip(650, 0.03);
      fileInput.click();
    });

    ['dragenter', 'dragover'].forEach(name => {
      dropZone?.addEventListener(name, (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
      });
    });

    ['dragleave', 'drop'].forEach(name => {
      dropZone?.addEventListener(name, (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
      });
    });

    dropZone?.addEventListener('drop', (e) => {
      const files = e.dataTransfer?.files;
      if (files && files.length > 0) processIncomingFile(files[0]);
    });

    fileInput?.addEventListener('change', (e) => {
      const files = e.target.files;
      if (files && files.length > 0) processIncomingFile(files[0]);
    });

    async function processIncomingFile(file) {
      if (!file) return;
      window.SFX?.playBlip(820, 0.04);
      const cleanName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
      if (!titleInput.value) {
        titleInput.value = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
      }

      progressBox.style.display = 'flex';
      setIngestProgress(5, 'Reading file binary...');

      if (file.name.toLowerCase().endsWith('.pdf')) {
        try {
          const buffer = await file.arrayBuffer();
          const pdf = await window.pdfjsLib.getDocument({ data: buffer }).promise;
          let fullText = '';
          const totalPages = pdf.numPages;

          for (let p = 1; p <= totalPages; p++) {
            const pct = Math.round((p / totalPages) * 100);
            setIngestProgress(pct, `Extracting PDF: Page ${p} of ${totalPages}...`);
            const page = await pdf.getPage(p);
            const textContent = await page.getTextContent();
            fullText += `\n\n[Page ${p}]\n` + textContent.items.map(i => i.str).join(' ');
          }

          contentInput.value = fullText.trim();
          contentInput.dispatchEvent(new Event('input'));
          setIngestProgress(100, `Successfully parsed ${totalPages} pages from ${file.name}`);
          window.SFX?.playReplyChime();
        } catch (err) {
          setIngestProgress(0, 'Error parsing PDF: ' + err.message);
        }
      } else {
        const reader = new FileReader();
        reader.onload = () => {
          contentInput.value = reader.result;
          contentInput.dispatchEvent(new Event('input'));
          setIngestProgress(100, `Loaded text: ${file.name} (${Math.round(file.size / 1024)} KB)`);
          window.SFX?.playReplyChime();
        };
        reader.readAsText(file);
      }
    }

    function setIngestProgress(pct, msg) {
      if (progressFill) progressFill.style.width = pct + '%';
      if (progressPercent) progressPercent.textContent = pct + '%';
      if (progressLabel) progressLabel.textContent = msg;
    }

    // Submit Ingestion
    async function submitAdminIngest() {
      const title = titleInput.value.trim();
      const author = authorInput.value.trim() || 'Unknown';
      const description = descInput.value.trim() || 'Curated library volume.';
      const content = contentInput.value.trim();

      if (!title || !content) return;

      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Ingesting & Vectorizing...';
      formMsg.textContent = 'Processing volume chunks...';
      stepsTracker.style.display = 'grid';

      updateStepNode(1, 'done');
      updateStepNode(2, 'active');

      try {
        updateStepNode(2, 'done');
        updateStepNode(3, 'active');

        const res = await fetch('/api.php?action=ingest_book', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ title, author, description, content })
        });

        const data = await res.json();
        if (data.success) {
          updateStepNode(3, 'done');
          updateStepNode(4, 'done');
          window.SFX?.playReplyChime();
          formMsg.textContent = `Ingested "${title}" (${data.chunks_count} chunks, ${data.embedded_chunks} embedded)!`;
          setTimeout(() => window.location.reload(), 1400);
        } else {
          alert(data.error || 'Ingestion failed.');
          formMsg.textContent = 'Ingestion error.';
        }
      } catch (err) {
        alert('Network failure while committing book.');
        formMsg.textContent = 'Network error.';
      } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Ingest Volume into Neon Postgres';
      }
    }

    function updateStepNode(num, status) {
      const node = document.getElementById('step-node-' + num);
      if (!node) return;
      node.classList.remove('active', 'done');
      if (status) node.classList.add(status);
    }

    // Chunk Inspector
    async function openChunkInspector(bookId, bookTitle) {
      window.SFX?.playBlip(720, 0.03);
      const modal = document.getElementById('chunk-modal');
      const titleEl = document.getElementById('modal-chunk-title');
      const subEl = document.getElementById('modal-chunk-sub');
      const listEl = document.getElementById('modal-chunks-list');

      titleEl.textContent = bookTitle;
      subEl.textContent = `Book ID: ${bookId} \u2022 Loading chunks...`;
      listEl.innerHTML = '<div style="text-align:center; color: var(--text-muted); font-family: var(--font-mono); font-size: 0.75rem; padding: 24px;">Querying chunks from Neon Postgres...</div>';
      modal.classList.add('open');

      try {
        const res = await fetch(`/api.php?action=get_book_chunks&book_id=${bookId}`);
        const data = await res.json();
        if (data.success && Array.isArray(data.chunks)) {
          subEl.textContent = `Book ID: ${bookId} \u2022 ${data.chunks.length} chunks indexed`;
          listEl.innerHTML = '';

          data.chunks.forEach((c, idx) => {
            const card = document.createElement('div');
            card.className = 'chunk-item-card';
            const isVec = c.is_embedded;
            card.innerHTML = `
              <div class="chunk-item-header">
                <span>#${c.chunk_index || (idx + 1)} &bull; ${escapeHtml(c.chapter_title || 'Section')} &bull; ${c.char_length || 0} chars</span>
                <span class="badge-chunk-stat ${isVec ? 'badge-vector-active' : ''}">${isVec ? '768-DIM VECTORIZED' : 'PENDING'}</span>
              </div>
              <div class="chunk-item-text">${escapeHtml(c.chunk_text)}</div>
            `;
            listEl.appendChild(card);
          });
        } else {
          listEl.innerHTML = '<div style="text-align:center; color: #ff5555; font-family: var(--font-mono); font-size: 0.75rem; padding: 24px;">Failed to load chunks.</div>';
        }
      } catch (e) {
        listEl.innerHTML = '<div style="text-align:center; color: #ff5555; font-family: var(--font-mono); font-size: 0.75rem; padding: 24px;">Network error loading chunks.</div>';
      }
    }

    function closeChunkInspector() {
      window.SFX?.playBlip(500, 0.03);
      document.getElementById('chunk-modal').classList.remove('open');
    }

    // Delete Volume
    function confirmDeleteBook(bookId, bookTitle) {
      window.SFX?.playBlip(440, 0.04);
      pendingDeleteBookId = bookId;
      document.getElementById('delete-book-name').textContent = `"${bookTitle}" (ID: ${bookId})`;
      document.getElementById('delete-modal').classList.add('open');
    }

    function closeDeleteModal() {
      window.SFX?.playBlip(500, 0.03);
      pendingDeleteBookId = null;
      document.getElementById('delete-modal').classList.remove('open');
    }

    async function executeDeleteBook() {
      if (!pendingDeleteBookId) return;
      const btn = document.getElementById('btn-confirm-delete');
      btn.disabled = true;
      btn.textContent = 'Deleting...';

      try {
        const res = await fetch('/api.php?action=delete_book', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ book_id: pendingDeleteBookId })
        });
        const data = await res.json();
        if (data.success) {
          const row = document.getElementById('book-row-' + pendingDeleteBookId);
          if (row) row.remove();
          closeDeleteModal();
          window.location.reload();
        } else {
          alert(data.error || 'Delete failed.');
        }
      } catch (err) {
        alert('Network error deleting book.');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Confirm Delete';
      }
    }

    // Backfill Trigger
    async function triggerQuickBackfill() {
      window.SFX?.playBlip(800, 0.04);
      logDiag('[Vector Backfill] Initiating batch embedding call...');
      const btn = document.getElementById('btn-backfill-full');
      if (btn) btn.disabled = true;

      try {
        const res = await fetch('/api.php?action=backfill_embeddings');
        const data = await res.json();
        if (data.success) {
          logDiag(`[Vector Backfill] ${data.message}`);
          if (data.embedding_pct !== undefined) {
            document.getElementById('kpi-vector-pct-badge').textContent = `${data.embedding_pct}%`;
            document.getElementById('kpi-vector-progress').style.width = `${data.embedding_pct}%`;
            document.getElementById('kpi-vector-count').innerHTML = `${data.embedded_chunks} <span style="font-size: 1rem; color: var(--text-dim); font-weight: normal;">/ ${data.total_chunks}</span>`;
          }
          if (data.remaining > 0) {
            logDiag(`[Vector Backfill] Triggering next batch (${data.remaining} remaining)...`);
            setTimeout(triggerQuickBackfill, 1500);
          } else {
            window.SFX?.playReplyChime();
            logDiag('[Vector Backfill] All chunks successfully vectorized in pgvector!');
          }
        } else {
          logDiag(`[Vector Backfill Error] ${data.error || 'Backfill failed.'}`);
        }
      } catch (e) {
        logDiag(`[Vector Backfill Error] Network connection error.`);
      } finally {
        if (btn) btn.disabled = false;
      }
    }

    // Schema Migrate
    async function runSchemaMigrate() {
      window.SFX?.playBlip(700, 0.03);
      logDiag('[Schema] Running database migration and vector extension verify...');
      try {
        const res = await fetch('/api.php?action=migrate');
        const data = await res.json();
        if (data.success) {
          logDiag('[Schema Success] Database schema and HNSW indexes verified.');
          window.SFX?.playReplyChime();
        } else {
          logDiag(`[Schema Error] ${data.error}`);
        }
      } catch (e) {
        logDiag('[Schema Error] Network error running migration.');
      }
    }

    function logDiag(msg) {
      const consoleEl = document.getElementById('diag-console-log');
      if (!consoleEl) return;
      const time = new Date().toLocaleTimeString();
      consoleEl.innerHTML += `<br>[${time}] ${escapeHtml(msg)}`;
      consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    // Close modals on ESC or backdrop click
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeChunkInspector();
        closeDeleteModal();
      }
    });

    ['chunk-modal', 'delete-modal'].forEach(id => {
      document.getElementById(id)?.addEventListener('click', (e) => {
        if (e.target.id === id) {
          closeChunkInspector();
          closeDeleteModal();
        }
      });
    });
  </script>
</body>
</html>