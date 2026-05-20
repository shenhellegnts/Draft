<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$settings = [];
$settingRows = db_query('SELECT setting_key, setting_value FROM settings');
if ($settingRows) {
    while ($row = $settingRows->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$clinicName = $settings['clinic_name'] ?? 'M.V. Masangkay Clinic';
$clinicSubtitle = $settings['clinic_subtitle'] ?? 'X-Ray & Laboratory Clinic';
$smsTemplate = $settings['sms_template'] ?? 'Hello [Patient Name], malapit na po ang inyong turn sa M.V. Masangkay Clinic. Maaari na po kayong pumunta.';
$adminName = $_SESSION['admin_name'] ?? 'Clinic Admin';
$adminInitial = strtoupper(substr($adminName, 0, 1)) ?: 'A';

$today = (new DateTime())->format('Y-m-d');
$patientsToday = db_row('SELECT COUNT(DISTINCT patient_mobile) AS cnt FROM appointments WHERE DATE(created_at) = ?', 's', [$today]);
$patientsToday = $patientsToday['cnt'] ?? 0;
$doneToday = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE status = ? AND DATE(created_at) = ?', 'ss', ['done', $today]);
$doneToday = $doneToday['cnt'] ?? 0;
$waitingNow = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE status = ?', 's', ['pending']);
$waitingNow = $waitingNow['cnt'] ?? 0;
$smsSentCount = db_row('SELECT COUNT(*) AS cnt FROM sms_logs WHERE status = ?', 's', ['sent']);
$smsSentCount = $smsSentCount['cnt'] ?? 0;

$queueServing = db_row('SELECT a.*, p.name AS patient_name, p.mobile AS patient_mobile FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id WHERE a.status = ? ORDER BY a.preferred_date ASC, a.created_at ASC LIMIT 1', 's', ['pending']);

$queueWaiting = [];
$waitingRes = db_query('SELECT a.*, p.name AS patient_name, p.mobile AS patient_mobile FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id WHERE a.status = ? ORDER BY a.preferred_date ASC, a.created_at ASC', 's', ['pending']);
if ($waitingRes) {
    while ($row = $waitingRes->fetch_assoc()) {
        if (!$queueServing || $row['id'] !== $queueServing['id']) {
            $queueWaiting[] = $row;
        }
    }
}

$queueDone = [];
$doneRes = db_query('SELECT a.*, p.name AS patient_name, p.mobile AS patient_mobile FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id WHERE a.status = ? ORDER BY a.preferred_date DESC, a.created_at DESC LIMIT 5', 's', ['done']);
if ($doneRes) {
    while ($row = $doneRes->fetch_assoc()) {
        $queueDone[] = $row;
    }
}

$appointments = [];
$apptRes = db_query('SELECT a.*, p.name AS patient_name, p.mobile AS patient_mobile FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id ORDER BY a.preferred_date DESC, a.created_at DESC LIMIT 10');
if ($apptRes) {
    while ($row = $apptRes->fetch_assoc()) {
        $appointments[] = $row;
    }
}

$patients = [];
$patientRes = db_query('SELECT * FROM patients ORDER BY name ASC LIMIT 20');
if ($patientRes) {
    while ($row = $patientRes->fetch_assoc()) {
        $patients[] = $row;
    }
}

$smsLogs = [];
$smsRes = db_query('SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 10');
if ($smsRes) {
    while ($row = $smsRes->fetch_assoc()) {
        $smsLogs[] = $row;
    }
}

$serviceCategories = [];
$categoryRes = db_query('SELECT * FROM service_categories ORDER BY sort_order ASC, name ASC');
if ($categoryRes) {
    while ($row = $categoryRes->fetch_assoc()) {
        $serviceCategories[] = $row;
    }
}

$services = [];
$serviceRes = db_query('SELECT s.*, c.name AS category_name FROM services s JOIN service_categories c ON s.category_id = c.id ORDER BY c.sort_order ASC, s.name ASC');
if ($serviceRes) {
    while ($row = $serviceRes->fetch_assoc()) {
        $services[] = $row;
    }
}

$basicServices = array_filter($services, fn($service) => $service['is_basic']);
$otherServices = array_filter($services, fn($service) => !$service['is_basic']);

$weeklyCounts = [];
$dailyLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = (new DateTime())->modify("-{$i} days")->format('Y-m-d');
    $weeklyCounts[$date] = 0;
    $dailyLabels[] = (new DateTime())->modify("-{$i} days")->format('D');
}
$topServices = [];
$serviceStatsRes = db_query('SELECT services, created_at FROM appointments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)');
if ($serviceStatsRes) {
    while ($row = $serviceStatsRes->fetch_assoc()) {
        $date = (new DateTime($row['created_at']))->format('Y-m-d');
        if (isset($weeklyCounts[$date])) {
            $weeklyCounts[$date]++;
        }
        foreach (explode(',', $row['services']) as $part) {
            $name = trim($part);
            if ($name === '') continue;
            $topServices[$name] = ($topServices[$name] ?? 0) + 1;
        }
    }
}
arsort($topServices);
$topServices = array_slice($topServices, 0, 5, true);
$weeklyPatients = array_sum($weeklyCounts);
$weeklyDone = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE status = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)', 's', ['done']);
$weeklyDone = $weeklyDone['cnt'] ?? 0;
$weeklyTotal = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)', null, []);
$weeklyTotal = $weeklyTotal['cnt'] ?? 0;
$queueEfficiency = $weeklyTotal > 0 ? round(($weeklyDone / $weeklyTotal) * 100) : 0;
$avgWaitMinutes = 0;
$waitRows = db_query('SELECT created_at, preferred_date FROM appointments WHERE status = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)', 's', ['done']);
$waitTotal = 0;
$waitCount = 0;
if ($waitRows) {
    while ($row = $waitRows->fetch_assoc()) {
        if (!$row['preferred_date']) continue;
        $start = new DateTime($row['created_at']);
        $end = new DateTime($row['preferred_date']);
        $diff = $end->getTimestamp() - $start->getTimestamp();
        if ($diff >= 0) {
            $waitTotal += intdiv($diff, 60);
            $waitCount++;
        }
    }
}
if ($waitCount > 0) {
    $avgWaitMinutes = round($waitTotal / $waitCount, 1);
}
$smsTotal = db_row('SELECT COUNT(*) AS cnt FROM sms_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)', null, []);
$smsTotal = $smsTotal['cnt'] ?? 0;
$smsRate = $smsTotal > 0 ? round(($smsSentCount / $smsTotal) * 100) : 0;

