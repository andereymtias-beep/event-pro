<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffLogin();

$staffName = (string) ($_SESSION['staff_name'] ?? 'Staff');
$staffRole = currentStaffRole();

$canManageContacts = in_array(
    $staffRole,
    ['admin', 'organizer'],
    true
);

// Dashboard statistics
$eventCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM events WHERE is_active = 1')
    ->fetchColumn();

$attendeeCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM attendees')
    ->fetchColumn();

$checkinCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM attendance')
    ->fetchColumn();

$pendingCount = max(0, $attendeeCount - $checkinCount);

// Count pending contact inquiries for authorized roles only.
$contactPendingCount = 0;

if ($canManageContacts) {
    $contactPendingCount = (int) $pdo
        ->query(
            "SELECT COUNT(*)
             FROM contact_messages
             WHERE status = 'pending'"
        )
        ->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Staff Dashboard | Event-Pro</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            background: #f4f7fb;
            color: #1e293b;
        }

        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #172554;
            color: #fff;
            padding: 24px 16px;
        }

        .brand {
            display: block;
            color: #fff;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 800;
            padding: 0 12px 28px;
        }

        .brand:hover {
            color: #dbeafe;
        }

        .sidebar-label {
            color: #a5b4fc;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 0 12px;
            margin: 12px 0;
        }

        .nav-link-custom {
            display: block;
            color: #dbeafe;
            text-decoration: none;
            padding: 12px;
            margin-bottom: 5px;
            border-radius: 8px;
            transition: background .2s;
        }

        .nav-link-custom:hover,
        .nav-link-custom.active {
            background: #1e40af;
            color: #fff;
        }

        .nav-link-custom.logout-link {
            color: #fecaca;
        }

        .nav-link-custom.logout-link:hover {
            background: #7f1d1d;
            color: #fff;
        }

        .main-content {
            min-width: 0;
            flex: 1;
            padding: 32px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            height: 100%;
            padding: 24px;
            background: #fff;
            border: 1px solid #e8edf5;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
        }

        .stat-label {
            color: #64748b;
            font-size: .92rem;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-icon {
            font-size: 1.6rem;
        }

        .section-card {
            height: 100%;
            background: #fff;
            border: 1px solid #e8edf5;
            border-radius: 14px;
            padding: 24px;
        }

        .action-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 15px 0;
            border-bottom: 1px solid #edf1f7;
            text-decoration: none;
            color: #1e293b;
        }

        .action-link:last-child {
            border-bottom: 0;
        }

        .action-link:hover {
            color: #2563eb;
        }

        .action-description {
            display: block;
            color: #64748b;
            font-size: .85rem;
            margin-top: 3px;
        }

        .contact-count {
            min-width: 28px;
        }

        @media (max-width: 767px) {
            .app-layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 16px;
            }

            .brand {
                padding: 0 8px 12px;
            }

            .sidebar-label {
                margin-top: 8px;
            }

            .main-content {
                padding: 20px 14px;
            }

            .stat-number {
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>
<div class="app-layout">

    <aside class="sidebar">
        <a class="brand" href="dashboard.php">Event-Pro</a>

        <div class="sidebar-label">Main Menu</div>

        <nav aria-label="Staff navigation">

            <a class="nav-link-custom active"
               href="dashboard.php"
               aria-current="page">
                🏠 Dashboard
            </a>

            <a class="nav-link-custom" href="attendees.php">
                👥 Attendee Management
            </a>

            <a class="nav-link-custom" href="scanner.php">
                📷 QR Scanner
            </a>

            <a class="nav-link-custom" href="reports.php">
                📊 Attendance Reports
            </a>

            <?php if ($canManageContacts): ?>
                <a class="nav-link-custom d-flex justify-content-between align-items-center"
                   href="contact-messages.php">

                    <span>✉️ Contact Messages</span>

                    <span class="badge rounded-pill text-bg-warning contact-count">
                        <?= $contactPendingCount ?>
                    </span>
                </a>
            <?php endif; ?>

            <div class="sidebar-label">Event Management</div>

            <a class="nav-link-custom" href="event-settings.php">
                ⚙️ Event Settings
            </a>

            <a class="nav-link-custom" href="export-csv.php">
                📥 Export Attendance CSV
            </a>

            <div class="sidebar-label">Account</div>

            <a class="nav-link-custom logout-link" href="logout.php">
                ↪ Log Out
            </a>

        </nav>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1 class="h2 fw-bold mb-1">Staff Dashboard</h1>
                <p class="text-secondary mb-0">
                    Welcome back, <?= e($staffName) ?>!
                </p>
            </div>

            <div class="text-md-end">
                <span class="badge rounded-pill text-bg-primary px-3 py-2">
                    <?= e($staffRole ?? 'staff') ?>
                </span>

                <div class="small text-secondary mt-2">
                    Event-Pro Management System
                </div>
            </div>
        </header>

        <section class="row g-3 mb-4"
                 aria-label="Dashboard statistics">

            <div class="col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Active Events</div>
                            <div class="stat-number">
                                <?= $eventCount ?>
                            </div>
                        </div>

                        <span class="stat-icon">📅</span>
                    </div>

                    <a href="event-settings.php"
                       class="small text-decoration-none">
                        Manage events →
                    </a>
                </div>
            </div>

            <div class="col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Registered Attendees</div>
                            <div class="stat-number">
                                <?= $attendeeCount ?>
                            </div>
                        </div>

                        <span class="stat-icon">👥</span>
                    </div>

                    <a href="attendees.php"
                       class="small text-decoration-none">
                        View attendees →
                    </a>
                </div>
            </div>

            <div class="col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Check-ins Recorded</div>
                            <div class="stat-number">
                                <?= $checkinCount ?>
                            </div>
                        </div>

                        <span class="stat-icon">✅</span>
                    </div>

                    <a href="reports.php"
                       class="small text-decoration-none">
                        View attendance →
                    </a>
                </div>
            </div>

        </section>

        <section class="row g-4">

            <div class="col-lg-7">
                <div class="section-card">

                    <h2 class="h5 fw-bold mb-1">Quick Actions</h2>

                    <p class="text-secondary small mb-3">
                        Access the tools you need to manage your seminar.
                    </p>

                    <a href="../admin/manage-events.php"
                       class="btn btn-primary">
                        + Create Event
                    </a>

                    <a class="action-link" href="scanner.php">
                        <span>
                            <strong>Open QR Scanner</strong>
                            <span class="action-description">
                                Scan attendee QR codes and record attendance.
                            </span>
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>

                    <a class="action-link" href="attendees.php">
                        <span>
                            <strong>Manage Attendees</strong>
                            <span class="action-description">
                                View and search registered participants.
                            </span>
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>

                    <a class="action-link" href="reports.php">
                        <span>
                            <strong>Attendance Reports</strong>
                            <span class="action-description">
                                Review check-in records and attendance status.
                            </span>
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>

                    <a class="action-link" href="export-csv.php">
                        <span>
                            <strong>Download CSV Report</strong>
                            <span class="action-description">
                                Export attendance data for Excel.
                            </span>
                        </span>
                        <span aria-hidden="true">↓</span>
                    </a>

                    <?php if ($canManageContacts): ?>
                        <a class="action-link" href="contact-messages.php">
                            <span>
                                <strong>Contact Messages</strong>
                                <span class="action-description">
                                    Review and resolve visitor inquiries.
                                </span>
                            </span>

                            <span class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill text-bg-warning">
                                    <?= $contactPendingCount ?> pending
                                </span>
                                <span aria-hidden="true">→</span>
                            </span>
                        </a>
                    <?php endif; ?>

                </div>
            </div>

            <div class="col-lg-5">
                <div class="section-card">

                    <h2 class="h5 fw-bold mb-1">Event Management</h2>

                    <p class="text-secondary small mb-3">
                        Keep your seminar information up to date.
                    </p>

                    <a class="action-link" href="event-settings.php">
                        <span>
                            <strong>Event Settings</strong>
                            <span class="action-description">
                                Update event details and attendee capacity.
                            </span>
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>

                    <div class="mt-4 p-3 rounded-3 bg-light">
                        <div class="fw-semibold mb-1">
                            Attendance Overview
                        </div>

                        <div class="small text-secondary">
                            <?= $checkinCount ?> check-in record(s) are saved.
                        </div>

                        <a href="reports.php"
                           class="btn btn-outline-primary btn-sm mt-3">
                            View Full Report
                        </a>
                    </div>

                    <?php if ($canManageContacts): ?>
                        <div class="mt-3 p-3 rounded-3 bg-light">
                            <div class="fw-semibold mb-1">
                                Contact Inbox Overview
                            </div>

                            <div class="small text-secondary">
                                <?= $contactPendingCount ?> pending inquiry/inquiries need review.
                            </div>

                            <a href="contact-messages.php"
                               class="btn btn-outline-primary btn-sm mt-3">
                                Open Contact Inbox
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </section>

        <footer class="text-secondary small mt-5">
            Event-Pro &copy; <?= date('Y') ?>.
            Seminar Registration and Attendance Management System.
        </footer>

    </main>

</div>
</body>
</html>
