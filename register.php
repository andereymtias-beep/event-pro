<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/vendor/autoload.php';

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedEventId = filter_input(
        INPUT_POST,
        'event_id',
        FILTER_VALIDATE_INT
    );

    $eventId = ($postedEventId !== false
        && $postedEventId !== null
        && $postedEventId > 0) ? $postedEventId : 0;
}

if (!$eventId || $eventId < 1) {
    $eventId = 1;
}

$errors = [];
$success = false;

$fullName = '';
$studentId = '';
$email = '';
$contactNumber = '';
$department = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    }

    if ($fullName === '' || mb_strlen($fullName) > 120) {
        $errors[] = 'Enter your full name (maximum 120 characters).';
    }

    if (mb_strlen($studentId) > 60) {
        $errors[] = 'Student ID must not exceed 60 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 254) {
        $errors[] = 'Enter a valid email address.';
    }

    if (mb_strlen($contactNumber) > 32) {
        $errors[] = 'Contact number must not exceed 32 characters.';
    }

    if (mb_strlen($department) > 120) {
        $errors[] = 'Department must not exceed 120 characters.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the event row to serialize concurrent registrations.
            $eventStmt = $pdo->prepare(
                'SELECT id, title, max_attendees,
                        registration_open, is_active
                 FROM events
                 WHERE id = :id
                 FOR UPDATE'
            );

            $eventStmt->execute(['id' => $eventId]);
            $event = $eventStmt->fetch();

            if (!$event) {
                throw new RuntimeException('EVENT_NOT_FOUND');
            }

            $countStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM attendees
                 WHERE event_id = :event_id'
            );

            $countStmt->execute(['event_id' => $eventId]);
            $registeredCount = (int) $countStmt->fetchColumn();

            if (!(bool) $event['is_active']
                || !(bool) $event['registration_open']) {
                throw new RuntimeException('REGISTRATION_CLOSED');
            }

            if ($registeredCount >= (int) $event['max_attendees']) {
                throw new RuntimeException('EVENT_FULL');
            }

            $duplicateStmt = $pdo->prepare(
                'SELECT id
                 FROM attendees
                 WHERE event_id = :event_id AND email = :email
                 LIMIT 1'
            );

            $duplicateStmt->execute([
                'event_id' => $eventId,
                'email' => $email,
            ]);

            if ($duplicateStmt->fetch()) {
                throw new RuntimeException('DUPLICATE_EMAIL');
            }

            // Generate unpredictable, unique attendee credentials.
            $attendeeCode = 'EP-' . strtoupper(bin2hex(random_bytes(6)));
            $qrToken = bin2hex(random_bytes(32));
            $qrTokenHash = hash('sha256', $qrToken);

            $insertStmt = $pdo->prepare(
                'INSERT INTO attendees
                    (event_id, attendee_code, full_name, student_id,
                     email, contact_number, department, qr_token_hash)
                 VALUES
                    (:event_id, :attendee_code, :full_name, :student_id,
                     :email, :contact_number, :department, :qr_token_hash)'
            );

            $insertStmt->execute([
                'event_id' => $eventId,
                'attendee_code' => $attendeeCode,
                'full_name' => $fullName,
                'student_id' => $studentId !== '' ? $studentId : null,
                'email' => $email,
                'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                'department' => $department !== '' ? $department : null,
                'qr_token_hash' => $qrTokenHash,
            ]);


$attendeeId = (int) $pdo->lastInsertId();

// Use a random filename rather than attendee details.
$qrFilename = bin2hex(random_bytes(16)) . '.png';
$qrFilePath = __DIR__ . '/storage/qrcodes/' . $qrFilename;

try {
    generateQrCode($qrToken, $qrFilePath);
} catch (Throwable $qrError) {
    error_log('Event-Pro QR generation failed: ' . $qrError->getMessage());

    if (is_file($qrFilePath)) {
        unlink($qrFilePath);
    }

    throw new RuntimeException('QR_GENERATION_FAILED');
}

