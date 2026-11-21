<?php
if (empty($_COOKIE['verbose_session'])) {
    header('Location: /login');
    exit;
}
$currentUser = htmlspecialchars($_COOKIE['verbose_session'] ?? 'Reader');
$displayName = explode('@', $currentUser)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="Verbose AI — Zero-hallucination literary intelligence grounded in verified book text.">
  <title>Verbose AI</title>
  <link rel="icon" type="image/svg+xml" href="/img/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>

  <!-- Collapsible Sidebar -->
  <aside id="app-sidebar" class="sidebar">
    <div class="sidebar-brand">
      <img src="/img/logo.svg" class="sidebar-logo" alt="Verbose AI">
      <span class="sidebar-brand-name">Verbose AI</span>
    </div>

    <div class="sidebar-top">
      <button id="btn-new-chat" class="btn-new-chat">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        <span>New Conversation</span>
      </button>
    </div>

    <div id="chat-history-list" class="chat-history-list"></div>

    <div class="sidebar-bottom">
      <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
          <div class="user-avatar"><?= strtoupper(substr($displayName, 0, 1)) ?></div>
          <span style="font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= $displayName ?></span>
        </div>
        <a href="/logout" style="color: var(--text-dim); font-size: 0.72rem; text-decoration: none; transition: color 0.15s ease; flex-shrink: 0;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-dim)'">Sign Out</a>
      </div>
    </div>
  </aside>

  <!-- Full-Screen Main Stage -->
  <main class="main-stage">
    <canvas id="starfield-canvas" class="starfield-canvas"></canvas>

    <!-- Top Bar -->
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
        <button id="btn-open-settings" class="btn-settings-nav" title="Settings">
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
          <div class="greeting-logo-wrap">
            <img src="/img/logo.svg" class="greeting-logo" alt="Verbose AI">
          </div>
          <h1 id="greeting-heading" class="greeting-time-title">Good morning</h1>
          <p class="greeting-subtitle">Zero-hallucination literary intelligence.<br>Every answer grounded in verified book text.</p>
          <div class="book-attach-hint">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span>Tap <strong>+</strong> to attach a book and begin</span>
          </div>
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
        </div>

        <div id="attached-book-pill" class="attached-book-pill" style="display: none;">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
          </svg>
          <span id="attached-book-label">Book Context</span>
          <button id="btn-remove-book" class="attached-book-remove" title="Remove book">&times;</button>
        </div>

        <div class="input-main-row">
          <button id="btn-book-plus" class="btn-book-plus" title="Select book">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
          </button>
          <textarea id="prompt-input" class="input-textarea" rows="1" placeholder="Ask a question about the book..."></textarea>
          <button id="btn-send-prompt" class="btn-action-send" title="Send" disabled>
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
        <div style="display: flex; align-items: center; gap: 10px;">
          <img src="/img/logo.svg" style="width: 20px; height: 20px;" alt="">
          <h2 class="modal-title">Preferences</h2>
        </div>
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
        <p class="setting-desc">Retro terminal keyboard ticks, blips, and reply chimes.</p>
      </div>
      <div class="setting-row">
        <div class="setting-label-row">
          <span>Audio Volume</span>
          <span id="setting-volume-percent" style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted);">60%</span>
        </div>
        <input type="range" id="setting-volume-slider" class="range-slider" min="0" max="100" value="60">
      </div>
      <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-subtle); padding-top: 12px;">
        <button id="btn-test-sound" class="btn-test-sound">Test Sound</button>
        <span style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--text-dim);">Web Audio API</span>
      </div>
      <div style="border-top: 1px solid var(--border-subtle); padding-top: 12px;">
        <p style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--text-dim); line-height: 1.6;">
          Verbose AI v1.0 &bull; Neon Lakebase Postgres &bull; Gemini AI<br>
          Answers grounded exclusively in verified book passages.
        </p>
      </div>
    </div>
  </div>

  <script src="/js/thinking-orbs.js"></script>
  <script src="/js/sfx.js"></script>
  <script src="/js/starfield.js"></script>
  <script src="/js/app.js"></script>
</body>
</html>
