<?php
require_once __DIR__ . '/config.php';

// If logged in, bounce to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: admindashboard.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // simple CSRF check
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid session. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $errors[] = 'Provide username and password.';
        } else {
            $stmt = $mysqli->prepare("SELECT id, password FROM admins WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                // password stored as bcrypt (password_hash). Use password_verify.
                if (password_verify($password, $row['password'])) {
                    // good login
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = (int)$row['id'];
                    header('Location: admindashboard.php');
                    exit;
                } else {
                    $errors[] = 'Invalid credentials.';
                }
            } else {
                $errors[] = 'Invalid credentials.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Login - Ozyde</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
  .login-card{width:360px;background:#fff;padding:22px;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.08)}
  .login-card h1{margin:0 0 12px;font-size:20px}
  .form-row{margin-bottom:12px}
  input[type=text],input[type=password]{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px}
  .btn{background:#111827;color:#fff;padding:10px 14px;border-radius:6px;border:none;cursor:pointer}
  .errors{color:#b91c1c;margin-bottom:10px}
</style>
</head>
<body>
  <div class="login-card">
    <h1>Admin Login</h1>
    <?php if ($errors): ?>
      <div class="errors"><?= e(implode('<br>', $errors)) ?></div>
    <?php endif; ?>
    <form method="post" action="">
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
      <div class="form-row">
        <label>Username</label>
        <input type="text" name="username" required>
      </div>
      <div class="form-row">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <div style="text-align:right">
        <button class="btn" type="submit">Sign in</button>
      </div>
    </form>
    <p style="margin-top:10px;color:#666;font-size:13px">
      Default admins in DB: <strong>admin</strong> and <strong>superadmin</strong> (change passwords after first login). 
    </p>
  </div>
</body>
</html>
