<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaffLogin();
requireStaffRole(['admin']);

$errors = [];
$success = '';

$title = '';
$description = '';
$eventDate = '';
$startTime = '';
$endTime = '';
$venue = '';
$organizer = '';
$maxAttendees = '100';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session security token is invalid. Refresh the page and try again.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));
        $venue = trim((string) ($_POST['venue'] ?? ''));
        $organizer = trim((string) ($_POST['organizer'] ?? ''));
        $maxAttendees = trim((string) ($_POST['max_attendees'] ?? ''));

        if ($title === '' || $eventDate === '' || $startTime === ''
            || $endTime === '' || $venue === '' || $organizer === '') {
            $errors[] = 'Please complete all required fields.';
        }

        if (mb_strlen($title) > 255) {
            $errors[] = 'Event title must not exceed 255 characters.';
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
            $errors[] = 'Maximum attendees must be a whole number greater than zero.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO events
                    (title, description, event_date, start_time, end_time,
                     venue, organizer, max_attendees, registration_open, is_active)
                    VALUES
                    (:title, :description, :event_date, :start_time, :end_time,
                     :venue, :organizer, :max_attendees, 1, 1)'
                );

                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'event_date' => $eventDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'venue' => $venue,
                    'organizer' => $organizer,
                    'max_attendees' => $capacity,
                ]);

                $newEventId = (int) $pdo->lastInsertId();

                logActivity(
                    $pdo,
                    'event_created',
                    currentStaffId(),
                    $newEventId,
                    'Created event: ' . $title
                );

                $success = 'Event created successfully.';

                $title = '';
                $description = '';
                $eventDate = '';
                $startTime = '';
                $endTime = '';
                $venue = '';
                $organizer = '';
                $maxAttendees = '100';
            } catch (Throwable $exception) {
                error_log('Event creation failed: ' . $exception->getMessage());
                $errors[] = 'Unable to create the event. Please check the information and try again.';
            }
        }
    }
}

$eventsStmt = $pdo->query(
    'SELECT e.*,
        (SELECT COUNT(*) FROM attendees a WHERE a.event_id = e.id) AS registered_count
     FROM events e
     ORDER BY e.event_date DESC, e.id DESC'
);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Events | Event-Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <style>
        body {
            background: #f5f7fb;
        }
        .page-header {
            margin-bottom: 24px;
        }
        .content-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .05);
        }
        .form-label {
            font-weight: 600;
        }
        .table th,
        .table td {
            vertical-align: middle;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
    </style>
</head>
<body>
<main class="container py-4 py-lg-5">

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">Manage Events</h1>
            <p class="text-muted mb-0">Create, edit, and manage seminar events.</p>
        </div>
        <a href="../staff/dashboard.php" class="btn btn-outline-secondary">
            Back to Dashboard
        </a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="alert">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Please correct the following:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="card content-card mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-4">Create New Event</h2>

            <form method="POST" action="manage-events.php">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="title" class="form-label">Event Title *</label>
                    <input type="text" id="title" name="title"
                           class="form-control" maxlength="255" required
                           value="<?= e($title) ?>"
                           placeholder="Enter event title">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description"
                              class="form-control" rows="3"
                              placeholder="Describe the event"><?= e($description) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="event_date" class="form-label">Event Date *</label>
                        <input type="date" id="event_date" name="event_date"
                               class="form-control" required
                               value="<?= e($eventDate) ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="start_time" class="form-label">Start Time *</label>
                        <input type="time" id="start_time" name="start_time"
                               class="form-control" required
                               value="<?= e($startTime) ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="end_time" class="form-label">End Time *</label>
                        <input type="time" id="end_time" name="end_time"
                               class="form-control" required
                               value="<?= e($endTime) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="venue" class="form-label">Venue *</label>
                    <input type="text" id="venue" name="venue"
                           class="form-control" maxlength="255" required
                           value="<?= e($venue) ?>"
                           placeholder="Enter venue">
                </div>

                <div class="mb-3">
                    <label for="organizer" class="form-label">Organizer *</label>
                    <input type="text" id="organizer" name="organizer"
                           class="form-control" maxlength="255" required
                           value="<?= e($organizer) ?>"
                           placeholder="Enter organizer">
                </div>

                <div class="mb-4">
                    <label for="max_attendees" class="form-label">
                        Maximum Attendees *
                    </label>
                    <input type="number" id="max_attendees" name="max_attendees"
                           class="form-control" min="1" required
                           value="<?= e($maxAttendees) ?>">
                </div>

                <button type="submit" class="btn btn-primary">
                    Create Event
                </button>
            </form>
        </div>
    </section>

    <section class="card content-card">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">Existing Events</h2>
                <span class="badge text-bg-secondary">
                    <?= count($events) ?> event(s)
                </span>
            </div>

            <?php if (!$events): ?>
                <div class="alert alert-info mb-0">
                    No events have been created yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Registrations</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $event['title']) ?></strong>
                                    <div class="small text-muted">
                                        <?= e(substr((string) $event['start_time'], 0, 5)) ?>
                                        –
                                        <?= e(substr((string) $event['end_time'], 0, 5)) ?>
                                    </div>
                                </td>

                                <td><?= e((string) $event['event_date']) ?></td>

                                <td><?= e((string) $event['venue']) ?></td>

                                <td>
                                    <?= (int) $event['registered_count'] ?>
                                    / <?= (int) $event['max_attendees'] ?>
                                </td>

                                <td>
                                    <?php if (!(int) $event['is_active']): ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php elseif (!(int) $event['registration_open']): ?>
                                        <span class="badge text-bg-warning">Registration Closed</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">Registration Open</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="action-buttons">
                                        <a href="edit-event.php?id=<?= (int) $event['id'] ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>

                                        <form method="POST" action="delete-event.php"
                                              class="d-inline"
                                              onsubmit="return confirm('Are you sure you want to delete this event? This action cannot be undone.');">
                                            <?= csrfField() ?>

                                            <input type="hidden" name="event_id"
                                                   value="<?= (int) $event['id'] ?>">

                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>
