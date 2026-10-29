<nav class="navbar">
  <a href="<?= SITE_URL ?>" class="navbar-brand">✂ <span>Barber</span>Book</a>
  <ul class="navbar-nav">
    <?php if (isLoggedIn()): ?>
      <li><a href="<?= SITE_URL ?>/client/dashboard.php">Dashboard</a></li>
      <li><a href="<?= SITE_URL ?>/client/appointments.php">My Appointments</a></li>
      <li><a href="<?= SITE_URL ?>/logout.php" class="btn btn-outline btn-sm">Logout</a></li>
    <?php elseif (isBarber()): ?>
      <li><a href="<?= SITE_URL ?>/barber/dashboard.php">Dashboard</a></li>
      <li><a href="<?= SITE_URL ?>/barber/logout.php" class="btn btn-outline btn-sm">Logout</a></li>
    <?php elseif (isAdmin()): ?>
      <li><a href="<?= SITE_URL ?>/admin/dashboard.php">Admin Panel</a></li>
      <li><a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-outline btn-sm">Logout</a></li>
    <?php else: ?>
      <li><a href="<?= SITE_URL ?>/login.php">Login</a></li>
      <li><a href="<?= SITE_URL ?>/register.php" class="btn btn-primary btn-sm">Sign Up</a></li>
    <?php endif; ?>
  </ul>
</nav>
