<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = trim($input['action'] ?? '');
if (!in_array($action, ['done', 'skip'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid queue action']);
    exit;
}

$current = db_row('SELECT * FROM appointments WHERE status = ? ORDER BY preferred_date ASC, created_at ASC LIMIT 1', 's', ['pending']);
if (!$current) {
    echo json_encode(['success' => false, 'message' => 'No pending patient in queue']);
    exit;
}

if ($action === 'done') {
    db_query('UPDATE appointments SET status = ? WHERE id = ?', 'si', ['done', $current['id']]);
    $next = db_row('SELECT * FROM appointments WHERE status = ? ORDER BY preferred_date ASC, created_at ASC LIMIT 1', 's', ['pending']);
    if ($next) {
        $settingsRow = db_row('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1', 's', ['sms_template']);
        $template = $settingsRow['setting_value'] ?? 'Hello [Patient Name], malapit na po ang inyong turn sa M.V. Masangkay Clinic.';
        $message = str_replace('[Patient Name]', $next['patient_name'], $template);
        $message = str_replace('[Queue #]', '#' . $next['queue_number'], $message);
        db_query('INSERT INTO sms_logs (appointment_id, patient_name, patient_mobile, message, status) VALUES (?, ?, ?, ?, ?)', 'issss', [$next['id'], $next['patient_name'], $next['patient_mobile'], $message, 'sent']);
    }
    echo json_encode(['success' => true, 'next' => $next ?? null]);
    exit;
}

if ($action === 'skip') {
    $pendingCount = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE status = ? AND id != ?', 'si', ['pending', $current['id']]);
    if (($pendingCount['cnt'] ?? 0) === 0) {
        echo json_encode(['success' => false, 'message' => 'No other patients to skip to']);
        exit;
    }
    db_query('UPDATE appointments SET created_at = NOW() WHERE id = ?', 'i', [$current['id']]);
    $next = db_row('SELECT * FROM appointments WHERE status = ? ORDER BY preferred_date ASC, created_at ASC LIMIT 1', 's', ['pending']);
    echo json_encode(['success' => true, 'next' => $next ?? null]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unhandled action']);
