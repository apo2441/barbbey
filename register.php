<?php
$pageTitle = 'Register — BarberBook';
require_once 'includes/config.php';
if (isLoggedIn()) { header('Location: '.SITE_URL.'/client/dashboard.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name)   $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $db = getDB();
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'Email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare('INSERT INTO users (name, email, phone, password) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, $phone, $hash]);
            $_SESSION['user_id']    = $db->lastInsertId();
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            header('Location: '.SITE_URL.'/client/dashboard.php'); exit;
        }
    }
}
require_once 'includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-logo">
      <h1>✂ BarberBook</h1>
      <p>Create your free account</p>
    </div>
    <?php if ($errors): ?>
      <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" required placeholder="John Smith" value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
        <input type="tel" name="phone" class="form-control" placeholder="+1 555 000 0000" value="<?= e($_POST['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required placeholder="Min 8 characters">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm" class="form-control" required placeholder="Repeat password">
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg">Create Account</button>
    </form>
    <div class="auth-divider">already have one?</div>
    <p class="text-center text-small"><a href="login.php"><strong>Sign in →</strong></a></p>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
