<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verbose AI — Sign In</title>
  <link rel="icon" type="image/svg+xml" href="/img/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
  <style>
    .auth-page-wrap {
      width: 100vw;
      height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-page);
      padding: 24px;
      position: relative;
      overflow: hidden;
    }
    /* Subtle background grid */
    .auth-page-wrap::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(var(--border-subtle) 1px, transparent 1px),
        linear-gradient(90deg, var(--border-subtle) 1px, transparent 1px);
      background-size: 40px 40px;
      opacity: 0.4;
      pointer-events: none;
    }
    .auth-card {
      width: 100%;
      max-width: 410px;
      background: rgba(14, 14, 18, 0.82);
      backdrop-filter: blur(28px) saturate(190%);
      -webkit-backdrop-filter: blur(28px) saturate(190%);
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-radius: var(--radius-md);
      padding: 32px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      box-shadow: 0 32px 80px rgba(0, 0, 0, 0.98), inset 0 1px 1px 0 rgba(255, 255, 255, 0.16);
      position: relative;
      z-index: 2;
    }
    .auth-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      text-align: center;
    }
    .auth-logo {
      width: 44px;
      height: 44px;
      padding: 10px;
      border: 1px solid var(--border-medium);
      border-radius: var(--radius-sm);
      background: var(--bg-hover);
    }
    .auth-title {
      font-family: var(--font-serif);
      font-size: 1.7rem;
      font-weight: 400;
      color: var(--text-main);
      letter-spacing: -0.02em;
    }
    .auth-sub {
      font-size: 0.78rem;
      color: var(--text-muted);
      font-family: var(--font-mono);
      max-width: 280px;
    }

    /* Google OAuth Button */
    .oauth-container {
      display: flex;
      flex-direction: column;
      gap: 12px;
      width: 100%;
    }
    .btn-google-oauth {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: var(--radius-sm);
      padding: 12px 16px;
      color: var(--text-main);
      font-family: var(--font-sans);
      font-size: 0.88rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      user-select: none;
    }
    .btn-google-oauth:hover {
      background: rgba(255, 255, 255, 0.11);
      border-color: rgba(255, 255, 255, 0.35);
      transform: translateY(-1px);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
    }
    .btn-google-oauth:active {
      transform: translateY(0);
    }
    .google-icon {
      flex-shrink: 0;
    }

    /* Divider */
    .auth-divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 2px 0;
    }
    .auth-divider-line {
      flex: 1;
      height: 1px;
      background: var(--border-subtle);
    }
    .auth-divider-text {
      font-family: var(--font-mono);
      font-size: 0.65rem;
      color: var(--text-dim);
      letter-spacing: 0.08em;
      white-space: nowrap;
    }

    .auth-tabs {
      display: flex;
      border-bottom: 1px solid var(--border-subtle);
    }
    .auth-tab-btn {
      flex: 1;
      background: transparent;
      border: none;
      border-bottom: 2px solid transparent;
      padding: 10px;
      font-size: 0.82rem;
      font-family: var(--font-mono);
      color: var(--text-muted);
      cursor: pointer;
      text-align: center;
      transition: all 0.15s ease;
    }
    .auth-tab-btn.active {
      color: var(--text-main);
      border-bottom-color: #ffffff;
      font-weight: 600;
    }
    .auth-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .form-label {
      font-family: var(--font-mono);
      font-size: 0.7rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .form-input {
      background: var(--bg-page);
      border: 1px solid var(--border-medium);
      color: var(--text-main);
      font-family: var(--font-sans);
      font-size: 0.9rem;
      padding: 12px 14px;
      border-radius: var(--radius-sm);
      outline: none;
      transition: border-color 0.15s ease;
    }
    .form-input:focus { border-color: #ffffff; }
    .btn-auth-submit {
      background: #ffffff;
      color: #000000;
      border: none;
      border-radius: var(--radius-sm);
      padding: 13px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 4px;
      letter-spacing: 0.01em;
      transition: opacity 0.15s ease;
    }
    .btn-auth-submit:hover { opacity: 0.88; }
    .btn-auth-submit:disabled { opacity: 0.5; cursor: not-allowed; }
    .auth-error {
      display: none;
      font-family: var(--font-mono);
      font-size: 0.74rem;
      color: #ff6b6b;
      padding: 8px 12px;
      border: 1px solid rgba(255, 107, 107, 0.3);
      border-radius: var(--radius-sm);
      background: rgba(255, 107, 107, 0.05);
      line-height: 1.4;
    }
    .auth-footer {
      text-align: center;
      font-family: var(--font-mono);
      font-size: 0.65rem;
      color: var(--text-dim);
      border-top: 1px solid var(--border-subtle);
      padding-top: 14px;
    }

    /* Setup Modal for Google OAuth Configuration */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.85);
      backdrop-filter: blur(16px);
      z-index: 100;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-overlay.active {
      display: flex;
    }
    .modal-card {
      background: #0f0f13;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: var(--radius-md);
      max-width: 480px;
      width: 100%;
      padding: 28px;
      display: flex;
      flex-direction: column;
      gap: 18px;
      box-shadow: 0 24px 64px rgba(0, 0, 0, 0.9);
      animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes modalFadeIn {
      from { opacity: 0; transform: scale(0.96); }
      to { opacity: 1; transform: scale(1); }
    }
    .modal-title {
      font-family: var(--font-serif);
      font-size: 1.35rem;
      color: var(--text-main);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .modal-body {
      font-size: 0.82rem;
      color: var(--text-muted);
      line-height: 1.6;
      font-family: var(--font-sans);
    }
    .code-box {
      background: #000;
      border: 1px solid var(--border-medium);
      border-radius: var(--radius-sm);
      padding: 12px;
      font-family: var(--font-mono);
      font-size: 0.72rem;
      color: #9cdcfe;
      overflow-x: auto;
      white-space: pre;
    }
    .modal-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 6px;
    }
    .btn-modal-close {
      background: #ffffff;
      color: #000000;
      border: none;
      border-radius: var(--radius-sm);
      padding: 9px 18px;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-modal-secondary {
      background: transparent;
      color: var(--text-muted);
      border: 1px solid var(--border-medium);
      border-radius: var(--radius-sm);
      padding: 9px 16px;
      font-size: 0.8rem;
      cursor: pointer;
    }
    .btn-modal-secondary:hover {
      color: var(--text-main);
      border-color: #fff;
    }
  </style>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
  <div class="auth-page-wrap">
    <canvas id="starfield-canvas" class="starfield-canvas"></canvas>

    <div class="auth-card">
      <div class="auth-brand">
        <a href="/landing" style="font-family: var(--font-mono); font-size: 0.72rem; color: #00f0ff; text-decoration: none; margin-bottom: 6px; display: inline-flex; align-items: center; gap: 6px; transition: opacity 0.15s ease;">
          <span>&larr; View Model &amp; VGPU Showcase</span>
        </a>
        <img src="/img/logo.svg" class="auth-logo" alt="Verbose AI Logo">
        <h1 class="auth-title">Verbose AI</h1>
        <p class="auth-sub">Zero-hallucination literary intelligence</p>
      </div>

      <!-- Google OAuth Action -->
      <div class="oauth-container">
        <button type="button" id="btn-google-oauth" class="btn-google-oauth" onclick="handleGoogleSignInClick()">
          <svg class="google-icon" viewBox="0 0 24 24" width="18" height="18">
            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.34 24 12 24z"/>
            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.03 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
          </svg>
          <span id="btn-google-text">Continue with Google</span>
        </button>
      </div>

      <div class="auth-divider">
        <div class="auth-divider-line"></div>
        <span class="auth-divider-text">OR EMAIL</span>
        <div class="auth-divider-line"></div>
      </div>

      <div class="auth-tabs">
        <button id="tab-signin" class="auth-tab-btn active">Sign In</button>
        <button id="tab-signup" class="auth-tab-btn">Create Account</button>
      </div>

      <form id="auth-form" class="auth-form" onsubmit="event.preventDefault(); handleAuthSubmit();">
        <div class="form-group">
          <label class="form-label" for="auth-email">Email Address</label>
          <input type="email" id="auth-email" class="form-input" placeholder="reader@example.com" required autocomplete="email">
        </div>
        <div class="form-group">
          <label class="form-label" for="auth-password">Password</label>
          <input type="password" id="auth-password" class="form-input" placeholder="••••••••••••" required autocomplete="current-password">
        </div>

        <div id="auth-error" class="auth-error"></div>

        <button type="submit" id="btn-submit" class="btn-auth-submit">Sign In</button>
      </form>

      <div class="auth-footer">
        Verbose AI v1.0 &bull; Google OAuth 2.0 &bull; Neon Postgres
      </div>
    </div>
  </div>

  <!-- Google OAuth Setup Modal (Displayed if credentials are not yet configured) -->
  <div id="google-modal" class="modal-overlay" onclick="if(event.target === this) closeGoogleModal();">
    <div class="modal-card">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="22" height="22">
          <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
          <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.34 24 12 24z"/>
          <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 10.03 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
          <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
        </svg>
        <span>Google OAuth Setup</span>
      </div>
      <div class="modal-body">
        <p style="margin-bottom: 8px;">To enable one-click Google Sign-In, configure your Google Cloud OAuth Client credentials in your <code>.env</code> file:</p>
        <div class="code-box"># In your .env file:
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback</div>
        <p style="margin-top: 10px; font-size: 0.76rem; color: var(--text-dim);">
          1. Go to <strong style="color:#fff;">Google Cloud Console &rarr; APIs & Services &rarr; Credentials</strong>.<br>
          2. Create an <strong>OAuth 2.0 Client ID (Web application)</strong>.<br>
          3. Add your Authorized Redirect URI: <code id="modal-redirect-uri" style="color:#9cdcfe;">http://localhost:8080/auth/google/callback</code>
        </p>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-modal-secondary" onclick="copyEnvConfig()">Copy Template</button>
        <button type="button" class="btn-modal-close" onclick="closeGoogleModal()">Got It</button>
      </div>
    </div>
  </div>

  <script src="/js/sfx.js"></script>
  <script src="/js/vgpu-background.js"></script>
  <script src="/js/starfield.js"></script>
  <script>
    const tabSignin = document.getElementById('tab-signin');
    const tabSignup = document.getElementById('tab-signup');
    const btnSubmit = document.getElementById('btn-submit');
    const authEmail = document.getElementById('auth-email');
    const authPassword = document.getElementById('auth-password');
    const authError = document.getElementById('auth-error');
    const googleModal = document.getElementById('google-modal');
    const modalRedirectUri = document.getElementById('modal-redirect-uri');
    let mode = 'signin';
    let googleConfig = { configured: false, client_id: null, redirect_uri: null };

    // Fetch Google OAuth configuration on load
    fetch('/api.php?action=google_config')
      .then(res => res.json())
      .then(data => {
        if (data && data.success) {
          googleConfig = data;
          if (data.redirect_uri && modalRedirectUri) {
            modalRedirectUri.textContent = data.redirect_uri;
          }
          // Initialize Google Identity Services if client ID is configured
          if (data.configured && data.client_id && window.google) {
            initGoogleGIS(data.client_id);
          }
        }
      })
      .catch(() => {});

    function initGoogleGIS(clientId) {
      try {
        window.google.accounts.id.initialize({
          client_id: clientId,
          callback: handleGoogleCredentialResponse,
          auto_select: false,
          cancel_on_tap_outside: true
        });
      } catch (e) {
        console.warn('Google GIS init:', e);
      }
    }

    function handleGoogleCredentialResponse(response) {
      if (!response || !response.credential) return;
      window.SFX?.playSend();
      showError('');
      
      const btnGoogleText = document.getElementById('btn-google-text');
      if (btnGoogleText) btnGoogleText.textContent = 'Verifying with Google...';

      fetch('/api.php?action=google_verify_token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ credential: response.credential })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          window.SFX?.playReplyChime();
          const user = data.user || {};
          localStorage.setItem('verbose_user', JSON.stringify(user));
          window.location.href = '/app';
        } else {
          window.SFX?.playBlip(320, 0.08);
          showError(data.error || 'Google token verification failed.');
          if (btnGoogleText) btnGoogleText.textContent = 'Continue with Google';
        }
      })
      .catch(() => {
        window.SFX?.playBlip(320, 0.08);
        showError('Network error during Google authentication.');
        if (btnGoogleText) btnGoogleText.textContent = 'Continue with Google';
      });
    }

    function handleGoogleSignInClick() {
      window.SFX?.playBlip(750, 0.04);
      if (googleConfig.configured) {
        window.location.href = '/auth/google';
      } else {
        openGoogleModal();
      }
    }

    function openGoogleModal() {
      if (googleModal) googleModal.classList.add('active');
    }

    function closeGoogleModal() {
      if (googleModal) googleModal.classList.remove('active');
    }

    function copyEnvConfig() {
      const template = "# Google OAuth Credentials\nGOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com\nGOOGLE_CLIENT_SECRET=your_client_secret\nGOOGLE_REDIRECT_URI=" + (googleConfig.redirect_uri || window.location.origin + '/auth/google/callback');
      navigator.clipboard.writeText(template).then(() => {
        window.SFX?.playReplyChime();
        alert('Copied .env Google OAuth template to clipboard!');
      }).catch(() => {
        prompt('Copy this config:', template);
      });
    }

    // Check URL parameters for errors
    const urlParams = new URLSearchParams(window.location.search);
    const errorParam = urlParams.get('error');
    if (errorParam) {
      showError(decodeURIComponent(errorParam));
    }
    if (urlParams.get('google_unconfigured') === '1') {
      openGoogleModal();
    }

    tabSignin.addEventListener('click', () => {
      window.SFX?.playBlip(620, 0.03);
      mode = 'signin';
      tabSignin.classList.add('active');
      tabSignup.classList.remove('active');
      btnSubmit.textContent = 'Sign In';
      authError.style.display = 'none';
    });

    tabSignup.addEventListener('click', () => {
      window.SFX?.playBlip(780, 0.03);
      mode = 'signup';
      tabSignup.classList.add('active');
      tabSignin.classList.remove('active');
      btnSubmit.textContent = 'Create Account';
      authError.style.display = 'none';
    });

    [authEmail, authPassword].forEach(input => {
      input.addEventListener('input', () => window.SFX?.playKeyTick());
      input.addEventListener('focus', () => { authError.style.display = 'none'; });
    });

    function showError(msg) {
      if (!msg) {
        authError.style.display = 'none';
        return;
      }
      authError.textContent = msg;
      authError.style.display = 'block';
    }

    function handleAuthSubmit() {
      window.SFX?.playSend();
      const email = authEmail.value.trim();
      const password = authPassword.value;
      if (!email || !password) return;

      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Authenticating...';
      authError.style.display = 'none';

      fetch('/api.php?action=auth', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode, email, password })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          window.SFX?.playReplyChime();
          const user = data.user || { email };
          localStorage.setItem('verbose_user', JSON.stringify(user));
          window.location.href = '/app';
        } else {
          window.SFX?.playBlip(320, 0.08);
          showError(data.error || 'Authentication failed. Please check your credentials.');
          btnSubmit.disabled = false;
          btnSubmit.textContent = mode === 'signin' ? 'Sign In' : 'Create Account';
        }
      })
      .catch(() => {
        // Offline fallback - set session client-side
        document.cookie = "verbose_session=" + encodeURIComponent(email) + "; path=/; max-age=2592000; SameSite=Lax";
        localStorage.setItem('verbose_user', JSON.stringify({ email }));
        window.location.href = '/app';
      });
    }
  </script>
</body>
</html>
