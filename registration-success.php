<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$registration = $_SESSION['event_pro_registration'] ?? null;

if (!is_array($registration)) {
    header('Location: index.html');
    exit;
}

// Remove the registration details from the session after reading them.
// Preserve the values locally for this page.
unset($_SESSION['event_pro_registration']);


$attendeeCode = (string) ($registration['attendee_code'] ?? '');
$fullName = (string) ($registration['full_name'] ?? '');
$email = (string) ($registration['email'] ?? '');
$eventId = (int) ($registration['event_id'] ?? 1);
$qrFilename = (string) ($registration['qr_filename'] ?? '');

if (
    $qrFilename === ''
    || !preg_match('/\A[a-f0-9]{32}\.png\z/', $qrFilename)
    || !is_file(__DIR__ . '/storage/qrcodes/' . $qrFilename)
) {
    http_response_code(500);
    exit('Your registration was received, but the QR code image could not be found. Please contact the event organizer.');
}

$qrImageUrl = 'storage/qrcodes/' . rawurlencode($qrFilename);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration Successful | Event-Pro</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.html">
            Event-Pro
        </a>
    </div>
</nav>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="content-card text-center">

                <div class="display-4 text-success mb-3">
                    &#10003;
                </div>

                <h1 class="h2 fw-bold">Registration Successful!</h1>

                <p class="text-secondary">
                    Thank you for registering. Your registration
                    has been recorded.
                </p>

                <hr>

                <div class="text-start">
                    <p>
                        <strong>Attendee:</strong>
                        <?= e($fullName) ?>
                    </p>

                    <p>
                        <strong>Email:</strong>
                        <?= e($email) ?>
                    </p>

                    <p>
                        <strong>Registration Reference:</strong>
                        <span class="badge text-bg-primary">
                            <?= e($attendeeCode) ?>
                        </span>
                    </p>
                </div>


<div class="alert alert-success text-start mt-4">
    <strong>You're registered!</strong>
    Save your QR code and present it to the event staff
    when you arrive for check-in.
</div>

<div class="my-4">
    <h2 class="h5 fw-bold">Your Attendee QR Code</h2>

    <img
        src="<?= e($qrImageUrl) ?>"
        alt="QR code for attendee <?= e($attendeeCode) ?>"
        class="img-fluid border rounded p-2 bg-white"
        style="width: 300px; max-width: 100%;"
    >

    <p class="text-secondary small mt-2">
        Registration Reference: <?= e($attendeeCode) ?>
    </p>

    <a
        href="<?= e($qrImageUrl) ?>"
        download="<?= e($attendeeCode) ?>-QR.png"
        class="btn btn-outline-primary w-100 mt-2"
    >
        Download QR Code
    </a>
</div>


                <a href="event-details.php?event_id=<?= $eventId ?>"
                   class="btn btn-primary w-100">
                    Return to Event Details
                </a>

                <a href="index.html"
                   class="btn btn-outline-secondary w-100 mt-2">
                    Back to Home
                </a>

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
