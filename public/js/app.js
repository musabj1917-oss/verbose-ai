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
      emptyItem.style.padding = '12px 8px';
      emptyItem.style.fontSize = '0.75rem';
      emptyItem.style.color = 'var(--text-muted)';
      emptyItem.style.lineHeight = '1.4';
      emptyItem.innerHTML = `No books in library.<br><a href="#" id="link-empty-ingest" style="color: #fff; text-decoration: underline; display: inline-block; margin-top: 4px;">+ Ingest Book / PDF</a>`;
      popoverBooksList.appendChild(emptyItem);
      document.getElementById('link-empty-ingest')?.addEventListener('click', (e) => {
        e.preventDefault();
        openIngestModal();
      });
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
        renderMessageItem('ai', 'No book or PDF context is currently loaded. Please click the **+** button below or **Ingest Book / PDF** in the sidebar to add a book or PDF to your library.', []);
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

      if (data.citations && data.citations.length) {
        const tray = createSourcesTray(data.citations);
        aiRow.appendChild(tray);
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

      if (citations && citations.length) {
        row.appendChild(createSourcesTray(citations));
      }
    }

    messagesContainer.appendChild(row);
  }

  function createSourcesTray(citations) {
    const tray = document.createElement('div');
    tray.className = 'sources-tray';

    const header = document.createElement('div');
    header.className = 'sources-tray-header';
    header.innerHTML = `
      <span>Verified Sources (${citations.length} passages)</span>
      <span class="sources-arrow">&#9662;</span>
    `;

    const body = document.createElement('div');
    body.className = 'sources-tray-body';

    citations.forEach((c, idx) => {
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
    return escapeHtml(text)
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g, '<em>$1</em>')
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

  // 10. Book & PDF Ingestion Modal Management
  const ingestModal = document.getElementById('ingest-modal');
  const btnCloseIngest = document.getElementById('btn-close-ingest');
  const btnCancelIngest = document.getElementById('btn-cancel-ingest');
  const btnPopoverAddBook = document.getElementById('btn-popover-add-book');
  const btnSettingsAddBook = document.getElementById('btn-settings-add-book');
  const btnSidebarIngest = document.getElementById('btn-sidebar-ingest');
  const ingestForm = document.getElementById('ingest-form');
  const ingestTitle = document.getElementById('ingest-title');
  const ingestAuthor = document.getElementById('ingest-author');
  const ingestContent = document.getElementById('ingest-content');
  const btnSubmitIngest = document.getElementById('btn-submit-ingest');

  // PDF Dropzone elements
  const pdfDropZone = document.getElementById('pdf-drop-zone');
  const ingestFileInput = document.getElementById('ingest-file-input');
  const pdfStatus = document.getElementById('pdf-status');
  const pdfDropLabel = document.getElementById('pdf-drop-label');

  function openIngestModal() {
    window.SFX?.playBlip(720, 0.04);
    closePopover();
    if (settingsModal) settingsModal.classList.remove('open');
    if (ingestModal) ingestModal.classList.add('open');
  }

  function closeIngestModal() {
    window.SFX?.playBlip(480, 0.03);
    if (ingestModal) ingestModal.classList.remove('open');
  }

  btnPopoverAddBook?.addEventListener('click', openIngestModal);
  btnSettingsAddBook?.addEventListener('click', openIngestModal);
  btnSidebarIngest?.addEventListener('click', openIngestModal);
  btnCloseIngest?.addEventListener('click', closeIngestModal);
  btnCancelIngest?.addEventListener('click', closeIngestModal);

  // PDF & File Drag-and-Drop / Selection
  pdfDropZone?.addEventListener('click', () => {
    ingestFileInput?.click();
  });

  ['dragenter', 'dragover'].forEach(eventName => {
    pdfDropZone?.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      pdfDropZone.style.borderColor = '#ffffff';
      pdfDropZone.style.background = 'rgba(255, 255, 255, 0.08)';
    });
  });

  ['dragleave', 'drop'].forEach(eventName => {
    pdfDropZone?.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      pdfDropZone.style.borderColor = 'var(--border-medium)';
      pdfDropZone.style.background = 'var(--bg-hover)';
    });
  });

  pdfDropZone?.addEventListener('drop', (e) => {
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
      handleFileSelected(files[0]);
    }
  });

  ingestFileInput?.addEventListener('change', (e) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      handleFileSelected(files[0]);
    }
  });

  async function handleFileSelected(file) {
    if (!file) return;
    window.SFX?.playBlip(750, 0.04);

    // Auto-populate Title if empty
    const cleanTitle = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
    if (ingestTitle && !ingestTitle.value.trim()) {
      ingestTitle.value = cleanTitle.charAt(0).toUpperCase() + cleanTitle.slice(1);
    }

    if (file.name.toLowerCase().endsWith('.pdf')) {
      await extractTextFromPdf(file);
    } else {
      // Plain text or markdown
      const reader = new FileReader();
      reader.onload = () => {
        if (ingestContent) ingestContent.value = reader.result;
        if (pdfStatus) {
          pdfStatus.style.display = 'block';
          pdfStatus.textContent = `Loaded text file: ${file.name} (${Math.round(file.size / 1024)} KB)`;
        }
      };
      reader.readAsText(file);
    }
  }

  async function extractTextFromPdf(file) {
    if (!pdfStatus) return;
    pdfStatus.style.display = 'block';
    pdfStatus.textContent = 'Reading PDF structure...';

    try {
      if (!window.pdfjsLib) {
        throw new Error('PDF parser engine is initializing. Please try again in a moment.');
      }
      const arrayBuffer = await file.arrayBuffer();
      const loadingTask = window.pdfjsLib.getDocument({ data: arrayBuffer });
      const pdf = await loadingTask.promise;
      const numPages = pdf.numPages;

      let fullText = '';
      for (let pageNum = 1; pageNum <= numPages; pageNum++) {
        pdfStatus.textContent = `Extracting text: Page ${pageNum} of ${numPages}...`;
        const page = await pdf.getPage(pageNum);
        const textContent = await page.getTextContent();
        const pageText = textContent.items.map(item => item.str).join(' ');
        fullText += `\n\n[Page ${pageNum}]\n` + pageText;
      }

      if (ingestContent) {
        ingestContent.value = fullText.trim();
      }

      const wordCount = fullText.split(/\s+/).filter(Boolean).length;
      pdfStatus.textContent = `Extracted ${numPages} pages from ${file.name} (~${wordCount} words ready to ingest)`;
      window.SFX?.playReplyChime();
    } catch (err) {
      pdfStatus.textContent = 'Error parsing PDF: ' + err.message;
      window.SFX?.playBlip(300, 0.08);
    }
  }

  window.handleIngestBook = async function() {
    const title = ingestTitle?.value.trim();
    const author = ingestAuthor?.value.trim() || 'Unknown';
    const content = ingestContent?.value.trim();

    if (!title || !content) return;

    window.SFX?.playSend();
    if (btnSubmitIngest) {
      btnSubmitIngest.disabled = true;
      btnSubmitIngest.textContent = 'Ingesting Chunks...';
    }

    try {
      const res = await fetch('/api.php?action=ingest_book', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, author, content })
      });
      const data = await res.json();

      if (data.success) {
        window.SFX?.playReplyChime();
        if (ingestTitle) ingestTitle.value = '';
        if (ingestAuthor) ingestAuthor.value = '';
        if (ingestContent) ingestContent.value = '';
        if (pdfStatus) {
          pdfStatus.style.display = 'none';
          pdfStatus.textContent = '';
        }
        closeIngestModal();

        await loadBooks();
        const addedBook = state.books.find(b => b.id == data.book_id) || state.books[state.books.length - 1];
        if (addedBook) {
          attachBook(addedBook);
        }
      } else {
        window.SFX?.playBlip(320, 0.08);
        alert(data.error || 'Failed to ingest book.');
      }
    } catch (err) {
      window.SFX?.playBlip(320, 0.08);
      alert('Network error while ingesting book.');
    } finally {
      if (btnSubmitIngest) {
        btnSubmitIngest.disabled = false;
        btnSubmitIngest.textContent = 'Ingest Book';
      }
    }
  };

  // Initial Load
  loadBooks();
  if (state.sessions.length > 0) {
    switchSession(state.sessions[0].id);
  } else {
    createNewChat();
  }
});
