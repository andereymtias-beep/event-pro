<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireStaffRole(['admin', 'organizer']);

$statusFilter = $_GET['status'] ?? 'all';
$allowedFilters = ['all', 'pending', 'resolved'];

if (!is_string($statusFilter) || !in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

$sql = 'SELECT id, name, email, subject, message, status, created_at, resolved_at
        FROM contact_messages';

if ($statusFilter !== 'all') {
    $sql .= ' WHERE status = :status';
}

$sql .= ' ORDER BY created_at DESC, id DESC';

$stmt = $pdo->prepare($sql);

if ($statusFilter !== 'all') {
    $stmt->execute(['status' => $statusFilter]);
} else {
    $stmt->execute();
}

$messages = $stmt->fetchAll();

$countStmt = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending,
        SUM(status = 'resolved') AS resolved
     FROM contact_messages"
);

$counts = $countStmt->fetch() ?: [
    'total' => 0,
    'pending' => 0,
    'resolved' => 0,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Messages | Event-Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Contact Messages</h1>
            <p class="text-secondary mb-0">
                Review inquiries submitted through the Event-Pro homepage.
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-primary">
            Back to Dashboard
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="content-card h-100">
                <div class="text-secondary small">Total messages</div>
                <div class="fs-3 fw-bold"><?= (int) $counts['total'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="content-card h-100">
                <div class="text-secondary small">Pending</div>
                <div class="fs-3 fw-bold"><?= (int) $counts['pending'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="content-card h-100">
                <div class="text-secondary small">Resolved</div>
                <div class="fs-3 fw-bold"><?= (int) $counts['resolved'] ?></div>
            </div>
        </div>
    </div>

    <div class="content-card mb-4">
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
            <label for="status" class="form-label mb-0">Filter messages:</label>
            <select name="status" id="status" class="form-select"
                    style="max-width: 220px">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>
                    All messages
                </option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                    Pending
                </option>
                <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>
                    Resolved
                </option>
            </select>
            <button type="submit" class="btn btn-primary">Apply Filter</button>
        </form>
    </div>

    <?php if ($messages === []): ?>
        <div class="alert alert-info">
            No contact messages match this filter.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($messages as $item): ?>
                <div class="col-12">
                    <article class="content-card">

                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <h2 class="h5 fw-bold mb-0">
                                <?= e($item['subject']) ?>
                            </h2>
                            <span class="badge <?= $item['status'] === 'pending'
                                ? 'text-bg-warning'
                                : 'text-bg-success' ?>">
                                <?= e(ucfirst($item['status'])) ?>
                            </span>
                        </div>

                        <p class="mb-1">
                            <strong>From:</strong> <?= e($item['name']) ?>
                        </p>

                        <p class="mb-1">
                            <strong>Email:</strong>
                            <a href="mailto:<?= e($item['email']) ?>">
                                <?= e($item['email']) ?>
                            </a>
                        </p>

                        <p class="text-secondary small mb-3">
                            Received:
                            <?= e(date('M j, Y g:i A', strtotime($item['created_at']))) ?>
                        </p>

                        <div class="border rounded p-3"
                             style="white-space: pre-wrap; overflow-wrap: anywhere"><?= e($item['message']) ?></div>

                        <?php if ($item['resolved_at']): ?>
                            <p class="text-secondary small mt-3 mb-0">
                                Resolved:
                                <?= e(date('M j, Y g:i A', strtotime($item['resolved_at']))) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (in_array(currentStaffRole(), ['admin', 'organizer'], true)): ?>
                            <form action="update-contact-status.php"
                                  method="POST"
                                  class="mt-3">
                                <?= csrfField() ?>

                                <input type="hidden"
                                       name="message_id"
                                       value="<?= (int) $item['id'] ?>">

                                <?php if ($item['status'] === 'pending'): ?>
                                    <input type="hidden" name="status" value="resolved">
                                    <button type="submit" class="btn btn-success">
                                        Mark as Resolved
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="pending">
                                    <button type="submit" class="btn btn-outline-primary">
                                        Reopen Message
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>

                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
