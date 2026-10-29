<?php
$pageTitle = 'Book Appointment — BarberBook';
require_once 'includes/config.php';
requireLogin();
require_once 'includes/mailer.php';

$db = getDB();
$userId    = $_SESSION['user_id'];
$barberId  = (int)($_GET['barber'] ?? 0);
$shopId    = (int)($_GET['shop'] ?? 0);
$isGeneral = !$barberId && $shopId;

// Load barber(s)
if ($barberId) {
    $barberStmt = $db->prepare('SELECT b.*, s.name as shop_name, s.id as shop_id FROM barbers b JOIN shops s ON s.id=b.shop_id WHERE b.id=? AND b.is_active=1');
    $barberStmt->execute([$barberId]);
    $barber = $barberStmt->fetch();
    if (!$barber) { setFlash('danger','Barber not found.'); header('Location: '.SITE_URL.'/client/dashboard.php'); exit; }
    $barbers = [$barber];
    $shopId  = $barber['shop_id'];
} else {
    $bStmt = $db->prepare('SELECT b.*, s.name as shop_name FROM barbers b JOIN shops s ON s.id=b.shop_id WHERE b.shop_id=? AND b.is_active=1');
    $bStmt->execute([$shopId]);
    $barbers = $bStmt->fetchAll();
}

// Week navigation
$weekOffset = (int)($_GET['week'] ?? 0);
$weekStart  = new DateTime('monday this week');
$weekStart->modify("+{$weekOffset} weeks");
$weekEnd = clone $weekStart; $weekEnd->modify('+6 days');

// Build schedule for each barber — for each day, generate 30-min slots
function getWeekSlots($db, $barber, $weekStart) {
    $slots = [];
    for ($d = 0; $d < 7; $d++) {
        $day = clone $weekStart; $day->modify("+{$d} days");
        $dow = (int)$day->format('w'); // 0=Sun
        $dateStr = $day->format('Y-m-d');
        
        // Get availability for this DOW
        $avail = $db->prepare('SELECT * FROM availability WHERE barber_id=? AND day_of_week=? AND is_active=1');
        $avail->execute([$barber['id'], $dow]);
        $avails = $avail->fetchAll();
        
        // Get booked slots
        $booked = $db->prepare('SELECT appointment_time, end_time FROM appointments WHERE barber_id=? AND appointment_date=? AND status NOT IN ("cancelled")');
        $booked->execute([$barber['id'], $dateStr]);
        $bookedTimes = $booked->fetchAll();
        
        $daySlots = [];
        foreach ($avails as $a) {
            $cur = strtotime($dateStr.' '.$a['start_time']);
            $end = strtotime($dateStr.' '.$a['end_time']);
            while ($cur + 1800 <= $end) {
                $timeStr = date('H:i', $cur);
                $endStr  = date('H:i', $cur + 1800);
                $isBooked = false;
                foreach ($bookedTimes as $bt) {
                    if ($timeStr >= substr($bt['appointment_time'],0,5) && $timeStr < substr($bt['end_time'],0,5)) {
                        $isBooked = true; break;
                    }
                }
                $isPast = strtotime($dateStr.' '.$timeStr) < time();
                $daySlots[] = ['time'=>$timeStr,'end'=>$endStr,'booked'=>$isBooked,'past'=>$isPast];
                $cur += 1800;
            }
        }
        $slots[$dateStr] = $daySlots;
    }
    return $slots;
}

