<?php
$pageTitle = 'Barbershop — BarberBook';
require_once 'includes/config.php';

$shopId = (int)($_GET['id'] ?? 0);
$general = isset($_GET['general']);
$db = getDB();

$shop = $db->prepare('SELECT * FROM shops WHERE id=?');
$shop->execute([$shopId]);
$shop = $shop->fetch();
if (!$shop) { header('Location: '.SITE_URL); exit; }

$pageTitle = e($shop['name']).' — BarberBook';

$barbers = $db->prepare('
    SELECT b.*, COALESCE(AVG(r.rating),0) as avg_rating, COUNT(r.id) as rcount
    FROM barbers b LEFT JOIN ratings r ON r.barber_id=b.id
    WHERE b.shop_id=? AND b.is_active=1
    GROUP BY b.id
');
$barbers->execute([$shopId]);
$barbers = $barbers->fetchAll();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<div class="container" style="padding-top:40px;padding-bottom:60px">
  <div class="page-header">
    <a href="<?=SITE_URL?>/client/dashboard.php" style="font-size:13px;color:var(--text-light)">← Back to Dashboard</a>
    <h1 style="margin-top:12px"><?=e($shop['name'])?></h1>
    <p class="text-muted"><?=e($shop['address'])?>, <?=e($shop['city'])?> · <?=e($shop['phone'])?></p>
    <?php if($shop['description']): ?>
      <p style="max-width:600px;margin-top:12px"><?=e($shop['description'])?></p>
    <?php endif; ?>
  </div>

  <div class="flex-between mb-3">
    <h2>Barbers</h2>
    <a href="book.php?shop=<?=$shopId?>" class="btn btn-outline">📅 View General Availability</a>
  </div>

  <?php if(empty($barbers)): ?>
    <div class="empty-state"><div class="icon">✂</div><p>No active barbers at this shop.</p></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach($barbers as $b): ?>
    <div class="barber-card">
      <div class="barber-avatar" style="width:64px;height:64px;font-size:26px">✂</div>
      <div class="barber-info">
        <div class="barber-name" style="font-size:17px"><?=e($b['name'])?></div>
        <div class="barber-id">ID: <?=e($b['barber_id'])?></div>
        <?php if($b['specialties']): ?><div class="text-small text-muted"><?=e($b['specialties'])?></div><?php endif; ?>
        <?php if($b['bio']): ?><div class="text-small" style="margin-top:4px"><?=e($b['bio'])?></div><?php endif; ?>
        <div class="flex-center gap-2 mt-1">
          <span class="stars"><?=str_repeat('★',round($b['avg_rating']))?><?=str_repeat('☆',5-round($b['avg_rating']))?></span>
          <span class="rating-count"><?=number_format($b['avg_rating'],1)?> (<?=$b['rcount']?> reviews)</span>
        </div>
      </div>
      <div class="barber-actions" style="flex-direction:column;gap:8px">
        <a href="book.php?barber=<?=$b['id']?>" class="btn btn-primary btn-sm">Book <?=e($b['name'])?></a>
        <a href="barber-profile.php?id=<?=$b['id']?>" class="btn btn-outline btn-sm">View Profile</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
