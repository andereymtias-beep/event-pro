<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaffLogin();
requireStaffRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed. Please delete events through the admin page.');
}

$eventId = filter_var(
    $_POST['event_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$eventId) {
    http_response_code(400);
    exit('Invalid event ID.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid security token. Return to Manage Events and try again.');
}

$message = '';
$messageType = 'danger';

try {
    $pdo->beginTransaction();

    $eventStmt = $pdo->prepare(
        'SELECT id, title FROM events WHERE id = :id FOR UPDATE'
    );
    $eventStmt->execute(['id' => $eventId]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $pdo->rollBack();
        http_response_code(404);
        $message = 'The event could not be found. It may already have been deleted.';
    } else {
        $attendeeStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM attendees WHERE event_id = :id'
        );
        $attendeeStmt->execute(['id' => $eventId]);
        $attendeeCount = (int) $attendeeStmt->fetchColumn();

        $attendanceStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM attendance WHERE event_id = :id'
        );
        $attendanceStmt->execute(['id' => $eventId]);
        $attendanceCount = (int) $attendanceStmt->fetchColumn();

        if ($attendeeCount > 0 || $attendanceCount > 0) {
            $pdo->rollBack();
            $message = 'This event cannot be deleted because it has existing registrations or attendance records. Your data has been preserved.';
        } else {
            $deleteStmt = $pdo->prepare(
                'DELETE FROM events WHERE id = :id'
            );
            $deleteStmt->execute(['id' => $eventId]);

            $pdo->commit();

            $messageType = 'success';
            $message = 'Event "' . (string) $event['title'] . '" was deleted successfully.';
        }
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Event deletion failed: ' . $exception->getMessage());

    http_response_code(500);
    $message = 'The event could not be deleted. It may be referenced by other records. No successful deletion was confirmed.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete Event | Event-Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5" style="max-width: 700px;">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Delete Event</h1>

                <div class="alert alert-<?= e($messageType) ?>" role="alert">
                    <?= e($message) ?>
                </div>

                <a href="manage-events.php" class="btn btn-primary">
                    Return to Manage Events
                </a>
            </div>
        </div>
    </main>
</body>
</html>
