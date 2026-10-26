# Event-Pro — Seminar Registration System

Event-Pro is a PHP-based web application for managing seminar registration and attendee check-in. It includes QR code generation, staff authentication, event management, and attendance reporting.

## Features

- **Seminar registration:** Collect attendee information through a registration form.
- **Capacity management:** Configure a maximum number of attendees per event.
- **QR code generation:** Generate a unique QR code for registered attendees.
- **Staff authentication:** Provide a password-protected staff area with role-based access.
- **QR code check-in:** Scan attendee QR codes and record attendance.
- **Duplicate check-in detection:** Help prevent the same attendee from being checked in twice for the same event.
- **Event management:** Create, edit, and delete events.
- **Attendee management:** View registered attendees.
- **Attendance reports:** Review attendance records and export data to CSV.
- **Activity logging:** Record selected administrative activities.

## Technology Stack

- PHP 8.2 or compatible version
- MySQL/MariaDB
- HTML5, CSS3, and JavaScript
- Bootstrap 5
- Apache through XAMPP
- Composer
- Endroid QR Code library

## Requirements

Install the following before running the application:

- XAMPP with Apache, PHP, and MySQL/MariaDB
- Composer
- A modern web browser
- Git, if you want to clone the repository

## Installation

### 1. Clone the repository

Replace `YOUR-USERNAME` with your GitHub username.

```bash
git clone https://github.com/YOUR-USERNAME/event-pro.git
```

Alternatively, download the repository as a ZIP file and extract it into your XAMPP `htdocs` directory.

### 2. Install PHP dependencies

Open a terminal in the project directory and run:

```bash
composer install
```

If the repository does not include a `composer.json` file, check the project's dependency setup before continuing.

### 3. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 4. Create the database

1. Open [phpMyAdmin](http://localhost/phpmyadmin).
2. Create a database named `event_pro`.
3. Import the project's database SQL file after confirming that it contains the schema and only safe sample data.

Do not import an empty SQL file. A clean database setup script should include the required table definitions.

### 5. Configure the database connection

Configure the application's database connection for your local environment.

For a typical XAMPP installation, the local settings may use:

- Host: `127.0.0.1`
- Database: `event_pro`
- Username: `root`
- Password: your local MySQL password

Your actual settings may differ. Do not publish real database passwords, production credentials, or other secrets in this repository.

### 6. Open the application

After the database and configuration are ready, open:

- Main page: [http://localhost/event-pro/](http://localhost/event-pro/)
- Staff login: [http://localhost/event-pro/staff/login.php](http://localhost/event-pro/staff/login.php)
- Admin event management: [http://localhost/event-pro/admin/manage-events.php](http://localhost/event-pro/admin/manage-events.php)

Admin pages require an authenticated account with the appropriate role.

## Creating an Administrator Account

Use the application's authorized staff-account creation process to create an administrator account. Follow the application's setup instructions and use a strong, unique password.

Do not commit real usernames, password hashes, passwords, or private account information to GitHub.

## Project Structure

```text
event-pro/
├── admin/                  # Administrative event management
├── assets/
│   ├── css/                # Stylesheets
│   └── js/                 # JavaScript files
├── config/                 # Application configuration
├── database/               # Database setup scripts
├── includes/               # Shared PHP functions and security helpers
├── staff/                  # Staff dashboard and attendance tools
├── storage/
│   └── qrcodes/            # Generated QR codes (local files)
├── index.html              # Public landing page
├── register.php            # Attendee registration
├── event-details.php       # Event details
├── registration-success.php
├── README.md
└── .gitignore
```

## Security Notes

- Keep local credentials and environment files out of version control.
- Use strong passwords for staff accounts.
- Use HTTPS and secure session settings when deploying to a public server.
- Keep PHP and third-party dependencies updated.
- Use a least-privilege database account for production.
- Back up the database before performing maintenance.
- Do not publish attendee personal information, private QR codes, generated reports, or production database exports.
- Configure production errors and logs so sensitive information is not exposed to visitors.

## Development

For local development, place the project in XAMPP's `htdocs` directory and access it through `localhost`.

Run PHP syntax checks from the project root with:

```bash
C:\xampp\php\php.exe -l path\to\file.php
```

## Limitations

Deployment requirements depend on the hosting environment, PHP version, database configuration, and installed Composer packages. Verify registration capacity enforcement, QR scanning, permissions, and database setup in a clean environment before deploying.

## License

No license has been specified yet. Add a license file if you intend to permit others to reuse, modify, or distribute this project.
