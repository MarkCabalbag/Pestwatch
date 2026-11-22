<?php
require_once 'db.php';
require_once 'auth.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password =       $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            loginUser($user['id'], $user['username']);
            header('Location: /iot/dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — PestWatch</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0f0d; --card: #111a14; --border: #1e3a24;
    --green: #2ecc71; --green-dim: #1a7a43; --text: #d4f0dc;
    --muted: #5a8a66; --red: #e74c3c;
    --font-head: 'Syne', sans-serif; --font-mono: 'Space Mono', monospace;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg); color: var(--text); font-family: var(--font-mono);
    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;
    background-image: radial-gradient(ellipse at 20% 50%, #0d2e1a 0%, transparent 60%),
                      radial-gradient(ellipse at 80% 20%, #081f10 0%, transparent 50%);
  }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 4px; padding: 2.5rem; width: 100%; max-width: 400px; box-shadow: 0 0 40px rgba(46,204,113,0.05); }
  .logo { font-family: var(--font-head); font-size: 1.8rem; font-weight: 800; color: var(--green); letter-spacing: -1px; margin-bottom: 0.25rem; }
  .tagline { color: var(--muted); font-size: 0.75rem; margin-bottom: 2rem; }
  label { display: block; font-size: 0.7rem; color: var(--muted); margin-bottom: 0.4rem; letter-spacing: 1px; text-transform: uppercase; }
  input { width: 100%; background: #0d1a10; border: 1px solid var(--border); color: var(--text); padding: 0.75rem 1rem; font-family: var(--font-mono); font-size: 0.85rem; border-radius: 2px; margin-bottom: 1.2rem; outline: none; transition: border-color 0.2s; }
  input:focus { border-color: var(--green-dim); }
  .btn { width: 100%; background: var(--green); color: #000; border: none; padding: 0.85rem; font-family: var(--font-head); font-size: 1rem; font-weight: 700; border-radius: 2px; cursor: pointer; transition: background 0.2s; }
  .btn:hover { background: #27ae60; }
  .error { color: var(--red); font-size: 0.8rem; margin-bottom: 1rem; padding: 0.5rem 0.75rem; background: rgba(231,76,60,0.1); border-left: 3px solid var(--red); }
  .footer-link { text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: var(--muted); }
  .footer-link a { color: var(--green); text-decoration: none; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">🌿 PestWatch</div>
  <div class="tagline">// SIGN IN TO CONTINUE</div>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <label>Username or Email</label>
    <input type="text" name="username" required autocomplete="off">

    <label>Password</label>
    <input type="password" name="password" required>

    <button class="btn" type="submit">Login</button>
  </form>

  <div class="footer-link">No account? <a href="signup.php">Sign up</a></div>
</div>
</body>
</html>
