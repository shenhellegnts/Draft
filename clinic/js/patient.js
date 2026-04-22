/* ============================================================
   M.V. Masangkay Clinic — Patient Portal JS
   patient.js
   ============================================================ */

'use strict';

/* ─── State ─── */
const state = {
  mobile: '',
  otpSent: false,
  patient: null,
  selectedServices: [],
  queueNum: null,
  currentStep: 0,
};

/* ─── Navigation ─── */
function goPatientStep(n) {
  document.querySelectorAll('.patient-screen').forEach((s, i) => {
    s.classList.toggle('active', i === n);
  });

  // Show/hide flow bar
  const flowBar = document.getElementById('flow-bar');
  if (n === 0) {
    flowBar.style.display = 'none';
  } else {
    flowBar.style.display = 'block';
    updateFlowSteps(n);
  }

  state.currentStep = n;
  window.scrollTo({ top: 0, behavior: 'smooth' });

  // Sync summary on step 4
  if (n === 4) updateSummary();
}

function updateFlowSteps(active) {
  // Map screen index → flow step index
  // pstep-0=landing, pstep-1=mobile, pstep-2=otp, pstep-3=profile, pstep-4=booking, pstep-5=queue
  const map = { 1:1, 2:2, 3:3, 4:4, 5:5 };
  const flowIdx = map[active] ?? 0;
  for (let i = 0; i <= 5; i++) {
    const el = document.getElementById('fstep-' + i);
    if (!el) continue;
    el.classList.remove('active', 'done');
    if (i < flowIdx)  el.classList.add('done');
    if (i === flowIdx) el.classList.add('active');
  }
}

/* ─── Dropdown ─── */
function toggleDropdown() {
  const menu = document.getElementById('dropdown-menu');
  const backdrop = document.getElementById('dd-backdrop');
  const isOpen = menu.classList.contains('open');
  if (isOpen) {
    menu.classList.remove('open');
    backdrop.style.display = 'none';
  } else {
    menu.classList.add('open');
    backdrop.style.display = 'block';
  }
}
function closeDropdown() {
  document.getElementById('dropdown-menu').classList.remove('open');
  document.getElementById('dd-backdrop').style.display = 'none';
}

/* ─── Toast ─── */
function showToast(msg, type = 'default', duration = 3500) {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = 'toast' + (type !== 'default' ? ' ' + type : '');
  toast.innerHTML = `
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      ${type === 'success' ? '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>' : '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>'}
    </svg>
    <span>${msg}</span>
  `;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all .3s ease';
    setTimeout(() => toast.remove(), 320);
  }, duration);
}

/* ─── OTP Flow ─── */
function sendOTP() {
  const input = document.getElementById('mobile-input');
  const mobile = input.value.replace(/\D/g, '');
  if (mobile.length < 10) {
    showToast('Please enter a valid 10-digit mobile number.', 'error');
    input.focus();
    return;
  }
  state.mobile = '+63' + mobile;
  document.getElementById('otp-phone-display').textContent = state.mobile;
  document.getElementById('sms-phone-display').textContent = state.mobile;
  // Prefill mobile in profile
  const mobileDisplay = document.getElementById('patient-mobile-display');
  if (mobileDisplay) mobileDisplay.value = state.mobile;
  state.otpSent = true;
  goPatientStep(2);
  showToast('OTP sent to ' + state.mobile, 'success');
  // Auto-focus first OTP box
  setTimeout(() => {
    const boxes = document.querySelectorAll('.otp-box');
    if (boxes.length) boxes[0].focus();
  }, 350);
}

function verifyOTP() {
  const boxes = document.querySelectorAll('.otp-box');
  const code = Array.from(boxes).map(b => b.value).join('');
  if (code.length < 6) {
    showToast('Please enter the full 6-digit OTP code.', 'error');
    boxes.forEach(box => box.classList.add('error'));
    setTimeout(() => boxes.forEach(box => box.classList.remove('error')), 600);
    if (boxes[0]) boxes[0].focus();
    return;
  }
  boxes.forEach(box => box.classList.remove('error'));
  // Demo: any 6-digit code accepted
  showToast('OTP verified! Welcome.', 'success');
  updateProfileHeader(true);
  goPatientStep(3);
}

function updateProfileHeader(loggedIn) {
  const avatar = document.getElementById('profile-avatar-initial');
  const label  = document.getElementById('profile-btn-label');
  const loginItem   = document.getElementById('dd-login-item');
  const profileItem = document.getElementById('dd-profile-item');
  const bookingItem = document.getElementById('dd-booking-item');
  const queueItem   = document.getElementById('dd-queue-item');
  if (loggedIn) {
    const name = document.getElementById('patient-name')?.value || 'Patient';
    avatar.textContent = name.charAt(0).toUpperCase();
    label.textContent  = name.split(' ')[0];
    loginItem.style.display   = 'none';
    profileItem.style.display = '';
    bookingItem.style.display = '';
    queueItem.style.display   = '';
  } else {
    avatar.textContent = '?';
    label.textContent  = 'Login / Register';
    loginItem.style.display   = '';
    profileItem.style.display = 'none';
    bookingItem.style.display = 'none';
    queueItem.style.display   = 'none';
  }
}

