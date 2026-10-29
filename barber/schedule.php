<?php
$pageTitle = 'My Schedule — BarberBook';
require_once '../includes/config.php';
requireBarber();
require_once '../includes/mailer.php';
$db = getDB();
$bid = $_SESSION['barber_id'];

$barber = $db->prepare('SELECT b.*, s.name as shop_name FROM barbers b JOIN shops s ON s.id=b.shop_id WHERE b.id=?');
$barber->execute([$bid]); $barber = $barber->fetch();

$days     = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$dayNums  = [1,2,3,4,5,6,0]; // PHP date('w'): 0=Sun,1=Mon...

// ── AJAX: toggle a slot open/closed ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_toggle'])) {
    header('Content-Type: application/json');
    $dow      = (int)$_POST['dow'];   // 0-6 (0=Sun)
    $slotTime = $_POST['slot'];       // "09:00"
    $slotEnd  = date('H:i', strtotime($slotTime) + 1800);

    // Check if there's a booked appointment at this slot on any future date with this DOW
    $booked = $db->prepare("
        SELECT id FROM appointments 
        WHERE barber_id=? AND DAYOFWEEK(appointment_date)=? AND appointment_time=? AND status NOT IN ('cancelled') AND appointment_date >= CURDATE()
    ");
    $booked->execute([$bid, $dow == 0 ? 1 : $dow + 1, $slotTime.':00']);
    if ($booked->fetch()) {
        echo json_encode(['error' => 'This slot has an existing booking. Cancel or reschedule it first.']);
        exit;
    }

    // Check if slot is currently open (exists in availability)
    $exists = $db->prepare("SELECT id FROM availability WHERE barber_id=? AND day_of_week=? AND start_time<=? AND end_time>? AND is_active=1");
    $exists->execute([$bid, $dow, $slotTime.':00', $slotTime.':00']);
    $avail = $exists->fetch();

    if ($avail) {
        // Slot is open → close it by removing/splitting availability
        // Find the availability block this slot belongs to
        $block = $db->prepare("SELECT * FROM availability WHERE barber_id=? AND day_of_week=? AND start_time<=? AND end_time>? AND is_active=1");
        $block->execute([$bid, $dow, $slotTime.':00', $slotTime.':00']);
        $block = $block->fetch();

        // Delete this block
        $db->prepare("DELETE FROM availability WHERE id=?")->execute([$block['id']]);

        // Re-insert the parts before and after the removed slot
        if ($block['start_time'] < $slotTime.':00') {
            $db->prepare("INSERT INTO availability (barber_id,day_of_week,start_time,end_time) VALUES (?,?,?,?)")
               ->execute([$bid, $dow, $block['start_time'], $slotTime.':00']);
        }
        if ($slotEnd.':00' < $block['end_time']) {
            $db->prepare("INSERT INTO availability (barber_id,day_of_week,start_time,end_time) VALUES (?,?,?,?)")
               ->execute([$bid, $dow, $slotEnd.':00', $block['end_time']]);
        }
        echo json_encode(['status' => 'closed']);
    } else {
        // Slot is closed → open it by inserting availability
        $db->prepare("INSERT INTO availability (barber_id,day_of_week,start_time,end_time) VALUES (?,?,?,?)")
           ->execute([$bid, $dow, $slotTime.':00', $slotEnd.':00']);
        // Merge adjacent blocks
        $db->prepare("
            UPDATE availability a1
            JOIN availability a2 ON a1.barber_id=a2.barber_id AND a1.day_of_week=a2.day_of_week AND a1.end_time=a2.start_time
            SET a1.end_time=a2.end_time
            WHERE a1.barber_id=? AND a1.day_of_week=?
        ")->execute([$bid, $dow]);
        echo json_encode(['status' => 'open']);
    }
    exit;
}

// ── AJAX: reschedule from schedule page ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_reschedule'])) {
    header('Content-Type: application/json');
    $apptId  = (int)$_POST['appt_id'];
    $newDate = $_POST['new_date'];
    $newTime = $_POST['new_time'];
    $a = $db->prepare('SELECT * FROM appointments WHERE id=? AND barber_id=?');
    $a->execute([$apptId, $bid]); $appt = $a->fetch();
    if ($appt && $newDate && $newTime) {
        $token = bin2hex(random_bytes(16));
        $db->prepare("UPDATE appointments SET status='reschedule_pending', reschedule_date=?, reschedule_time=?, reschedule_token=? WHERE id=?")
           ->execute([$newDate, $newTime, $token, $apptId]);
        sendRescheduleRequest($appt['client_email'], $appt['client_name'], $newDate, $newTime, $token, $barber['name']);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid data.']);
    }
    exit;
}

