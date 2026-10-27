<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

// Validate the CSRF token before processing form data.
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Your session has expired or the form is invalid. Please go back and try again.');
}

// Read and normalize submitted fields.
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

// Validate required fields and maximum lengths.
$errors = [];

if ($name === '' || mb_strlen($name) > 100) {
    $errors[] = 'Enter your name using no more than 100 characters.';
}

if (
    $email === ''
    || mb_strlen($email) > 254
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
) {
    $errors[] = 'Enter a valid email address.';
}

$allowedSubjects = [
    'Registration inquiry',
    'Event details',
    'QR code or check-in problem',
    'Other',
];

if (!in_array($subject, $allowedSubjects, true)) {
    $errors[] = 'Choose a valid inquiry subject.';
}

if ($message === '' || mb_strlen($message) > 5000) {
    $errors[] = 'Enter a message of no more than 5,000 characters.';
}

if ($errors !== []) {
    http_response_code(422);
    exit(implode("\n", $errors));
}

// Save the inquiry with a prepared statement.
try {
    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (name, email, subject, message)
         VALUES (:name, :email, :subject, :message)'
    );

    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message,
    ]);
} catch (PDOException $e) {
    error_log('Event-Pro contact submission failed: ' . $e->getMessage());

    http_response_code(500);
    exit('We could not save your message right now. Please try again later.');
}

// Redirect after successful submission to prevent accidental resubmission.
header('Location: index.php?contact=success#contact', true, 303);
exit;
