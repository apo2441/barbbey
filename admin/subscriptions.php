<?php
$pageTitle = 'Subscriptions — BarberBook';
require_once '../includes/config.php';
requireAdmin();
$db = getDB();

// Record payment
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $bid    = (int)$_POST['barber_id'];
    $amount = (float)$_POST['amount'];
    $month  = $_POST['period_month'];
    $paidAt = $_POST['paid_at'];
    $notes  = trim($_POST['notes']??'');
    $db->prepare('INSERT INTO subscription_payments (barber_id,amount,period_month,paid_at,notes,recorded_by) VALUES (?,?,?,?,?,?)')
       ->execute([$bid,$amount,$month,$paidAt,$notes,$_SESSION['admin_id']]);
    // Update barber subscription status
    $db->prepare("UPDATE barbers SET subscription_status='paid', subscription_due=DATE_ADD(?,INTERVAL 1 MONTH) WHERE id=?")
       ->execute([$paidAt,$bid]);
    setFlash('success','Payment recorded!');
    header('Location: subscriptions.php'); exit;
}

// Mark unpaid
if (isset($_GET['unpaid'])) {
    $db->prepare("UPDATE barbers SET subscription_status='unpaid' WHERE id=?")->execute([(int)$_GET['unpaid']]);
    setFlash('warning','Barber marked as unpaid.');
    header('Location: subscriptions.php'); exit;
}

$filterBarber = (int)($_GET['barber'] ?? 0);

$barbers = $db->query("SELECT b.*, s.name as shop_name, 
    (SELECT paid_at FROM subscription_payments WHERE barber_id=b.id ORDER BY paid_at DESC LIMIT 1) as last_paid
    FROM barbers b JOIN shops s ON s.id=b.shop_id ORDER BY b.subscription_status, b.name")->fetchAll();

$targetBarber = null;
if ($filterBarber) {
    foreach ($barbers as $b) if ($b['id']===$filterBarber) { $targetBarber=$b; break; }
}

// Payment history
$payments = $db->query("SELECT sp.*, b.name as barber_name FROM subscription_payments sp JOIN barbers b ON b.id=sp.barber_id ORDER BY sp.paid_at DESC LIMIT 50")->fetchAll();

require_once '../includes/header.php';
?>
<nav class="navbar">
  <a href="dashboard.php" class="navbar-brand">✂ <span>Barber</span>Book</a>
  <ul class="navbar-nav"><li><a href="logout.php" class="btn btn-outline btn-sm">Logout</a></li></ul>
</nav>
<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-user"><div class="sidebar-avatar">A</div><div class="sidebar-name"><?=e($_SESSION['admin_name'])?></div><div class="sidebar-role">Administrator</div></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><span class="icon">📋</span> Overview</a></li>
      <li><a href="barbers.php"><span class="icon">✂</span> Manage Barbers</a></li>
      <li><a href="shops.php"><span class="icon">🏪</span> Manage Shops</a></li>
      <li><a href="subscriptions.php" class="active"><span class="icon">💳</span> Subscriptions</a></li>
      <li><a href="appointments.php"><span class="icon">📅</span> All Appointments</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    <h1 style="margin-bottom:8px">Subscription Payments</h1>
    <p class="text-muted" style="margin-bottom:32px">Manually track monthly barber subscription payments</p>

    <div class="grid grid-2" style="gap:32px">
      <!-- Record Payment -->
      <div>
        <h3 style="margin-bottom:16px">Record a Payment</h3>
        <div class="card">
          <div class="card-body">
            <form method="POST">
              <div class="form-group">
                <label class="form-label">Barber *</label>
                <select name="barber_id" class="form-control" required>
                  <option value="">— Select Barber —</option>
                  <?php foreach($barbers as $b): ?>
                    <option value="<?=$b['id']?>" <?=$targetBarber&&$targetBarber['id']==$b['id']?'selected':''?>>
                      <?=e($b['name'])?> — <?=e($b['shop_name'])?>
                      <?=$b['subscription_status']==='unpaid'?' ⚠ UNPAID':''?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Amount ($) *</label>
                <input type="number" name="amount" step="0.01" min="0" class="form-control" required placeholder="e.g. 49.99"></div>
              <div class="form-group"><label class="form-label">Period (Month) *</label>
                <input type="month" name="period_month" class="form-control" required value="<?=date('Y-m')?>"></div>
              <div class="form-group"><label class="form-label">Date Paid *</label>
                <input type="date" name="paid_at" class="form-control" required value="<?=date('Y-m-d')?>"></div>
              <div class="form-group"><label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Cash payment, bank transfer…"></textarea></div>
              <button type="submit" class="btn btn-success btn-full">✓ Record Payment</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Status Overview -->
      <div>
        <h3 style="margin-bottom:16px">Payment Status</h3>
        <?php foreach($barbers as $b): ?>
        <div class="flex-between" style="padding:12px 16px;background:var(--white);border:1px solid var(--border);border-radius:10px;margin-bottom:6px;border-left:3px solid var(--<?=$b['subscription_status']==='paid'?'success':($b['subscription_status']==='trial'?'warning':'danger')?>)">
          <div>
            <div style="font-weight:600;font-size:14px"><?=e($b['name'])?></div>
            <div class="text-small text-muted"><?=e($b['shop_name'])?></div>
            <?php if($b['last_paid']): ?><div class="text-small text-muted">Last paid: <?=date('M j, Y',strtotime($b['last_paid']))?></div><?php endif; ?>
            <?php if($b['subscription_due']): ?><div class="text-small text-muted">Due: <?=date('M j, Y',strtotime($b['subscription_due']))?></div><?php endif; ?>
          </div>
          <div style="text-align:right">
            <span class="badge badge-<?=$b['subscription_status']==='paid'?'success':($b['subscription_status']==='trial'?'warning':'danger')?>"><?=ucfirst($b['subscription_status'])?></span>
            <div style="margin-top:6px">
              <?php if($b['subscription_status']==='paid'): ?>
                <a href="?unpaid=<?=$b['id']?>" class="btn btn-sm btn-warning" onclick="return confirm('Mark as unpaid?')">Mark Unpaid</a>
              <?php else: ?>
                <a href="?barber=<?=$b['id']?>#record" class="btn btn-sm btn-success">Record Payment</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="divider"></div>
    <h3 style="margin-bottom:16px">Payment History</h3>
    <?php if(empty($payments)): ?>
      <p class="text-muted text-small">No payments recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date Paid</th><th>Barber</th><th>Period</th><th>Amount</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach($payments as $p): ?>
          <tr>
            <td><?=date('M j, Y',strtotime($p['paid_at']))?></td>
            <td><?=e($p['barber_name'])?></td>
            <td><?=$p['period_month']?></td>
            <td style="font-weight:600">$<?=number_format($p['amount'],2)?></td>
            <td class="text-muted"><?=e($p['notes'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