// ── AJAX: cancel from schedule page ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_cancel'])) {
    header('Content-Type: application/json');
    $apptId = (int)$_POST['appt_id'];
    $a = $db->prepare('SELECT * FROM appointments WHERE id=? AND barber_id=?');
    $a->execute([$apptId, $bid]); $appt = $a->fetch();
    if ($appt) {
        $db->prepare("UPDATE appointments SET status='cancelled' WHERE id=?")->execute([$apptId]);
        sendCancellationEmail($appt['client_email'], $appt['client_name'], $appt['appointment_date'], $appt['appointment_time'], $barber['name']);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Not found.']);
    }
    exit;
}

// ── Build slot data for selected week ────────────────────────────────────────
$weekOffset = (int)($_GET['week'] ?? 0);
$selectedDay = $_GET['day'] ?? null; // 0-6 index into $days array

// Get all availability for this barber
$availRows = $db->prepare("SELECT * FROM availability WHERE barber_id=? AND is_active=1");
$availRows->execute([$bid]);
$availRows = $availRows->fetchAll();

// Get all appointments for next 4 weeks
$appts = $db->prepare("
    SELECT a.*, u.name as client_name, u.phone as client_phone, sv.name as service_name
    FROM appointments a 
    LEFT JOIN users u ON u.id=a.user_id 
    LEFT JOIN services sv ON sv.id=a.service_id
    WHERE a.barber_id=? AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 28 DAY)
    AND a.status NOT IN ('cancelled')
    ORDER BY a.appointment_date, a.appointment_time
");
$appts->execute([$bid]);
$appts = $appts->fetchAll();

// Index appointments by date+time
$apptIndex = [];
foreach ($appts as $a) {
    $key = $a['appointment_date'].'_'.substr($a['appointment_time'],0,5);
    $apptIndex[$key] = $a;
}

// Build week dates
$weekStart = new DateTime('monday this week');
$weekStart->modify("+{$weekOffset} weeks");
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $weekStart;
    $d->modify("+{$i} days");
    $weekDates[] = $d->format('Y-m-d');
}
$weekEnd = new DateTime(end($weekDates));

// Build availability lookup by DOW
$availByDow = [];
foreach ($availRows as $a) {
    $availByDow[$a['day_of_week']][] = $a;
}

// Time slots from 6:00 to 23:30
$timeSlots = [];
for ($h = 6; $h < 24; $h++) {
    $timeSlots[] = sprintf('%02d:00', $h);
    $timeSlots[] = sprintf('%02d:30', $h);
}

// Check if a time is within availability for a given DOW
function isAvailable($dow, $time, $availByDow) {
    if (!isset($availByDow[$dow])) return false;
    $t = $time.':00';
    $tEnd = date('H:i:s', strtotime($t) + 1800);
    foreach ($availByDow[$dow] as $a) {
        if ($a['start_time'] <= $t && $a['end_time'] >= $tEnd) return true;
    }
    return false;
}

