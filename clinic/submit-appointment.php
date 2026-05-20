<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$mobile = preg_replace('/\D+/', '', $input['mobile'] ?? '');
if (strlen($mobile) !== 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mobile must be 10 digits.']);
    exit;
}
$mobile = '+63' . substr($mobile, -10);
$name = trim($input['name'] ?? '');
$dob = trim($input['dob'] ?? '');
$sex = trim($input['sex'] ?? 'Male');
$company = trim($input['company'] ?? '');
$services = $input['services'] ?? [];
$custom = trim($input['custom_service'] ?? '');
$preferredDate = trim($input['preferred_date'] ?? '');

if ($name === '' || $dob === '' || empty($services) || $preferredDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
    exit;
}

$allowedSex = ['Female', 'Male', 'Other'];
if (!in_array($sex, $allowedSex, true)) {
    $sex = 'Male';
}

$services = array_filter(array_map('trim', $services));
if ($custom !== '') {
    $services[] = $custom;
}
$servicesText = implode(', ', $services);

$patient = db_row('SELECT id FROM patients WHERE mobile = ? LIMIT 1', 's', [$mobile]);
if ($patient) {
    db_query('UPDATE patients SET name = ?, dob = ?, sex = ?, company = ? WHERE id = ?', 'ssssi', [$name, $dob, $sex, $company, $patient['id']]);
    $patientId = $patient['id'];
} else {
    db_query('INSERT INTO patients (name, mobile, dob, sex, company) VALUES (?, ?, ?, ?, ?)', 'sssss', [$name, $mobile, $dob, $sex, $company]);
    $patientId = db_insert_id();
}

$today = date('Y-m-d');
$queueCount = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE DATE(created_at) = ?', 's', [$today]);
$nextQueue = ($queueCount['cnt'] ?? 0) + 1;
$queueNumber = str_pad($nextQueue, 3, '0', STR_PAD_LEFT);
$preferredDateTime = date('Y-m-d 00:00:00', strtotime($preferredDate));

$result = db_query('INSERT INTO appointments (patient_id, patient_name, patient_mobile, services, preferred_date, status, queue_number) VALUES (?, ?, ?, ?, ?, ?, ?)', 'issssss', [$patientId, $name, $mobile, $servicesText, $preferredDateTime, 'pending', $queueNumber]);
if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save appointment.']);
    exit;
}

$appointmentId = db_insert_id();
$peopleAheadRow = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE DATE(created_at) = ? AND status = ? AND id < ?', 'ssi', [$today, 'pending', $appointmentId]);
$peopleAhead = $peopleAheadRow['cnt'] ?? 0;
$totalTodayRow = db_row('SELECT COUNT(*) AS cnt FROM appointments WHERE DATE(created_at) = ?', 's', [$today]);
$totalToday = $totalTodayRow['cnt'] ?? 0;

$nextAppointments = [];
$appointmentRes = db_query('SELECT queue_number, patient_name, services FROM appointments WHERE DATE(created_at) = ? AND status = ? ORDER BY preferred_date ASC, created_at ASC LIMIT 2', 'ss', [$today, 'pending']);
if ($appointmentRes) {
    while ($row = $appointmentRes->fetch_assoc()) {
        $nextAppointments[] = $row;
    }
}

$nowServing = null;
$upNext = null;
if (count($nextAppointments) > 0) {
    $nowServing = $nextAppointments[0];
}
if (count($nextAppointments) > 1) {
    $upNext = $nextAppointments[1];
}

$response = [
    'success' => true,
    'queue_number' => $queueNumber,
    'patient_name' => $name,
    'services' => $servicesText,
    'preferred_date' => date('F j, Y', strtotime($preferredDate)),
    'people_ahead' => $peopleAhead,
    'estimated_wait' => max(0, $peopleAhead * 5),
    'total_today' => $totalToday,
    'now_serving' => $nowServing,
    'up_next' => $upNext,
];

echo json_encode($response);