// If booking submitted
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $bId    = (int)$_POST['barber_id'];
    $date   = $_POST['date'];
    $time   = $_POST['time'];
    $svcId  = $_POST['service_id'] ?: null;
    $notes  = trim($_POST['notes'] ?? '');
    
    // Validate slot is still free
    $check = $db->prepare('SELECT id FROM appointments WHERE barber_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ("cancelled")');
    $check->execute([$bId, $date, $time]);
    if ($check->fetch()) {
        setFlash('danger','That slot was just taken. Please choose another time.');
        header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }
    
    $endTime = date('H:i:s', strtotime($time) + 1800);
    $userStmt = $db->prepare('SELECT * FROM users WHERE id=?');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    
    $barberName = $db->prepare('SELECT b.name, b.email, s.name as shop_name FROM barbers b JOIN shops s ON s.id=b.shop_id WHERE b.id=?');
    $barberName->execute([$bId]);
    $bInfo = $barberName->fetch();
    
    $svcName = 'Haircut';
    if ($svcId) {
        $svcStmt = $db->prepare('SELECT name FROM services WHERE id=?');
        $svcStmt->execute([$svcId]);
        $svc = $svcStmt->fetch();
        $svcName = $svc['name'] ?? 'Haircut';
    }
    
    $apptStmt = $db->prepare('INSERT INTO appointments (user_id,barber_id,service_id,appointment_date,appointment_time,end_time,client_name,client_email,client_phone,notes,status) VALUES (?,?,?,?,?,?,?,?,?,?,"confirmed")');
    $apptStmt->execute([$userId,$bId,$svcId,$date,$time,$endTime,$user['name'],$user['email'],$user['phone'],$notes]);
    $apptId = $db->lastInsertId();
    
    $appt = ['appointment_date'=>$date,'appointment_time'=>$time];
    sendBookingConfirmation($user['email'],$user['name'],$appt,$bInfo['name'],$bInfo['shop_name'],$svcName);
    
    setFlash('success','✓ Appointment booked! A confirmation email has been sent.');
    header('Location: '.SITE_URL.'/client/appointments.php'); exit;
}