require_once '../includes/header.php';
?>
<nav class="navbar">
  <a href="dashboard.php" class="navbar-brand">✂ <span>Barber</span>Book</a>
  <ul class="navbar-nav">
    <li><span style="font-size:13px;color:var(--muted)"><?=e($barber['name'])?></span></li>
    <li><a href="logout.php" class="btn btn-outline btn-sm">Logout</a></li>
  </ul>
</nav>

<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?=strtoupper(substr($barber['name'],0,1))?></div>
      <div class="sidebar-name"><?=e($barber['name'])?></div>
      <div class="sidebar-role">Barber · <?=e($barber['shop_name'])?></div>
    </div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><span class="icon">📋</span> Overview</a></li>
      <li><a href="appointments.php"><span class="icon">📅</span> All Appointments</a></li>
      <li><a href="schedule.php" class="active"><span class="icon">🕐</span> My Schedule</a></li>
      <li><a href="services.php"><span class="icon">✂</span> Services & Pricing</a></li>
      <li><a href="manual-booking.php"><span class="icon">➕</span> Add Appointment</a></li>
      <li><a href="profile.php"><span class="icon">👤</span> My Profile</a></li>
    </ul>
  </aside>

  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>

    <div class="flex-between" style="margin-bottom:24px;flex-wrap:wrap;gap:12px">
      <div>
        <h1 style="margin-bottom:4px">My Schedule</h1>
        <p class="text-muted text-small">View and manage your weekly availability</p>
      </div>
      <div class="flex-center gap-2">
        <a href="?week=<?=$weekOffset-1?>" class="btn btn-outline btn-sm">← Prev Week</a>
        <span style="font-weight:600;font-size:14px"><?=$weekStart->format('M j')?> – <?=$weekEnd->format('M j, Y')?></span>
        <a href="?week=<?=$weekOffset+1?>" class="btn btn-outline btn-sm">Next Week →</a>
      </div>
    </div>

    <!-- Legend -->
    <div class="flex-center gap-2" style="margin-bottom:20px;flex-wrap:wrap">
      <div class="flex-center gap-2"><span style="width:16px;height:16px;background:#e8f5e9;border:2px solid #2d6a4f;border-radius:4px;display:inline-block"></span><span class="text-small">Open (bookable)</span></div>
      <div class="flex-center gap-2"><span style="width:16px;height:16px;background:#fff3cd;border:2px solid #e67e22;border-radius:4px;display:inline-block"></span><span class="text-small">Booked appointment</span></div>
      <div class="flex-center gap-2"><span style="width:16px;height:16px;background:#fdecea;border:2px solid #c0392b;border-radius:4px;display:inline-block"></span><span class="text-small">Closed / unavailable</span></div>
    </div>

    <!-- Day tabs -->
    <div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
      <?php foreach($days as $i=>$d): ?>
      <?php $date = $weekDates[$i]; $isToday = $date===date('Y-m-d'); ?>
      <button onclick="showDay(<?=$i?>)" id="tab-<?=$i?>"
        class="btn btn-sm <?=$i===0?'btn-primary':'btn-outline'?>"
        style="<?=$isToday?'font-weight:700':''?>">
        <?=$d?><br><span style="font-size:10px;opacity:0.7"><?=date('M j',strtotime($date))?></span>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Day panels -->
    <?php foreach($days as $i=>$d): ?>
    <?php 
      $date    = $weekDates[$i];
      $dow     = (int)date('w', strtotime($date)); // 0=Sun
      $isPast  = $date < date('Y-m-d');
    ?>
    <div id="day-panel-<?=$i?>" style="display:<?=$i===0?'block':'none'?>">
      
      <div class="flex-between" style="margin-bottom:16px">
        <h2 style="margin:0"><?=$d?>, <?=date('F j',strtotime($date))?></h2>
        <div class="flex-center gap-2">
          <button onclick="setMode(<?=$i?>,'view')" id="btn-view-<?=$i?>" class="btn btn-sm btn-primary">👁 Show</button>
          <button onclick="setMode(<?=$i?>,'edit')" id="btn-edit-<?=$i?>" class="btn btn-sm btn-outline">✏️ Edit</button>
          <button onclick="setMode(<?=$i?>,'add')" id="btn-add-<?=$i?>" class="btn btn-sm btn-outline">➕ Add</button></div>
      </div>

      <div id="edit-hint-<?=$i?>" style="display:none" class="alert alert-info" style="margin-bottom:12px">
        ✏️ <strong>Edit mode:</strong> Click a <span style="color:#2d6a4f;font-weight:600">green slot</span> to close it (make unavailable). Click a <span style="color:#c0392b;font-weight:600">red slot</span> to open it. <span style="color:#e67e22;font-weight:600">Yellow slots</span> have bookings — cancel or reschedule them first.
      </div>

      <?php if($isPast): ?>
        <div class="empty-state" style="border:1px solid var(--border);border-radius:12px;padding:32px">
          <div class="icon">📅</div><p>This day is in the past.</p>
        </div>
      <?php else: ?>
      <div style="overflow-x:auto">
        <div style="min-width:320px">
          <?php 
          $hasAnySlot = false;
          foreach($timeSlots as $t):
            $avail   = isAvailable($dow, $t, $availByDow);
            $apptKey = $date.'_'.$t;
            $appt    = $apptIndex[$apptKey] ?? null;
            $hasAnySlot = true;

            if ($avail) {
              $color = '#e8f5e9'; $border = '#2d6a4f'; $label = 'Open';
              $textColor = '#2d6a4f';
              if ($appt) {
                $color = '#fff3cd'; $border = '#e67e22'; $label = ''; $textColor = '#b45309';
              }
            } else {
              $color = '#fdecea'; $border = '#e0ccc9'; $label = 'Closed';
              $textColor = '#c0392b';
            }
          ?>
          <div class="sched-slot" 
               data-dow="<?=$dow?>" 
               data-time="<?=$t?>"
               data-date="<?=$date?>"
               data-has-appt="<?=$appt?'1':'0'?>"
               data-appt-id="<?=$appt?$appt['id']:''?>"
               data-appt-client="<?=$appt?e($appt['client_name']):''?>"
               data-appt-phone="<?=$appt?e($appt['client_phone']??''):''?>"
               data-appt-service="<?=$appt?e($appt['service_name']??'General'):''?>"
               data-avail="<?=$avail?'1':'0'?>"
               style="display:flex;align-items:center;gap:12px;padding:8px 12px;margin-bottom:4px;border-radius:8px;border:2px solid <?=$border?>;background:<?=$color?>;transition:all 0.15s ease;cursor:default">
            
            <!-- Time label -->
            <div style="min-width:70px;font-size:13px;font-weight:600;color:var(--text-light)">
              <?=date('g:i A',strtotime($t))?>
            </div>

            <!-- Status bar -->
            <div style="flex:1">
              <?php if($appt): ?>
                <div style="font-weight:600;font-size:13px;color:<?=$textColor?>">
                  📋 <?=e($appt['client_name'] ?? 'Walk-in')?>
                </div>
                <div style="font-size:11px;color:var(--text-light)">
                  <?=e($appt['service_name'] ?? 'General')?><?=$appt['client_phone']?' · '.e($appt['client_phone']):''?>
                </div>
              <?php else: ?>
                <div style="font-size:12px;color:<?=$textColor?>;font-weight:500"><?=$label?></div>
              <?php endif; ?>
            </div>

            <!-- Action buttons (shown in edit mode or for appointments) -->
            <div class="slot-actions" style="display:flex;gap:6px;align-items:center">
              <?php if($appt): ?>
                <button class="btn btn-warning btn-sm appt-reschedule-btn" 
                        data-appt-id="<?=$appt['id']?>"
                        data-client="<?=e($appt['client_name'])?>"
                        data-date="<?=$date?>"
                        data-time="<?=$t?>"
                        style="display:none">
                  Reschedule
                </button>
                <button class="btn btn-danger btn-sm appt-cancel-btn"
                        data-appt-id="<?=$appt['id']?>"
                        data-client="<?=e($appt['client_name'])?>"
                        style="display:none">
                  Cancel
                </button>
              <?php else: ?>
                <button class="btn btn-sm toggle-slot-btn"
                        data-dow="<?=$dow?>"
                        data-time="<?=$t?>"
                        data-avail="<?=$avail?'1':'0'?>"
                        style="display:none;background:<?=$avail?'#fdecea':'#e8f5e9'?>;color:<?=$avail?'#c0392b':'#2d6a4f'?>;border:1px solid <?=$avail?'#c0392b':'#2d6a4f'?>">
                  <?=$avail?'🔴 Close':'🟢 Open'?>
                </button>
              <?php endif; ?>
              <button class="btn btn-sm add-slot-btn"
        data-date="<?=$date?>"
        data-time="<?=$t?>"
        onclick="openAddModal('<?=$date?>','<?=$t?>')"
        style="display:none;background:#e3f2fd;color:#1565c0;border:1px solid #1565c0">
  ➕ Add
