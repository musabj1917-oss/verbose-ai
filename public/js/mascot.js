/**
 * Verbose AI - Interactive Book Mascot ("Verby") Engine
 * Coordinates climbing, book-searching, answer-throwing, jumping, and writing.
 */

(function() {
  class VerboseMascotController {
    constructor() {
      this.el = null;
      this.sprite = null;
      this.hand = null;
      this.quill = null;
      this.scrollEl = null;
      this.bubbleEl = null;
      this.promptInput = null;
      this.inputCapsule = null;
      this.chatCanvas = null;

      this.currentState = 'idle'; // 'idle' | 'climbing' | 'typing' | 'searching-book' | 'throwing' | 'jumping' | 'writing' | 'celebrating'
      this.typingTimer = null;
      this.bubbleTimer = null;
      this.isAtReply = false;
      this.activeTargetBubble = null;
      this.hasSearchedBook = false;

      this.funPhrases = [
        "Scanning the Grade 8 texts!",
        "Every fact strictly verified.",
        "Zero hallucinations here!",
        "Let me flip through the chapters...",
        "I've got the exact citation!",
        "Ready to write your answer!"
      ];

      this.init();
    }

    init() {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => this.setupDOM());
      } else {
        this.setupDOM();
      }
    }

    setupDOM() {
      this.promptInput = document.getElementById('prompt-input');
      this.inputCapsule = document.querySelector('.input-capsule');
      this.chatCanvas = document.getElementById('chat-canvas');

      if (!this.inputCapsule) return;

      // Create Mascot Element if not already in DOM
      let mascotContainer = document.getElementById('verbose-mascot');
      if (!mascotContainer) {
        mascotContainer = document.createElement('div');
        mascotContainer.id = 'verbose-mascot';
        mascotContainer.className = 'verbose-mascot state-climbing';
        mascotContainer.title = 'Verbose Mascot (Click me!)';

        mascotContainer.innerHTML = `
          <div class="mascot-paws">
            <div class="mascot-paw paw-left"></div>
            <div class="mascot-paw paw-right"></div>
          </div>
          <div class="mascot-body-wrap">
            <div class="mascot-book-aura">
              <div class="book-magic-sparkle s1"></div>
              <div class="book-magic-sparkle s2"></div>
              <div class="book-magic-sparkle s3"></div>
              <div class="book-floating-page p1"></div>
              <div class="book-floating-page p2"></div>
            </div>
            <img src="/img/mascot.png" class="mascot-sprite" alt="Verbose Mascot" id="mascot-sprite">
            <div class="mascot-active-hand" id="mascot-active-hand">
              <div class="hand-palm"></div>
            </div>
            <div class="mascot-quill-tool" id="mascot-quill-tool">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                <path d="M12 19l7-7 3 3-7 7-3-3z" fill="#ffffff"/>
                <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z" fill="#ffffff" opacity="0.9"/>
                <circle cx="17" cy="7" r="1.5" fill="#00f0ff"/>
                <line x1="2" y1="2" x2="8" y2="8" stroke="#000000" stroke-width="1.5"/>
              </svg>
              <div class="quill-spark"></div>
            </div>
          </div>
          <div class="mascot-speech-bubble" id="mascot-speech-bubble">
            <span class="bubble-text">Reading...</span>
          </div>
        `;

        this.inputCapsule.appendChild(mascotContainer);
      }

      // Create Flying Citation Scroll Parcel element
      let thrownScroll = document.getElementById('mascot-thrown-scroll');
      if (!thrownScroll) {
        thrownScroll = document.createElement('div');
        thrownScroll.id = 'mascot-thrown-scroll';
        thrownScroll.className = 'mascot-thrown-scroll';
        thrownScroll.innerHTML = `
          <div class="thrown-scroll-content">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span class="scroll-label">Grounded Passage</span>
          </div>
        `;
        document.body.appendChild(thrownScroll);
      }

      this.el = mascotContainer;
      this.sprite = mascotContainer.querySelector('.mascot-sprite');
      this.hand = mascotContainer.querySelector('#mascot-active-hand');
      this.quill = mascotContainer.querySelector('#mascot-quill-tool');
      this.bubbleEl = mascotContainer.querySelector('#mascot-speech-bubble');
      this.scrollEl = thrownScroll;

      // Initial climb onto ledge
      setTimeout(() => {
        this.setState('idle');
      }, 700);

      // Mascot interactive click
      this.el.addEventListener('click', (e) => {
        e.stopPropagation();
        this.onClickMascot();
      });

      // Hook up input events
      if (this.promptInput) {
        ['input', 'keyup'].forEach(evt => {
          this.promptInput.addEventListener(evt, () => {
            this.onTyping(this.promptInput.value);
          });
        });

        this.promptInput.addEventListener('focus', () => this.onFocus());
        this.promptInput.addEventListener('blur', () => this.onBlur());
      }
    }

    setState(newState) {
      if (this.currentState === newState) return;
      this.currentState = newState;

      if (!this.el) return;
      // Remove all state classes
      this.el.classList.remove(
        'state-idle',
        'state-climbing',
        'state-typing',
        'state-searching-book',
        'state-throwing',
        'state-jumping',
        'state-writing',
        'state-celebrating'
      );
      this.el.classList.add(`state-${newState}`);
    }

    // ─── 1. Typing & Idle Behaviors ───
    onFocus() {
      if (this.isAtReply) return;
      this.setState('climbing');
      setTimeout(() => {
        if (this.currentState === 'climbing') this.setState('idle');
      }, 600);
    }

    onBlur() {
      if (this.isAtReply) return;
      this.setState('idle');
    }

    onTyping(text) {
      if (this.isAtReply) return;

      const trimmed = (text || '').trim();
      const isQuestion = /[\?？]|^(what|how|why|who|when|where|which|define|explain|give|is|are|can|does|did|ስለ|ምን|ምንድን|እንዴት)/i.test(trimmed);

      // If typing a question or book inquiry, reach into the book with hand!
      if (isQuestion && trimmed.length >= 6) {
        if (this.currentState !== 'searching-book') {
          this.setState('searching-book');
          this.hasSearchedBook = true;
          this.speak("Searching book pages...", 1800);
          window.SFX?.playBlip(720, 0.03);
        }
      } else {
        this.setState('typing');
      }

      clearTimeout(this.typingTimer);
      this.typingTimer = setTimeout(() => {
        if (!this.isAtReply) {
          if (isQuestion && trimmed.length >= 6) {
            this.setState('searching-book');
          } else {
            this.setState('idle');
          }
        }
      }, 1200);
    }

    onBookSelected(bookTitle) {
      if (this.isAtReply) return;
      this.setState('searching-book');
      this.hasSearchedBook = true;
      this.speak(`Attaching ${bookTitle ? bookTitle.slice(0, 18) : 'book'}...`, 2200);
      window.SFX?.playBlip(840, 0.04);
      setTimeout(() => {
        if (this.currentState === 'searching-book' && !this.promptInput.value.trim()) {
          this.setState('idle');
        }
      }, 2400);
    }

    // ─── 2. Throwing the Answer Parcel & Jumping to Reply ───
    async onSend(aiRow, aiBubble, metadata = {}) {
      if (!this.el || !aiRow) return;

      this.isAtReply = true;
      this.activeTargetBubble = aiBubble;

      // Step A: Wind-up and throw the answer scroll to the reply section
      await this.performThrowAnswer(aiRow);

      // Step B: Mascot jumps to the reply section to write the reply
      await this.performJumpToReply(aiRow, aiBubble);
    }

    performThrowAnswer(aiRow) {
      return new Promise((resolve) => {
        if (!this.el || !this.scrollEl) return resolve();

        this.setState('throwing');
        window.SFX?.playBlip(560, 0.05);

        const mascotRect = this.el.getBoundingClientRect();
        const rowRect = aiRow.getBoundingClientRect();

        const startX = mascotRect.left + mascotRect.width / 2;
        const startY = mascotRect.top + 10;
        const endX = rowRect.left + 40;
        const endY = rowRect.top + 20;

        // Position scroll at mascot hand
        this.scrollEl.style.left = `${startX}px`;
        this.scrollEl.style.top = `${startY}px`;
        this.scrollEl.style.opacity = '1';
        this.scrollEl.style.transform = 'scale(0.6) rotate(-20deg)';

        const startTime = performance.now();
        const duration = 520; // ms

        const animateScroll = (now) => {
          const elapsed = now - startTime;
          const p = Math.min(1, elapsed / duration);

          // Quadratic bezier trajectory with high arc peak
          const arcHeight = Math.min(180, Math.abs(endY - startY) * 0.4 + 80);
          const currentX = startX + (endX - startX) * p;
          const currentY = startY + (endY - startY) * p - Math.sin(p * Math.PI) * arcHeight;
          const rot = -20 + p * 380;
          const scale = 0.6 + Math.sin(p * Math.PI) * 0.45;

          this.scrollEl.style.left = `${currentX}px`;
          this.scrollEl.style.top = `${currentY}px`;
          this.scrollEl.style.transform = `scale(${scale}) rotate(${rot}deg)`;

          if (p < 1) {
            requestAnimationFrame(animateScroll);
          } else {
            // Landing burst
            window.SFX?.playBlip(920, 0.04);
            this.scrollEl.style.opacity = '0';
            setTimeout(() => {
              this.scrollEl.style.transform = 'scale(0.6)';
            }, 200);
            resolve();
          }
        };

        requestAnimationFrame(animateScroll);
      });
    }

    performJumpToReply(aiRow, aiBubble) {
      return new Promise((resolve) => {
        if (!this.el) return resolve();

        this.setState('jumping');
        window.SFX?.playBlip(640, 0.05);

        const mascotRect = this.el.getBoundingClientRect();
        const targetRect = aiRow.getBoundingClientRect();

        const dx = (targetRect.left + 16) - mascotRect.left;
        const dy = (targetRect.top - 50) - mascotRect.top;

        this.el.style.transform = `translate3d(${dx}px, ${dy}px, 0) scale(1.08) rotate(4deg)`;

        setTimeout(() => {
          this.setState('writing');
          this.speak("Writing reply...", 2000);
          resolve();
        }, 550);
      });
    }

    // ─── 3. Writing Motion during Streaming ───
    onWritingToken(container) {
      if (!this.isAtReply || !this.el) return;

      // Jiggle writing quill and bob slightly as tokens stream
      if (this.currentState !== 'writing') {
        this.setState('writing');
      }

      // Small bounce in sync with words
      const randOffset = (Math.random() - 0.5) * 4;
      if (this.sprite) {
        this.sprite.style.transform = `translateY(${randOffset}px) rotate(${randOffset * 0.8}deg)`;
      }

      // Auto-follow active AI row in canvas if canvas scrolled
      if (this.activeTargetBubble && this.el) {
        const mascotRect = this.el.getBoundingClientRect();
        const bubbleRect = this.activeTargetBubble.getBoundingClientRect();
        const targetY = bubbleRect.top - 46;
        const currentY = mascotRect.top;
        if (Math.abs(targetY - currentY) > 8) {
          const currentTransform = this.el.style.transform;
          const match = currentTransform.match(/translate3d\(([^,]+),\s*([^,]+)/);
          if (match) {
            const currentDx = parseFloat(match[1]);
            const newDy = parseFloat(match[2]) + (targetY - currentY) * 0.35;
            this.el.style.transform = `translate3d(${currentDx}px, ${newDy}px, 0) scale(1.04)`;
          }
        }
      }
    }

    // ─── 4. Celebrating & Returning Home ───
    onComplete(aiBubble) {
      if (!this.isAtReply || !this.el) return;

      this.setState('celebrating');
      this.speak("Verified citation ready!", 2600);
      window.SFX?.playReplyChime();

      if (this.sprite) {
        this.sprite.style.transform = 'translateY(0) rotate(0deg)';
      }

      // Stay beside answer for 3.2 seconds, then return home to input ledge
      setTimeout(() => {
        this.returnToInputDock();
      }, 3200);
    }

    returnToInputDock() {
      if (!this.el) return;

      this.setState('jumping');
      this.el.style.transition = 'transform 0.65s cubic-bezier(0.2, 0.9, 0.3, 1), opacity 0.3s ease';
      this.el.style.transform = 'translate3d(0, 0, 0) scale(1) rotate(0deg)';

      setTimeout(() => {
        this.isAtReply = false;
        this.activeTargetBubble = null;
        this.el.style.transition = '';
        this.setState('idle');
      }, 680);
    }

    // ─── 5. Micro-Interactions: Thought Bubble & Click ───
    speak(text, duration = 2200) {
      if (!this.bubbleEl) return;
      const span = this.bubbleEl.querySelector('.bubble-text');
      if (span) span.textContent = text;

      this.bubbleEl.classList.add('visible');
      clearTimeout(this.bubbleTimer);
      this.bubbleTimer = setTimeout(() => {
        this.bubbleEl.classList.remove('visible');
      }, duration);
    }

    onClickMascot() {
      window.SFX?.playBlip(880, 0.05);

      if (this.isAtReply) {
        this.speak("I'm drafting the verified text!", 2000);
        return;
      }

      // Fun playful hop
      this.el.style.transform = 'translateY(-14px) scale(1.12) rotate(-3deg)';
      setTimeout(() => {
        this.el.style.transform = 'translateY(0) scale(1) rotate(0deg)';
      }, 260);

      const phrase = this.funPhrases[Math.floor(Math.random() * this.funPhrases.length)];
      this.speak(phrase, 2500);

      if (window.AndroidBridge && window.AndroidBridge.vibrate) {
        window.AndroidBridge.vibrate(15);
      }
    }
  }

  // Export globally
  window.VerboseMascot = new VerboseMascotController();
})();
