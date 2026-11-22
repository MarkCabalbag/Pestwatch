<?php
// receive.php
// Receives POST data from Arduino and stores in database
// Place this file at: htdocs/iot/receive.php  (XAMPP)

header('Content-Type: application/json');

// FIX #3: Allow Arduino (non-browser) to POST — disable strict origin checks
// If you have CORS issues from a browser-based test, add:
// header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';   // FIX #4: use __DIR__ so path works regardless of where PHP is called from

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// FIX #5: sanitize all string inputs to prevent injection
$temp      = isset($_POST['temp'])      ? floatval($_POST['temp'])                        : null;
$humidity  = isset($_POST['humidity'])  ? floatval($_POST['humidity'])                    : null;
$pest      = isset($_POST['pest'])      ? intval($_POST['pest'])                          : 0;
$sender    = isset($_POST['sender'])    ? substr(trim($_POST['sender']),    0, 20)         : 'SIM800L Module';
$recipient = isset($_POST['recipient']) ? substr(trim($_POST['recipient']), 0, 20)         : 'Unknown';

if ($temp === null || $humidity === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: temp and humidity are required']);
    exit;
}

// FIX #6: sanity-check sensor ranges so garbage values are rejected
if ($temp < -40 || $temp > 80) {
    http_response_code(422);
    echo json_encode(['error' => 'Temperature out of valid range (-40 to 80 °C)']);
    exit;
}
if ($humidity < 0 || $humidity > 100) {
    http_response_code(422);
    echo json_encode(['error' => 'Humidity out of valid range (0–100%)']);
    exit;
}
$pest = ($pest === 1) ? 1 : 0;  // force boolean-safe tinyint

try {
    $stmt = $pdo->prepare("
        INSERT INTO sensor_logs (temperature, humidity, pest_detected, sender, recipient, created_at)
        VALUES (:temp, :humidity, :pest, :sender, :recipient, NOW())
    ");
    $stmt->execute([
        ':temp'      => $temp,
        ':humidity'  => $humidity,
        ':pest'      => $pest,
        ':sender'    => $sender,
        ':recipient' => $recipient,
    ]);

    $insertedId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Data stored',
        'id'      => $insertedId,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    // FIX #7: Don't expose raw DB errors to the network in production
    // Log the real error server-side; return a safe message
    error_log('[PestWatch] DB error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error — check server logs']);
}
