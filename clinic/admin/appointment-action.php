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
$appointmentId = intval($input['appointment_id'] ?? 0);
if ($appointmentId <= 0 || !in_array($action, ['approve', 'cancel'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$appointment = db_row('SELECT id, status FROM appointments WHERE id = ? LIMIT 1', 'i', [$appointmentId]);
if (!$appointment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

if ($action === 'approve') {
    db_query('UPDATE appointments SET status = ? WHERE id = ?', 'si', ['confirmed', $appointmentId]);
    echo json_encode(['success' => true, 'status' => 'confirmed']);
    exit;
}

if ($action === 'cancel') {
    db_query('UPDATE appointments SET status = ? WHERE id = ?', 'si', ['cancelled', $appointmentId]);
    echo json_encode(['success' => true, 'status' => 'cancelled']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unhandled action']);
