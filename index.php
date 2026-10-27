<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

// Load the first active event and its registration count.
$stmt = $pdo->query(
    'SELECT e.*,
        (SELECT COUNT(*) FROM attendees a
         WHERE a.event_id = e.id) AS registered_count
     FROM events e
     WHERE e.is_active = 1
     ORDER BY e.event_date ASC, e.id ASC
     LIMIT 1'
);

$event = $stmt->fetch();

$registered = 0;
$capacity = 0;
$available = 0;
$isOpen = false;
$status = 'Unavailable';
$date = '';
$startTime = '';
$endTime = '';
$percentage = 0;

if ($event) {
    $registered = (int) $event['registered_count'];
    $capacity = (int) $event['max_attendees'];
    $available = max(0, $capacity - $registered);

    $isOpen = (bool) $event['registration_open']
        && (bool) $event['is_active']
        && $available > 0;

    $status = !$isOpen
        ? 'Closed'
        : ($available <= max(1, (int) ceil($capacity * 0.1))
            ? 'Almost Full'
            : 'Open');

    $date = date(
        'F j, Y',
        strtotime((string) $event['event_date'])
    );

    $startTime = date(
        'g:i A',
        strtotime((string) $event['start_time'])
    );

    $endTime = !empty($event['end_time'])
        ? date('g:i A', strtotime((string) $event['end_time']))
        : '';

    $percentage = $capacity > 0
        ? min(100, ($registered / $capacity) * 100)
        : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Event-Pro | Seminar Registration</title>

    <meta name="description"
          content="Discover seminars, register for academic events, and receive your personal QR code with Event-Pro.">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            Event-Pro
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                <li class="nav-item">
                    <a class="nav-link active" href="#home">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#event">Event Details</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#registration">Registration</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#contact">Contact</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main>
    <section class="hero-section" id="home">
        <div class="container py-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <span class="badge text-bg-light mb-3">
                        SEMINAR REGISTRATION PLATFORM
                    </span>

                    <h1 class="display-4 fw-bold mb-3">
                        Your next learning experience starts here.
                    </h1>

                    <p class="lead mb-4">
                        Discover seminars, reserve your slot, and receive
                        your personal QR code for faster event check-in.
                    </p>

                    <?php if ($event && $isOpen): ?>
                        <a href="register.php?event_id=<?= (int) $event['id'] ?>"
                           class="btn btn-light btn-lg fw-semibold">
                            Register Now
                        </a>
                    <?php else: ?>
                        <a href="#event"
                           class="btn btn-light btn-lg fw-semibold">
                            Explore Event
                        </a>
                    <?php endif; ?>

                    <a href="#event"
                       class="btn btn-outline-light btn-lg ms-2">
                        View Event
                    </a>
                </div>

                <div class="col-lg-5">
                    <div class="hero-card">
                        <div class="small text-uppercase fw-semibold mb-2">
                            Featured Seminar
                        </div>

                        <?php if ($event): ?>
                            <h2 class="h3 fw-bold">
                                <?= e($event['title']) ?>
                            </h2>

                            <p>
                                <?= e($event['description'] ?? '') ?>
                            </p>

                            <hr>

                            <p class="mb-2">
                                <strong>Date:</strong> <?= e($date) ?>
                            </p>

                            <p class="mb-2">
                                <strong>Time:</strong>
                                <?= e($startTime) ?>
                                <?= $endTime !== '' ? ' – ' . e($endTime) : '' ?>
                            </p>

                            <p class="mb-0">
                                <strong>Venue:</strong>
                                <?= e($event['venue']) ?>
                            </p>
                        <?php else: ?>
                            <h2 class="h3 fw-bold">
                                No Upcoming Event Available
                            </h2>

                            <p class="mb-0">
                                Please check back later for seminar
                                announcements and registration details.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="event">
        <div class="container">
            <?php if ($event): ?>
                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-8">
                        <div class="content-card h-100">
                            <span class="section-label">ABOUT THE EVENT</span>

                            <h2 class="h3 fw-bold mt-2">
                                <?= e($event['title']) ?>
                            </h2>

                            <p class="text-secondary">
                                <?= nl2br(e($event['description'] ?? '')) ?>
                            </p>

                            <div class="row g-3 mt-3">
                                <div class="col-sm-6">
                                    <div class="detail-item">
                                        <span class="detail-label">Date</span>
                                        <strong><?= e($date) ?></strong>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="detail-item">
                                        <span class="detail-label">Time</span>
                                        <strong>
                                            <?= e($startTime) ?>
                                            <?= $endTime !== '' ? ' – ' . e($endTime) : '' ?>
                                        </strong>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="detail-item">
                                        <span class="detail-label">Venue</span>
                                        <strong><?= e($event['venue']) ?></strong>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="detail-item">
                                        <span class="detail-label">Organizer</span>
                                        <strong><?= e($event['organizer']) ?></strong>
                                    </div>
                                </div>
                            </div>

                            <a class="btn btn-outline-primary mt-4"
                               href="event-details.php?event_id=<?= (int) $event['id'] ?>">
                                View Full Event Details
                            </a>
                        </div>
                    </div>

                    <div class="col-lg-4" id="registration">
                        <div class="content-card h-100">
                            <span class="section-label">REGISTRATION</span>

                            <h2 class="h4 fw-bold mt-2">
                                Reserve Your Slot
                            </h2>

                            <p>
                                <span class="badge <?= $isOpen
                                    ? ($status === 'Almost Full'
                                        ? 'text-bg-warning'
                                        : 'text-bg-success')
                                    : 'text-bg-danger' ?>">
                                    <?= e($status) ?>
                                </span>
                            </p>

                            <div class="d-flex justify-content-between mb-1">
                                <span>Registered</span>
                                <strong>
                                    <?= $registered ?> / <?= $capacity ?>
                                </strong>
                            </div>

                            <div class="progress mb-3"
                                 role="progressbar"
                                 aria-label="Event registration capacity"
                                 aria-valuenow="<?= (int) round($percentage) ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                                <div class="progress-bar"
                                     style="width: <?= $percentage ?>%">
                                </div>
                            </div>

                            <p>
                                <strong>Available slots:</strong>
                                <?= $available ?>
                            </p>

                            <?php if ($isOpen): ?>
                                <a href="register.php?event_id=<?= (int) $event['id'] ?>"
                                   class="btn btn-primary w-100">
                                    Register for Seminar
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100" disabled>
                                    Registration Closed
                                </button>
                            <?php endif; ?>

                            <p class="small text-secondary mt-3 mb-0">
                                Registration availability is based on the
                                current event settings and attendee count.
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="content-card text-center py-5">
                    <h2 class="h3 fw-bold">No Active Event</h2>
                    <p class="text-secondary mb-0">
                        There are currently no active events available.
                        Please check back later.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>


<section class="py-5 bg-light" id="contact">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="content-card">
                    <span class="section-label">CONTACT</span>
                    <h2 class="h3 fw-bold mt-2">Contact the Organizer</h2>
                    <p class="text-secondary">
                        Have a question about registration, event details,
                        or QR check-in? Send us a message.
                    </p>

                    <?php if (($_GET['contact'] ?? '') === 'success'): ?>
                        <div class="alert alert-success" role="status">
                            Your message was submitted successfully.
                        </div>
                    <?php elseif (($_GET['contact'] ?? '') === 'error'): ?>
                        <div class="alert alert-danger" role="alert">
                            We couldn't process your message. Please try again.
                        </div>
                    <?php endif; ?>

                    <form action="contact-submit.php" method="POST">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="contact-name" class="form-label">
                                Full name
                            </label>
                            <input type="text" class="form-control"
                                   id="contact-name" name="name"
                                   maxlength="100" autocomplete="name"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="contact-email" class="form-label">
                                Email address
                            </label>
                            <input type="email" class="form-control"
                                   id="contact-email" name="email"
                                   maxlength="254" autocomplete="email"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="contact-subject" class="form-label">
                                Subject
                            </label>
                            <select class="form-select" id="contact-subject"
                                    name="subject" required>
                                <option value="Registration inquiry">
                                    Registration inquiry
                                </option>
                                <option value="Event details">Event details</option>
                                <option value="QR code or check-in problem">
                                    QR code or check-in problem
                                </option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="contact-message" class="form-label">
                                Message
                            </label>
                            <textarea class="form-control"
                                      id="contact-message" name="message"
                                      rows="5" maxlength="5000"
                                      required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="footer py-4">
    <div class="container d-flex flex-column flex-md-row
                justify-content-between gap-2">
        <span class="fw-bold">Event-Pro</span>
        <span class="text-secondary">
            Seminar Registration and QR Attendance Management
        </span>
        <span class="text-secondary">
            &copy; <?= date('Y') ?> Event-Pro
        </span>
    </div>
</footer>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>