$pdo->commit();

// Keep the token and QR filename for the confirmation page.
$_SESSION['event_pro_registration'] = [
    'attendee_id' => $attendeeId,
    'event_id' => $eventId,
    'attendee_code' => $attendeeCode,
    'full_name' => $fullName,
    'email' => $email,
    'qr_token' => $qrToken,
    'qr_filename' => $qrFilename,
];

redirect('registration-success.php');

        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $messages = [
                'EVENT_NOT_FOUND' => 'The selected event could not be found.',
                'REGISTRATION_CLOSED' => 'Registration for this event is closed.',
                'EVENT_FULL' => 'Sorry, the event has reached maximum capacity.',
                'DUPLICATE_EMAIL' => 'This email is already registered for this event.',
                'QR_GENERATION_FAILED' => 'We could not generate your QR code. Please try registering again.',
            ];

            $errors[] = $messages[$e->getMessage()]
                ?? 'Registration failed. Please try again.';

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Event-Pro registration error: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                $errors[] = 'This registration conflicts with an existing record. Check your email and try again.';
            } else {
                $errors[] = 'A database error occurred. Please try again.';
            }
        }
    }
}

$eventStmt = $pdo->prepare(
    'SELECT id, title, max_attendees, registration_open, is_active,
            (SELECT COUNT(*) FROM attendees a WHERE a.event_id = events.id)
            AS registered_count
     FROM events
     WHERE id = :id'
);

$eventStmt->execute(['id' => $eventId]);
$event = $eventStmt->fetch();

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$remaining = max(
    0,
    (int) $event['max_attendees'] - (int) $event['registered_count']
);

$isOpen = (bool) $event['is_active']
    && (bool) $event['registration_open']
    && $remaining > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | Event-Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.php">Event-Pro</a>
        <a href="event-details.php?event_id=<?= (int) $event['id'] ?>"
           class="btn btn-outline-primary">Event Details</a>
    </div>
</nav>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="content-card">
                <span class="section-label">SEMINAR REGISTRATION</span>
                <h1 class="h2 fw-bold mt-2">Register for an Event</h1>
                <p class="text-secondary"><?= e($event['title']) ?></p>

                <p>
                    <strong>Available slots:</strong>
                    <?= $remaining ?>
                </p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!$isOpen): ?>
                    <div class="alert alert-warning">
                        Registration is closed or the event has reached capacity.
                    </div>
                <?php else: ?>
                    <form method="POST"
                          action="register.php?event_id=<?= (int) $event['id'] ?>"
                          autocomplete="on">

                        <?= csrfField() ?>

                        <input type="hidden" name="event_id"
                               value="<?= (int) $event['id'] ?>">

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input class="form-control" id="full_name"
                                   name="full_name" maxlength="120"
                                   autocomplete="name" required
                                   value="<?= e($fullName) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="student_id" class="form-label">
                                Student ID (optional)
                            </label>
                            <input class="form-control" id="student_id"
                                   name="student_id" maxlength="60"
                                   value="<?= e($studentId) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input class="form-control" type="email"
                                   id="email" name="email" maxlength="254"
                                   autocomplete="email" required
                                   value="<?= e($email) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="contact_number" class="form-label">
                                Contact Number
                            </label>
                            <input class="form-control" type="tel"
                                   id="contact_number" name="contact_number"
                                   maxlength="32" autocomplete="tel"
                                   value="<?= e($contactNumber) ?>">
                        </div>

                        <div class="mb-4">
                            <label for="department" class="form-label">
                                Course, Department, or Organization
                            </label>
                            <input class="form-control" id="department"
                                   name="department" maxlength="120"
                                   value="<?= e($department) ?>">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Submit Registration
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<footer class="footer py-4">
    <div class="container text-center text-secondary">
        Event-Pro &copy; 2026
    </div>
</footer>

</body>
</html>
