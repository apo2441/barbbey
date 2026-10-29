<?php
$pageTitle = 'Admin Login — BarberBook';
require_once '../includes/config.php';
if (isAdmin()) { header('Location: '.SITE_URL.'/admin/dashboard.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    $db=getDB();
    $stmt=$db->prepare('SELECT * FROM admins WHERE email=?');
    $stmt->execute([$email]); $admin=$stmt->fetch();
    if ($admin && password_verify($pass,$admin['password'])) {
        $_SESSION['admin_id']=$admin['id']; $_SESSION['admin_name']=$admin['name'];
        header('Location: '.SITE_URL.'/admin/dashboard.php'); exit;
    } else { $error='Invalid credentials.'; }
}
require_once '../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-logo"><h1>✂ BarberBook</h1><p>Admin Panel — Sign in</p></div>
    <?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required></div>
      <button type="submit" class="btn btn-primary btn-full btn-lg">Admin Login</button>
    </form>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
