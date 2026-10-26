<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireStaffLogin();

$token = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Check-in Scanner | Event-Pro</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <style>
        body {
            background: #f4f6f9;
        }

        .scanner-card {
            max-width: 700px;
            margin: 40px auto;
        }

        #qr-reader {
            width: 100%;
            background: white;
        }

        #scanner-status {
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Event-Pro</a>
            <div class="d-flex gap-3">
                <a class="text-white text-decoration-none" href="dashboard.php">
                    Dashboard
                </a>
                <a class="text-white text-decoration-none" href="logout.php">
                    Log Out
                </a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="card shadow-sm scanner-card">
            <div class="card-body p-4">
                <h1 class="h3 mb-2">QR Code Check-in</h1>
                <p class="text-secondary">
                    Scan an attendee's registration QR code to record attendance.
                </p>

                <div
                    id="qr-reader"
                    data-csrf-token="<?= e($token) ?>"
                ></div>

                <div
                    id="scanner-status"
                    class="alert alert-info mt-3 mb-0"
                    role="status"
                    aria-live="polite"
                >
                    Allow camera access to begin scanning.
                </div>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="../assets/js/scanner.js"></script>
</body>
</html>
