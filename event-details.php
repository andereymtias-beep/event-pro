<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$eventId = ($eventId !== false && $eventId !== null && $eventId > 0)
    ? $eventId
    : 1;

$stmt = $pdo->prepare(
    'SELECT e.*,
        (SELECT COUNT(*) FROM attendees a
         WHERE a.event_id = e.id) AS registered_count
     FROM events e
     WHERE e.id = :event_id
     LIMIT 1'
);

$stmt->execute(['event_id' => $eventId]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$registered = (int) $event['registered_count'];
$capacity = (int) $event['max_attendees'];
$available = max(0, $capacity - $registered);

$isOpen = (bool) $event['registration_open']
    && (bool) $event['is_active']
    && $available > 0;

$status = !(bool) $event['is_active']
    ? 'Inactive'
    : (!$isOpen
        ? 'Closed'
        : ($available <= max(1, (int) ceil($capacity * 0.1))
            ? 'Almost Full'
            : 'Open'));

$date = date('F j, Y', strtotime((string) $event['event_date']));
$startTime = date('g:i A', strtotime((string) $event['start_time']));
$endTime = !empty($event['end_time'])
    ? date('g:i A', strtotime((string) $event['end_time']))
    : null;

$percentage = $capacity > 0
    ? min(100, ($registered / $capacity) * 100)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($event['title']) ?> | Event-Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.html">Event-Pro</a>
        <a class="btn btn-outline-primary" href="index.html">Back to Home</a>
    </div>
</nav>

<main class="container py-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="content-card h-100">
                <span class="section-label">EVENT DETAILS</span>
                <h1 class="h2 fw-bold mt-2"><?= e($event['title']) ?></h1>
                <p class="text-secondary"><?= nl2br(e($event['description'] ?? '')) ?></p>
                <hr>
                <p><strong>Date:</strong> <?= e($date) ?></p>
                <p><strong>Time:</strong> <?= e($startTime) ?><?= $endTime ? ' – ' . e($endTime) : '' ?></p>
                <p><strong>Venue:</strong> <?= e($event['venue']) ?></p>
                <p><strong>Organizer:</strong> <?= e($event['organizer']) ?></p>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="content-card">
                <h2 class="h4 fw-bold">Registration Status</h2>
                <p>
                    <span class="badge <?= $isOpen
                        ? ($status === 'Almost Full' ? 'text-bg-warning' : 'text-bg-success')
                        : 'text-bg-danger' ?>">
                        <?= e($status) ?>
                    </span>
                </p>

                <div class="d-flex justify-content-between mb-1">
                    <span>Registered</span>
                    <strong><?= $registered ?> / <?= $capacity ?></strong>
                </div>

                <div class="progress mb-3" role="progressbar"
                     aria-label="Registration capacity"
                     aria-valuenow="<?= (int) round($percentage) ?>"
                     aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                </div>

                <p><strong>Available slots:</strong> <?= $available ?></p>

                <?php if ($isOpen): ?>
                    <a class="btn btn-primary w-100"
                       href="register.php?event_id=<?= (int) $event['id'] ?>">
                        Register Now
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>
                        Registration Closed
                    </button>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<footer class="footer py-4">
    <div class="container text-center text-secondary">Event-Pro &copy; 2026</div>
</footer>
</body>
</html>