<?php
require_once __DIR__ . '/db.php';

$settings = [];
$settingRows = db_query('SELECT setting_key, setting_value FROM settings');
if ($settingRows) {
    while ($row = $settingRows->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$siteName = $settings['clinic_name'] ?? 'M.V. Masangkay Clinic';
$siteSubtitle = $settings['clinic_subtitle'] ?? 'X-Ray & Laboratory';

$serviceCategories = [];
$categoryRows = db_query('SELECT id, name, slug FROM service_categories ORDER BY sort_order, name');
if ($categoryRows) {
    while ($row = $categoryRows->fetch_assoc()) {
        $serviceCategories[] = $row;
    }
}

$services = [];
$serviceRows = db_query('SELECT s.*, c.slug AS category_slug, c.name AS category_name FROM services s JOIN service_categories c ON s.category_id = c.id WHERE s.active = 1 ORDER BY c.sort_order, s.name');
if ($serviceRows) {
    while ($row = $serviceRows->fetch_assoc()) {
        $services[] = $row;
    }
}

$serviceCategoryMap = array_column($serviceCategories, 'name', 'slug');

$today = date('Y-m-d');
$queueNow = db_row('SELECT queue_number, services FROM appointments WHERE status = ? ORDER BY preferred_date ASC, created_at ASC LIMIT 1', 's', ['pending']);
$waitingCount = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE status = ?', 's', ['pending']);
$doneToday = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE status = ? AND DATE(created_at) = ?', 'ss', ['done', $today]);
$waitingCount = $waitingCount['cnt'] ?? 0;
$doneToday = $doneToday['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($siteName) ?></title>
  <link rel="stylesheet" href="css/style.css"/>
  <link rel="stylesheet" href="css/patient.css"/>
</head>
<body>

<!-- ════════════════════════════════════════
     TOP NAVIGATION
════════════════════════════════════════ -->
<nav class="top-nav">
  <div class="nav-brand">
    <div class="nav-logo">
      <svg width="18" height="18" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24">
        <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3M9 7h1m-1 4h1m4-4h1m-1 4h1M9 21v-3.5a.5.5 0 01.5-.5h5a.5.5 0 01.5.5V21"/>
      </svg>
    </div>
    <div class="nav-brand-text">
      <div class="clinic-title"><?= htmlspecialchars($siteName) ?></div>
      <div class="clinic-sub"><?= htmlspecialchars($siteSubtitle) ?></div>
    </div>
  </div>

  <div class="nav-right">
    <button class="profile-btn" onclick="toggleDropdown()">
      <div class="profile-avatar" id="profile-avatar-initial">?</div>
      <span id="profile-btn-label">Login / Register</span>
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="dropdown-menu" id="dropdown-menu">
      <div class="dropdown-item" id="dd-login-item" onclick="goPatientStep(1); closeDropdown()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
        Login / Register
      </div>
      <div class="dropdown-item" id="dd-profile-item" style="display:none;" onclick="goPatientStep(0); closeDropdown()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        My Profile
      </div>
      <div class="dropdown-item" id="dd-booking-item" style="display:none;" onclick="goPatientStep(3); closeDropdown()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        My Appointments
      </div>
      <div class="dropdown-item" id="dd-queue-item" style="display:none;" onclick="goPatientStep(5); closeDropdown()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Track Queue
      </div>
      <div class="dropdown-divider"></div>
      <a class="dropdown-item admin-link" href="admin/login.php">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Admin Login
      </a>
    </div>
  </div>
</nav>

<!-- ════════════════════════════════════════
     PATIENT CONTENT AREA
════════════════════════════════════════ -->
<main class="patient-main">
  <div class="patient-content">

    <!-- ── STEP 0: Landing ── -->
    <div class="patient-screen active" id="pstep-0">
      <div class="landing-wrap">
        <div class="landing-hero">
          <h1><?= htmlspecialchars($siteName) ?>,<br><em>Appointment</em><br>System</h1>
          <p>Skip the long lines. Book your lab tests and check-ups online. Get your queue number and wait for SMS updates.</p>
          <div class="feature-list">
            <div class="feature-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              OTP login — no password needed
            </div>
            <div class="feature-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              CBC, Urinalysis, X-Ray &amp; more
            </div>
            <div class="feature-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Real-time queue tracking with SMS alerts
            </div>
            <div class="feature-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              No waiting inside the clinic
            </div>
          </div>
<div class="hero-cta">
  <button class="btn btn-primary" onclick="goPatientStep(1)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg>
    Book Appointment
  </button>
          </div>
        </div>

        <div>
          <div class="queue-preview">
            <div class="queue-preview-header">
              <div class="qlabel">Now Serving</div>
              <div class="qnum"><?php if ($queueNow && !empty($queueNow['queue_number'])): ?>#<span id="live-now"><?= htmlspecialchars($queueNow['queue_number']) ?></span><?php else: ?><span id="live-now">—</span><?php endif; ?></div>
              <div class="qbadge"><?= htmlspecialchars($queueNow['services'] ?? '—') ?></div>
            </div>
            <div class="queue-preview-body">
              <div class="queue-mini-stats">
                <div class="qms">
                  <div class="qval" id="live-waiting"><?= htmlspecialchars($waitingCount) ?></div>
                  <div class="qlbl">Waiting</div>
                </div>
                <div class="qms">
                  <div class="qval">~5 min</div>
                  <div class="qlbl">Per patient</div>
                </div>
                <div class="qms">
                  <div class="qval" id="live-done"><?= htmlspecialchars($doneToday) ?></div>
                  <div class="qlbl">Done today</div>
                </div>
              </div>
              <div class="live-badge" style="width:fit-content;margin:0 auto 12px;">
                <div class="live-dot"></div> Live updates
              </div>
              <p class="clinic-hours"><strong>Open today</strong> · 8:00 AM – 5:00 PM</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── STEP 1: Enter Mobile Number ── -->
    <div class="patient-screen" id="pstep-1">
      <div class="otp-card">
        <div class="card-icon">
          <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
        </div>
        <h3>Enter your mobile number</h3>
        <p class="desc">We'll send you a one-time password (OTP) to verify your identity. No account needed.</p>
        <div class="form-group">
          <label class="form-label">Mobile Number</label>
          <div class="phone-group">
            <div class="phone-prefix">🇵🇭 +63</div>
            <input type="tel" class="form-control" id="mobile-input" placeholder="9XX XXX XXXX" maxlength="10" inputmode="numeric"/>
          </div>
        </div>
        <button class="btn btn-primary btn-block" onclick="sendOTP()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013 12.62 19.79 19.79 0 01.06 4.1a2 2 0 012-2.18h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.13 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
          Send OTP
        </button>
        <p class="disclaimer mt-8">By continuing, you agree to receive SMS. Standard rates apply.</p>
        <button class="btn btn-gray btn-sm btn-block mt-8" onclick="goPatientStep(0)">← Back to home</button>
      </div>
    </div>

    <!-- ── STEP 2: Verify OTP ── -->
    <div class="patient-screen" id="pstep-2">
      <div class="otp-card">
        <div class="card-icon">
          <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <h3>Enter OTP</h3>
        <p class="desc" id="otp-sent-msg">We sent a 6-digit code to <strong id="otp-phone-display">your number</strong>. Enter it below to continue.</p>
        <div class="otp-boxes">
          <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
          <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
          <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
          <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
          <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
          <input type="text" class="otp-box" maxlength="1" inputmode="numeric"/>
        </div>
        <button class="btn btn-primary btn-block" onclick="verifyOTP()">Verify &amp; Continue</button>
        <p class="hint-text mt-8">Didn't get a code? <a href="#" onclick="sendOTP(); return false;">Resend</a> · <a href="#" onclick="goPatientStep(1); return false;">Change number</a></p>
        <button class="btn btn-gray btn-sm btn-block mt-8" onclick="goPatientStep(1)">← Back</button>
      </div>
    </div>

    <!-- ── STEP 3: Profile Form ── -->
    <div class="patient-screen" id="pstep-3">
      <div class="profile-card">
        <div class="step-label">Step 1 of 2 — Patient Profile</div>
        <h2>Personal Information</h2>
        <p>This information will be used for accurate record preparation prior to your appointment.</p>
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" class="form-control" id="patient-name" placeholder="Juan Dela Cruz" value=""/>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date of Birth *</label>
            <input type="date" class="form-control" id="patient-dob" />
          </div>
          <div class="form-group">
            <label class="form-label">Sex *</label>
            <select class="form-select" id="patient-sex">
              <option value="female">Female</option>
              <option value="male" selected>Male</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Company / School (optional)</label>
          <input type="text" class="form-control" id="patient-company" placeholder="e.g. ABC Corp, University of Santo Tomas"/>
        </div>
        <div class="form-group">
          <label class="form-label">Mobile Number (confirmed)</label>
          <input type="text" class="form-control" id="patient-mobile-display" readonly style="background:var(--gray-100);"/>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px;">
          <button class="btn btn-gray btn-sm" onclick="goPatientStep(2)">← Back</button>
          <button class="btn btn-primary" style="flex:1;" onclick="saveProfileAndContinue()">Continue to booking →</button>
        </div>
      </div>
    </div>

    <!-- ── STEP 4: Book Services ── -->
    <div class="patient-screen" id="pstep-4">
      <div class="step-label">Step 2 of 2 — Select Services</div>
      <h2 class="section-heading">Choose your services</h2>
      <p class="section-sub">Select one or more tests and procedures. You can also add a custom request for the doctor's review.</p>

      <div class="booking-layout">
        <div class="booking-panel">

          <!-- Basic 5 Toggle Banner -->
          <div class="basic5-banner" id="basic5-banner" onclick="toggleBasic5()">
            <div class="basic5-info">
              <div class="b5-title">⭐ Basic 5 Package</div>
              <div class="b5-sub">CBC · Urinalysis · Fecalysis · Physical Exam · Chest X-Ray — one click</div>
            </div>
            <div class="basic5-toggle" id="basic5-toggle"></div>
          </div>

          <!-- Service Category Tabs -->
          <div class="service-tabs">
            <button class="service-tab active" onclick="filterServices(this,'all')">All Services</button>
            <?php foreach ($serviceCategories as $category): ?>
              <button class="service-tab" onclick="filterServices(this,'<?= htmlspecialchars($category['slug']) ?>')"><?= htmlspecialchars($category['name']) ?></button>
            <?php endforeach; ?>
          </div>

          <!-- Services Grid -->
          <div class="services-grid" id="services-grid">
          <?php foreach ($services as $service): ?>
            <div class="service-item<?= $service['is_basic'] ? ' selected' : '' ?>" data-cat="<?= htmlspecialchars($service['category_slug']) ?>" data-basic="<?= $service['is_basic'] ? 'true' : 'false' ?>" data-name="<?= htmlspecialchars($service['name']) ?>" data-price="<?= htmlspecialchars(number_format($service['price'], 0, '.', '')) ?>" data-time="<?= htmlspecialchars($service['duration']) ?>" onclick="toggleService(this)">
              <div class="check-badge"><svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></div>
              <div class="service-name"><?= htmlspecialchars($service['name']) ?></div>
              <div class="service-price"><strong>₱<?= htmlspecialchars(number_format($service['price'], 0, '.', '')) ?></strong></div>
              <div class="service-duration">~<?= htmlspecialchars($service['duration']) ?> min</div>
            </div>
          <?php endforeach; ?>
          </div>

          <!-- Custom Service -->
          <div class="form-group">
            <label class="form-label">Custom Service Request (optional)</label>
            <input type="text" class="form-control" id="custom-service" placeholder="e.g. Specialized blood panel, other test..."/>
          </div>
          <!-- Preferred Date -->
          <div class="form-group">
            <label class="form-label">Preferred Date</label>
            <input type="date" class="form-control" id="appt-datetime" />
          </div>
          <button class="btn btn-gray btn-sm mt-8" onclick="goPatientStep(3)">← Back to profile</button>
        </div>

        <!-- Booking Summary -->
        <div>
          <div class="booking-summary">
            <h4>Booking Summary</h4>
            <div class="summary-patient" id="sum-patient-name">Patient Name</div>
            <div class="summary-phone" id="sum-patient-phone">+63 —</div>
            <hr class="summary-divider"/>
            <div class="summary-items" id="summary-items"></div>
            <div class="summary-empty" id="summary-empty">No services selected yet</div>
            <hr class="summary-divider"/>
            <div class="summary-total">
              <span>Total</span>
              <span id="summary-total">₱0</span>
            </div>
            <button class="btn" style="background:#fff;color:var(--blue);width:100%;font-weight:700;" onclick="confirmBooking()">
              Confirm Booking →
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── STEP 5: Queue Tracker ── -->
    <div class="patient-screen" id="pstep-5">
      <div class="queue-wrap">
        <div class="flex items-center gap-8 mb-16">
          <div class="live-badge"><div class="live-dot"></div> Live</div>
          <span class="text-gray" style="font-size:13px;">Updates every few seconds. You can leave and come back.</span>
        </div>
        <div class="queue-card">
          <div class="queue-header">
            <div class="queue-label">Your Queue Number</div>
            <div class="queue-number"><sup>#</sup><span id="my-queue-num">—</span></div>
            <div class="queue-type-badge" id="my-queue-type">—</div>
          </div>
          <div class="queue-body">
            <div class="queue-stats">
              <div class="queue-stat">
                <div class="val" id="ahead-count">—</div>
                <div class="lbl">Ahead of you</div>
              </div>
              <div class="queue-stat">
                <div class="val" id="est-wait">—</div>
                <div class="lbl">Est. wait (min)</div>
              </div>
              <div class="queue-stat">
                <div class="val" id="total-today">—</div>
                <div class="lbl">Total today</div>
              </div>
            </div>
            <div class="serving-row">
              <div class="serving-card now-serving">
                <div class="sc-lbl">Now Serving</div>
                <div class="sc-val" id="now-serving-info">—</div>
                <div class="sc-lbl" style="margin-top:4px;" id="now-serving-service">—</div>
              </div>
              <div class="serving-card">
                <div class="sc-lbl">Up Next</div>
                <div class="sc-val" id="up-next-val">—</div>
              </div>
            </div>
            <div class="sms-note">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px;"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              <span>You'll receive an SMS at <strong id="sms-phone-display">your number</strong> when you're 5 patients away. No need to wait inside.</span>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
              <button class="btn btn-outline btn-sm" onclick="goPatientStep(4)">← Modify booking</button>
              <button class="btn btn-danger btn-sm" style="margin-left:auto;" onclick="cancelQueue()">Cancel queue</button>
            </div>
          </div>
        </div>

        <div class="card mt-24">
          <div class="card-title">Appointment Details</div>
          <table class="appt-table">
            <tr><td>Patient</td><td class="fw-600" id="appt-det-patient">—</td></tr>
            <tr><td>Services</td><td class="fw-600" id="appt-det-services">—</td></tr>
            <tr><td>Date</td><td class="fw-600" id="appt-det-date">—</td></tr>
            <tr><td>Queue #</td><td class="fw-600 text-blue" id="appt-det-queue">—</td></tr>
            <tr><td>Status</td><td><span class="tag blue">Waiting</span></td></tr>
          </table>
        </div>
      </div>
    </div>

  </div><!-- end patient-content -->
</main>

<!-- ── Toast Container ── -->
<div class="toast-container" id="toast-container"></div>

<!-- ── Dropdown backdrop ── -->
<div id="dd-backdrop" style="position:fixed;inset:0;z-index:998;display:none;" onclick="closeDropdown()"></div>

<script src="js/patient.js"></script>
</body>
</html>
