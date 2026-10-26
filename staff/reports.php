<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireStaffLogin();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query(
    'SELECT
        e.id AS event_id,
        e.title AS event_title,
        a.id AS attendee_id,
        a.full_name,
        a.email,
        at.checked_in_at,
        at.checked_in_by
     FROM attendees a
     INNER JOIN events e ON e.id = a.event_id
     LEFT JOIN attendance at
        ON at.attendee_id = a.id
        AND at.event_id = e.id
     ORDER BY e.event_date DESC, a.full_name ASC'
);

$attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRegistered = count($attendees);
$totalCheckedIn = 0;

foreach ($attendees as $attendee) {
    if ($attendee['checked_in_at'] !== null) {
        $totalCheckedIn++;
    }
}

$totalPending = $totalRegistered - $totalCheckedIn;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Reports | Event-Pro</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Event-Pro</a>
        <div class="d-flex gap-3">
            <a class="text-white text-decoration-none" href="dashboard.php">
                Dashboard
            </a>
            <a class="text-white text-decoration-none" href="scanner.php">
                QR Scanner
            </a>
            <a class="text-white text-decoration-none" href="logout.php">
                Log Out
            </a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <h1 class="h3 mb-4">Attendance Reports</h1>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-secondary mb-1">Registered Attendees</p>
                    <h2><?= $totalRegistered ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-secondary mb-1">Checked In</p>
                    <h2 class="text-success"><?= $totalCheckedIn ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-secondary mb-1">Not Yet Checked In</p>
                    <h2 class="text-warning"><?= $totalPending ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Attendee List</h2>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                    <tr>
                        <th>Event</th>
                        <th>Attendee</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Check-in Time</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$attendees): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                No registered attendees found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendees as $attendee): ?>
                            <tr>
                                <td><?= e($attendee['event_title']) ?></td>
                                <td><?= e($attendee['full_name']) ?></td>
                                <td><?= e($attendee['email']) ?></td>
                                <td>
                                    <?php if ($attendee['checked_in_at'] !== null): ?>
                                        <span class="badge text-bg-success">
                                            Checked In
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($attendee['checked_in_at'] !== null): ?>
                                        <?= e($attendee['checked_in_at']) ?>
                                    <?php else: ?>
                                        <span class="text-secondary">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
