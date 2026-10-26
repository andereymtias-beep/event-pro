<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaffLogin();
requireStaffRole(['admin']);

$pdo = $pdo ?? null;

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$eventId || $eventId < 1) {
    http_response_code(400);
    exit('Invalid event ID.');
}

$stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$errors = [];
$success = '';

$title = (string) $event['title'];
$description = (string) ($event['description'] ?? '');
$eventDate = (string) $event['event_date'];
$startTime = substr((string) $event['start_time'], 0, 5);
$endTime = substr((string) $event['end_time'], 0, 5);
$venue = (string) $event['venue'];
$organizer = (string) $event['organizer'];
$maxAttendees = (string) $event['max_attendees'];
$registrationOpen = (int) $event['registration_open'];
$isActive = (int) $event['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));
        $venue = trim((string) ($_POST['venue'] ?? ''));
        $organizer = trim((string) ($_POST['organizer'] ?? ''));
        $maxAttendees = trim((string) ($_POST['max_attendees'] ?? ''));
        $registrationOpen = isset($_POST['registration_open']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $venue === '' || $organizer === '') {
            $errors[] = 'Title, venue, and organizer are required.';
        }

        if (mb_strlen($title) > 255) {
            $errors[] = 'The event title must not exceed 255 characters.';
        }

        if (mb_strlen($venue) > 255 || mb_strlen($organizer) > 255) {
            $errors[] = 'Venue and organizer must not exceed 255 characters.';
        }

        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDate);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $eventDate) {
            $errors[] = 'Please enter a valid event date.';
        }

        $validTime = static function (string $time): bool {
            return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
        };

        if (!$validTime($startTime) || !$validTime($endTime)) {
            $errors[] = 'Please enter valid start and end times.';
        } elseif ($endTime <= $startTime) {
            $errors[] = 'The end time must be later than the start time.';
        }

        $capacity = filter_var($maxAttendees, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($capacity === false) {
            $errors[] = 'Capacity must be a whole number greater than zero.';
        }

        if (!$errors) {
            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM attendees WHERE event_id = :event_id'
            );
            $countStmt->execute(['event_id' => $eventId]);
            $registeredCount = (int) $countStmt->fetchColumn();

            if ($capacity < $registeredCount) {
                $errors[] = "Capacity cannot be less than the {$registeredCount} existing registrations.";
            }
        }

        if (!$errors) {
            try {
                $update = $pdo->prepare(
                    'UPDATE events
                     SET title = :title,
                         description = :description,
                         event_date = :event_date,
                         start_time = :start_time,
                         end_time = :end_time,
                         venue = :venue,
                         organizer = :organizer,
                         max_attendees = :max_attendees,
                         registration_open = :registration_open,
                         is_active = :is_active
                     WHERE id = :id'
                );

                $update->execute([
                    'title' => $title,
                    'description' => $description,
                    'event_date' => $eventDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'venue' => $venue,
                    'organizer' => $organizer,
                    'max_attendees' => $capacity,
                    'registration_open' => $registrationOpen,
                    'is_active' => $isActive,
                    'id' => $eventId,
                ]);

                logActivity(
                    $pdo,
                    'event_updated',
                    currentStaffId(),
                    (int) $eventId,
                    'Updated event: ' . $title
                );

                $success = 'Event updated successfully.';

                $stmt = $pdo->prepare(
                    'SELECT * FROM events WHERE id = :id LIMIT 1'
                );
                $stmt->execute(['id' => $eventId]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $exception) {
                error_log('Event update failed: ' . $exception->getMessage());
                $errors[] = 'Unable to update the event. Please try again.';
            }
        }
    }
}

$registrationCountStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM attendees WHERE event_id = :event_id'
);
$registrationCountStmt->execute(['event_id' => $eventId]);
$registrationCount = (int) $registrationCountStmt->fetchColumn();

function editEventValue(string $key, array $event, string $fallback = ''): string
{
    return e($fallback !== '' ? $fallback : (string) ($event[$key] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Event | Event-Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; }
        .page-card { max-width: 900px; margin: 32px auto; }
        .card { border: 0; border-radius: 14px; }
        .form-label { font-weight: 600; }
    </style>
</head>
<body>
<div class="container page-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Event</h1>
            <p class="text-muted mb-0">Update event details and registration settings.</p>
        </div>
        <a href="manage-events.php" class="btn btn-outline-secondary">Back to Events</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Please correct the following:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="alert alert-info">
                Current registrations: <strong><?= $registrationCount ?></strong>
            </div>

            <form method="POST" action="edit-event.php?id=<?= (int) $eventId ?>">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="title" class="form-label">Event Title *</label>
                    <input id="title" name="title" type="text" class="form-control"
                           maxlength="255" required
                           value="<?= e($title) ?>">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control"
                              rows="4"><?= e($description) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="event_date" class="form-label">Event Date *</label>
                        <input id="event_date" name="event_date" type="date"
                               class="form-control" required
                               value="<?= e($eventDate) ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="start_time" class="form-label">Start Time *</label>
                        <input id="start_time" name="start_time" type="time"
                               class="form-control" required
                               value="<?= e($startTime) ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="end_time" class="form-label">End Time *</label>
                        <input id="end_time" name="end_time" type="time"
                               class="form-control" required
                               value="<?= e($endTime) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="venue" class="form-label">Venue *</label>
                    <input id="venue" name="venue" type="text" class="form-control"
                           maxlength="255" required
                           value="<?= e($venue) ?>">
                </div>

                <div class="mb-3">
                    <label for="organizer" class="form-label">Organizer *</label>
                    <input id="organizer" name="organizer" type="text" class="form-control"
                           maxlength="255" required
                           value="<?= e($organizer) ?>">
                </div>

                <div class="mb-4">
                    <label for="max_attendees" class="form-label">Maximum Attendees *</label>
                    <input id="max_attendees" name="max_attendees" type="number"
                           class="form-control" min="<?= max(1, $registrationCount) ?>"
                           required value="<?= e($maxAttendees) ?>">
                    <div class="form-text">
                        Capacity cannot be lower than the current registration count.
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox"
                           id="registration_open" name="registration_open" value="1"
                           <?= $registrationOpen ? 'checked' : '' ?>>
                    <label class="form-check-label" for="registration_open">
                        Registration Open
                    </label>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox"
                           id="is_active" name="is_active" value="1"
                           <?= $isActive ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">
                        Event Active
                    </label>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="manage-events.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
