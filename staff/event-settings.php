<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireStaffLogin();

$message = '';
$messageType = 'success';

$events = $pdo->query(
    "SELECT id, title, description, event_date, start_time,
            end_time, venue, organizer, max_attendees
     FROM events
     ORDER BY event_date DESC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$selectedId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

if (!verifyCsrfToken(
    is_string($submittedToken) ? $submittedToken : null
)) {
    http_response_code(403);
    exit('Invalid or expired security token. Please refresh the page and try again.');
}
    $selectedId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '');
    $venue = trim($_POST['venue'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $maxAttendees = filter_var(
        $_POST['max_attendees'] ?? '',
        FILTER_VALIDATE_INT
    );

    if (!$selectedId || $title === '' || $eventDate === '' ||
        $startTime === '' || $endTime === '' || $venue === '' ||
        $maxAttendees === false || $maxAttendees < 1) {
        $message = 'Please complete all required fields with valid values.';
        $messageType = 'danger';
    } elseif ($endTime <= $startTime) {
        $message = 'The end time must be later than the start time.';
        $messageType = 'danger';
    } else {
        $check = $pdo->prepare(
            "SELECT id FROM events WHERE id = ? LIMIT 1"
        );
        $check->execute([$selectedId]);

        if (!$check->fetch()) {
            $message = 'The selected event was not found.';
            $messageType = 'danger';
        } else {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM attendees WHERE event_id = ?"
            );
            $countStmt->execute([$selectedId]);
            $registeredCount = (int) $countStmt->fetchColumn();

            if ($maxAttendees < $registeredCount) {
                $message = "Capacity cannot be lower than the current " .
                    $registeredCount . " registered attendee(s).";
                $messageType = 'danger';
            } else {
                $update = $pdo->prepare(
                    "UPDATE events
                     SET title = ?, description = ?, event_date = ?,
                         start_time = ?, end_time = ?, venue = ?,
                         organizer = ?, max_attendees = ?
                     WHERE id = ?"
                );

                $update->execute([
                    $title,
                    $description,
                    $eventDate,
                    $startTime,
                    $endTime,
                    $venue,
                    $organizer,
                    $maxAttendees,
                    $selectedId
                ]);

                $message = 'Event settings saved successfully.';
            }
        }
    }

    $events = $pdo->query(
        "SELECT id, title, description, event_date, start_time,
                end_time, venue, organizer, max_attendees
         FROM events
         ORDER BY event_date DESC, id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

$selectedEvent = null;

foreach ($events as $event) {
    if ((int) $event['id'] === (int) $selectedId) {
        $selectedEvent = $event;
        break;
    }
}

if (!$selectedEvent && !empty($events)) {
    $selectedEvent = $events[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    $selectedId && $selectedEvent) {
    $selectedEvent['title'] = trim($_POST['title'] ?? $selectedEvent['title']);
    $selectedEvent['description'] = trim(
        $_POST['description'] ?? ($selectedEvent['description'] ?? '')
    );
    $selectedEvent['event_date'] = $_POST['event_date'] ?? $selectedEvent['event_date'];
    $selectedEvent['start_time'] = $_POST['start_time'] ?? $selectedEvent['start_time'];
    $selectedEvent['end_time'] = $_POST['end_time'] ?? $selectedEvent['end_time'];
    $selectedEvent['venue'] = trim($_POST['venue'] ?? $selectedEvent['venue']);
    $selectedEvent['organizer'] = trim(
        $_POST['organizer'] ?? ($selectedEvent['organizer'] ?? '')
    );
    $selectedEvent['max_attendees'] = $_POST['max_attendees'] ??
        $selectedEvent['max_attendees'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Settings | Event-Pro</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <style>
        body { background: #f5f7fb; }
        .settings-card {
            background: #fff;
            border: 0;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">Event Settings</h2>
            <p class="text-muted mb-0">Manage seminar details and capacity.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            Back to Dashboard
        </a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($messageType) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($events)): ?>
        <div class="alert alert-info">No events were found in the database.</div>
    <?php else: ?>
        <form method="GET" class="settings-card p-3 mb-4">
            <label for="event_id" class="form-label">Select Event</label>
            <div class="d-flex gap-2">
                <select name="event_id" id="event_id" class="form-select">
                    <?php foreach ($events as $event): ?>
                        <option
                            value="<?= (int) $event['id'] ?>"
                            <?= (int) $event['id'] === (int) ($selectedEvent['id'] ?? 0) ? 'selected' : '' ?>
                        >
                            <?= e($event['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Load</button>
            </div>
        </form>

        <?php if ($selectedEvent): ?>
            <form method="POST" class="settings-card p-3 p-md-4">
                <?= csrfField() ?>
                <input
                    type="hidden"
                    name="event_id"
                    value="<?= (int) $selectedEvent['id'] ?>"
                >

                <div class="row g-3">
                    <div class="col-12">
                        <label for="title" class="form-label">Event Title *</label>
                        <input
                            id="title" name="title" class="form-control"
                            maxlength="255" required
                            value="<?= e($selectedEvent['title'] ?? '') ?>"
                        >
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea
                            id="description" name="description"
                            class="form-control" rows="4"
                        ><?= e($selectedEvent['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label for="event_date" class="form-label">Event Date *</label>
                        <input
                            type="date" id="event_date" name="event_date"
                            class="form-control" required
                            value="<?= e(substr((string) $selectedEvent['event_date'], 0, 10)) ?>"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="start_time" class="form-label">Start Time *</label>
                        <input
                            type="time" id="start_time" name="start_time"
                            class="form-control" required
                            value="<?= e(substr((string) $selectedEvent['start_time'], 0, 5)) ?>"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="end_time" class="form-label">End Time *</label>
                        <input
                            type="time" id="end_time" name="end_time"
                            class="form-control" required
                            value="<?= e(substr((string) $selectedEvent['end_time'], 0, 5)) ?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="venue" class="form-label">Venue *</label>
                        <input
                            id="venue" name="venue" class="form-control"
                            maxlength="255" required
                            value="<?= e($selectedEvent['venue'] ?? '') ?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="organizer" class="form-label">Organizer</label>
                        <input
                            id="organizer" name="organizer"
                            class="form-control" maxlength="255"
                            value="<?= e($selectedEvent['organizer'] ?? '') ?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="max_attendees" class="form-label">
                            Maximum Attendees *
                        </label>
                        <input
                            type="number" id="max_attendees"
                            name="max_attendees" class="form-control"
                            min="1" required
                            value="<?= e((string) $selectedEvent['max_attendees']) ?>"
                        >
                        <div class="form-text">
                            Capacity cannot be lower than existing registrations.
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        Save Event Settings
                    </button>
                    <a href="event-settings.php?event_id=<?= (int) $selectedEvent['id'] ?>"
                       class="btn btn-outline-secondary">
                        Reset Changes
                    </a>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