</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Add Appointment Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Add Appointment</h3>
      <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">✕</button>
    </div>
    <p class="text-small text-muted" style="margin-bottom:16px" id="addModalTitle"></p>
    <form method="POST" action="manual-booking.php">
      <input type="hidden" name="date" id="addDate">
      <input type="hidden" name="time" id="addTime">
      <div class="form-group">
        <label class="form-label">Client Name *</label>
        <input type="text" name="client_name" class="form-control" required placeholder="John Smith">
      </div>
      <div class="form-group">
        <label class="form-label">Client Email *</label>
        <input type="email" name="client_email" class="form-control" required placeholder="client@email.com">
      </div>
      <div class="form-group">
        <label class="form-label">Client Phone</label>
        <input type="tel" name="client_phone" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"></textarea>
      </div>
      <div class="flex-between">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Appointment</button>
      </div>
    </form>
  </div>
</div>

<!-- Reschedule Modal -->
<div class="modal-overlay" id="rescheduleModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Reschedule Appointment</h3>
      <button class="modal-close" onclick="document.getElementById('rescheduleModal').classList.remove('open')">✕</button>
    </div>
    <p class="text-small text-muted" style="margin-bottom:16px">Client: <strong id="rClient"></strong></p>
    <div class="alert alert-info" style="margin-bottom:16px">The client will receive an email to accept or decline.</div>
    <div class="form-group">
      <label class="form-label">New Date</label>
      <input type="date" id="rNewDate" class="form-control" min="<?=date('Y-m-d')?>">
    </div>
    <div class="form-group">
      <label class="form-label">New Time</label>
      <input type="time" id="rNewTime" class="form-control" step="1800">
    </div>
    <div class="flex-between">
      <button class="btn btn-outline" onclick="document.getElementById('rescheduleModal').classList.remove('open')">Cancel</button>
      <button class="btn btn-warning" onclick="submitReschedule()">Send Request</button>
    </div>
  </div>
