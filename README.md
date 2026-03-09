# Myra Starter Kit

A production-ready **Laravel 12 + Inertia.js + Vue 3** admin dashboard starter kit with everything you need to build modern web applications — authentication, CMS, email system, push notifications, media management, and 50+ UI components out of the box.

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3.5-4FC08D?logo=vuedotjs&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-5.0-3178C6?logo=typescript&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind_CSS-4.2-06B6D4?logo=tailwindcss&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Default Roles](#default-roles)
- [Admin Modules](#admin-modules)
- [CMS](#cms)
- [Homepage Builder](#homepage-builder)
- [Authentication & Security](#authentication--security)
- [Email System](#email-system)
- [Push Notifications](#push-notifications)
- [Theme System](#theme-system)
- [Settings](#settings)
- [UI Components](#ui-components)
- [Form & Table Schema Builders](#form--table-schema-builders)
- [Scaffolding Commands](#scaffolding-commands)
- [Project Structure](#project-structure)
- [Key URLs](#key-urls)
- [License](#license)

---

## Features

### Core
- **User Management** — CRUD, status management, role assignment, impersonation, bulk actions, CSV export
- **Roles & Permissions** — Spatie RBAC with permission matrix, role cloning, grouped permissions
- **Dashboard** — Real-time stats, user growth charts, activity feed, role/status breakdowns
- **Activity Log** — Full audit trail of all user and system actions with CSV export
- **Media Manager** — Upload, organize, and manage files with Spatie Media Library
- **System Health** — Database, cache, disk space, and environment monitoring
- **Backups** — Full database and file backups with download/delete via Spatie Backup
- **API Tokens** — Laravel Sanctum token creation and revocation

### Content
- **Articles** — Full CMS with categories, featured images, rich text editing, draft/publish workflow
- **Pages** — Static page management with slug routing and SEO meta
- **Categories** — Organize articles with category taxonomy
- **Homepage Builder** — Configurable landing page with hero, features, testimonials, pricing, and CTA sections

### Communication
- **Email Templates** — Database-driven templates with variable substitution
- **Email Log** — Audit trail of all sent emails with export
- **Push Notifications** — Firebase Cloud Messaging with web push support
- **In-App Notifications** — Real-time bell indicator with read/unread tracking

### Developer Experience
- **Command Palette** — Quick navigation with `Cmd+K` / `Ctrl+K`
- **Dark Mode** — System-aware theme toggle with persistence
- **Theme Presets** — 10 shadcn color themes selectable from admin
- **Scaffolding CLI** — Generate pages and full CRUD resources with one command
- **20+ Feature Demos** — Interactive showcases for every major UI pattern
- **Type-Safe** — Full TypeScript coverage on the frontend

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 12, PHP 8.2+ |
| **Frontend** | Vue 3.5 (TypeScript), Inertia.js 2.0 |
| **Styling** | Tailwind CSS 4.2 with oklch colors |
| **UI Components** | shadcn-vue, Reka UI, Lucide Icons |
| **Data Tables** | TanStack Vue Table |
| **Rich Text** | Tiptap |
| **Charts** | Chart.js via vue-chartjs |
| **Forms** | Vee-Validate + Zod |
| **Auth** | Laravel Sanctum, Google2FA |
| **RBAC** | Spatie Laravel Permission |
| **Media** | Spatie Media Library |
| **Settings** | Spatie Laravel Settings |
| **Backups** | Spatie Backup |
| **Health** | Spatie Health |
| **Activity Log** | Spatie Activity Log |
| **Push** | Firebase Cloud Messaging (Kreait SDK) |
| **Real-time** | Laravel Echo + Pusher |
| **Build** | Vite 7 |

---

## Requirements

- PHP 8.2+
- Node.js 18+
- MySQL / PostgreSQL / SQLite
- Composer 2+

---

## Installation

```bash
# Clone or create project
composer create-project myra/starter-kit my-project
cd my-project
```

Copy environment config and generate app key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your database in `.env`, then run the full setup:

```bash
php artisan app:install
```

This will run migrations, seed roles/permissions/settings, and interactively create your admin user.

Install frontend dependencies and start development:

```bash
npm install
composer dev
```

This starts Laravel, Vite dev server, queue worker, and Pail log viewer concurrently.

---

## Default Roles

| Role | Description |
|------|------------|
| **super-admin** | Full access to everything |
| **admin** | Administrative access |
| **user** | Basic user access |

50+ granular permissions organized into groups: `users`, `roles`, `permissions`, `settings`, `email`, `media`, `backups`, `system-health`, `api-tokens`, `notifications`, `activity-log`, `pages`, `articles`, `categories`, `firebase`.

---

## Admin Modules

### User Management
- Create, edit, delete (soft delete with restore)
- Status management: active, suspended, pending
- Role assignment with multi-select
- **User impersonation** — log in as any user for troubleshooting
- Bulk actions: delete, change status
- CSV export, advanced filters (status, role, search, trashed)
- Avatar upload, phone number tracking

### Roles & Permissions
- Visual permission matrix (roles vs permissions grid)
- Create, edit, delete roles
- **Clone role** — duplicate with all permissions
- Permission grouping with bulk select/deselect
- User count per role

### Dashboard
- **Stats**: Total users, active users, new this month, pending verifications
- **Charts**: User growth (6-month trend), users by role, users by status
- **Activity feed**: Last 10 actions with user attribution
- **Recent users**: Latest registrations with role badges

### Activity Log
- Automatic audit trail via Spatie Activity Log
- Causer tracking (who performed the action)
- Subject type, description, and timestamps
- CSV export, bulk delete

### Media Manager
- File upload and organization via Spatie Media Library
- Image preview, bulk delete
- Used by: user avatars, article/page featured images

### System Health
- Database connectivity check
- Cache driver status
- Disk space monitoring (warning at 70%, critical at 90%)
- Environment validation

### Backups
- On-demand database and file backups
- Download and delete backup files
- Powered by Spatie Backup

### API Tokens
- Create named Sanctum tokens
- One-time token display after creation
- Revoke tokens

---

## CMS

### Articles
- Rich text body editing with Tiptap
- Title, slug (auto-generated), excerpt, tags
- Category assignment
- Featured image upload
- **Workflow**: Draft → Published → Archived
- Public visibility toggle
- Published date scheduling
- Soft delete with restore
- Bulk actions: publish, archive, delete
- SEO meta fields

### Pages
- Static content pages (About, Terms, Contact, etc.)
- Same rich editing and workflow as articles
- Accessible at `/pages/{slug}`

### Categories
- Simple taxonomy for organizing articles
- Create, edit, delete with article count

### Public Views
- **Blog listing** at `/blog` — paginated published articles
- **Article detail** at `/blog/{slug}` — full article with related content
- **Page detail** at `/pages/{slug}` — static page rendering
- **Public layout** with responsive navbar and footer

---

## Homepage Builder

Fully configurable landing page managed from **Settings > Homepage**:

| Section | Configurable Fields |
|---------|-------------------|
| **Hero** | Title, subtitle, CTA button text/URL, background image |
| **Navbar** | Navigation links, CTA button |
| **Features** | Section title/subtitle, feature cards (icon, title, description) |
| **Testimonials** | Section title/subtitle, testimonial cards (name, role, quote) |
| **Pricing** | Section title/subtitle, pricing plan cards (name, price, features, CTA) |
| **CTA Banner** | Title, subtitle, button text/URL |
| **Footer** | Footer text, footer links |

Each section can be independently enabled or disabled. All content is managed through repeater fields with live preview.

---

## Authentication & Security

- **Login** with "Remember me"
- **Registration** with email verification
- **Password reset** via email link
- **Two-Factor Authentication** — Google2FA with QR code setup and recovery codes
- **Session management** — View active sessions, logout other devices
- **Password confirmation** for sensitive actions
- **Login auditing** — Success/failure logging with IP and user agent
- **User impersonation** with banner indicator and stop action

---

## Email System

### Templates
- Database-driven email templates with CRUD management
- Variable substitution: `{{recipient_name}}`, `{{site_name}}`, custom variables
- Rich text HTML body editor
- Test email sending

### Email Log
- Audit trail of all sent emails
- Recipient, subject, status tracking
- CSV export, bulk delete

### SMTP Settings
- Configure host, port, username, password, encryption
- From email and name
- Test connection button
- Dynamic runtime configuration

---

## Push Notifications

### Firebase Cloud Messaging
- **Admin setup** at Settings > Firebase
- Service account JSON upload and validation
- Web SDK configuration (API key, project ID, VAPID key, etc.)
- Test notification sending

### Features
- Browser push notifications via service worker
- Per-user FCM token management (multiple devices)
- Notification preferences per user
- Foreground notification handling with toast display
- Background notification handling via service worker

### Notification Types
- **System notifications** — Admin to user alerts
- **Security alerts** — Login, 2FA, password change events
- **User action notifications** — Triggered by user actions
- Supports both database and FCM channels simultaneously

---

## Theme System

10 predefined shadcn color themes selectable from **Settings > Appearance**:

| Theme | | Theme | | Theme |
|-------|---|-------|---|-------|
| Zinc (default) | | Red | | Blue |
| Slate | | Rose | | Violet |
| Stone | | Orange | | Yellow |
| | | Green | | |

- Applies across the entire application (admin + public pages)
- Supports both light and dark mode with per-mode color values
- CSS custom properties updated at runtime
- Automatically re-applies when toggling dark mode
- Theme persists in database via settings

---

## Settings

All settings are managed via Spatie Laravel Settings and cached for performance.

| Group | Fields |
|-------|--------|
| **General** | Site name, description, URL, admin email, timezone |
| **Appearance** | Logo, favicon, theme preset |
| **SEO** | Meta title, description, keywords, Google Analytics ID |
| **Social** | Facebook, Twitter, Instagram, LinkedIn URLs |
| **Maintenance** | Enable/disable maintenance mode, custom message |
| **Homepage** | All homepage section content (see Homepage Builder) |
| **Email** | SMTP configuration |
| **Firebase** | FCM configuration and credentials |

Settings are shared globally to the frontend via Inertia middleware and accessible via the `site_setting('group.key')` helper.

---

## UI Components

### 57+ shadcn-vue Components
Accordion, Alert, AlertDialog, AspectRatio, Avatar, Badge, Breadcrumb, Button, Calendar, Card, Carousel, Checkbox, Collapsible, Command, ContextMenu, Dialog, Drawer, DropdownMenu, Input, Label, Menubar, NavigationMenu, NumberField, Pagination, PinInput, Popover, Progress, RadioGroup, RangeCalendar, Resizable, ScrollArea, Select, Separator, Sheet, Sidebar, Skeleton, Slider, Sonner (toast), Spinner, Stepper, Switch, Table, Tabs, TagsInput, Textarea, Tooltip, and more.

### Custom Components
| Component | Description |
|-----------|------------|
| `PageHeader` | Page title with description and action buttons |
| `StatCard` | Dashboard stat card with icon and trend indicator |
| `LoadingButton` | Button with loading spinner state |
| `PasswordInput` | Input with show/hide password toggle |
| `DataTable` | Advanced table with sorting, filtering, pagination (TanStack) |
| `EmptyState` | Empty state placeholder with illustration |
| `ConfirmDialog` | Confirmation modal for destructive actions |
| `NotificationBell` | Real-time notification indicator |
| `TeamSwitcher` | Multi-team switching dropdown |
| `StatusBadge` | Color-coded status indicator |
| `Modal` | Generic modal wrapper |

### Admin Components
| Component | Description |
|-----------|------------|
| `ResourceForm` | Dynamic form builder for create/edit pages |
| `FormField` / `FormFields` | Schema-driven form field rendering |
| `RepeaterField` | Dynamic array field (add/remove items) |
| `SettingsCard` | Settings section card with save action |
| `RowActions` | Inline row action buttons |
| `SimpleTable` | Basic table without advanced features |
| `RelationManager` | Manage related records inline |
| `InfolistEntries` | Read-only data display |
| `DashboardGrid` | Responsive widget grid |
| `ImportModal` | CSV import with preview |

---

## Form & Table Schema Builders

### Form Schema (`useFormSchema`)
Define forms declaratively with 20+ field types:

```typescript
const schema = [
    TextInput.make('name').required(),
    TextInput.make('email').email(),
    Textarea.make('bio').colSpan(2).rows(4),
    Select.make('role').options(roleOptions),
    Toggle.make('active').label('Is Active'),
    // ... Date, DateTime, File, Image, RichText, Tags, Repeater, and more
];
```

Supports conditional visibility, field dependencies, layout grouping (Section, Grid, Tabs, Fieldset, Wizard), and Zod validation.

### Table Schema (`useTableSchema`)
Define table columns with built-in formatting:

```typescript
const columns = [
    TextColumn.make('name').sortable().searchable(),
    BadgeColumn.make('status').variants({ active: 'success', suspended: 'destructive' }),
    DateColumn.make('created_at').format('relative'),
    ToggleColumn.make('is_active'),
    ActionsColumn.make(),
];
```

Supports inline editing, custom cell rendering, bulk actions, advanced filters (query builder), grouping, reordering, import/export, and soft delete management.

---

## Scaffolding Commands

```bash
# Full app setup with interactive admin user creation
php artisan app:install

# Scaffold a new admin page (Vue + Controller + Route)
php artisan myra:page {Name}

# Scaffold a full CRUD resource (Model, Controller, Service, Vue pages, Schema)
php artisan myra:resource {Name}
```

---

## Project Structure

```
app/
├── Console/Commands/        # Artisan commands (install, scaffold)
├── Http/
│   ├── Controllers/Admin/   # 21 admin controllers
│   ├── Controllers/Auth/    # Authentication controllers
│   ├── Middleware/           # Inertia, team context
│   ├── Requests/            # Form request validation
│   └── Resources/           # API resource formatting
├── Models/                  # Eloquent models
├── Services/                # Business logic (User, Article, Page, Email, Firebase)
├── Settings/                # Spatie settings classes
├── Notifications/           # Notification classes (System, Security, FCM)
├── Channels/                # FCM notification channel
└── Listeners/               # Auth event listeners

resources/js/
├── Pages/                   # 64 Vue pages
│   ├── Admin/               # 18 admin modules
│   ├── Auth/                # 7 auth pages
│   ├── Public/              # Public article/page views
│   ├── Errors/              # 403, 404, 419, 500, 503
│   └── Home.vue             # Configurable homepage
├── Layouts/                 # Authenticated, Guest, Public
├── components/
│   ├── ui/                  # 57 shadcn-vue components
│   └── admin/               # Admin-specific components
├── composables/             # 15+ Vue composables
├── types/                   # TypeScript definitions
└── lib/                     # Utility functions

database/
├── migrations/              # 27 migration files
└── seeders/                 # Roles, permissions, settings, CMS
```

---

## Key URLs

| URL | Description |
|-----|------------|
| `/` | Public homepage (configurable) |
| `/blog` | Public article listing |
| `/pages/{slug}` | Public static pages |
| `/login` | Login page |
| `/register` | Registration page |
| `/dashboard` | Admin dashboard |
| `/admin/users` | User management |
| `/admin/roles` | Roles & permissions |
| `/admin/articles` | Article management |
| `/admin/pages` | Page management |
| `/admin/categories` | Category management |
| `/admin/media` | Media manager |
| `/admin/settings` | All settings (General, SEO, Appearance, Social, Maintenance, Homepage) |
| `/admin/email-templates` | Email template editor |
| `/admin/email-logs` | Email audit log |
| `/admin/email-settings` | SMTP configuration |
| `/admin/firebase-settings` | Push notification setup |
| `/admin/activity-logs` | Activity audit trail |
| `/admin/backups` | Backup management |
| `/admin/system-health` | System health monitor |
| `/admin/api-tokens` | API token management |
| `/admin/notifications` | Notification management |
| `/admin/demo` | Feature demos & showcases |

---

## License

MIT License. See [LICENSE](LICENSE) for details.
