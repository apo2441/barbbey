<?php
require_once 'includes/config.php';
require_once 'includes/mailer.php';
$db = getDB();

$token  = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

$appt = $db->prepare('SELECT a.*, b.name as barber_name FROM appointments a JOIN barbers b ON b.id=a.barber_id WHERE a.reschedule_token=? AND a.status="reschedule_pending"');
$appt->execute([$token]);
$appt = $appt->fetch();

$msg = '';
if (!$appt) {
    $msg = 'This link is invalid or has already been used.';
} elseif ($action === 'accept') {
    $db->prepare('UPDATE appointments SET appointment_date=reschedule_date, appointment_time=reschedule_time, status="confirmed", reschedule_token=NULL, reschedule_date=NULL, reschedule_time=NULL WHERE id=?')
       ->execute([$appt['id']]);
    $msg = '✓ Appointment rescheduled! Your new time has been confirmed.';
} elseif ($action === 'deny') {
    $db->prepare('UPDATE appointments SET status="confirmed", reschedule_token=NULL, reschedule_date=NULL, reschedule_time=NULL WHERE id=?')
       ->execute([$appt['id']]);
    $msg = '✕ Reschedule declined. Your original appointment remains as booked.';
}

require_once 'includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card" style="text-align:center">
    <div class="auth-logo"><h1>✂ BarberBook</h1></div>
    <p style="font-size:16px;padding:24px;background:var(--off-white);border-radius:8px"><?=$msg?></p>
    <a href="<?=SITE_URL?>/login.php" class="btn btn-primary" style="margin-top:20px">Go to BarberBook</a>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