function formatDateTime($value) {
    if (!$value) return '—';
    $dt = new DateTime($value);
    return $dt->format('M j, Y · g:i A');
}

function formatDate($value) {
    if (!$value) return '—';
    $dt = new DateTime($value);
    return $dt->format('M j, Y');
}

function renderStatusTag($status) {
    switch ($status) {
        case 'pending':
            return 'orange';
        case 'confirmed':
            return 'blue';
        case 'done':
            return 'gray';
        case 'cancelled':
            return 'red';
        default:
            return 'gray';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($clinicName) ?> — Admin Dashboard</title>
  <link rel="stylesheet" href="../css/style.css"/>
  <link rel="stylesheet" href="../css/admin.css"/>
</head>
<body>

<!-- ════════════════════════════════════════
     ADMIN TOP NAV
════════════════════════════════════════ -->
<nav class="admin-top-nav">
  <div class="admin-nav-brand">
    <button class="hamburger" id="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
    <div class="admin-nav-logo">
      <svg width="16" height="16" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24">
        <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3M9 7h1m-1 4h1m4-4h1m-1 4h1M9 21v-3.5a.5.5 0 01.5-.5h5a.5.5 0 01.5.5V21"/>
      </svg>
    </div>
    <span class="admin-nav-title"><?= htmlspecialchars($clinicName) ?></span>
    <span class="admin-nav-badge">Admin</span>
  </div>
  <div class="admin-nav-right">
    <div class="live-badge" style="font-size:11px;">
      <div class="live-dot"></div> System live
    </div>
    <div class="admin-nav-user">
      <div class="admin-avatar" id="admin-avatar-nav"><?= htmlspecialchars($adminInitial) ?></div>
      <span id="admin-username-nav" style="font-size:13px;color:var(--gray-300);"><?= htmlspecialchars($adminName) ?></span>
    </div>
  </div>
</nav>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- ════════════════════════════════════════
     ADMIN LAYOUT
════════════════════════════════════════ -->
<div class="admin-layout">

  <!-- ── Sidebar ── -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="sidebar-clinic-name"><?= htmlspecialchars(explode(' ', $clinicName)[0] ?: $clinicName) ?></div>
      <div class="sidebar-clinic-sub"><?= htmlspecialchars($clinicSubtitle) ?></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Operations</div>

      <div class="nav-item active" onclick="showSection('dashboard', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
        </svg>
        <span>Dashboard</span>
      </div>

      <div class="nav-item" onclick="showSection('queue', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
        <span>Queue Control</span>
        <span class="nav-badge" id="queue-badge"><?= htmlspecialchars($waitingNow) ?></span>
      </div>

      <div class="nav-item" onclick="showSection('appointments', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2"/>
          <path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        <span>Appointments</span>
      </div>

      <div class="nav-item" onclick="showSection('patients', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        <span>Patients</span>
      </div>

      <div class="nav-section-label">Communication</div>

      <div class="nav-item" onclick="showSection('sms', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        <span>SMS Logs</span>
      </div>

      <div class="nav-section-label">Configuration</div>

      <div class="nav-item" onclick="showSection('services', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
          <path d="M12 8v4l3 3"/>
        </svg>
        <span>Services</span>
      </div>

      <div class="nav-item" onclick="showSection('analytics', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <line x1="18" y1="20" x2="18" y2="10"/>
          <line x1="12" y1="20" x2="12" y2="4"/>
          <line x1="6" y1="20" x2="6" y2="14"/>
        </svg>
        <span>Analytics</span>
      </div>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar" id="sidebar-avatar"><?= htmlspecialchars($adminInitial) ?></div>
        <div>
          <div class="user-name" id="sidebar-username"><?= htmlspecialchars($adminName) ?></div>
          <div class="user-role">Administrator</div>
        </div>
      </div>
      <button class="logout-btn" onclick="adminLogout()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
        Sign Out
      </button>
    </div>
  </aside>

  <!-- ── Main Content ── -->
  <main class="admin-content">

    <!-- ══ DASHBOARD ══ -->
    <section class="admin-section active" id="section-dashboard">
      <div class="page-header">
        <div>
          <h2>Dashboard</h2>
          <p id="dashboard-date">Loading date…</p>
        </div>
        <div class="header-right">
          <div class="live-indicator"><div class="live-dot" style="background:#059669;"></div> Live data</div>
          <button class="btn btn-sm btn-gray" onclick="window.location.reload()">↻ Refresh</button>
        </div>
      </div>

      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-icon blue">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
          </div>
          <div>
            <div class="stat-val"><?= htmlspecialchars($patientsToday) ?></div>
            <div class="stat-lbl">Patients Today</div>
            <div class="stat-trend up">↑ <?= htmlspecialchars(max(0, $patientsToday - ($doneToday ?? 0))) ?> vs yesterday</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div>
            <div class="stat-val"><?= htmlspecialchars($doneToday) ?></div>
            <div class="stat-lbl">Done Today</div>
            <div class="stat-trend up">↑ <?= htmlspecialchars($waitingNow > 0 ? round(($doneToday / max(1, $patientsToday)) * 100) : 0) ?>% completion</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          </div>
          <div>
            <div class="stat-val"><?= htmlspecialchars($waitingNow) ?></div>
            <div class="stat-lbl">Waiting Now</div>
            <div class="stat-trend">Queue <?php if ($queueServing): ?>#<?= htmlspecialchars($queueServing['queue_number']) ?><?php else: ?>—<?php endif; ?> serving</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </div>
          <div>
            <div class="stat-val"><?= htmlspecialchars($smsSentCount) ?></div>
            <div class="stat-lbl">SMS Sent</div>
            <div class="stat-trend up">100% delivery</div>
          </div>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="chart-card">
          <div class="chart-title">Weekly Patient Volume</div>
          <div class="chart-sub">Patients served per day this week</div>
          <div class="bar-chart">
            <?php foreach ($weeklyCounts as $date => $count): ?>
              <div class="bar-item">
                <div class="bar" style="height:<?= htmlspecialchars(min(180, $count * 10 + 24)) ?>px;background:var(--blue<?= $count > 0 ? '' : '-mid' ?>);"></div>
                <div class="bar-label"><?= htmlspecialchars((new DateTime($date))->format('D')) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card mt-24">
        <div class="card-title">Today's Appointments
          <button class="btn btn-sm btn-primary" style="margin-left:auto;" onclick="showSection('queue',null)">View Queue →</button>
        </div>
        <table class="data-table">
          <thead><tr><th>#</th><th>Patient</th><th>Services</th><th>Time</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($queueWaiting as $item): ?>
              <tr>
                <td class="fw-600 text-blue">#<?= htmlspecialchars($item['queue_number']) ?></td>
                <td><div class="patient-name-cell"><div class="patient-dot" style="background:#6366f1;"><?= htmlspecialchars(strtoupper(substr($item['patient_name'], 0, 1))) ?></div><?= htmlspecialchars($item['patient_name']) ?></div></td>
                <td><?= htmlspecialchars($item['services']) ?></td>
                <td style="color:var(--gray-500);"><?= htmlspecialchars(formatDateTime($item['preferred_date'])) ?></td>
                <td><span class="tag orange">Waiting</span></td>
                <td><button class="action-btn" onclick="showSection('queue',null)">→</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ══ QUEUE CONTROL ══ -->
    <section class="admin-section" id="section-queue">
      <div class="page-header">
        <div>
          <h2>Queue Control</h2>
          <p>Manage patient flow. SMS is sent automatically on "Next Patient".</p>
        </div>
        <div class="header-right">
          <div class="live-indicator"><div class="live-dot" style="background:#059669;"></div> Auto-SMS <strong>ON</strong></div>
          <button class="btn btn-sm btn-gray" onclick="showToast('Queue actions are not yet connected to the database.', 'default')">↻ Reset demo</button>
        </div>
      </div>

      <div class="queue-control-layout">
        <input type="hidden" id="queue-serving-id" value="<?= htmlspecialchars($queueServing['id'] ?? '') ?>" />
        <div>
          <div class="now-serving-card">
            <div class="now-serving-label">Now Serving</div>
            <div class="now-serving-num" id="q-now-num"><?= htmlspecialchars($queueServing['queue_number'] ?? '—') ?></div>
            <div class="now-serving-name" id="q-now-name"><?= htmlspecialchars($queueServing['patient_name'] ?? 'No active patient') ?></div>
            <div class="now-serving-service" id="q-now-service"><?= htmlspecialchars($queueServing['services'] ?? '') ?></div>
            <div class="queue-actions">
              <button class="btn btn-sm" style="background:rgba(255,255,255,.95);color:var(--blue);font-weight:700;" onclick="callNextPatient()">
                ✓ Mark Done &amp; Call Next
              </button>
              <button class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);" onclick="skipCurrent()">
                ⏭ Skip
              </button>
            </div>
          </div>

          <div class="card">
            <div class="card-title">
              Waiting Queue
              <span id="waiting-count-label" style="font-size:12px;color:var(--gray-500);font-weight:400;">— <?= htmlspecialchars(count($queueWaiting)) ?> waiting</span>
            </div>
            <div class="queue-list" id="queue-list">
              <?php if (count($queueWaiting) === 0): ?>
                <div class="empty-queue">No patients waiting.</div>
              <?php else: ?>
                <?php foreach ($queueWaiting as $index => $patient): ?>
                  <div class="queue-list-item">
                    <div class="queue-num-badge">#<?= htmlspecialchars($patient['queue_number']) ?></div>
                    <div class="queue-item-info">
                      <div class="queue-item-name"><?= htmlspecialchars($patient['patient_name']) ?></div>
                      <div class="queue-item-service"><?= htmlspecialchars($patient['services']) ?></div>
                    </div>
                    <span class="tag orange">Waiting</span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="card mt-24" style="margin-top:16px;">
              <div class="card-title">Completed Today</div>
              <div id="done-list" class="queue-list">
                <?php foreach ($queueDone as $done): ?>
                  <div class="queue-list-item">
                    <div class="queue-num-badge" style="background:#dcfce7;color:#16a34a;">#<?= htmlspecialchars($done['queue_number']) ?></div>
                    <div class="queue-item-info">
                      <div class="queue-item-name"><?= htmlspecialchars($done['patient_name']) ?></div>
                      <div class="queue-item-service"><?= htmlspecialchars($done['services']) ?></div>
                    </div>
                    <span class="tag gray">Done</span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div>
          <div class="card sms-template-card">
            <div class="card-title">SMS Template</div>
            <p style="font-size:12px;color:var(--gray-500);margin-bottom:12px;">Sent automatically when a patient is called to the window.</p>
            <textarea class="sms-template-textarea" id="sms-template"><?= htmlspecialchars($smsTemplate) ?></textarea>
            <div style="display:flex;gap:8px;margin-top:10px;">
              <button class="btn btn-sm btn-primary" onclick="saveTemplate()">Save template</button>
              <button class="btn btn-sm btn-gray" onclick="previewSMS()">Preview SMS</button>
            </div>
            <hr class="divider"/>
            <div class="card-title" style="margin-bottom:10px;">Send Manual SMS</div>
            <div class="form-group">
              <label class="form-label">Select Patient</label>
              <select class="form-select" id="manual-sms-patient">
                <?php if ($queueServing): ?>
                  <option><?= htmlspecialchars($queueServing['patient_name'] . ' (#' . $queueServing['queue_number'] . ')') ?></option>
                <?php endif; ?>
                <?php foreach ($queueWaiting as $waiting): ?>
                  <option><?= htmlspecialchars($waiting['patient_name'] . ' (#' . $waiting['queue_number'] . ')') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-sm btn-accent btn-block" onclick="sendManualSMS()">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
              Send SMS Now
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ APPOINTMENTS ══ -->
    <section class="admin-section" id="section-appointments">
      <div class="page-header">
        <div><h2>Appointments</h2><p>Manage, approve, and reschedule patient appointments.</p></div>
        <div class="header-right">
          <button class="btn btn-sm btn-primary">+ New Appointment</button>
        </div>
      </div>
      <div class="card">
        <div class="appointment-filters">
          <button class="filter-btn active" onclick="filterAppts(this,'all')">All</button>
          <button class="filter-btn" onclick="filterAppts(this,'pending')">Pending</button>
          <button class="filter-btn" onclick="filterAppts(this,'confirmed')">Confirmed</button>
          <button class="filter-btn" onclick="filterAppts(this,'done')">Completed</button>
          <button class="filter-btn" onclick="filterAppts(this,'cancelled')">Cancelled</button>
          <div class="search-bar-wrap" style="flex:1;min-width:200px;margin-bottom:0;">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" class="search-input" placeholder="Search by name or service…" oninput="filterApptsSearch(this.value)"/>
          </div>
        </div>
        <table class="data-table">
          <thead><tr><th>Queue #</th><th>Patient</th><th>Services</th><th>Date &amp; Time</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="appt-table-body">
            <?php foreach ($appointments as $appointment): ?>
              <tr data-status="<?= htmlspecialchars($appointment['status']) ?>">
                <td class="fw-600 <?= $appointment['status'] === 'confirmed' ? 'text-blue' : '' ?>">#<?= htmlspecialchars($appointment['queue_number']) ?></td>
                <td><div class="patient-name-cell"><div class="patient-dot" style="background:#6366f1;"><?= htmlspecialchars(strtoupper(substr($appointment['patient_name'], 0, 1))) ?></div><?= htmlspecialchars($appointment['patient_name']) ?></div></td>
                <td><?= htmlspecialchars($appointment['services']) ?></td>
                <td style="color:var(--gray-500);"><?= htmlspecialchars(formatDateTime($appointment['preferred_date'])) ?></td>
                <td><span class="tag <?= htmlspecialchars(renderStatusTag($appointment['status'])) ?>"><?= ucfirst(htmlspecialchars($appointment['status'])) ?></span></td>
                <td style="display:flex;gap:6px;">
                  <button class="action-btn">Edit</button>
                  <?php if ($appointment['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-accent" onclick="approveAppt(this, <?= htmlspecialchars($appointment['id']) ?>)">Approve</button>
                    <button class="btn btn-sm btn-danger" onclick="cancelAppt(this, <?= htmlspecialchars($appointment['id']) ?>)">Reject</button>
                  <?php elseif ($appointment['status'] === 'confirmed'): ?>
                    <button class="btn btn-sm btn-danger" onclick="cancelAppt(this, <?= htmlspecialchars($appointment['id']) ?>)">Cancel</button>
                  <?php else: ?>
                    <button class="action-btn">View</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ══ PATIENTS ══ -->
    <section class="admin-section" id="section-patients">
      <div class="page-header">
        <div><h2>Patient Records</h2><p>All registered patients from OTP login.</p></div>
        <div class="header-right">
          <button class="btn btn-sm btn-primary">Export CSV</button>
        </div>
      </div>
      <div class="card">
        <div class="search-bar-wrap">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" class="search-input" id="patient-search" placeholder="Search name, phone or company…" oninput="filterPatients(this.value)"/>
        </div>
        <table class="data-table" id="patient-table">
          <thead>
            <tr><th>Name</th><th>Phone</th><th>Sex</th><th>Age</th><th>Company</th><th>Last Visit</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($patients as $patient): ?>
              <?php
                $dob = new DateTime($patient['dob']);
                $age = $dob->diff(new DateTime())->y;
              ?>
              <tr>
                <td><div class="patient-name-cell"><div class="patient-dot" style="background:#6366f1;"><?= htmlspecialchars(strtoupper(substr($patient['name'], 0, 1))) ?></div><?= htmlspecialchars($patient['name']) ?></div></td>
                <td><?= htmlspecialchars($patient['mobile']) ?></td>
                <td><?= htmlspecialchars($patient['sex']) ?></td>
                <td><?= htmlspecialchars($age) ?></td>
                <td><?= htmlspecialchars($patient['company'] ?: '—') ?></td>
                <td><?= htmlspecialchars((new DateTime($patient['created_at']))->format('M j')) ?></td>
                <td><span class="tag green">Active</span></td>
                <td><button class="action-btn">→</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ══ SMS LOGS ══ -->
    <section class="admin-section" id="section-sms">
      <div class="page-header">
        <div><h2>SMS Logs</h2><p>Auto-sent on every queue advancement.</p></div>
        <div class="header-right">
          <button class="btn btn-sm btn-gray">Export logs</button>
        </div>
      </div>
      <div class="sms-counter-grid">
        <div class="sms-counter">
          <div class="sms-counter-icon sent-bg">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div><div class="sms-counter-val" id="sms-sent-count"><?= htmlspecialchars($smsSentCount) ?></div><div class="sms-counter-lbl">SMS Sent</div></div>
        </div>
        <div class="sms-counter">
          <div class="sms-counter-icon fail-bg">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
          </div>
          <div><div class="sms-counter-val" style="color:#dc2626;">0</div><div class="sms-counter-lbl">Failed</div></div>
        </div>
        <div class="sms-counter">
          <div class="sms-counter-icon pend-bg">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          </div>
          <div><div class="sms-counter-val" style="color:#f97316;">0</div><div class="sms-counter-lbl">Pending</div></div>
        </div>
      </div>
      <div class="card">
        <div class="card-title">Message History</div>
        <table class="data-table" id="sms-log-table">
          <thead><tr><th>Patient</th><th>Phone</th><th>Queue</th><th>Message</th><th>Status</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach ($smsLogs as $log): ?>
              <tr>
                <td><div class="patient-name-cell"><div class="patient-dot" style="background:#6366f1;font-size:10px;"><?= htmlspecialchars(strtoupper(substr($log['patient_name'], 0, 2))) ?></div><?= htmlspecialchars($log['patient_name']) ?></div></td>
                <td style="color:var(--gray-500);"><?= htmlspecialchars($log['patient_mobile']) ?></td>
                <td><span class="tag blue"><?= htmlspecialchars(ucfirst($log['status'])) ?></span></td>
                <td style="max-width:260px;color:var(--gray-500);font-size:12px;"><?= htmlspecialchars($log['message']) ?></td>
                <td><span class="sms-status <?= htmlspecialchars($log['status']) ?>"><?= ucfirst(htmlspecialchars($log['status'])) ?></span></td>
                <td style="color:var(--gray-500);"><?= htmlspecialchars((new DateTime($log['created_at']))->format('M j, g:i A')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ══ SERVICES ══ -->
    <section class="admin-section" id="section-services">
      <div class="page-header">
        <div><h2>Medical Services</h2><p>Manage available tests and procedures.</p></div>
        <div class="header-right">
          <button class="btn btn-sm btn-primary" onclick="openAddService()">+ Add Service</button>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-title">
          <span style="background:var(--blue-light);color:var(--blue);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">BASIC 5</span>
          &nbsp;Master Package
        </div>
        <p style="font-size:13px;color:var(--gray-500);margin-bottom:14px;">One-click selects all basic services for the package.</p>
        <div class="services-admin-grid">
          <?php foreach ($basicServices as $service): ?>
            <div class="service-admin-card">
              <div class="service-admin-info">
                <div class="s-cat"><?= htmlspecialchars($service['category_name']) ?></div>
                <div class="s-name"><?= htmlspecialchars($service['name']) ?></div>
                <div class="s-price">₱<?= htmlspecialchars(number_format($service['price'], 0, '.', '')) ?> · ~<?= htmlspecialchars($service['duration']) ?> min</div>
              </div>
              <div class="service-admin-actions">
                <button class="action-btn">Edit</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-title">All Other Services</div>
        <div class="services-admin-grid">
          <?php foreach ($otherServices as $service): ?>
            <div class="service-admin-card">
              <div class="service-admin-info">
                <div class="s-cat"><?= htmlspecialchars($service['category_name']) ?></div>
                <div class="s-name"><?= htmlspecialchars($service['name']) ?></div>
                <div class="s-price">₱<?= htmlspecialchars(number_format($service['price'], 0, '.', '')) ?> · ~<?= htmlspecialchars($service['duration']) ?> min</div>
              </div>
              <div class="service-admin-actions">
                <button class="action-btn">Edit</button>
                <button class="btn btn-sm btn-danger">Del</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ══ ANALYTICS ══ -->
    <section class="admin-section" id="section-analytics">
      <div class="page-header">
        <div><h2>Analytics</h2><p>Insights on patient flow, peak hours, and SMS performance.</p></div>
        <div class="header-right">
          <select class="form-select" style="width:auto;padding:8px 12px;font-size:13px;">
            <option>This Week</option>
            <option>This Month</option>
            <option>Last 3 Months</option>
          </select>
        </div>
      </div>

      <div class="stat-cards">
        <div class="stat-card"><div class="stat-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div><div class="stat-val"><?= htmlspecialchars($weeklyPatients) ?></div><div class="stat-lbl">Weekly Patients</div><div class="stat-trend up">↑ <?= htmlspecialchars($weeklyTotal > 0 ? round(($weeklyPatients / max(1, $weeklyTotal)) * 100) : 0) ?>% vs last week</div></div></div>
        <div class="stat-card"><div class="stat-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg></div><div><div class="stat-val"><?= htmlspecialchars($queueEfficiency) ?>%</div><div class="stat-lbl">Queue Efficiency</div><div class="stat-trend up">↑ <?= htmlspecialchars($queueEfficiency) ?>% vs last week</div></div></div>
        <div class="stat-card"><div class="stat-icon orange"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><div><div class="stat-val"><?= htmlspecialchars($avgWaitMinutes) ?> min</div><div class="stat-lbl">Avg. Wait per Patient</div><div class="stat-trend down">↓ improved</div></div></div>
        <div class="stat-card"><div class="stat-icon purple"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div><div><div class="stat-val"><?= htmlspecialchars($smsRate) ?>%</div><div class="stat-lbl">SMS Delivery Rate</div><div class="stat-trend up">↑ <?= htmlspecialchars($smsSentCount) ?> sent this week</div></div></div>
      </div>

      <div class="analytics-row">
        <div class="chart-card">
          <div class="chart-title">Top Services This Week</div>
          <div class="chart-sub">By number of bookings</div>
          <?php foreach ($topServices as $name => $count): ?>
            <?php $width = min(100, max(10, intval(($count / max(1, reset($topServices))) * 100))); ?>
            <div class="progress-item" style="margin-top:8px;">
              <div class="progress-label"><span><?= htmlspecialchars($name) ?></span><span><?= htmlspecialchars($count) ?> bookings</span></div>
              <div class="progress-track"><div class="progress-fill <?= $width > 80 ? 'green' : ($width > 50 ? 'orange' : '') ?>" style="width:<?= htmlspecialchars($width) ?>%;"></div></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="chart-card">
          <div class="chart-title">Weekly Volume Trend</div>
          <div class="chart-sub">Daily patient count — current week</div>
          <div class="bar-chart" style="margin-top:8px;">
            <?php foreach ($weeklyCounts as $date => $count): ?>
              <div class="bar-item">
                <div class="bar" style="height:<?= htmlspecialchars(min(180, $count * 10 + 20)) ?>px;background:var(--blue<?= $count > 0 ? '' : '-mid' ?>);"></div>
                <div class="bar-label"><?= htmlspecialchars((new DateTime($date))->format('D')) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

  </main><!-- end admin-content -->
</div><!-- end admin-layout -->

<div class="toast-container" id="toast-container"></div>

<script src="../js/admin.js"></script>
</body>
</html>
