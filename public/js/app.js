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
  const popoverBookSearch = document.getElementById('popover-book-search');
  const popoverBookCount = document.getElementById('popover-book-count');
  const attachedBookPill = document.getElementById('attached-book-pill');
  const attachedBookLabel = document.getElementById('attached-book-label');
  const btnRemoveBook = document.getElementById('btn-remove-book');

  // Image Scanning Elements
  const btnScanImage = document.getElementById('btn-scan-image');
  const imageScanInput = document.getElementById('image-scan-input');
  const attachedImagePill = document.getElementById('attached-image-pill');
  const attachedImageThumb = document.getElementById('attached-image-thumb');
  const attachedImageLabel = document.getElementById('attached-image-label');
  const btnRemoveImage = document.getElementById('btn-remove-image');

  // Dedicated Settings Modal
  const settingsModal = document.getElementById('settings-modal');
  const btnOpenSettings = document.getElementById('btn-open-settings');
  const btnCloseSettings = document.getElementById('btn-close-settings');
  const settingSfxToggle = document.getElementById('setting-sfx-toggle');
  const settingVolumeSlider = document.getElementById('setting-volume-slider');
  const settingVolumePercent = document.getElementById('setting-volume-percent');
  const btnTestSound = document.getElementById('btn-test-sound');

  // State
  const isUnlimitedBody = document.body.dataset.isUnlimited === '1';
  const selectedTierBody = document.body.dataset.selectedTier || 'medium';

  const state = {
    books: [],
    selectedBook: null,
    sessions: JSON.parse(localStorage.getItem('verbose_sessions') || '[]'),
    activeSessionId: null,
    isGenerating: false,
    scannedImage: null,
    selectedTier: selectedTierBody,
    isUnlimited: isUnlimitedBody,
    quota: {
      is_unlimited: isUnlimitedBody,
      credits_total: isUnlimitedBody ? 999999 : 1000,
      credits_used: 0,
      credits_remaining: isUnlimitedBody ? 999999 : 1000
    }
  };

  // 1. Studio Greeting & Starter Prompt Cards
  function initStudioGreeting() {
    // If running in native Android app, add class to body and enable native features
    if (window.AndroidBridge && typeof window.AndroidBridge.isNativeApp === 'function' && window.AndroidBridge.isNativeApp()) {
      document.body.classList.add('is-native-android');
    }

    // Attach click listeners to starter prompt cards
    document.querySelectorAll('.starter-prompt-card').forEach(card => {
      card.addEventListener('click', () => {
        const prompt = card.getAttribute('data-prompt');
        if (prompt && promptInput) {
          promptInput.value = prompt;
          autoResizeTextarea();
          updateSendButtonState();
          promptInput.focus();
          window.SFX?.playKeyboardTick();
          if (window.AndroidBridge && window.AndroidBridge.vibrate) {
            window.AndroidBridge.vibrate(15);
          }
        }
      });
    });
  }
  initStudioGreeting();

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

  // 2b. Three-Tier Cluster Selector & Quota Sync
  function initClusters() {
    const clusterButtons = document.querySelectorAll('.cluster-btn');
    clusterButtons.forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const tier = btn.dataset.tier;
        if (!tier || tier === state.selectedTier) return;
        state.selectedTier = tier;
        clusterButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        if (tier === 'pro') window.SFX?.playBlip(880, 0.05);
        else if (tier === 'medium') window.SFX?.playBlip(660, 0.04);
        else window.SFX?.playBlip(520, 0.03);

        if (window.AndroidBridge && window.AndroidBridge.vibrate) {
          window.AndroidBridge.vibrate(18);
        }

        try {
          await fetch('/api.php?action=set_tier', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tier: tier })
          });
        } catch (err) {}
      });
    });
  }
  initClusters();

  async function updateQuotaDisplay() {
    try {
      const res = await fetch('/api.php?action=get_quota');
      const data = await res.json();
      if (data.success) {
        state.isUnlimited = !!data.is_unlimited;
        state.quota = data;
        const badge = document.getElementById('user-quota-badge');
        if (badge) {
          if (data.is_unlimited) {
            badge.className = 'badge-unlimited';
            badge.title = 'Developer Unlimited Account (Zero Usage Depletion)';
            badge.innerHTML = '<span class="pulse-dot"></span><span>&infin; Unlimited</span>';
          } else {
            badge.className = 'quota-pill';
            badge.title = 'Remaining AI Turn Credits';
            const total = data.credits_total || 1000;
            const remaining = data.credits_remaining !== undefined ? data.credits_remaining : (total - (data.credits_used || 0));
            const pct = Math.round((remaining / Math.max(1, total)) * 100);
            badge.innerHTML = `
              <div class="quota-bar-fill">
                <div id="quota-bar-inner" class="quota-bar-inner" style="width: ${pct}%;"></div>
              </div>
              <span id="quota-credits-text">${remaining} / ${total}</span>
            `;
          }
        }
      }
    } catch (e) {}
  }

  // 3. Mechanical Key-tick SFX on typing & Send Button State
  function updateSendButtonState() {
    if (!promptInput || !btnSend) return;
    const hasText = promptInput.value.trim().length > 0;
    const hasImage = !!state.scannedImage;
    btnSend.disabled = (!hasText && !hasImage) || state.isGenerating;
  }

  // Image Scanning Listener & OCR Image Resizing (Strictly photo only, 720p cap for standard users)
  btnScanImage?.addEventListener('click', (e) => {
    e.stopPropagation();
    imageScanInput?.click();
  });

  imageScanInput?.addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;

    // Strict photo check: reject video
    if (file.type && !file.type.startsWith('image/')) {
      alert('Only photo files (JPG, PNG, WEBP) are supported. Video upload is not supported.');
      imageScanInput.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = function(evt) {
      const img = new Image();
      img.onload = function() {
        // Enforce 720p maximum dimension for standard users; full resolution (up to 2048) for unlimited users
        const maxDim = state.isUnlimited ? 2048 : 720;
        let width = img.width;
        let height = img.height;
        if (width > maxDim || height > maxDim) {
          if (width > height) {
            height = Math.round((height * maxDim) / width);
            width = maxDim;
          } else {
            width = Math.round((width * maxDim) / height);
            height = maxDim;
          }
        }
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        const resizedDataUrl = canvas.toDataURL('image/jpeg', 0.88);

        state.scannedImage = resizedDataUrl;
        if (attachedImageThumb) attachedImageThumb.src = resizedDataUrl;
        if (attachedImageLabel) {
          attachedImageLabel.textContent = file.name.length > 18 ? file.name.slice(0, 15) + '...' : file.name;
        }
        const resBadge = document.getElementById('attached-image-res-badge');
        if (resBadge) {
          resBadge.textContent = state.isUnlimited ? 'Full-Res' : '720p Max';
        }
        if (attachedImagePill) attachedImagePill.style.display = 'inline-flex';
        window.SFX?.playBlip(880, 0.05);
        if (window.AndroidBridge && window.AndroidBridge.showToast) {
          window.AndroidBridge.showToast('Book photo attached for analysis');
        }
        updateSendButtonState();
        promptInput?.focus();
      };
      img.src = evt.target.result;
    };
    reader.readAsDataURL(file);
    imageScanInput.value = '';
  });

  btnRemoveImage?.addEventListener('click', (e) => {
    e.stopPropagation();
    state.scannedImage = null;
    if (attachedImagePill) attachedImagePill.style.display = 'none';
    if (attachedImageThumb) attachedImageThumb.src = '';
    window.SFX?.playBlip(400, 0.04);
    updateSendButtonState();
  });

  ['input', 'keyup', 'change', 'paste', 'compositionend'].forEach(evt => {
    promptInput?.addEventListener(evt, () => {
      if (evt === 'input') window.SFX?.playKeyTick();
      updateSendButtonState();
      promptInput.style.height = 'auto';
      promptInput.style.height = Math.min(promptInput.scrollHeight, 140) + 'px';
      window.VerboseMascot?.onTyping(promptInput.value);
    });
  });

  promptInput?.addEventListener('focus', () => {
    window.VerboseMascot?.onFocus();
  });

  promptInput?.addEventListener('blur', () => {
    window.VerboseMascot?.onBlur();
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
    // Do NOT auto-attach the first book. User selects a book only when desired.
  }

  // 6. '+' Book Popover Management (Compact Scrollable Window with Search)
  function renderPopoverBooks(filterText = '') {
    if (!popoverBooksList) return;
    popoverBooksList.innerHTML = '';

    const query = filterText.toLowerCase().trim();
    const filtered = state.books.filter(b => {
      if (!query) return true;
      const title = (b.title || '').toLowerCase();
      const author = (b.author || '').toLowerCase();
      return title.includes(query) || author.includes(query);
    });

    if (popoverBookCount) {
      popoverBookCount.textContent = filtered.length;
    }

    if (state.books.length === 0) {
      const emptyItem = document.createElement('div');
      emptyItem.style.cssText = 'padding: 16px 12px; font-size: 0.74rem; color: var(--text-muted); line-height: 1.6; font-family: var(--font-mono); text-align: center;';
      emptyItem.textContent = 'No books in library. Contact your administrator to add books.';
      popoverBooksList.appendChild(emptyItem);
      return;
    }

    if (filtered.length === 0) {
      const emptyItem = document.createElement('div');
      emptyItem.style.cssText = 'padding: 16px 12px; font-size: 0.74rem; color: var(--text-muted); line-height: 1.6; font-family: var(--font-mono); text-align: center;';
      emptyItem.textContent = 'No matching books found.';
      popoverBooksList.appendChild(emptyItem);
      return;
    }

    filtered.forEach((b) => {
      const item = document.createElement('div');
      item.className = 'popover-book-option';
      if (state.selectedBook && state.selectedBook.id === b.id) {
        item.classList.add('selected');
      }
      item.innerHTML = `
        <div class="popover-book-title">${escapeHtml(b.title)}</div>
        <div class="popover-book-meta">by ${escapeHtml(b.author || 'Unknown')} &bull; ${b.chunks_count || 0} passages</div>
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
    window.VerboseMascot?.onBookSelected(book.title);
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
      if (popoverBookSearch) {
        popoverBookSearch.value = '';
        renderPopoverBooks('');
        setTimeout(() => popoverBookSearch.focus(), 50);
      }
    }
  }

  function closePopover() {
    bookPopover.classList.remove('open');
    if (popoverBookSearch) {
      popoverBookSearch.value = '';
    }
  }

  popoverBookSearch?.addEventListener('input', (e) => {
    renderPopoverBooks(e.target.value);
  });

  btnBookPlus?.addEventListener('click', (e) => {
    e.stopPropagation();
    togglePopover();
  });

  document.addEventListener('click', (e) => {
    if (!bookPopover.contains(e.target) && e.target !== btnBookPlus) {
      closePopover();
    }
    if (e.target.closest('.thought-summary')) {
      window.SFX?.playBlip(720, 0.03);
    }
  });

  // 7. Chat Sessions & History Management (with Chat Deletion)
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

  function deleteSession(sessionId) {
    window.SFX?.playBlip(350, 0.05);
    const idx = state.sessions.findIndex(s => s.id === sessionId);
    if (idx === -1) return;

    const wasActive = state.activeSessionId === sessionId;
    state.sessions.splice(idx, 1);
    saveSessions();

    if (state.sessions.length === 0) {
      createNewChat();
    } else if (wasActive) {
      const nextSession = state.sessions[idx] || state.sessions[idx - 1] || state.sessions[0];
      switchSession(nextSession.id);
    } else {
      renderSessionList();
    }
  }

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

      const titleSpan = document.createElement('span');
      titleSpan.className = 'history-item-title';
      titleSpan.textContent = s.title || 'Untitled';

      const delBtn = document.createElement('button');
      delBtn.className = 'btn-delete-session';
      delBtn.title = 'Delete conversation';
      delBtn.setAttribute('aria-label', 'Delete conversation');
      delBtn.innerHTML = `
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        </svg>
      `;
      delBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteSession(s.id);
      });

      item.appendChild(titleSpan);
      item.appendChild(delBtn);

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
        renderMessageItem(m.role, m.content, m.citations, m.bookTitle, m.image, m.provider);
      });
      scrollCanvasToBottom();
    }
  }

  // 8. Send Query & Stream Response
  async function handleSend() {
    const text = promptInput.value.trim();
    const image = state.scannedImage;
    if ((!text && !image) || state.isGenerating) return;

    state.isGenerating = true;
    promptInput.value = '';
    promptInput.style.height = 'auto';
    btnSend.disabled = true;

    // Reset attached image for subsequent messages
    state.scannedImage = null;
    if (attachedImagePill) attachedImagePill.style.display = 'none';
    if (attachedImageThumb) attachedImageThumb.src = '';

    window.SFX?.playSend();
    if (window.AndroidBridge && window.AndroidBridge.vibrate) {
      window.AndroidBridge.vibrate(25);
    }

    const displayTitle = text || 'Scanned Document';
    let session = state.sessions.find(s => s.id === state.activeSessionId);
    if (!session) {
      session = {
        id: 'session_' + Date.now(),
        title: displayTitle.slice(0, 32),
        messages: [],
        timestamp: Date.now()
      };
      state.sessions.unshift(session);
      state.activeSessionId = session.id;
      saveSessions();
      renderSessionList();
    }

    if (session.messages.length === 0) {
      session.title = displayTitle.slice(0, 32);
      currentChatTitle.textContent = session.title;
      saveSessions();
      renderSessionList();
    }

    emptyGreeting.style.display = 'none';

    const bookTitle = state.selectedBook ? state.selectedBook.title : null;
    const userMessageText = text || (image ? 'Analyze this scanned page.' : '');

    // Render User Message with image preview if scanned
    renderMessageItem('user', userMessageText, null, bookTitle, image);
    session.messages.push({ role: 'user', content: userMessageText, bookTitle, image });
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
    contemplationWord.textContent = image 
      ? 'Scanning image & reasoning...' 
      : (state.selectedBook ? `Contemplating ${state.selectedBook.title}...` : 'Contemplating inquiry...');

    contemplationBadge.appendChild(orbCanvas);
    contemplationBadge.appendChild(contemplationWord);
    aiRow.appendChild(contemplationBadge);

    const aiBubble = document.createElement('div');
    aiBubble.className = 'msg-bubble-ai';
    aiRow.appendChild(aiBubble);

    messagesContainer.appendChild(aiRow);
    scrollCanvasToBottom();

    // Trigger Mascot: throws answer parcel and leaps up to the AI row
    window.VerboseMascot?.onSend(aiRow, aiBubble, {
      isQuestion: /[\?？]|^(what|how|why|who|when|where|which|define|explain|give|is|are|can|does|did|ስለ|ምን|ምንድን|እንዴት)/i.test(userMessageText),
      bookTitle: bookTitle
    });

    let inlineOrb = null;
    if (window.ThinkingOrb) {
      inlineOrb = window.ThinkingOrb.create(orbCanvas, {
        state: 'searching',
        size: 22,
        theme: 'dark'
      });
    }

    window.app3DBackground?.setState('searching');

    try {
      // Gather previous conversation context (up to last 10 turns, excluding current prompt)
      const pastMessages = session.messages.slice(0, -1);
      const history = pastMessages.slice(-10).map(m => {
        let content = m.content || '';
        content = content.replace(/<think>[\s\S]*?<\/think>/gi, '').trim();
        return {
          role: m.role === 'ai' ? 'assistant' : m.role,
          content: content
        };
      }).filter(m => m.content && (m.role === 'user' || m.role === 'assistant'));

      const payload = {
        book_id: state.selectedBook ? state.selectedBook.id : null,
        query: userMessageText,
        image: image || null,
        mode: 'ask',
        history: history,
        tier: state.selectedTier || 'medium'
      };

      const res = await fetch('/api.php?action=query', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();

      if (data.quota_exceeded) {
        inlineOrb?.destroy();
        contemplationBadge.style.display = 'none';
        aiBubble.innerHTML = `
          <div style="padding: 14px 16px; border: 1px solid rgba(255, 120, 120, 0.35); border-radius: 10px; background: rgba(255, 60, 60, 0.08); color: #ff9b9b; font-size: 0.82rem; line-height: 1.6;">
            <div style="font-weight: 600; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
              <span>Cluster Usage Limit Reached</span>
            </div>
            <div>${escapeHtml(data.error || 'Your usage limit for this model cluster has been depleted.')}</div>
            <div style="margin-top: 8px; font-size: 0.74rem; color: #ccc;">
              Tip: Switch to <strong>Verbose Light</strong> (1 pt) in the top bar to continue reading with minimal credit consumption.
            </div>
          </div>
        `;
        window.SFX?.playBlip(320, 0.08);
        updateQuotaDisplay();
        return;
      }

      const fullAnswer = data.answer || 'No answer generated.';
      const finalProvider = data.provider || 'Verbose AI';
      const finalCitations = data.citations || [];

      // Update quota status in UI if returned
      if (data.quota) {
        state.quota = data.quota;
        updateQuotaDisplay();
      }

      // Update 3D background to grounding state if citations exist, then weaving
      window.app3DBackground?.setState(finalCitations.length > 0 ? 'grounding' : 'weaving');

      // Transition thinking orb to breathing & hide contemplation badge
      inlineOrb?.setState('breathing');
      contemplationBadge.style.display = 'none';

      // Write out the complete response line-by-line like ChatGPT and Claude
      window.app3DBackground?.setState('weaving');
      await streamLineByLine(aiBubble, fullAnswer);

      // Notify Mascot that answer writing is finished -> celebration & return
      window.VerboseMascot?.onComplete(aiBubble);

      // Finalize message display without cursor
      aiBubble.innerHTML = formatMarkdown(fullAnswer);
      window.SFX?.playReplyChime();

      if (finalProvider) {
        const provBadge = document.createElement('div');
        provBadge.className = 'provider-model-badge';
        provBadge.textContent = finalProvider;
        aiRow.appendChild(provBadge);
      }

      if (finalCitations && finalCitations.length > 0) {
        const tray = createSourcesTray(finalCitations);
        if (tray) aiRow.appendChild(tray);
      }

      session.messages.push({
        role: 'ai',
        content: fullAnswer,
        citations: finalCitations,
        provider: finalProvider
      });
      saveSessions();

    } catch (err) {
      inlineOrb?.destroy();
      contemplationBadge.style.display = 'none';
      aiBubble.textContent = 'Error contacting Verbose AI server. Please verify your connection.';
      window.VerboseMascot?.returnToInputDock();
    } finally {
      window.app3DBackground?.setState('idle');
      state.isGenerating = false;
      updateSendButtonState();
      scrollCanvasToBottom();
    }
  }

  // 9. UI Rendering Helpers
  function renderMessageItem(role, content, citations, bookTitle, image, provider) {
    const row = document.createElement('div');
    row.className = `msg-row ${role}`;

    if (role === 'user') {
      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble-user';
      if (image) {
        const imgEl = document.createElement('img');
        imgEl.src = image;
        imgEl.className = 'msg-image-attachment';
        imgEl.alt = 'Uploaded scan';
        bubble.appendChild(imgEl);
      }
      if (bookTitle) {
        const tag = document.createElement('span');
        tag.className = 'user-book-tag';
        tag.textContent = `Book: ${bookTitle}`;
        bubble.appendChild(tag);
      }
      if (content) {
        const textNode = document.createElement('div');
        textNode.textContent = content;
        bubble.appendChild(textNode);
      }
      row.appendChild(bubble);
    } else {
      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble-ai';
      bubble.innerHTML = formatMarkdown(content);
      row.appendChild(bubble);

      if (provider) {
        const provBadge = document.createElement('div');
        provBadge.className = 'provider-model-badge';
        provBadge.textContent = provider;
        row.appendChild(provBadge);
      }

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
      const hasSim = typeof c.similarity === 'number';
      const sim = hasSim ? Math.round(Math.max(0, c.similarity) * 100) : 0;
      card.innerHTML = `
        <div class="source-excerpt-header">
          <span>Passage ${idx + 1} &mdash; ${escapeHtml(c.chapter || 'Excerpt')}</span>
          <span class="source-similarity">${hasSim ? sim + '% Match' : 'Excerpt'}</span>
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

  // Progressive line-by-line typewriter streaming like ChatGPT & Claude
  async function streamLineByLine(container, text) {
    if (!text) return;
    const lines = text.split('\n');
    let displayedText = '';
    let tickCounter = 0;

    for (let li = 0; li < lines.length; li++) {
      const line = lines[li];
      if (line.trim().length === 0) {
        displayedText += '\n';
        container.innerHTML = formatMarkdown(displayedText) + '<span class="streaming-cursor"></span>';
        scrollCanvasToBottom();
        await new Promise(r => setTimeout(r, 20));
        continue;
      }

      // Word / token bursts within each line
      const tokens = line.match(/\S+|\s+/g) || [line];
      const step = 2; // Words per pulse
      for (let wi = 0; wi < tokens.length; wi += step) {
        const chunk = tokens.slice(wi, wi + step).join('');
        displayedText += chunk;
        container.innerHTML = formatMarkdown(displayedText) + '<span class="streaming-cursor"></span>';
        scrollCanvasToBottom();
        window.VerboseMascot?.onWritingToken(container);

        // Optional mechanical sound tick on burst
        tickCounter++;
        if (tickCounter % 3 === 0) {
          window.SFX?.playKeyTick();
        }

        // Natural typing cadence (faster for regular words, subtle pause on punctuation)
        let delay = 14;
        if (chunk.includes('.') || chunk.includes('!') || chunk.includes('?')) {
          delay = 32;
        } else if (chunk.includes(',') || chunk.includes(';') || chunk.includes(':')) {
          delay = 22;
        }
        await new Promise(r => setTimeout(r, delay));
      }

      displayedText += '\n';
      container.innerHTML = formatMarkdown(displayedText) + '<span class="streaming-cursor"></span>';
      scrollCanvasToBottom();
      await new Promise(r => setTimeout(r, 28)); // Line break pause like Claude/ChatGPT
    }
  }

  function renderThoughtAccordion(thoughtText, isOpen) {
    if (!thoughtText) return '';
    const cleanThought = escapeHtml(thoughtText).replace(/\n/g, '<br>');
    return `<details class="thought-dropdown"${isOpen ? ' open' : ''}><summary class="thought-summary"><div class="thought-summary-left"><svg class="thought-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg><span class="thought-label">Thought Process</span></div><span class="thought-arrow">&#9662;</span></summary><div class="thought-content">${cleanThought}</div></details>`;
  }

  function formatMarkdown(text) {
    if (!text) return '';

    let thoughtHtml = '';
    let mainText = text;

    // Check for thinking tokens: <think> ... </think> or unclosed <think> ...
    const thinkClosedRegex = /<think>([\s\S]*?)<\/think>/i;
    const closedMatch = mainText.match(thinkClosedRegex);
    if (closedMatch) {
      thoughtHtml = renderThoughtAccordion(closedMatch[1].trim(), false);
      mainText = mainText.replace(thinkClosedRegex, '').trim();
    } else {
      const thinkOpenRegex = /<think>([\s\S]*)$/i;
      const openMatch = mainText.match(thinkOpenRegex);
      if (openMatch) {
        thoughtHtml = renderThoughtAccordion(openMatch[1].trim(), true);
        mainText = mainText.replace(thinkOpenRegex, '').trim();
      }
    }

    const formattedBody = escapeHtml(mainText)
      .replace(/^### (.*$)/gim, '<h3>$1</h3>')
      .replace(/^## (.*$)/gim, '<h2>$1</h2>')
      .replace(/^# (.*$)/gim, '<h1>$1</h1>')
      .replace(/^> (.*$)/gim, '<blockquote>$1</blockquote>')
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g, '<em>$1</em>')
      .replace(/\[(.*?)\]\((https?:\/\/[^\s\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="chat-external-link">$1 <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-left:2px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></a>')
      .replace(/\n\n/g, '</p><p>')
      .replace(/\n/g, '<br>');

    return thoughtHtml + (formattedBody ? `<p>${formattedBody}</p>` : '');
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
