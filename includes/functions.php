<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function currentDateTime(): string
{
    return date('Y-m-d H:i:s');
}

function logActivity(
    PDO $pdo,
    string $action,
    ?int $staffId = null,
    ?int $eventId = null,
    ?string $details = null
): void {
    $sql = 'INSERT INTO activity_logs
            (staff_id, event_id, action, details)
            VALUES (:staff_id, :event_id, :action, :details)';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'staff_id' => $staffId,
        'event_id' => $eventId,
        'action' => $action,
        'details' => $details,
    ]);
}

function generateQrCode(string $token, string $filePath): void
{
    $qrCode = new \Endroid\QrCode\QrCode(
        data: $token,
        encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
        errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::High,
        size: 300,
        margin: 10,
        roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin,
        foregroundColor: new \Endroid\QrCode\Color\Color(0, 0, 0),
        backgroundColor: new \Endroid\QrCode\Color\Color(255, 255, 255)
    );

    $writer = new \Endroid\QrCode\Writer\PngWriter();
    $result = $writer->write($qrCode);

    $directory = dirname($filePath);

    if (!is_dir($directory)) {
        if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException(
                'Could not create the QR code directory.'
            );
        }
    }

    $result->saveToFile($filePath);

    if (!is_file($filePath) || filesize($filePath) === 0) {
        throw new RuntimeException(
            'Could not save the QR code image.'
        );
    }
}
