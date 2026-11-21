<?php
/**
 * Verbose AI - Admin Console & PDF Ingestion Center
 */

if (empty($_COOKIE['verbose_session'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../src/Database.php';
$pdo = Database::getConnection();

$books = [];
$totalChunks = 0;
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, title, author, description, chunks_count, created_at FROM books ORDER BY id DESC");
        $books = $stmt->fetchAll() ?: [];
        foreach ($books as $b) {
            $totalChunks += intval($b['chunks_count'] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Admin fetch books error: " . $e->getMessage());
    }
}
$currentUser = htmlspecialchars($_COOKIE['verbose_session'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verbose AI - Admin Console</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
  <style>
    .admin-container {
      max-width: 1040px;
      margin: 0 auto;
      padding: 36px 24px 80px 24px;
      display: flex;
      flex-direction: column;
      gap: 32px;
    }
    .admin-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border-medium);
      padding-bottom: 20px;
    }
    .admin-title-area {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .admin-title {
      font-family: var(--font-serif);
      font-size: 1.8rem;
      font-weight: 400;
      letter-spacing: -0.02em;
      color: var(--text-main);
    }
    .admin-subtitle {
      font-size: 0.8rem;
      color: var(--text-muted);
      font-family: var(--font-mono);
    }
    .admin-nav-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .btn-admin-nav {
      background: transparent;
      border: 1px solid var(--border-medium);
      color: var(--text-main);
      padding: 8px 14px;
      border-radius: var(--radius-sm);
      font-size: 0.8rem;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.15s ease;
      font-family: var(--font-sans);
    }
    .btn-admin-nav:hover {
      background: var(--bg-hover);
      border-color: #ffffff;
    }
    .metrics-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
    }
    .metric-card {
      background: var(--bg-card);
      border: 1px solid var(--border-medium);
      border-radius: var(--radius-md);
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .metric-card-label {
      font-family: var(--font-mono);
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
    }
    .metric-card-value {
      font-family: var(--font-mono);
      font-size: 1.7rem;
      font-weight: 600;
      color: var(--text-main);
    }
    .metric-card-sub {
      font-size: 0.72rem;
      color: var(--text-dim);
    }
    .admin-section {
      background: var(--bg-card);
      border: 1px solid var(--border-medium);
      border-radius: var(--radius-md);
      padding: 28px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .admin-section-header {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .admin-section-title {
      font-family: var(--font-serif);
      font-size: 1.3rem;
      color: var(--text-main);
    }
    .admin-section-desc {
      font-size: 0.78rem;
      color: var(--text-muted);
    }
    .drop-zone {
      border: 2px dashed var(--border-medium);
      border-radius: var(--radius-sm);
      padding: 32px 20px;
      text-align: center;
      cursor: pointer;
      background: var(--bg-hover);
      transition: all 0.2s ease;
    }
    .drop-zone:hover, .drop-zone.dragover {
      border-color: #ffffff;
      background: rgba(255, 255, 255, 0.05);
    }
    .ingest-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    @media (max-width: 640px) {
      .ingest-form-grid { grid-template-columns: 1fr; }
    }
    .form-group-full {
      grid-column: 1 / -1;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .library-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.8rem;
    }
    .library-table th {
      text-align: left;
      font-family: var(--font-mono);
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-dim);
      padding: 10px 12px;
      border-bottom: 1px solid var(--border-medium);
    }
    .library-table td {
      padding: 12px;
      border-bottom: 1px solid var(--border-subtle);
      color: var(--text-main);
    }
    .library-table tr:hover td {
      background: var(--bg-hover);
    }
    .btn-delete-book {
      background: transparent;
      border: 1px solid var(--border-medium);
      color: var(--text-muted);
      padding: 4px 10px;
      border-radius: var(--radius-sm);
      font-size: 0.72rem;
      font-family: var(--font-mono);
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .btn-delete-book:hover {
      border-color: #ff5555;
      color: #ff5555;
    }
    .badge-chunks {
      font-family: var(--font-mono);
      font-size: 0.72rem;
      background: rgba(255, 255, 255, 0.08);
      padding: 3px 8px;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border-subtle);
    }
  </style>
</head>
<body style="background-color: var(--bg-page); min-height: 100vh;">

  <div class="admin-container">
    
    <!-- Top Bar -->
    <header class="admin-header">
      <div class="admin-title-area">
        <h1 class="admin-title">Verbose AI Admin Console</h1>
        <span class="admin-subtitle">Logged in as <?= $currentUser ?> &bull; Neon Postgres Ingestion Panel</span>
      </div>
      <div class="admin-nav-actions">
        <a href="/app" class="btn-admin-nav">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
          <span>Return to Reader</span>
        </a>
        <a href="/logout" class="btn-admin-nav" style="color: var(--text-dim);">Sign Out</a>
      </div>
    </header>

    <!-- Metrics -->
    <div class="metrics-row">
      <div class="metric-card">
        <span class="metric-card-label">Curated Volumes</span>
        <span class="metric-card-value" id="count-books"><?= count($books) ?></span>
        <span class="metric-card-sub">Active in Neon Postgres</span>
      </div>
      <div class="metric-card">
        <span class="metric-card-label">Indexed Chunks</span>
        <span class="metric-card-value" id="count-chunks"><?= $totalChunks ?></span>
        <span class="metric-card-sub">Semantic chunks with pgvector</span>
      </div>
      <div class="metric-card">
        <span class="metric-card-label">Database Connection</span>
        <span class="metric-card-value" style="font-size: 1.1rem; color: #ffffff;"><?= $pdo ? 'CONNECTED' : 'STANDBY' ?></span>
        <span class="metric-card-sub">Neon Lakebase Postgres (AWS Oregon)</span>
      </div>
    </div>

    <!-- PDF & Book Ingestion Station -->
    <section class="admin-section">
      <div class="admin-section-header">
        <h2 class="admin-section-title">PDF & Document Ingestion Station</h2>
        <p class="admin-section-desc">Drop any multi-page PDF or text volume. The engine parses page content in real-time, splits text into semantic paragraphs, and commits verified chunks directly into the database.</p>
      </div>

      <!-- PDF Drop Zone -->
      <div id="admin-drop-zone" class="drop-zone">
        <input type="file" id="admin-file-input" accept=".pdf,.txt,.md" style="display: none;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="margin: 0 auto 10px; color: var(--text-muted); display: block;">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="12" y1="18" x2="12" y2="12"></line>
          <line x1="9" y1="15" x2="15" y2="15"></line>
        </svg>
        <span style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-main); display: block;">
          Drop PDF / Text file here, or <span style="text-decoration: underline;">click to browse</span>
        </span>
        <span style="font-size: 0.72rem; color: var(--text-dim); display: block; margin-top: 4px;">
          Supports multi-page PDFs, text manuscripts, and markdown files
        </span>
        <div id="admin-pdf-status" style="display: none; margin-top: 10px; font-family: var(--font-mono); font-size: 0.75rem; color: #ffffff;"></div>
      </div>

      <!-- Ingestion Form -->
      <form id="admin-ingest-form" onsubmit="event.preventDefault(); submitAdminIngest();">
        <div class="ingest-form-grid">
          <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
            <label class="form-label">Book Title</label>
            <input type="text" id="admin-title" class="form-input" placeholder="e.g. Meditations" required>
          </div>
          <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
            <label class="form-label">Author</label>
            <input type="text" id="admin-author" class="form-input" placeholder="e.g. Marcus Aurelius">
          </div>
          <div class="form-group-full">
            <label class="form-label">Description / Summary</label>
            <input type="text" id="admin-desc" class="form-input" placeholder="e.g. Stoic reflections on duty, inner sovereignty, and mortal existence.">
          </div>
          <div class="form-group-full">
            <label class="form-label">Book Content / Extracted PDF Pages</label>
            <textarea id="admin-content" class="form-input" rows="8" placeholder="Extracted PDF text or pasted volume passages will appear here..." required style="resize: vertical; font-family: var(--font-mono); font-size: 0.78rem; line-height: 1.5;"></textarea>
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 20px;">
          <span id="admin-form-msg" style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-dim);"></span>
          <button type="submit" id="btn-admin-submit" class="btn-auth-submit" style="margin-top: 0; padding: 12px 24px;">Ingest into Neon Postgres</button>
        </div>
      </form>
    </section>

    <!-- Library Inventory Table -->
    <section class="admin-section">
      <div class="admin-section-header">
        <h2 class="admin-section-title">Curated Library Inventory</h2>
        <p class="admin-section-desc">Active book volumes ingested and grounded in the database.</p>
      </div>

      <div style="overflow-x: auto;">
        <table class="library-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Author</th>
              <th>Description</th>
              <th>Chunks</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="library-table-body">
            <?php if (empty($books)): ?>
              <tr>
                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                  No books ingested yet. Drop a PDF above to add the first volume.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($books as $b): ?>
                <tr id="book-row-<?= $b['id'] ?>">
                  <td style="font-family: var(--font-mono);"><?= $b['id'] ?></td>
                  <td style="font-weight: 500; font-family: var(--font-serif); font-size: 0.95rem;"><?= htmlspecialchars($b['title']) ?></td>
                  <td style="color: var(--text-muted);"><?= htmlspecialchars($b['author'] ?? 'Unknown') ?></td>
                  <td style="color: var(--text-dim); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($b['description'] ?? '') ?></td>
                  <td><span class="badge-chunks"><?= $b['chunks_count'] ?? 0 ?> chunks</span></td>
                  <td>
                    <button class="btn-delete-book" onclick="deleteBook(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['title'])) ?>')">Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>
    if (window.pdfjsLib) {
      window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    const dropZone = document.getElementById('admin-drop-zone');
    const fileInput = document.getElementById('admin-file-input');
    const pdfStatus = document.getElementById('admin-pdf-status');
    const titleInput = document.getElementById('admin-title');
    const authorInput = document.getElementById('admin-author');
    const descInput = document.getElementById('admin-desc');
    const contentInput = document.getElementById('admin-content');
    const btnSubmit = document.getElementById('btn-admin-submit');
    const formMsg = document.getElementById('admin-form-msg');

    dropZone?.addEventListener('click', () => fileInput.click());

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
      if (files && files.length > 0) processFile(files[0]);
    });

    fileInput?.addEventListener('change', (e) => {
      const files = e.target.files;
      if (files && files.length > 0) processFile(files[0]);
    });

    async function processFile(file) {
      if (!file) return;
      const cleanName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
      if (!titleInput.value) {
        titleInput.value = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
      }

      if (file.name.toLowerCase().endsWith('.pdf')) {
        pdfStatus.style.display = 'block';
        pdfStatus.textContent = 'Reading PDF binary...';
        try {
          const buffer = await file.arrayBuffer();
          const pdf = await window.pdfjsLib.getDocument({ data: buffer }).promise;
          let fullText = '';
          for (let p = 1; p <= pdf.numPages; p++) {
            pdfStatus.textContent = `Extracting PDF: Page ${p} of ${pdf.numPages}...`;
            const page = await pdf.getPage(p);
            const textContent = await page.getTextContent();
            fullText += `\n\n[Page ${p}]\n` + textContent.items.map(i => i.str).join(' ');
          }
          contentInput.value = fullText.trim();
          const words = fullText.split(/\s+/).filter(Boolean).length;
          pdfStatus.textContent = `Extracted ${pdf.numPages} pages from ${file.name} (~${words} words parsed)`;
        } catch (err) {
          pdfStatus.textContent = 'Error parsing PDF: ' + err.message;
        }
      } else {
        const reader = new FileReader();
        reader.onload = () => {
          contentInput.value = reader.result;
          pdfStatus.style.display = 'block';
          pdfStatus.textContent = `Loaded text file: ${file.name} (${Math.round(file.size / 1024)} KB)`;
        };
        reader.readAsText(file);
      }
    }

    async function submitAdminIngest() {
      const title = titleInput.value.trim();
      const author = authorInput.value.trim() || 'Unknown';
      const description = descInput.value.trim() || 'Curated library volume.';
      const content = contentInput.value.trim();

      if (!title || !content) return;

      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Ingesting & Chunking...';
      formMsg.textContent = 'Splitting into semantic paragraphs...';

      try {
        const res = await fetch('/api.php?action=ingest_book', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ title, author, description, content })
        });
        const data = await res.json();
        if (data.success) {
          formMsg.textContent = `Ingested "${title}" (${data.chunks_count} chunks) successfully!`;
          titleInput.value = '';
          authorInput.value = '';
          descInput.value = '';
          contentInput.value = '';
          pdfStatus.style.display = 'none';
          setTimeout(() => window.location.reload(), 1200);
        } else {
          alert(data.error || 'Ingestion failed.');
          formMsg.textContent = 'Error occurred.';
        }
      } catch (err) {
        alert('Network error while ingesting book.');
        formMsg.textContent = 'Network failure.';
      } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Ingest into Neon Postgres';
      }
    }

    async function deleteBook(id, title) {
      if (!confirm(`Are you sure you want to delete "${title}" (ID: ${id}) from the library?`)) return;
      try {
        const res = await fetch('/api.php?action=delete_book', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ book_id: id })
        });
        const data = await res.json();
        if (data.success) {
          const row = document.getElementById('book-row-' + id);
          if (row) row.remove();
          window.location.reload();
        } else {
          alert(data.error || 'Failed to delete book.');
        }
      } catch (err) {
        alert('Network error while deleting book.');
      }
    }
  </script>
</body>
</html>
