<?php
// clinic/db.php
// Update these credentials for your local phpMyAdmin/MySQL setup.
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinic_db';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Database connection failed: " . htmlspecialchars($mysqli->connect_error);
    exit;
}
$mysqli->set_charset('utf8mb4');

function db_query($query, $types = null, $params = []) {
    global $mysqli;
    $stmt = $mysqli->prepare($query);
    if ($stmt === false) {
        return false;
    }
    if ($types !== null && count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        return false;
    }
    $result = $stmt->get_result();
    return $result === false ? $stmt : $result;
}

function db_row($query, $types = null, $params = []) {
    $result = db_query($query, $types, $params);
    if ($result === false || !method_exists($result, 'fetch_assoc')) {
        return false;
    }
    return $result->fetch_assoc();
}

function db_escape($value) {
    global $mysqli;
    return $mysqli->real_escape_string($value);
}

function db_insert_id() {
    global $mysqli;
    return $mysqli->insert_id;
}
