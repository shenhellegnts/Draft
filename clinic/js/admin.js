/* ============================================================
   M.V. Masangkay Clinic — Admin Dashboard JS
   admin.js
   ============================================================ */

'use strict';

/* ─── Auth guard ─── */
(function() {
  if (sessionStorage.getItem('admin_logged_in') !== '1') {
    window.location.href = 'login.html';
  }
  const user = sessionStorage.getItem('admin_user') || 'admin';
  const capUser = user.charAt(0).toUpperCase() + user.slice(1);
  ['sidebar-avatar','admin-avatar-nav'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = capUser.charAt(0);
  });
  ['sidebar-username','admin-username-nav'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = capUser;
  });
})();

/* ─── Date header ─── */
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('dashboard-date');
  if (el) {
    el.textContent = new Date().toLocaleDateString('en-PH', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
  }
  renderQueue();
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
const queueData = {
  serving: { num: '005', name: 'Mark Villanueva', service: 'CBC Screening' },
  waiting: [
    { num: '006', name: 'Ana Reyes', service: 'Urinalysis, Fecalysis' },
    { num: '007', name: 'Franz Mendoza', service: 'Physical Exam, ECG' },
    { num: '008', name: 'Shamilelle Quantas', service: 'Hepatitis AB Screening' },
    { num: '009', name: 'Paulo Mendoza', service: 'Drug Test' },
    { num: '010', name: 'Maria Garcia', service: 'Basic 5 Package' },
  ],
  done: [
    { num: '001', name: 'Jose Rivera', service: 'Urinalysis' },
    { num: '002', name: 'Andrea Bautista', service: 'Basic 5 Package' },
    { num: '003', name: 'Carmen dela Rosa', service: 'CBC Screening' },
    { num: '004', name: 'Lisa Reyes', service: 'Chest X-Ray' },
  ],
};

function renderQueue() {
  // Now serving
  const s = queueData.serving;
  if (s) {
    document.getElementById('q-now-num').textContent = '#' + s.num;
    document.getElementById('q-now-name').textContent = s.name;
    document.getElementById('q-now-service').textContent = s.service;
  }

  // Waiting list
  const list = document.getElementById('queue-list');
  const empty = document.getElementById('empty-queue');
  const countLabel = document.getElementById('waiting-count-label');

  if (!list) return;

  if (queueData.waiting.length === 0) {
    list.innerHTML = '';
    if (empty) empty.style.display = 'block';
    if (countLabel) countLabel.textContent = '— queue is empty';
  } else {
    if (empty) empty.style.display = 'none';
    if (countLabel) countLabel.textContent = `— ${queueData.waiting.length} patient${queueData.waiting.length !== 1 ? 's' : ''} waiting`;

    const colors = ['#6366f1','#f59e0b','#ec4899','#10b981','#3b82f6','#8b5cf6'];
    list.innerHTML = queueData.waiting.map((p, i) => `
      <div class="queue-list-item">
        <div class="queue-num-badge">#${p.num}</div>
        <div class="queue-item-info">
          <div class="queue-item-name">${p.name}</div>
          <div class="queue-item-service">${p.service}</div>
        </div>
        <span class="tag orange">Waiting</span>
      </div>
    `).join('');
  }

  // Update badge
  const badge = document.getElementById('queue-badge');
  if (badge) badge.textContent = queueData.waiting.length;

  // Done list
  const doneList = document.getElementById('done-list');
  if (doneList) {
    doneList.innerHTML = queueData.done.map(p => `
      <div class="queue-list-item">
        <div class="queue-num-badge" style="background:#dcfce7;color:#16a34a;">#${p.num}</div>
        <div class="queue-item-info">
          <div class="queue-item-name">${p.name}</div>
          <div class="queue-item-service">${p.service}</div>
        </div>
        <span class="tag gray">Done</span>
      </div>
    `).join('');
  }

  // Update SMS manual dropdown
  const sel = document.getElementById('manual-sms-patient');
  if (sel) {
    sel.innerHTML = [queueData.serving, ...queueData.waiting].map(p =>
      `<option>${p.name} (#${p.num})</option>`
    ).join('');
  }
}

function callNextPatient() {
  if (!queueData.serving) {
    showToast('No patient is currently being served.', 'error');
    return;
  }
  const done = queueData.serving;
  queueData.done.push({ ...done });
  addSMSLog(done);

  if (queueData.waiting.length > 0) {
    queueData.serving = queueData.waiting.shift();
    showToast(`✓ ${done.name} marked done. Now calling #${queueData.serving.num} — ${queueData.serving.name}`, 'success');
  } else {
    queueData.serving = null;
    document.getElementById('q-now-num').textContent = '—';
    document.getElementById('q-now-name').textContent = 'Queue is empty';
    document.getElementById('q-now-service').textContent = '';
    showToast(`✓ ${done.name} marked done. Queue is now empty.`, 'success');
  }
  renderQueue();
}

function skipCurrent() {
  if (!queueData.serving) return;
  const skipped = queueData.serving;
  if (queueData.waiting.length > 0) {
    queueData.serving = queueData.waiting.shift();
    queueData.waiting.push(skipped); // put skipped at end
    showToast(`Skipped #${skipped.num}. Now serving #${queueData.serving.num}.`, 'default');
    renderQueue();
  } else {
    showToast('No next patient to skip to.', 'error');
  }
}

function resetQueueDemo() {
  queueData.serving = { num: '005', name: 'Mark Villanueva', service: 'CBC Screening' };
  queueData.waiting = [
    { num: '006', name: 'Ana Reyes', service: 'Urinalysis, Fecalysis' },
    { num: '007', name: 'Franz Mendoza', service: 'Physical Exam, ECG' },
    { num: '008', name: 'Shamilelle Quantas', service: 'Hepatitis AB Screening' },
    { num: '009', name: 'Paulo Mendoza', service: 'Drug Test' },
    { num: '010', name: 'Maria Garcia', service: 'Basic 5 Package' },
  ];
  queueData.done = [
    { num: '001', name: 'Jose Rivera', service: 'Urinalysis' },
    { num: '002', name: 'Andrea Bautista', service: 'Basic 5 Package' },
    { num: '003', name: 'Carmen dela Rosa', service: 'CBC Screening' },
    { num: '004', name: 'Lisa Reyes', service: 'Chest X-Ray' },
  ];
  renderQueue();
  showToast('Queue demo reset.', 'default');
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

function saveTemplate() { showToast('SMS template saved.', 'success'); }
function previewSMS() {
  const t = document.getElementById('sms-template')?.value || '';
  const preview = t.replace('[Patient Name]', 'Mark Villanueva').replace('[Queue #]', '#005');
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

function approveAppt(btn) {
  const row = btn.closest('tr');
  const statusCell = row.querySelector('.tag');
  if (statusCell) {
    statusCell.className = 'tag blue';
    statusCell.textContent = 'Confirmed';
    row.setAttribute('data-status', 'confirmed');
  }
  const actionsCell = row.querySelector('td:last-child');
  if (actionsCell) {
    actionsCell.innerHTML = '<button class="action-btn">Edit</button> <button class="btn btn-sm btn-danger" onclick="cancelAppt(this)">Cancel</button>';
  }
  showToast('Appointment approved!', 'success');
}

function cancelAppt(btn) {
  if (confirm('Cancel this appointment?')) {
    const row = btn.closest('tr');
    const statusCell = row.querySelector('.tag');
    if (statusCell) {
      statusCell.className = 'tag red';
      statusCell.textContent = 'Cancelled';
      row.setAttribute('data-status', 'cancelled');
    }
    const actionsCell = row.querySelector('td:last-child');
    if (actionsCell) actionsCell.innerHTML = '<button class="action-btn">View</button>';
    showToast('Appointment cancelled.', 'default');
  }
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
    sessionStorage.removeItem('admin_logged_in');
    sessionStorage.removeItem('admin_user');
    window.location.href = 'login.html';
  }
}
