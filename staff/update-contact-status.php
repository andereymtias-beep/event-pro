<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireStaffRole(['admin', 'organizer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid or expired form token. Please go back and try again.');
}

$messageId = filter_var(
    $_POST['message_id'] ?? null,
    FILTER_VALIDATE_INT
);

$newStatus = $_POST['status'] ?? '';

if (
    $messageId === false
    || $messageId === null
    || $messageId < 1
    || !is_string($newStatus)
    || !in_array($newStatus, ['pending', 'resolved'], true)
) {
    http_response_code(400);
    exit('Invalid contact message or status.');
}

$stmt = $pdo->prepare(
    "UPDATE contact_messages
     SET status = :status,
         resolved_at = CASE
             WHEN :resolved_status = 'resolved'
             THEN CURRENT_TIMESTAMP
             ELSE NULL
         END
     WHERE id = :id"
);

$stmt->execute([
    'status' => $newStatus,
    'resolved_status' => $newStatus,
    'id' => $messageId,
]);

header('Location: contact-messages.php', true, 303);
exit;
