<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, bool $success, string $message): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ]);
    exit;
}

requireStaffLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Only POST requests are allowed.');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respond(400, false, 'Invalid request data.');
}

$token = $input['token'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    respond(400, false, 'Invalid QR code.');
}

if (!is_string($csrfToken) || !hash_equals(csrfToken(), $csrfToken)) {
    respond(403, false, 'Security token expired. Refresh the scanner page.');
}

$tokenHash = hash('sha256', $token);
$staffId = currentStaffId();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id, event_id, full_name, qr_token_hash
         FROM attendees
         WHERE qr_token_hash = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$tokenHash]);
    $attendee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendee) {
        $pdo->rollBack();
        respond(404, false, 'QR code not recognized. Check the attendee registration.');
    }

    $eventStmt = $pdo->prepare(
    'SELECT id, title
     FROM events
     WHERE id = ?
     LIMIT 1'
);
    $eventStmt->execute([$attendee['event_id']]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $pdo->rollBack();
        respond(404, false, 'The attendee event could not be found.');
    }

    $attendanceStmt = $pdo->prepare(
        'SELECT id
         FROM attendance
         WHERE event_id = ? AND attendee_id = ?
         LIMIT 1'
    );
    $attendanceStmt->execute([
        $attendee['event_id'],
        $attendee['id'],
    ]);

    if ($attendanceStmt->fetch()) {
        $pdo->rollBack();
        respond(409, false, 'This attendee has already been checked in.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO attendance (event_id, attendee_id, checked_in_by)
         VALUES (?, ?, ?)'
    );
    $insert->execute([
        $attendee['event_id'],
        $attendee['id'],
        $staffId,
    ]);

    logActivity(
        $pdo,
        'attendee_check_in',
        $staffId,
        (int) $attendee['event_id'],
        'Checked in attendee ID ' . (int) $attendee['id']
    );

    $pdo->commit();

    respond(
        200,
        true,
        'Check-in successful: ' . $attendee['full_name']
    );
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000') {
        respond(409, false, 'This attendee has already been checked in.');
    }

    error_log('Event-Pro check-in database error: ' . $e->getMessage());
    respond(500, false, 'A database error occurred. Please try again.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Event-Pro check-in error: ' . $e->getMessage());
    respond(500, false, 'Check-in failed. Please try again.');
}
