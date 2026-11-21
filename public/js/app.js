/**
 * Verbose AI - Application Logic (Clean Monochromatic Edition)
 * Zero emojis, dedicated settings modal with volume slider,
 * real Neon Postgres books only (no placeholder books),
 * and inline Thinking Orb contemplation.
 */

document.addEventListener('DOMContentLoaded', () => {
  // Navigation & Shell
  const sidebar = document.getElementById('app-sidebar');
  const btnToggleSidebar = document.getElementById('btn-toggle-sidebar');
  const btnNewChat = document.getElementById('btn-new-chat');
  const chatHistoryList = document.getElementById('chat-history-list');
  const currentChatTitle = document.getElementById('current-chat-title');
  const emptyGreeting = document.getElementById('empty-greeting');
  const greetingHeading = document.getElementById('greeting-heading');
  const messagesContainer = document.getElementById('messages-container');
  const chatCanvas = document.getElementById('chat-canvas');

  // Input & Book Attachment
  const promptInput = document.getElementById('prompt-input');
  const btnSend = document.getElementById('btn-send-prompt');
  const btnBookPlus = document.getElementById('btn-book-plus');
  const bookPopover = document.getElementById('book-popover');
  const popoverBooksList = document.getElementById('popover-books-list');
  const attachedBookPill = document.getElementById('attached-book-pill');
  const attachedBookLabel = document.getElementById('attached-book-label');
  const btnRemoveBook = document.getElementById('btn-remove-book');

  // Dedicated Settings Modal
  const settingsModal = document.getElementById('settings-modal');
  const btnOpenSettings = document.getElementById('btn-open-settings');
  const btnCloseSettings = document.getElementById('btn-close-settings');
  const settingSfxToggle = document.getElementById('setting-sfx-toggle');
  const settingVolumeSlider = document.getElementById('setting-volume-slider');
  const settingVolumePercent = document.getElementById('setting-volume-percent');
  const btnTestSound = document.getElementById('btn-test-sound');

  // State
  const state = {
    books: [],
    selectedBook: null,
    sessions: JSON.parse(localStorage.getItem('verbose_sessions') || '[]'),
    activeSessionId: null,
    isGenerating: false
  };

  // 1. Dynamic Greeting
  function updateGreeting() {
    if (!greetingHeading) return;
    const hour = new Date().getHours();
    let phrase = 'Good evening';
    if (hour >= 5 && hour < 12) {
      phrase = 'Good morning';
    } else if (hour >= 12 && hour < 18) {
      phrase = 'Good afternoon';
    }
    greetingHeading.textContent = phrase;
  }
  updateGreeting();

  // 2. Dedicated Settings Management
  function initSettings() {
    if (window.SFX) {
      const vol = window.SFX.getVolume();
      const pct = Math.round(vol * 100);
      if (settingVolumeSlider) settingVolumeSlider.value = pct;
      if (settingVolumePercent) settingVolumePercent.textContent = `${pct}%`;
      if (settingSfxToggle) settingSfxToggle.checked = window.SFX.enabled;
    }

    btnOpenSettings?.addEventListener('click', () => {
      window.SFX?.playBlip(720, 0.03);
      settingsModal?.classList.add('open');
    });

    btnCloseSettings?.addEventListener('click', () => {
      window.SFX?.playBlip(480, 0.03);
      settingsModal?.classList.remove('open');
    });

    settingsModal?.addEventListener('click', (e) => {
      if (e.target === settingsModal) {
        settingsModal.classList.remove('open');
      }
    });

    settingSfxToggle?.addEventListener('change', () => {
      window.SFX?.setEnabled(settingSfxToggle.checked);
    });

    settingVolumeSlider?.addEventListener('input', () => {
      const val = parseFloat(settingVolumeSlider.value) / 100;
      window.SFX?.setVolume(val);
      if (settingVolumePercent) {
        settingVolumePercent.textContent = `${Math.round(val * 100)}%`;
      }
    });

    btnTestSound?.addEventListener('click', () => {
      window.SFX?.playBlip(880, 0.06);
    });
  }
  initSettings();

  // 3. Mechanical Key-tick SFX on typing & Send Button State
  function updateSendButtonState() {
    if (!promptInput || !btnSend) return;
    const hasText = promptInput.value.trim().length > 0;
    btnSend.disabled = !hasText || state.isGenerating;
  }

  ['input', 'keyup', 'change', 'paste', 'compositionend'].forEach(evt => {
    promptInput?.addEventListener(evt, () => {
      if (evt === 'input') window.SFX?.playKeyTick();
      updateSendButtonState();
      promptInput.style.height = 'auto';
      promptInput.style.height = Math.min(promptInput.scrollHeight, 140) + 'px';
    });
  });

  promptInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  });

  // Explicit click and touch listeners for send button
  btnSend?.addEventListener('click', (e) => {
    e.preventDefault();
    handleSend();
  });
  btnSend?.addEventListener('touchend', (e) => {
    e.preventDefault();
    handleSend();
  });

  // 4. Sidebar Toggle
  btnToggleSidebar?.addEventListener('click', () => {
    window.SFX?.playBlip(700, 0.03);
    sidebar.classList.toggle('closed');
  });

  // 5. Load Real Books from Neon Postgres (No Mock/Placeholder Books)
  async function loadBooks() {
    try {
      const res = await fetch('/api.php?action=get_books');
      const data = await res.json();
      state.books = (data.success && Array.isArray(data.books)) ? data.books : [];
    } catch (e) {
      state.books = [];
    }
    renderPopoverBooks();
    if (state.books.length > 0 && !state.selectedBook) {
      attachBook(state.books[0]);
    }
  }

  // 6. '+' Book Popover Management
  function renderPopoverBooks() {
    if (!popoverBooksList) return;
    popoverBooksList.innerHTML = '';

    if (state.books.length === 0) {
      const emptyItem = document.createElement('div');
      emptyItem.style.cssText = 'padding: 14px 10px; font-size: 0.75rem; color: var(--text-muted); line-height: 1.6; font-family: var(--font-mono);';
      emptyItem.textContent = 'No books in library. Contact your administrator to add books.';
      popoverBooksList.appendChild(emptyItem);
      return;
    }

    state.books.forEach((b) => {
      const item = document.createElement('div');
      item.className = 'popover-book-option';
      item.innerHTML = `
        <div class="popover-book-title">${escapeHtml(b.title)}</div>
        <div class="popover-book-meta">by ${escapeHtml(b.author)} &bull; ${b.chunks_count || 0} chunks</div>
      `;
      item.addEventListener('click', () => {
        attachBook(b);
        closePopover();
      });
      popoverBooksList.appendChild(item);
    });
  }

  function attachBook(book) {
    state.selectedBook = book;
    attachedBookLabel.textContent = `Book: ${book.title}`;
    attachedBookPill.style.display = 'inline-flex';
    window.SFX?.playBlip(840, 0.05);
  }

  btnRemoveBook?.addEventListener('click', (e) => {
    e.stopPropagation();
    state.selectedBook = null;
    attachedBookPill.style.display = 'none';
    window.SFX?.playBlip(400, 0.04);
  });

  function togglePopover() {
    const isOpen = bookPopover.classList.contains('open');
    if (isOpen) {
      closePopover();
    } else {
      bookPopover.classList.add('open');
      window.SFX?.playBlip(620, 0.03);
    }
  }

  function closePopover() {
    bookPopover.classList.remove('open');
  }

  btnBookPlus?.addEventListener('click', (e) => {
    e.stopPropagation();
    togglePopover();
  });

  document.addEventListener('click', (e) => {
    if (!bookPopover.contains(e.target) && e.target !== btnBookPlus) {
      closePopover();
    }
  });

  // 7. Chat Sessions & History Management
  function createNewChat() {
    window.SFX?.playBlip(900, 0.04);
    const newSession = {
      id: 'session_' + Date.now(),
      title: 'New Conversation',
      messages: [],
      timestamp: Date.now()
    };
    state.sessions.unshift(newSession);
    saveSessions();
    switchSession(newSession.id);
  }

  btnNewChat?.addEventListener('click', createNewChat);

  function switchSession(sessionId) {
    state.activeSessionId = sessionId;
    const session = state.sessions.find(s => s.id === sessionId);
    if (!session) return;

    currentChatTitle.textContent = session.title || 'Conversation';
    renderSessionList();
    renderSessionMessages(session);
  }

  function saveSessions() {
    localStorage.setItem('verbose_sessions', JSON.stringify(state.sessions));
  }

  function renderSessionList() {
    chatHistoryList.innerHTML = '';
    state.sessions.forEach((s) => {
      const item = document.createElement('div');
      item.className = `history-item ${state.activeSessionId === s.id ? 'active' : ''}`;
      item.textContent = s.title || 'Untitled';
      item.addEventListener('click', () => {
        window.SFX?.playBlip(550, 0.03);
        switchSession(s.id);
      });
      chatHistoryList.appendChild(item);
    });
  }

  function renderSessionMessages(session) {
    messagesContainer.innerHTML = '';
    if (!session.messages || session.messages.length === 0) {
      emptyGreeting.style.display = 'flex';
    } else {
      emptyGreeting.style.display = 'none';
      session.messages.forEach(m => {
        renderMessageItem(m.role, m.content, m.citations, m.bookTitle);
      });
      scrollCanvasToBottom();
    }
  }

  // 8. Send Query & Stream Response
  async function handleSend() {
    const text = promptInput.value.trim();
    if (!text || state.isGenerating) return;

    state.isGenerating = true;
    promptInput.value = '';
    promptInput.style.height = 'auto';
    btnSend.disabled = true;

    window.SFX?.playSend();

    let session = state.sessions.find(s => s.id === state.activeSessionId);
    if (!session) {
      session = {
        id: 'session_' + Date.now(),
        title: text.slice(0, 32),
        messages: [],
        timestamp: Date.now()
      };
      state.sessions.unshift(session);
      state.activeSessionId = session.id;
      saveSessions();
      renderSessionList();
    }

    if (session.messages.length === 0) {
      session.title = text.slice(0, 32);
      currentChatTitle.textContent = session.title;
      saveSessions();
      renderSessionList();
    }

    emptyGreeting.style.display = 'none';

    // Auto-attach first book if none selected
    if (!state.selectedBook) {
      if (state.books.length > 0) {
        attachBook(state.books[0]);
      } else {
        state.isGenerating = false;
        renderMessageItem('user', text, null, null);
        aiBubble.textContent = 'No book context is loaded. Tap the book icon below to select a book from the library.';
        updateSendButtonState();
        scrollCanvasToBottom();
        return;
      }
    }

    const bookTitle = state.selectedBook ? state.selectedBook.title : null;

    // Render User Message
    renderMessageItem('user', text, null, bookTitle);
    session.messages.push({ role: 'user', content: text, bookTitle });
    saveSessions();
    scrollCanvasToBottom();

    // Render Inline Thinking Orb Contemplation Row
    const aiRow = document.createElement('div');
    aiRow.className = 'msg-row ai';

    const contemplationBadge = document.createElement('div');
    contemplationBadge.className = 'contemplation-badge';

    const orbCanvas = document.createElement('canvas');
    orbCanvas.width = 22;
    orbCanvas.height = 22;
    orbCanvas.className = 'contemplation-canvas';

    const contemplationWord = document.createElement('span');
    contemplationWord.className = 'contemplation-word';
    contemplationWord.textContent = state.selectedBook 
      ? `Contemplating ${state.selectedBook.title}...` 
      : 'Contemplating book context...';

    contemplationBadge.appendChild(orbCanvas);
    contemplationBadge.appendChild(contemplationWord);
    aiRow.appendChild(contemplationBadge);

    const aiBubble = document.createElement('div');
    aiBubble.className = 'msg-bubble-ai';
    aiRow.appendChild(aiBubble);

    messagesContainer.appendChild(aiRow);
    scrollCanvasToBottom();

    let inlineOrb = null;
    if (window.ThinkingOrb) {
      inlineOrb = window.ThinkingOrb.create(orbCanvas, {
        state: 'searching',
        size: 22,
        theme: 'dark'
      });
    }

    try {
      const payload = {
        book_id: state.selectedBook ? state.selectedBook.id : null,
        query: text,
        mode: 'ask'
      };

      const res = await fetch('/api.php?action=query', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      inlineOrb?.setState('breathing');
      contemplationBadge.style.display = 'none';

      window.SFX?.playReplyChime();

      const answerText = data.answer || 'No answer generated.';
      aiBubble.innerHTML = formatMarkdown(answerText);

      if (data.citations && data.citations.length > 0) {
        const tray = createSourcesTray(data.citations);
        if (tray) aiRow.appendChild(tray);
      }

      session.messages.push({
        role: 'ai',
        content: answerText,
        citations: data.citations || []
      });
      saveSessions();

    } catch (err) {
      inlineOrb?.destroy();
      contemplationBadge.style.display = 'none';
      aiBubble.textContent = 'Error contacting Verbose AI server. Please check database connection.';
    } finally {
      state.isGenerating = false;
      updateSendButtonState();
      scrollCanvasToBottom();
    }
  }

  // 9. UI Rendering Helpers
  function renderMessageItem(role, content, citations, bookTitle) {
    const row = document.createElement('div');
    row.className = `msg-row ${role}`;

    if (role === 'user') {
      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble-user';
      if (bookTitle) {
        bubble.innerHTML = `<span class="user-book-tag">Book: ${escapeHtml(bookTitle)}</span>${escapeHtml(content)}`;
      } else {
        bubble.textContent = content;
      }
      row.appendChild(bubble);
    } else {
      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble-ai';
      bubble.innerHTML = formatMarkdown(content);
      row.appendChild(bubble);

      if (citations && citations.length > 0) {
        const tray = createSourcesTray(citations);
        if (tray) row.appendChild(tray);
      }
    }

    messagesContainer.appendChild(row);
  }

  function createSourcesTray(citations) {
    const validCitations = Array.isArray(citations) ? citations : [];
    if (validCitations.length === 0) return null;

    const tray = document.createElement('div');
    tray.className = 'sources-tray';

    const header = document.createElement('div');
    header.className = 'sources-tray-header';
    const countLabel = `${validCitations.length} Passage${validCitations.length > 1 ? 's' : ''}`;

    header.innerHTML = `
      <div style="display: flex; align-items: center; gap: 8px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
        </svg>
        <span>Verified Book Sources (${countLabel})</span>
      </div>
      <span class="sources-arrow">&#9662;</span>
    `;

    const body = document.createElement('div');
    body.className = 'sources-tray-body';

    validCitations.forEach((c, idx) => {
      const card = document.createElement('div');
      card.className = 'source-excerpt-card';
      const sim = Math.round((c.similarity || 0.94) * 100);
      card.innerHTML = `
        <div class="source-excerpt-header">
          <span>Passage ${idx + 1} &mdash; ${escapeHtml(c.chapter || 'Excerpt')}</span>
          <span class="source-similarity">${sim}% Match</span>
        </div>
        <div class="source-excerpt-quote">"${escapeHtml(c.text)}"</div>
      `;
      body.appendChild(card);
    });

    header.addEventListener('click', () => {
      window.SFX?.playBlip(750, 0.03);
      tray.classList.toggle('open');
    });

    tray.appendChild(header);
    tray.appendChild(body);
    return tray;
  }

  function scrollCanvasToBottom() {
    chatCanvas.scrollTop = chatCanvas.scrollHeight;
  }

  function formatMarkdown(text) {
    if (!text) return '';
    return escapeHtml(text)
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g, '<em>$1</em>')
      .replace(/\[(.*?)\]\((https?:\/\/[^\s\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="chat-external-link">$1 <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-left:2px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></a>')
      .replace(/\n\n/g, '</p><p>')
      .replace(/\n/g, '<br>');
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

  // Initial Load
  loadBooks();
  if (state.sessions.length > 0) {
    switchSession(state.sessions[0].id);
  } else {
    createNewChat();
  }
});
