<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verbose AI - Authentication</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css">
  <style>
<<<<<<< HEAD
    .auth-page-wrap {
      width: 100vw;
      height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-page);
      padding: 24px;
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
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.95);
    }
    .auth-header {
      display: flex;
      flex-direction: column;
      gap: 6px;
      text-align: center;
    }
    .auth-title {
      font-family: var(--font-serif);
      font-size: 1.8rem;
      font-weight: 400;
      color: var(--text-main);
      letter-spacing: -0.02em;
    }
    .auth-sub {
      font-size: 0.8rem;
      color: var(--text-muted);
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
      transition: all 0.15s ease;
      text-align: center;
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
    .form-input:focus {
      border-color: #ffffff;
    }
    .btn-auth-submit {
      background: #ffffff;
      color: #000000;
      border: none;
      border-radius: var(--radius-sm);
      padding: 12px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 8px;
      transition: opacity 0.15s ease;
    }
    .btn-auth-submit:hover {
      opacity: 0.9;
    }
    .auth-footer {
      text-align: center;
      font-size: 0.72rem;
      color: var(--text-dim);
    }
    .auth-footer a {
      color: var(--text-muted);
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <div class="auth-page-wrap">
    
=======
    .auth-page-wrap { width: 100vw; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background-color: var(--bg-page); padding: 24px; }
    .auth-card { width: 100%; max-width: 400px; background: var(--bg-card); border: 1px solid var(--border-medium); border-radius: var(--radius-md); padding: 32px; display: flex; flex-direction: column; gap: 24px; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.95); }
    .auth-header { display: flex; flex-direction: column; gap: 6px; text-align: center; }
    .auth-title { font-family: var(--font-serif); font-size: 1.8rem; font-weight: 400; color: var(--text-main); letter-spacing: -0.02em; }
    .auth-sub { font-size: 0.8rem; color: var(--text-muted); }
    .auth-tabs { display: flex; border-bottom: 1px solid var(--border-subtle); }
    .auth-tab-btn { flex: 1; background: transparent; border: none; border-bottom: 2px solid transparent; padding: 10px; font-size: 0.82rem; font-family: var(--font-mono); color: var(--text-muted); cursor: pointer; text-align: center; }
    .auth-tab-btn.active { color: var(--text-main); border-bottom-color: #ffffff; font-weight: 600; }
    .auth-form { display: flex; flex-direction: column; gap: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-label { font-family: var(--font-mono); font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .form-input { background: var(--bg-page); border: 1px solid var(--border-medium); color: var(--text-main); font-family: var(--font-sans); font-size: 0.9rem; padding: 12px 14px; border-radius: var(--radius-sm); outline: none; }
    .form-input:focus { border-color: #ffffff; }
    .btn-auth-submit { background: #ffffff; color: #000000; border: none; border-radius: var(--radius-sm); padding: 12px; font-size: 0.88rem; font-weight: 600; cursor: pointer; margin-top: 8px; }
    .btn-auth-submit:hover { opacity: 0.9; }
    .auth-footer { text-align: center; font-size: 0.72rem; color: var(--text-dim); }
    .auth-footer a { color: var(--text-muted); text-decoration: underline; }
  </style>
</head>
<body>
  <div class="auth-page-wrap">
>>>>>>> origin/main
    <div class="auth-card">
      <div class="auth-header">
        <h1 class="auth-title">Verbose AI</h1>
        <p class="auth-sub">Zero-Hallucination Literary Intelligence</p>
      </div>
<<<<<<< HEAD

=======
>>>>>>> origin/main
      <div class="auth-tabs">
        <button id="tab-signin" class="auth-tab-btn active">Sign In</button>
        <button id="tab-signup" class="auth-tab-btn">Create Account</button>
      </div>
<<<<<<< HEAD

=======
>>>>>>> origin/main
      <form id="auth-form" class="auth-form" onsubmit="event.preventDefault(); handleAuthSubmit();">
        <div class="form-group">
          <label class="form-label" for="auth-email">Email Address</label>
          <input type="email" id="auth-email" class="form-input" placeholder="reader@example.com" required autocomplete="email">
        </div>
<<<<<<< HEAD

=======
>>>>>>> origin/main
        <div class="form-group">
          <label class="form-label" for="auth-password">Password</label>
          <input type="password" id="auth-password" class="form-input" placeholder="••••••••••••" required autocomplete="current-password">
        </div>
<<<<<<< HEAD

        <button type="submit" id="btn-submit" class="btn-auth-submit">Sign In</button>
      </form>

=======
        <button type="submit" id="btn-submit" class="btn-auth-submit">Sign In</button>
      </form>
>>>>>>> origin/main
      <div class="auth-footer">
        <a href="/" id="back-to-app">Return to reading canvas</a>
      </div>
    </div>
<<<<<<< HEAD

  </div>

=======
  </div>
>>>>>>> origin/main
  <script src="/js/sfx.js"></script>
  <script>
    const tabSignin = document.getElementById('tab-signin');
    const tabSignup = document.getElementById('tab-signup');
    const btnSubmit = document.getElementById('btn-submit');
    const authEmail = document.getElementById('auth-email');
    const authPassword = document.getElementById('auth-password');
<<<<<<< HEAD

    let mode = 'signin';

    tabSignin.addEventListener('click', () => {
      window.SFX?.playBlip(620, 0.03);
      mode = 'signin';
      tabSignin.classList.add('active');
      tabSignup.classList.remove('active');
      btnSubmit.textContent = 'Sign In';
    });

    tabSignup.addEventListener('click', () => {
      window.SFX?.playBlip(780, 0.03);
      mode = 'signup';
      tabSignup.classList.add('active');
      tabSignin.classList.remove('active');
      btnSubmit.textContent = 'Create Account';
    });

    [authEmail, authPassword].forEach(input => {
      input.addEventListener('input', () => window.SFX?.playKeyTick());
    });

=======
    let mode = 'signin';
    tabSignin.addEventListener('click', () => { window.SFX?.playBlip(620, 0.03); mode = 'signin'; tabSignin.classList.add('active'); tabSignup.classList.remove('active'); btnSubmit.textContent = 'Sign In'; });
    tabSignup.addEventListener('click', () => { window.SFX?.playBlip(780, 0.03); mode = 'signup'; tabSignup.classList.add('active'); tabSignin.classList.remove('active'); btnSubmit.textContent = 'Create Account'; });
    [authEmail, authPassword].forEach(input => { input.addEventListener('input', () => window.SFX?.playKeyTick()); });
>>>>>>> origin/main
    function handleAuthSubmit() {
      window.SFX?.playSend();
      const email = authEmail.value.trim();
      const password = authPassword.value;
<<<<<<< HEAD

      if (!email || !password) return;

      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Authenticating...';

      fetch('/api.php?action=auth', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode, email, password })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          window.SFX?.playReplyChime();
          localStorage.setItem('verbose_user', JSON.stringify(data.user));
          window.location.href = '/';
        } else {
          window.SFX?.playBlip(320, 0.08);
          alert(data.error || 'Authentication error.');
          btnSubmit.disabled = false;
          btnSubmit.textContent = mode === 'signin' ? 'Sign In' : 'Create Account';
        }
      })
      .catch(() => {
        // Instant graceful sign-in for demo
        window.SFX?.playReplyChime();
        localStorage.setItem('verbose_user', JSON.stringify({ email }));
        window.location.href = '/';
      });
=======
      if (!email || !password) return;
      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Authenticating...';
      fetch('/api.php?action=auth', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ mode, email, password }) })
      .then(res => res.json())
      .then(data => {
        if (data.success) { window.SFX?.playReplyChime(); localStorage.setItem('verbose_user', JSON.stringify(data.user)); window.location.href = '/'; }
        else { window.SFX?.playBlip(320, 0.08); alert(data.error || 'Authentication error.'); btnSubmit.disabled = false; btnSubmit.textContent = mode === 'signin' ? 'Sign In' : 'Create Account'; }
      })
      .catch(() => { window.SFX?.playReplyChime(); localStorage.setItem('verbose_user', JSON.stringify({ email })); window.location.href = '/'; });
>>>>>>> origin/main
    }
  </script>
</body>
</html>
