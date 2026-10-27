<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['staff_id'])) {
   redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Enter both your username and password.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, full_name, username, password_hash, role
                 FROM staff
                 WHERE username = :username
                   AND is_active = 1
                 LIMIT 1'
            );

            $stmt->execute(['username' => $username]);
            $staff = $stmt->fetch();

            if ($staff && password_verify($password, $staff['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION['staff_id'] = (int) $staff['id'];
                $_SESSION['staff_name'] = $staff['full_name'];
                $_SESSION['staff_username'] = $staff['username'];
                $_SESSION['staff_role'] = $staff['role'];

                logActivity(
                    $pdo,
                    'staff_login',
                    (int) $staff['id'],
                    null,
                    'Staff logged in successfully.'
                );

                header('Location: dashboard.php');
exit;
            }

            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Login | Event-Pro</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            Event-Pro
        </a>
        <a href="index.php" class="btn btn-outline-primary">Home</a>
    </div>
</nav>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="content-card">
                <span class="section-label">STAFF PORTAL</span>
                <h1 class="h2 fw-bold mt-2">Staff Login</h1>
                <p class="text-secondary">
                    Sign in to manage event attendance.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" autocomplete="on">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">
                            Username
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            autocomplete="username"
                            maxlength="100"
                            required>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">
                            Password
                        </label>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Sign In
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>
</body>
</html>
