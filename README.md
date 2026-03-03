# Myra Starter Kit

A production-ready **Laravel + Inertia.js + Vue 3** admin dashboard with everything you need to build modern web applications.

## Features

- **User Management** - CRUD with status management, role assignment, and user impersonation
- **Roles & Permissions** - Spatie-based RBAC with granular permission groups
- **Media Manager** - Upload, organize, and manage files with Spatie Media Library
- **Email System** - Templates (with variables), email log, and SMTP settings
- **Activity Log** - Track all user and system actions
- **Backups** - Database and file backups with Spatie Backup
- **System Health** - Monitor app health, environment checks, and disk usage
- **API Tokens** - Laravel Sanctum token management
- **Notifications** - In-app notification system with real-time bell indicator
- **General Settings** - Site name, description, logo, favicon, primary color
- **Two-Factor Auth** - Google2FA with QR code setup
- **Dark Mode** - System-aware theme toggle
- **Command Palette** - Quick navigation with `Cmd+K` / `Ctrl+K`

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Vue 3 (TypeScript), Inertia.js, Tailwind CSS v4
- **UI Components**: shadcn-vue, Reka UI, Lucide Icons
- **Charts**: Chart.js via vue-chartjs

## Installation

```bash
composer create-project myra/starter-kit my-project
cd my-project
```

Copy `.env.example` to `.env` and configure your database:

```bash
cp .env.example .env
php artisan key:generate
```

Run the setup:

```bash
php artisan app:install
```

This will run migrations, seed roles/permissions, and create your admin user.

Start the development server:

```bash
composer dev
```

This starts Laravel, Vite, queue worker, and Pail concurrently.

## Default Roles

- **super-admin** - Full access to everything
- **admin** - Administrative access
- **user** - Basic user access

## Artisan Commands

```bash
# Full app setup with admin user creation
php artisan app:install

# Scaffold a new admin page
php artisan myra:page {Name}

# Scaffold a full CRUD resource
php artisan myra:resource {Name}
```

## Requirements

- PHP 8.2+
- Node.js 18+
- MySQL / PostgreSQL / SQLite

## License

MIT License. See [LICENSE](LICENSE) for details.