// Load services for each barber
$services = [];
foreach ($barbers as $b) {
    $s = $db->prepare('SELECT * FROM services WHERE barber_id=? AND is_active=1');
    $s->execute([$b['id']]);
    $services[$b['id']] = $s->fetchAll();
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';

$days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
?>
<div class="container" style="padding-top:40px;padding-bottom:60px">
  <div class="page-header">
    <a href="shop.php?id=<?=$shopId?>" style="font-size:13px;color:var(--text-light)">← Back to Shop</a>
    <h1 style="margin-top:12px">
      <?=$isGeneral ? 'General Availability' : 'Book '.e($barbers[0]['name'])?>
    </h1>
    <p class="text-muted">Select a date and time below</p>
  </div>

  <!-- Week Navigation -->
  <div class="flex-between mb-3">
    <a href="?<?=http_build_query(array_merge($_GET,['week'=>$weekOffset-1]))?>" class="btn btn-outline btn-sm">← Previous Week</a>
    <span style="font-weight:600">
      <?=$weekStart->format('M j')?> – <?=$weekEnd->format('M j, Y')?>
    </span>
    <a href="?<?=http_build_query(array_merge($_GET,['week'=>$weekOffset+1]))?>" class="btn btn-outline btn-sm">Next Week →</a>
  </div>

  <?php foreach($barbers as $barber): ?>
  <?php if($isGeneral): ?><h3 class="mb-3"><?=e($barber['name'])?></h3><?php endif; ?>
  
  <div class="card mb-3" style="margin-bottom:24px">
    <div class="card-header">
      <strong><?=$isGeneral ? e($barber['name']) : 'Weekly Schedule'?></strong>
      <span class="text-small text-muted" style="margin-left:12px">ID: <?=e($barber['barber_id'])?></span>
    </div>
    <div class="card-body">
      <?php $slots = getWeekSlots($db, $barber, $weekStart); ?>
      
      <div class="schedule-wrap">
        <div class="schedule-header">
          <div></div>
          <?php $today = date('Y-m-d'); ?>
          <?php for($d=0;$d<7;$d++): ?>
            <?php $day=clone $weekStart; $day->modify("+{$d} days"); ?>
            <div class="schedule-day-header <?=$day->format('Y-m-d')===$today?'today':''?>">
              <?=$days[$d]?><br><span style="font-size:16px;font-weight:700"><?=$day->format('j')?></span>
            </div>
          <?php endfor; ?>
        </div>

        <!-- Collect all time labels -->
        <?php
        $allTimes = [];
        foreach($slots as $daySlots) foreach($daySlots as $sl) $allTimes[$sl['time']] = true;
        ksort($allTimes);
        ?>

        <?php if(empty($allTimes)): ?>
          <div class="empty-state" style="padding:40px"><p>No availability set for this week.</p></div>
        <?php else: ?>
        <?php foreach($allTimes as $t=>$_): ?>
        <div class="schedule-row">
          <div class="schedule-time"><?=date('g:i A',strtotime($t))?></div>
          <?php for($d=0;$d<7;$d++): ?>
            <?php $day=clone $weekStart; $day->modify("+{$d} days"); $dateStr=$day->format('Y-m-d'); ?>
            <?php $slot=null; foreach($slots[$dateStr]??[] as $sl) if($sl['time']===$t){$slot=$sl;break;} ?>
            <?php if(!$slot): ?>
              <div class="schedule-slot closed"></div>
            <?php elseif($slot['past']): ?>
              <div class="schedule-slot past" title="Past">—</div>
            <?php elseif($slot['booked']): ?>
              <div class="schedule-slot booked" title="Booked">✕</div>
            <?php else: ?>
              <div class="schedule-slot available" 
                   onclick="selectSlot(this,'<?=$barber['id']?>','<?=$dateStr?>','<?=$t?>')"
                   data-barber="<?=$barber['id']?>" data-date="<?=$dateStr?>" data-time="<?=$t?>"
                   title="<?=date('g:i A',strtotime($t))?>">
                <?=date('g:i',strtotime($t))?>
              </div>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Booking Modal -->
<div class="modal-overlay" id="bookingModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Confirm Booking</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <p id="modalSummary" style="font-weight:500;margin-bottom:20px;padding:14px;background:var(--off-white);border-radius:8px"></p>
    <form method="POST">
      <input type="hidden" name="barber_id" id="modalBarberId">
      <input type="hidden" name="date" id="modalDate">
      <input type="hidden" name="time" id="modalTime">
      <div class="form-group">
        <label class="form-label">Service</label>
        <select name="service_id" id="modalService" class="form-control">
          <option value="">— Select service (optional) —</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Notes for barber</label>
        <textarea name="notes" class="form-control" placeholder="Any special requests…" rows="3"></textarea>
      </div>
      <div class="flex-between">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Confirm Booking</button>
      </div>
    </form>
  </div>
</div>

<script>
const allServices = <?=json_encode($services)?>;

function selectSlot(el, barberId, date, time) {
  // Deselect others for this barber
  document.querySelectorAll('.schedule-slot.selected').forEach(s => s.classList.remove('selected'));
  el.classList.add('selected');
  
  document.getElementById('modalBarberId').value = barberId;
  document.getElementById('modalDate').value = date;
  document.getElementById('modalTime').value = time;
  
  const d = new Date(date+'T00:00:00');
  const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const t = time.split(':');
  const h = parseInt(t[0]), m = t[1];
  const ampm = h >= 12 ? 'PM' : 'AM';
  const h12 = h % 12 || 12;
  document.getElementById('modalSummary').textContent = 
    days[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + d.getDate() + ' at ' + h12 + ':' + m + ' ' + ampm;
  
  const sel = document.getElementById('modalService');
  sel.innerHTML = '<option value="">— Select service (optional) —</option>';
  (allServices[barberId] || []).forEach(svc => {
    sel.innerHTML += `<option value="${svc.id}">${svc.name} — $${parseFloat(svc.price).toFixed(2)} (${svc.duration_minutes}min)</option>`;
  });
  
  document.getElementById('bookingModal').classList.add('open');
}
function closeModal() {
  document.getElementById('bookingModal').classList.remove('open');
  document.querySelectorAll('.schedule-slot.selected').forEach(s => s.classList.remove('selected'));
}
document.getElementById('bookingModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
<?php require_once 'includes/footer.php'; ?>
