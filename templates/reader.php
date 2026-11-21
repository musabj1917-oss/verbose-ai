<?php
if (empty($_COOKIE['verbose_session'])) {
    header('Location: /login');
    exit;
}
$currentUser = htmlspecialchars($_COOKIE['verbose_session'] ?? 'Reader');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verbose AI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>

  <!-- Collapsible Sidebar -->
  <aside id="app-sidebar" class="sidebar">
    <div class="sidebar-top">
      <button id="btn-new-chat" class="btn-new-chat">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        <span>New Chat</span>
      </button>
    </div>

    <div id="chat-history-list" class="chat-history-list"></div>

    <div class="sidebar-bottom">
      <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <span style="font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 130px;"><?= $currentUser ?></span>
        <a href="/logout" id="btn-logout" style="color: var(--text-dim); font-size: 0.72rem; text-decoration: none; transition: color 0.15s ease;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-dim)'">Sign Out</a>
      </div>
    </div>
  </aside>

  <!-- Full-Screen Main Stage -->
  <main class="main-stage">
    
    <!-- Minimal Top Bar -->
    <header class="top-bar">
      <div class="top-bar-left">
        <button id="btn-toggle-sidebar" class="btn-icon" title="Toggle Sidebar">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <span id="current-chat-title" class="current-chat-title">New Conversation</span>
      </div>

      <div class="top-bar-right">
        <button id="btn-open-settings" class="btn-settings-nav" title="Audio & Settings">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
          <span>Settings</span>
        </button>
      </div>
    </header>

    <!-- Scrollable Chat Stream -->
    <div id="chat-canvas" class="chat-canvas">
      <div id="chat-inner" class="chat-inner">
        <div id="empty-greeting" class="empty-greeting-container">
          <h1 id="greeting-heading" class="greeting-time-title">Good morning</h1>
          <p class="greeting-subtitle">Zero-hallucination literary intelligence. Every answer is strictly grounded in verified book text.</p>
          <div class="book-attach-hint"><span>Click the <strong>+</strong> button below to attach a book</span></div>
        </div>
        <div id="messages-container" style="display: flex; flex-direction: column; gap: 28px; width: 100%;"></div>
      </div>
    </div>

    <!-- Floating Input Dock -->
    <div class="bottom-dock">
      <div class="input-capsule">
        <div id="book-popover" class="book-popover">
          <div class="popover-header">Select Book for Context</div>
          <div id="popover-books-list" style="display: flex; flex-direction: column; gap: 4px;"></div>
          <div style="border-top: 1px solid var(--border-subtle); padding-top: 6px; margin-top: 4px;">
            <button id="btn-popover-add-book" type="button" style="width: 100%; background: transparent; border: 1px dashed var(--border-medium); color: var(--text-muted); padding: 6px; border-radius: var(--radius-sm); cursor: pointer; font-family: var(--font-mono); font-size: 0.7rem; transition: all 0.15s ease;" onmouseover="this.style.borderColor='#fff';this.style.color='#fff'" onmouseout="this.style.borderColor='var(--border-medium)';this.style.color='var(--text-muted)'">+ Add / Ingest Book</button>
          </div>
        </div>

        <div id="attached-book-pill" class="attached-book-pill" style="display: none;">
          <span id="attached-book-label">Book Context</span>
          <button id="btn-remove-book" class="attached-book-remove" title="Detach book">&times;</button>
        </div>

        <div class="input-main-row">
          <button id="btn-book-plus" class="btn-book-plus" title="Attach book (+)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
          </button>
          <textarea id="prompt-input" class="input-textarea" rows="1" placeholder="Reply or ask a question based on the book..."></textarea>
          <button id="btn-send-prompt" class="btn-action-send" title="Send query" disabled>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="19" x2="12" y2="5"></line>
              <polyline points="5 12 12 5 19 12"></polyline>
            </svg>
          </button>
        </div>
      </div>
    </div>
  </main>

  <!-- Settings Modal -->
  <div id="settings-modal" class="modal-backdrop">
    <div class="modal-dialog">
      <div class="modal-dialog-header">
        <h2 class="modal-title">Settings</h2>
        <button id="btn-close-settings" class="modal-close-btn">&times;</button>
      </div>
      <div class="setting-row">
        <div class="setting-label-row">
          <span>Sound Effects</span>
          <label class="toggle-switch">
            <input type="checkbox" id="setting-sfx-toggle" checked>
            <span class="toggle-slider"></span>
          </label>
        </div>
        <p class="setting-desc">Retro terminal mechanical keyboard ticks, action blips, and arrival chimes.</p>
      </div>
      <div class="setting-row">
        <div class="setting-label-row">
          <span>Audio Volume</span>
          <span id="setting-volume-percent" style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted);">60%</span>
        </div>
        <input type="range" id="setting-volume-slider" class="range-slider" min="0" max="100" value="60">
      </div>
      <div class="setting-row">
        <div class="setting-label-row">
          <span>Book Library</span>
          <button type="button" id="btn-settings-add-book" style="background: #ffffff; color: #000000; border: none; padding: 4px 10px; border-radius: var(--radius-sm); font-size: 0.72rem; font-weight: 600; cursor: pointer;">+ Ingest Book</button>
        </div>
        <p class="setting-desc">Ingest book volumes with semantic paragraph chunking into Neon Postgres.</p>
      </div>
      <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-subtle); padding-top: 12px;">
        <button id="btn-test-sound" class="btn-test-sound">Test Sound</button>
        <span style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--text-dim);">Audio Web API</span>
      </div>
    </div>
  </div>

  <!-- Ingest Book Modal -->
  <div id="ingest-modal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 520px;">
      <div class="modal-dialog-header">
        <h2 class="modal-title">Ingest Book into Library</h2>
        <button id="btn-close-ingest" class="modal-close-btn">&times;</button>
      </div>
      <form id="ingest-form" onsubmit="event.preventDefault(); window.handleIngestBook && window.handleIngestBook();">
        <div class="setting-row" style="padding-bottom: 8px;">
          <label style="font-family: var(--font-mono); font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Book Title</label>
          <input type="text" id="ingest-title" placeholder="e.g. Meditations" required style="width: 100%; margin-top: 4px; background: var(--bg-page); border: 1px solid var(--border-medium); color: var(--text-main); padding: 8px 10px; border-radius: var(--radius-sm); outline: none;">
        </div>
        <div class="setting-row" style="padding-bottom: 8px;">
          <label style="font-family: var(--font-mono); font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Author</label>
          <input type="text" id="ingest-author" placeholder="e.g. Marcus Aurelius" style="width: 100%; margin-top: 4px; background: var(--bg-page); border: 1px solid var(--border-medium); color: var(--text-main); padding: 8px 10px; border-radius: var(--radius-sm); outline: none;">
        </div>
        <div class="setting-row" style="padding-bottom: 8px;">
          <label style="font-family: var(--font-mono); font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Book Content / Excerpts</label>
          <textarea id="ingest-content" rows="6" placeholder="Paste the book chapters, sections, or text passages here..." required style="width: 100%; margin-top: 4px; background: var(--bg-page); border: 1px solid var(--border-medium); color: var(--text-main); padding: 8px 10px; border-radius: var(--radius-sm); resize: vertical; font-family: var(--font-mono); font-size: 0.8rem; outline: none;"></textarea>
          <p style="font-size: 0.68rem; color: var(--text-dim); margin-top: 4px;">Text will automatically be semantically chunked and indexed into Neon Postgres.</p>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px;">
          <button type="button" id="btn-cancel-ingest" style="background: transparent; border: 1px solid var(--border-medium); color: var(--text-muted); padding: 8px 14px; border-radius: var(--radius-sm); cursor: pointer; font-size: 0.8rem;">Cancel</button>
          <button type="submit" id="btn-submit-ingest" style="background: #ffffff; border: none; color: #000000; padding: 8px 16px; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; font-size: 0.8rem;">Ingest Book</button>
        </div>
      </form>
    </div>
  </div>

  <script src="/js/thinking-orbs.js"></script>
  <script src="/js/sfx.js"></script>
  <script src="/js/app.js"></script>
</body>
</html>
