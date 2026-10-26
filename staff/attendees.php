<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireStaffLogin();

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        a.id,
        a.full_name,
        a.email,
        a.attendee_code,
        e.title AS event_title,
        CASE
            WHEN att.id IS NOT NULL THEN 'Checked In'
            ELSE 'Not Yet Checked In'
        END AS attendance_status,
        att.checked_in_at
    FROM attendees a
    INNER JOIN events e ON e.id = a.event_id
    LEFT JOIN attendance att
        ON att.attendee_id = a.id
        AND att.event_id = a.event_id
";

$params = [];

if ($search !== '') {
    $sql .= "
        WHERE a.full_name LIKE ?
           OR a.email LIKE ?
           OR a.attendee_code LIKE ?
           OR e.title LIKE ?
    ";

    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
}

$sql .= " ORDER BY a.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAttendees = count($attendees);
$totalCheckedIn = 0;

foreach ($attendees as $attendee) {
    if ($attendee['attendance_status'] === 'Checked In') {
        $totalCheckedIn++;
    }
}

$totalPending = $totalAttendees - $totalCheckedIn;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendee Management | Event-Pro</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <style>
        body {
            background: #f5f7fb;
        }

        .page-header {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .stat-card,
        .table-card {
            background: #fff;
            border: 0;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        .stat-card {
            padding: 20px;
            height: 100%;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
        }

        .table-card {
            overflow: hidden;
        }

        .table th {
            white-space: nowrap;
            background: #f8fafc;
        }

        .attendee-name {
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container-fluid px-3 px-md-4 py-4">

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="mb-1">Attendee Management</h2>
            <p class="text-muted mb-0">View and search registered seminar attendees.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            Back to Dashboard
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="text-muted">Displayed Attendees</div>
                <div class="stat-number"><?= $totalAttendees ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="text-muted">Checked In</div>
                <div class="stat-number text-success"><?= $totalCheckedIn ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="text-muted">Not Yet Checked In</div>
                <div class="stat-number text-warning"><?= $totalPending ?></div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="p-3 p-md-4 border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-8">
                    <input
                        type="search"
                        name="search"
                        class="form-control"
                        placeholder="Search name, email, attendee code, or event..."
                        value="<?= e($search) ?>"
                    >
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        Search
                    </button>
                    <a href="attendees.php" class="btn btn-outline-secondary">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="px-3">#</th>
                        <th>Attendee</th>
                        <th>Email</th>
                        <th>Attendee Code</th>
                        <th>Event</th>
                        <th>Attendance</th>
                        <th>Check-in Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($attendees)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            No attendees found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendees as $index => $attendee): ?>
                        <tr>
                            <td class="px-3"><?= $index + 1 ?></td>
                            <td class="attendee-name">
                                <?= e($attendee['full_name']) ?>
                            </td>
                            <td><?= e($attendee['email']) ?></td>
                            <td>
                                <code><?= e($attendee['attendee_code'] ?? '') ?></code>
                            </td>
                            <td><?= e($attendee['event_title']) ?></td>
                            <td>
                                <?php if ($attendee['attendance_status'] === 'Checked In'): ?>
                                    <span class="badge text-bg-success">Checked In</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($attendee['checked_in_at'])): ?>
                                    <?= e($attendee['checked_in_at']) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top text-muted small">
            Showing <?= $totalAttendees ?> attendee(s).
        </div>
    </div>

</div>
</body>
</html>