/* ─── Profile ─── */
function saveProfileAndContinue() {
  const name = document.getElementById('patient-name').value.trim();
  if (!name) {
    showToast('Please enter your full name.', 'error');
    return;
  }
  const dob = document.getElementById('patient-dob').value;
  if (!dob) {
    showToast('Please select your date of birth.', 'error');
    return;
  }
  state.patient = {
    name,
    dob,
    sex:     document.getElementById('patient-sex').value,
    company: document.getElementById('patient-company').value,
    mobile:  state.mobile,
  };
  // Update summary header
  document.getElementById('sum-patient-name').textContent = name;
  document.getElementById('sum-patient-phone').textContent = state.mobile || '+63 —';
  // Update nav
  updateProfileHeader(true);
  goPatientStep(4);
}

/* ─── Services ─── */
const BASIC5_NAMES = ['Complete Blood Count (CBC)', 'Urinalysis', 'Fecalysis', 'Physical Examination', 'Chest X-Ray'];

let basic5On = true; // starts with all selected

function toggleService(el) {
  el.classList.toggle('selected');
  syncBasic5State();
  updateSummary();
}

function toggleBasic5() {
  const toggle = document.getElementById('basic5-toggle');
  basic5On = !basic5On;
  toggle.classList.toggle('on', basic5On);

  document.querySelectorAll('.service-item[data-basic="true"]').forEach(item => {
    if (basic5On) item.classList.add('selected');
    else         item.classList.remove('selected');
  });
  updateSummary();
}

function syncBasic5State() {
  const basicItems = document.querySelectorAll('.service-item[data-basic="true"]');
  const allSelected = Array.from(basicItems).every(i => i.classList.contains('selected'));
  basic5On = allSelected;
  document.getElementById('basic5-toggle').classList.toggle('on', allSelected);
}

function filterServices(btn, cat) {
  document.querySelectorAll('.service-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.service-item').forEach(item => {
    const itemCat = item.getAttribute('data-cat');
    if (cat === 'all' || itemCat === cat) {
      item.style.display = '';
    } else {
      item.style.display = 'none';
    }
  });
}

function updateSummary() {
  const items = document.querySelectorAll('.service-item');
  let total = 0;
  let rows = '';
  let serviceNames = [];

  items.forEach(el => {
    if (el.classList.contains('selected')) {
      const name  = el.getAttribute('data-name');
      const price = parseInt(el.getAttribute('data-price'), 10);
      total += price;
      serviceNames.push(name);
      rows += `<div class="summary-item"><span>${name}</span><span>₱${price}</span></div>`;
    }
  });

  state.selectedServices = serviceNames;
  const container = document.getElementById('summary-items');
  const empty     = document.getElementById('summary-empty');
  const totalEl   = document.getElementById('summary-total');

  container.innerHTML = rows;
  empty.style.display = rows ? 'none' : 'block';
  if (totalEl) totalEl.textContent = '₱' + total;
}

function confirmBooking() {
  if (!state.patient) {
    showToast('Please complete your profile first.', 'error');
    goPatientStep(3);
    return;
  }
  if (state.selectedServices.length === 0) {
    showToast('Please select at least one service.', 'error');
    return;
  }
  const datetime = document.getElementById('appt-datetime').value;
  if (!datetime) {
    showToast('Please pick a preferred date and time.', 'error');
    return;
  }

  // Generate queue number
  state.queueNum = Math.floor(Math.random() * 10) + 7;
  const queueStr = String(state.queueNum).padStart(3, '0');

  // Populate queue tracker
  document.getElementById('my-queue-num').textContent = queueStr;
  document.getElementById('my-queue-type').textContent = state.selectedServices.slice(0,2).join(' + ') + (state.selectedServices.length > 2 ? ' +more' : '');
  document.getElementById('ahead-count').textContent = Math.max(0, state.queueNum - 5);
  document.getElementById('est-wait').textContent = '~' + (Math.max(0, state.queueNum - 5) * 5);

  // Appointment detail table
  document.getElementById('appt-det-patient').textContent  = state.patient.name;
  document.getElementById('appt-det-services').textContent = state.selectedServices.join(', ');
  const dtLabel = new Date(datetime).toLocaleString('en-PH', { dateStyle: 'long', timeStyle: 'short' });
  document.getElementById('appt-det-date').textContent = dtLabel;
  document.getElementById('appt-det-queue').textContent = '#' + queueStr;

  showToast('Booking confirmed! Queue #' + queueStr, 'success');
  goPatientStep(5);
}

function cancelQueue() {
  if (confirm('Are you sure you want to cancel your queue?')) {
    state.queueNum = null;
    showToast('Queue cancelled.', 'default');
    goPatientStep(0);
  }
}

/* ─── OTP Box Auto-focus ─── */
document.addEventListener('DOMContentLoaded', () => {
  const boxes = document.querySelectorAll('.otp-box');
  boxes.forEach((box, i) => {
    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g, '');
      if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
    });
    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
    });
    box.addEventListener('paste', e => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
      boxes.forEach((b, j) => { if (pasted[j]) b.value = pasted[j]; });
      const last = Math.min(pasted.length, boxes.length) - 1;
      if (last >= 0) boxes[last].focus();
    });
  });

  // Initialize basic5 toggle state
  document.getElementById('basic5-toggle').classList.add('on');
  updateSummary();
  goPatientStep(0);
});

/* ─── Live Queue Simulation (landing page) ─── */
let liveWaiting = 9, liveDone = 4, liveNow = 5;
setInterval(() => {
  const el = document.getElementById('ahead-count');
  if (el && parseInt(el.textContent) > 0) {
    el.textContent = Math.max(0, parseInt(el.textContent) - 1);
    if (parseInt(el.textContent) === 0) {
      document.getElementById('up-next-val').textContent = "You're next!";
    }
  }
}, 10000);