</div>

<script>
let currentRescheduleId = null;
let editModes = {};

function showDay(i) {
  for (let j = 0; j < 7; j++) {
    document.getElementById('day-panel-'+j).style.display = j===i ? 'block' : 'none';
    document.getElementById('tab-'+j).className = 'btn btn-sm ' + (j===i ? 'btn-primary' : 'btn-outline');
  }
}

function setMode(dayIdx, mode) {
  editModes[dayIdx] = mode;
  const isEdit = (mode === 'edit');
  const isAdd  = (mode === 'add');
  const btnView = document.getElementById('btn-view-'+dayIdx);
  const btnEdit = document.getElementById('btn-edit-'+dayIdx);
  const btnAdd  = document.getElementById('btn-add-'+dayIdx);
  const hint    = document.getElementById('edit-hint-'+dayIdx);
  if (btnView) btnView.className = 'btn btn-sm ' + ((!isEdit && !isAdd) ? 'btn-primary' : 'btn-outline');
  if (btnEdit) btnEdit.className = 'btn btn-sm ' + (isEdit ? 'btn-primary' : 'btn-outline');
  if (btnAdd)  btnAdd.className  = 'btn btn-sm ' + (isAdd  ? 'btn-primary' : 'btn-outline');
  if (hint) hint.style.display = isEdit ? 'block' : 'none';
  const panel = document.getElementById('day-panel-'+dayIdx);
  if (!panel) return;
  panel.querySelectorAll('.toggle-slot-btn, .appt-reschedule-btn, .appt-cancel-btn').forEach(function(btn) {
    btn.style.display = isEdit ? 'inline-flex' : 'none';
  });
  panel.querySelectorAll('.add-slot-btn').forEach(function(btn) {
    btn.style.display = isAdd ? 'inline-flex' : 'none';
  });
}

