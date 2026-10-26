<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireStaffLogin();

if (currentStaffRole() !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}

$message = '';
$messageType = 'success';
$fullName = '';
$username = '';
$role = 'staff';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!verifyCsrfToken(
        is_string($submittedToken) ? $submittedToken : null
    )) {
        http_response_code(403);
        exit('Invalid or expired security token. Please refresh the page and try again.');
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'staff';

    if ($fullName === '' || $username === '' || $password === '') {
        $message = 'Please complete all required fields.';
        $messageType = 'danger';
    } elseif (mb_strlen($fullName) > 120) {
        $message = 'Full name must not exceed 120 characters.';
        $messageType = 'danger';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) {
        $message = 'Username must be 3-60 characters and contain only letters, numbers, dots, underscores, or hyphens.';
        $messageType = 'danger';
    } elseif (strlen($password) < 12) {
        $message = 'Password must contain at least 12 characters.';
        $messageType = 'danger';
    } elseif ($password !== $confirmPassword) {
        $message = 'The passwords do not match.';
        $messageType = 'danger';
    } elseif (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
        $message = 'Please select a valid role.';
        $messageType = 'danger';
    } else {
        $check = $pdo->prepare(
            'SELECT id FROM staff WHERE username = ? LIMIT 1'
        );
        $check->execute([$username]);

        if ($check->fetch()) {
            $message = 'That username is already in use.';
            $messageType = 'danger';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $insert = $pdo->prepare(
                    'INSERT INTO staff
                        (full_name, username, password_hash, role, is_active)
                     VALUES (?, ?, ?, ?, 1)'
                );

                $insert->execute([
                    $fullName,
                    $username,
                    $passwordHash,
                    $role
                ]);

                $message = 'Staff account created successfully.';
                $messageType = 'success';
                $fullName = '';
                $username = '';
                $role = 'staff';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $message = 'That username may already exist. Please try another.';
                    $messageType = 'danger';
                } else {
                    error_log('Staff account creation failed: ' . $e->getMessage());
                    $message = 'Unable to create the account. Check the server error log.';
                    $messageType = 'danger';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Staff Account | Event-Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9;
            color: #212529;
        }
        .form-card {
            max-width: 720px;
            margin: 48px auto;
            padding: 32px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .07);
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="form-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Create Staff Account</h1>
                    <p class="text-muted mb-0">Event-Pro administration</p>
                </div>
                <a href="../staff/dashboard.php" class="btn btn-outline-secondary">
                    Dashboard
                </a>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= e($messageType) ?>" role="alert">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input
                        type="text"
                        class="form-control"
                        id="full_name"
                        name="full_name"
                        maxlength="120"
                        value="<?= e($fullName) ?>"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        class="form-control"
                        id="username"
                        name="username"
                        minlength="3"
                        maxlength="60"
                        pattern="[A-Za-z0-9_.-]+"
                        value="<?= e($username) ?>"
                        autocomplete="username"
                        required
                    >
                    <div class="form-text">
                        Use 3–60 letters, numbers, dots, underscores, or hyphens.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Account Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>
                            Staff
                        </option>
                        <option value="organizer" <?= $role === 'organizer' ? 'selected' : '' ?>>
                            Organizer
                        </option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>
                            Administrator
                        </option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        minlength="12"
                        autocomplete="new-password"
                        required
                    >
                    <div class="form-text">Minimum 12 characters.</div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">
                        Confirm Password
                    </label>
                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="12"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Create Account
                </button>
            </form>
        </div>
    </main>
</body>
</html>
