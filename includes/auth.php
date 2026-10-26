<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function requireStaffLogin(): void
{
    if (empty($_SESSION['staff_id'])) {
        header('Location: /event-pro/staff/login.php');
        exit;
    }
}

function currentStaffId(): ?int
{
    return isset($_SESSION['staff_id'])
        ? (int) $_SESSION['staff_id']
        : null;
}

function currentStaffRole(): ?string
{
    return isset($_SESSION['staff_role'])
        ? (string) $_SESSION['staff_role']
        : null;
}

function requireStaffRole(array $allowedRoles): void
{
    requireStaffLogin();

    if (!in_array(currentStaffRole(), $allowedRoles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}
