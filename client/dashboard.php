<?php
$pageTitle = 'Dashboard — BarberBook';
require_once '../includes/config.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Save location if AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lat'])) {
    $lat = (float)$_POST['lat']; $lon = (float)$_POST['lon'];
    $db->prepare('UPDATE users SET latitude=?, longitude=? WHERE id=?')->execute([$lat, $lon, $userId]);
    echo 'ok'; exit;
}

$userStmt = $db->prepare('SELECT * FROM users WHERE id=?');
$userStmt->execute([$userId]);
$me = $userStmt->fetch();
$userLat = $me['latitude']; $userLon = $me['longitude'];

$shops = $db->query('
    SELECT s.*, COALESCE(AVG(r.rating),0) as avg_rating, COUNT(DISTINCT r.id) as rating_count
    FROM shops s
    LEFT JOIN barbers b ON b.shop_id=s.id AND b.is_active=1
    LEFT JOIN ratings r ON r.barber_id=b.id
    GROUP BY s.id
')->fetchAll();

foreach ($shops as &$shop) {
    $shop['distance'] = ($userLat && $userLon)
        ? haversineDistance($userLat, $userLon, $shop['latitude'], $shop['longitude'])
        : null;
}
unset($shop);
usort($shops, fn($a,$b) => ($a['distance'] ?? 99999) <=> ($b['distance'] ?? 99999));

$nearbyShops = array_filter($shops, fn($s) => $s['distance'] === null || $s['distance'] <= SEARCH_RADIUS_KM);
$topRated = array_filter($shops, fn($s) => ($s['distance']===null || $s['distance']<=SEARCH_RADIUS_KM) && $s['avg_rating']>0);
usort($topRated, fn($a,$b) => $b['avg_rating'] <=> $a['avg_rating']);
$topRated = array_slice(array_values($topRated), 0, TOP_RATED_COUNT);

$searchResults = null;
$q = trim($_GET['q'] ?? '');
if ($q) {
    $s = $db->prepare('
        SELECT s.*, b.id as bid, b.barber_id, b.name as bname, b.specialties,
               COALESCE(AVG(r.rating),0) as avg_rating, COUNT(r.id) as rcount
        FROM shops s
        LEFT JOIN barbers b ON b.shop_id=s.id AND b.is_active=1
        LEFT JOIN ratings r ON r.barber_id=b.id
        WHERE s.name LIKE ? OR b.name LIKE ? OR b.barber_id=?
        GROUP BY s.id, b.id ORDER BY s.name
    ');
    $s->execute(["%$q%","%$q%",$q]);
    $searchResults = $s->fetchAll();
}
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>
<div class="container" style="padding-top:40px;padding-bottom:60px">

<?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>

<?php if(!$userLat): ?>
<div class="alert alert-info">📍 <strong>Enable location</strong> to see barbershops near you.
  <button class="btn btn-primary btn-sm" style="margin-left:12px" onclick="requestLocation()">Allow Location</button>
</div>
<?php endif; ?>

<?php if(!$q): ?>
<section class="section">
  <div class="section-label">📍 Nearest Barbershops</div>
  <h2 class="mb-3"><?=$userLat?'Shops Near You':'All Barbershops'?></h2>
  <?php if(empty($nearbyShops)): ?>
    <div class="empty-state"><div class="icon">✂</div><p>No barbershops found.</p></div>
  <?php else: ?>
  <div class="grid grid-3">
    <?php foreach($nearbyShops as $shop): ?>
    <a href="../shop.php?id=<?=$shop['id']?>" style="text-decoration:none;color:inherit">
      <div class="card shop-card">
        <div class="shop-card-img">🏪</div>
        <?php if($shop['distance']!==null): ?>
          <span class="shop-card-distance"><?=number_format($shop['distance'],1)?> km</span>
        <?php endif; ?>
        <div class="shop-card-body">
          <div class="shop-card-name"><?=e($shop['name'])?></div>
          <div class="shop-card-meta"><?=e($shop['address'])?>, <?=e($shop['city'])?></div>
          <div class="flex-center gap-2 mt-1">
            <span class="stars"><?=str_repeat('★',round($shop['avg_rating']))?><?=str_repeat('☆',5-round($shop['avg_rating']))?></span>
            <span class="rating-count">(<?=$shop['rating_count']?>)</span>
          </div>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if(!empty($topRated)): ?>
<section class="section">
  <div class="section-label">⭐ Top Rated Within <?=SEARCH_RADIUS_KM?>km</div>
  <h2 class="mb-3">Best Barbershops Near You</h2>
  <div class="grid grid-3">
    <?php foreach($topRated as $i=>$shop): ?>
    <a href="../shop.php?id=<?=$shop['id']?>" style="text-decoration:none;color:inherit">
      <div class="card" style="border-top:3px solid var(--dark)">
        <div class="card-body">
          <div class="flex-between mb-3">
            <span style="font-size:24px;font-family:'Playfair Display',serif;font-weight:700;color:var(--muted)">#<?=$i+1?></span>
            <span class="stars"><?=number_format($shop['avg_rating'],1)?> ★</span>
          </div>
          <div class="shop-card-name"><?=e($shop['name'])?></div>
          <div class="shop-card-meta"><?=e($shop['address'])?></div>
          <div class="shop-card-meta mt-1"><?=$shop['rating_count']?> reviews</div>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>

<section class="section" id="search">
  <div class="section-label">🔍 Search</div>
  <h2 class="mb-3">Find Your Barber</h2>
  <form method="GET">
    <div class="search-wrap mb-3">
      <span class="search-icon">🔍</span>
      <input type="text" name="q" class="search-input" placeholder="Shop name, barber name, or 8-digit barber ID…" value="<?=e($q)?>">
      <button type="submit" class="btn btn-primary search-btn">Search</button>
    </div>
  </form>
  <p class="text-center text-small text-muted">Search by shop name, barber name, or 8-digit barber ID</p>
</section>

<?php if($searchResults!==null): ?>
<section class="section">
  <div class="section-label">Results for "<?=e($q)?>"</div>
  <?php
  $grouped=[];
  foreach($searchResults as $row){
    $grouped[$row['id']]['shop']=$row;
    if($row['bid']) $grouped[$row['id']]['barbers'][]=$row;
  }
  ?>
  <?php if(empty($grouped)): ?>
    <div class="empty-state"><div class="icon">🔍</div><p>No results found for "<?=e($q)?>".</p></div>
  <?php else: ?>
    <?php foreach($grouped as $sid=>$data): ?>
    <?php $shop=$data['shop']; $barbers=$data['barbers']??[]; ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header flex-between">
        <div>
          <h3 style="margin:0"><?=e($shop['name'])?></h3>
          <div class="text-small text-muted"><?=e($shop['address'])?>, <?=e($shop['city'])?></div>
        </div>
        <a href="../shop.php?id=<?=$shop['id']?>" class="btn btn-outline btn-sm">View Shop</a>
      </div>
      <div class="card-body">
        <?php if(empty($barbers)): ?>
          <p class="text-muted text-small">No active barbers.</p>
        <?php else: ?>
          <div class="section-label" style="margin-bottom:12px">Barbers at this shop</div>
          <?php foreach($barbers as $b): ?>
          <div class="barber-card" style="margin-bottom:10px">
            <div class="barber-avatar">✂</div>
            <div class="barber-info">
              <div class="barber-name"><?=e($b['bname'])?></div>
              <div class="barber-id">ID: <?=e($b['barber_id'])?></div>
              <div class="text-small text-muted"><?=e($b['specialties'])?></div>
              <div class="flex-center gap-2 mt-1">
                <span class="stars" style="font-size:11px"><?=str_repeat('★',round($b['avg_rating']))?></span>
                <span class="rating-count">(<?=$b['rcount']?>)</span>
              </div>
            </div>
            <div class="barber-actions">
              <a href="../book.php?barber=<?=$b['bid']?>" class="btn btn-primary btn-sm">Book</a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <div class="mt-2">
          <a href="../shop.php?id=<?=$shop['id']?>&general=1" class="btn btn-outline btn-sm">📅 General Availability</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

</div>
<script>
function requestLocation(){
  if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(function(p){
      fetch('dashboard.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'lat='+p.coords.latitude+'&lon='+p.coords.longitude}).then(()=>location.reload());
    });
  }
}
<?php if(!$userLat): ?>setTimeout(requestLocation,500);<?php endif; ?>
</script>
<?php require_once '../includes/footer.php'; ?>
