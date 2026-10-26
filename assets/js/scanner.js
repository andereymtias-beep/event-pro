
"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const scannerElement = document.getElementById("qr-reader");
    const statusElement = document.getElementById("scanner-status");

    if (!scannerElement || !statusElement) return;

    if (typeof Html5QrcodeScanner === "undefined") {
        statusElement.textContent =
            "QR scanner library failed to load. Refresh the page.";
        return;
    }

    const csrfToken = scannerElement.dataset.csrfToken;

    if (!csrfToken) {
        statusElement.textContent =
            "Security token is missing. Refresh the scanner page.";
        return;
    }

    let processing = false;

    const scanner = new Html5QrcodeScanner(
        "qr-reader",
        {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            rememberLastUsedCamera: true
        },
        false
    );

    async function handleScan(decodedText) {
        if (processing) return;

        processing = true;
        statusElement.textContent = "QR scanned. Verifying registration...";

        try {
            const response = await fetch("checkin.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                credentials: "same-origin",
                body: JSON.stringify({ token: decodedText })
            });

            const result = await response.json();

            statusElement.textContent =
                result.message || "No response message received.";

            statusElement.dataset.status =
                result.success ? "success" : "error";

            if (result.success) {
                scanner.pause(true);
            }
        } catch (error) {
            statusElement.textContent =
                "Unable to verify the QR code. Please try again.";
            statusElement.dataset.status = "error";
        } finally {
            processing = false;
        }
    }

    scanner.render(
        handleScan,
        () => {
            // Ignore frames without a QR code.
        }
    );
});