document.addEventListener('click', function(e) {
  const toggleBtn = e.target.closest('.toggle-slot-btn');
  if (toggleBtn) {
    const dow  = toggleBtn.dataset.dow;
    const time = toggleBtn.dataset.time;
    const avail = toggleBtn.dataset.avail === '1';
    toggleBtn.disabled = true;
    toggleBtn.textContent = '...';
    fetch('schedule.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'ajax_toggle=1&dow='+dow+'&slot='+encodeURIComponent(time)
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.error) { alert(data.error); toggleBtn.disabled=false; toggleBtn.textContent=avail?'🔴 Close':'🟢 Open'; return; }
      location.reload();
    });
    return;
  }

  const cancelBtn = e.target.closest('.appt-cancel-btn');
  if (cancelBtn) {
    if (!confirm('Cancel appointment for ' + cancelBtn.dataset.client + '? They will be notified by email.')) return;
    cancelBtn.disabled = true;
    fetch('schedule.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'ajax_cancel=1&appt_id='+cancelBtn.dataset.apptId
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.error) { alert(data.error); cancelBtn.disabled=false; return; }
      location.reload();
    });
    return;
  }

  const reschedBtn = e.target.closest('.appt-reschedule-btn');
  if (reschedBtn) {
    currentRescheduleId = reschedBtn.dataset.apptId;
    document.getElementById('rClient').textContent = reschedBtn.dataset.client;
    document.getElementById('rNewDate').value = '';
    document.getElementById('rNewTime').value = '';
    document.getElementById('rescheduleModal').classList.add('open');
    return;
  }
});

function submitReschedule() {
  const date = document.getElementById('rNewDate').value;
  const time = document.getElementById('rNewTime').value;
  if (!date || !time) { alert('Please select a date and time.'); return; }
  fetch('schedule.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'ajax_reschedule=1&appt_id='+currentRescheduleId+'&new_date='+date+'&new_time='+encodeURIComponent(time)
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    if (data.error) { alert(data.error); return; }
    alert('Reschedule request sent to client!');
    document.getElementById('rescheduleModal').classList.remove('open');
    location.reload();
  });
}

function openAddModal(date, time) {
  document.getElementById('addDate').value = date;
  document.getElementById('addTime').value = time;
  const d = new Date(date+'T00:00:00');
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const hr = parseInt(time.split(':')[0]);
  const min = time.split(':')[1];
  const ampm = hr >= 12 ? 'PM' : 'AM';
  const hr12 = (hr % 12) || 12;
  document.getElementById('addModalTitle').textContent = dayNames[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + d.getDate() + ' at ' + hr12 + ':' + min + ' ' + ampm;
  document.getElementById('addModal').classList.add('open');
}
</script>

<?php require_once '../includes/footer.php'; ?>
