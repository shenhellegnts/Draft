/* ============================================================
   M.V. Masangkay Clinic — Admin Dashboard JS
   admin.js
   ============================================================ */

'use strict';

/* ─── Date header ─── */
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('dashboard-date');
  if (el) {
    el.textContent = new Date().toLocaleDateString('en-PH', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
  }
});

/* ─── Section switching ─── */
function showSection(name, clickedEl) {
  document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
  const section = document.getElementById('section-' + name);
  if (section) section.classList.add('active');

  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  if (clickedEl) {
    clickedEl.classList.add('active');
  } else {
    // Auto-highlight matching nav item
    document.querySelectorAll('.nav-item').forEach(n => {
      const onclick = n.getAttribute('onclick') || '';
      if (onclick.includes("'" + name + "'")) n.classList.add('active');
    });
  }

  // Close sidebar on mobile
  if (window.innerWidth <= 768) closeSidebar();

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ─── Sidebar toggle ─── */
function toggleSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebar-overlay');
  const isOpen   = sidebar.classList.contains('open');
  if (isOpen) {
    sidebar.classList.remove('open');
    overlay.classList.remove('visible');
  } else {
    sidebar.classList.add('open');
    overlay.classList.add('visible');
  }
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('visible');
}

/* ─── Toast ─── */
function showToast(msg, type = 'default') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = 'toast' + (type !== 'default' ? ' ' + type : '');
  const icon = type === 'success'
    ? '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'
    : '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>';
  toast.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${icon}</svg><span>${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(8px)';
    toast.style.transition = 'all .3s ease';
    setTimeout(() => toast.remove(), 320);
  }, 3500);
}

/* ─── Queue system ─── */
function updateQueueWidgets() {
  const waitingCount = document.querySelectorAll('#queue-list .queue-list-item').length;
  const badge = document.getElementById('queue-badge');
  if (badge) badge.textContent = waitingCount;
}

function callNextPatient() {
  fetch('queue-action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'done' })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) {
      showToast(data.message || 'Unable to advance the queue.', 'error');
      return;
    }
    showToast('Patient marked done. Calling next patient.', 'success');
    setTimeout(() => window.location.reload(), 300);
  })
  .catch(() => showToast('Unable to advance the queue.', 'error'));
}

function skipCurrent() {
  fetch('queue-action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'skip' })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) {
      showToast(data.message || 'Unable to skip the current patient.', 'error');
      return;
    }
    showToast('Current patient moved to end of queue.', 'success');
    setTimeout(() => window.location.reload(), 300);
  })
  .catch(() => showToast('Unable to skip the current patient.', 'error'));
}

function resetQueueDemo() {
  showToast('Reset is not available when using live database data.', 'default');
}

/* ─── SMS Log ─── */
let smsSentCount = 13;

function addSMSLog(patient) {
  const table = document.getElementById('sms-log-table');
  if (!table) return;
  const tbody = table.querySelector('tbody');
  smsSentCount++;
  const now = new Date().toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' });
  const template = document.getElementById('sms-template')?.value || 'Hello [Patient Name], malapit na po ang inyong turn sa M.V. Masangkay Clinic.';
  const msg = template.replace('[Patient Name]', patient.name).replace('[Queue #]', '#' + patient.num);
  const initials = patient.name.split(' ').map(n=>n[0]).join('').slice(0,2).toUpperCase();
  const colors = ['#6366f1','#f59e0b','#ec4899','#10b981','#3b82f6','#8b5cf6','#06b6d4'];
  const color = colors[smsSentCount % colors.length];
  const row = document.createElement('tr');
  row.innerHTML = `
    <td><div class="patient-name-cell"><div class="patient-dot" style="background:${color};font-size:10px;">${initials}</div>${patient.name}</div></td>
    <td style="color:var(--gray-500);">+63912345${(smsSentCount+1000).toString().slice(-4)}</td>
    <td><span class="tag blue">Now Serving</span></td>
    <td style="max-width:260px;color:var(--gray-500);font-size:12px;">${msg}</td>
    <td><span class="sms-status sent">Sent</span></td>
    <td style="color:var(--gray-500);">Apr 22, ${now}</td>
  `;
  tbody.insertBefore(row, tbody.firstChild);
  document.getElementById('sms-sent-count').textContent = smsSentCount;
}

function saveTemplate() {
  const t = document.getElementById('sms-template')?.value || '';
  fetch('save-settings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ setting_key: 'sms_template', setting_value: t })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast('SMS template saved successfully.', 'success');
    } else {
      showToast('Unable to save SMS template.', 'error');
    }
  })
  .catch(() => showToast('Unable to save SMS template.', 'error'));
}
function previewSMS() {
  const t = document.getElementById('sms-template')?.value || '';
  const preview = t.replace('[Patient Name]', 'Patient Name').replace('[Queue #]', '#005');
  alert('SMS Preview:\n\n' + preview);
}
function sendManualSMS() {
  const sel = document.getElementById('manual-sms-patient');
  if (sel) showToast('SMS sent to ' + sel.value, 'success');
}

/* ─── Appointments ─── */
function filterAppts(btn, status) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const rows = document.querySelectorAll('#appt-table-body tr');
  rows.forEach(row => {
    if (status === 'all' || row.getAttribute('data-status') === status) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

function filterApptsSearch(query) {
  const rows = document.querySelectorAll('#appt-table-body tr');
  const q = query.toLowerCase();
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(q) ? '' : 'none';
  });
}

function approveAppt(btn, appointmentId) {
  fetch('appointment-action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approve', appointment_id: appointmentId })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) {
      showToast(data.message || 'Unable to approve appointment.', 'error');
      return;
    }
    showToast('Appointment approved.', 'success');
    setTimeout(() => window.location.reload(), 300);
  })
  .catch(() => showToast('Unable to approve appointment.', 'error'));
}

function cancelAppt(btn, appointmentId) {
  if (!confirm('Cancel this appointment?')) return;
  fetch('appointment-action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'cancel', appointment_id: appointmentId })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) {
      showToast(data.message || 'Unable to cancel appointment.', 'error');
      return;
    }
    showToast('Appointment cancelled.', 'default');
    setTimeout(() => window.location.reload(), 300);
  })
  .catch(() => showToast('Unable to cancel appointment.', 'error'));
}

/* ─── Patient search ─── */
function filterPatients(query) {
  const rows = document.querySelectorAll('#patient-table tbody tr');
  const q = query.toLowerCase();
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(q) ? '' : 'none';
  });
}

/* ─── Services ─── */
function openAddService() {
  const name = prompt('Service name:');
  if (!name) return;
  const price = prompt('Price (₱):');
  if (!price) return;
  showToast('Service "' + name + '" added (UI only — connect PHP to save to DB).', 'success');
}

/* ─── Logout ─── */
function adminLogout() {
  if (confirm('Sign out of admin panel?')) {
    window.location.href = 'logout.php';
  }
}
