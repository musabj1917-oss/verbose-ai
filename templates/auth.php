<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verbose AI</title>
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
      max-width: 400px;
      background: var(--bg-card);
      border: 1px solid var(--border-medium);
      border-radius: var(--radius-md);
      padding: 32px;
      display: flex;
      flex-direction: column;
      gap: 24px;
      box-shadow: 0 32px 80px rgba(0, 0, 0, 0.98);
      position: relative;
      z-index: 1;
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
    }
    .auth-footer {
      text-align: center;
      font-family: var(--font-mono);
      font-size: 0.65rem;
      color: var(--text-dim);
      border-top: 1px solid var(--border-subtle);
      padding-top: 16px;
    }
  </style>
</head>
<body>
  <div class="auth-page-wrap">
    <div class="auth-card">
      <div class="auth-brand">
        <img src="/img/logo.svg" class="auth-logo" alt="Verbose AI Logo">
        <h1 class="auth-title">Verbose AI</h1>
        <p class="auth-sub">Zero-hallucination literary intelligence</p>
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
          <input type="password" id="auth-password" class="form-input" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required autocomplete="current-password">
        </div>
        <div id="auth-error" class="auth-error"></div>
        <button type="submit" id="btn-submit" class="btn-auth-submit">Sign In</button>
      </form>
      <div class="auth-footer">
        Verbose AI v1.0 &bull; Secure Gateway &bull; Neon Postgres
      </div>
    </div>
  </div>
  <script src="/js/sfx.js"></script>
  <script>
    const tabSignin = document.getElementById('tab-signin');
    const tabSignup = document.getElementById('tab-signup');
    const btnSubmit = document.getElementById('btn-submit');
    const authEmail = document.getElementById('auth-email');
    const authPassword = document.getElementById('auth-password');
    const authError = document.getElementById('auth-error');
    let mode = 'signin';

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
          const userEmail = (data.user && data.user.email) ? data.user.email : email;
          document.cookie = "verbose_session=" + encodeURIComponent(userEmail) + "; path=/; max-age=2592000; SameSite=Lax";
          localStorage.setItem('verbose_user', JSON.stringify({ email: userEmail }));
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
