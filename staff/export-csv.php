<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireStaffLogin();

$sql = "
    SELECT
        a.full_name,
        a.email,
        a.attendee_code,
        e.title AS event_title,
        e.event_date,
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
    ORDER BY e.event_date DESC, a.full_name ASC
";

$stmt = $pdo->query($sql);

$filename = 'event-pro-attendance-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');

if ($output === false) {
    exit('Unable to generate the CSV file.');
}

// UTF-8 BOM helps Microsoft Excel display text correctly.
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Attendee Name',
    'Email',
    'Attendee Code',
    'Event Title',
    'Event Date',
    'Attendance Status',
    'Check-in Date and Time'
]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['full_name'],
        $row['email'],
        $row['attendee_code'] ?? '',
        $row['event_title'],
        $row['event_date'],
        $row['attendance_status'],
        $row['checked_in_at'] ?? ''
    ]);
}

fclose($output);
exit;
