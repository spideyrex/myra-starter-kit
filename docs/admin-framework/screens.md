# Screen Reference

Every admin page in Myra v2.6.2, captured against production at 1440×900.

Screenshots are produced by `take-screenshots-all.mjs`, which **verifies as it captures**: a page
that renders an error modal, a 4xx/5xx page or a blank body is skipped rather than saved, so a
broken page can never be mistaken for documentation.

> Regenerate with:
> ```
> MYRA_CHROME="<path to chrome>" node take-screenshots-all.mjs
> ```
>
> For a faster health check with no screenshots, `node check-pages.mjs` crawls the same pages and
> reports only the failures. Both log in as the seeded demo admin.

**Why this exists.** An HTTP status check cannot see these pages fail — they all return 200. A
plugin nav item pointing at a JSON route, an empty-string `SelectItem`, or a paginator missing its
`meta` all render a broken page behind a perfectly healthy status code. Three such defects reached
production before this pass existed.

**What it does not check.** These verify a page *renders without erroring*. They say nothing about
whether it **looks right** — layout, spacing and visual regressions still need human eyes.

**78 pages captured, none skipped.**

---

## Contents

- [Core](#core) — 6 pages
- [User management](#user-management) — 5 pages
- [Content](#content) — 7 pages
- [Brand & appearance](#brand-appearance) — 2 pages
- [Reporting](#reporting) — 2 pages
- [shadcn](#shadcn) — 2 pages
- [Email](#email) — 5 pages
- [System](#system) — 9 pages
- [Feature gallery](#feature-gallery) — 40 pages

---

## Core

### Dashboard

`/dashboard`

<img src="../../public/docs/screenshots/dashboard.png" alt="Dashboard" width="900">

### Profile

`/profile`

<img src="../../public/docs/screenshots/profile.png" alt="Profile" width="900">

### Profile edit

`/profile/edit`

<img src="../../public/docs/screenshots/profile-edit.png" alt="Profile edit" width="900">

### Profile security

`/profile/security`

<img src="../../public/docs/screenshots/profile-security.png" alt="Profile security" width="900">

### Notifications

`/notifications`

<img src="../../public/docs/screenshots/notifications.png" alt="Notifications" width="900">

### Notifications preferences

`/notifications/preferences`

<img src="../../public/docs/screenshots/notifications-preferences.png" alt="Notifications preferences" width="900">

## User management

### Users

`/admin/users`

<img src="../../public/docs/screenshots/users.png" alt="Users" width="900">

### User create

`/admin/users/create`

<img src="../../public/docs/screenshots/user-create.png" alt="User create" width="900">

### Roles

`/admin/roles`

<img src="../../public/docs/screenshots/roles.png" alt="Roles" width="900">

### Role create

`/admin/roles/create`

<img src="../../public/docs/screenshots/role-create.png" alt="Role create" width="900">

### Permissions

`/admin/permissions`

<img src="../../public/docs/screenshots/permissions.png" alt="Permissions" width="900">

## Content

### Pages

`/admin/pages`

<img src="../../public/docs/screenshots/pages.png" alt="Pages" width="900">

### Page create

`/admin/pages/create`

<img src="../../public/docs/screenshots/page-create.png" alt="Page create" width="900">

### Articles

`/admin/articles`

<img src="../../public/docs/screenshots/articles.png" alt="Articles" width="900">

### Article create

`/admin/articles/create`

<img src="../../public/docs/screenshots/article-create.png" alt="Article create" width="900">

### Categories

`/admin/categories`

<img src="../../public/docs/screenshots/categories.png" alt="Categories" width="900">

### Media

`/admin/media`

<img src="../../public/docs/screenshots/media.png" alt="Media" width="900">

### Landing

`/admin/landing`

<img src="../../public/docs/screenshots/landing.png" alt="Landing" width="900">

## Brand & appearance

### Brand

`/admin/brand`

<img src="../../public/docs/screenshots/brand.png" alt="Brand" width="900">

### Settings

`/admin/settings`

<img src="../../public/docs/screenshots/settings.png" alt="Settings" width="900">

## Reporting

### Reports index

`/admin/reports`

<img src="../../public/docs/screenshots/reports-index.png" alt="Reports index" width="900">

### Report schedules

`/admin/report-schedules`

<img src="../../public/docs/screenshots/report-schedules.png" alt="Report schedules" width="900">

## shadcn

### Blocks

`/admin/blocks`

<img src="../../public/docs/screenshots/blocks.png" alt="Blocks" width="900">

### Examples

`/admin/examples`

<img src="../../public/docs/screenshots/examples.png" alt="Examples" width="900">

## Email

### Email templates

`/admin/email-templates`

<img src="../../public/docs/screenshots/email-templates.png" alt="Email templates" width="900">

### Email template create

`/admin/email-templates/create`

<img src="../../public/docs/screenshots/email-template-create.png" alt="Email template create" width="900">

### Email log

`/admin/email-logs`

<img src="../../public/docs/screenshots/email-log.png" alt="Email log" width="900">

### Email settings

`/admin/email-settings`

<img src="../../public/docs/screenshots/email-settings.png" alt="Email settings" width="900">

### Firebase settings

`/admin/firebase-settings`

<img src="../../public/docs/screenshots/firebase-settings.png" alt="Firebase settings" width="900">

## System

### Activity log

`/admin/activity-logs`

<img src="../../public/docs/screenshots/activity-log.png" alt="Activity log" width="900">

### Api tokens

`/admin/api-tokens`

<img src="../../public/docs/screenshots/api-tokens.png" alt="Api tokens" width="900">

### Backups

`/admin/backups`

<img src="../../public/docs/screenshots/backups.png" alt="Backups" width="900">

### System health

`/admin/system-health`

<img src="../../public/docs/screenshots/system-health.png" alt="System health" width="900">

### Admin notifications

`/admin/notifications`

<img src="../../public/docs/screenshots/admin-notifications.png" alt="Admin notifications" width="900">

### Admin notification create

`/admin/notifications/create`

<img src="../../public/docs/screenshots/admin-notification-create.png" alt="Admin notification create" width="900">

### Plugin example

`/admin/myra-example`

<img src="../../public/docs/screenshots/plugin-example.png" alt="Plugin example" width="900">

### Learning courses

`/admin/learning/courses`

<img src="../../public/docs/screenshots/learning-courses.png" alt="Learning courses" width="900">

### Learning site identity

`/admin/learning/site-identity`

<img src="../../public/docs/screenshots/learning-site-identity.png" alt="Learning site identity" width="900">

## Feature gallery

### Demo

`/admin/demo`

<img src="../../public/docs/screenshots/demo.png" alt="Demo" width="900">

### Action modals

`/admin/demo/action-modals`

<img src="../../public/docs/screenshots/demo-action-modals.png" alt="Action modals" width="900">

### Advanced filters

`/admin/demo/advanced-filters`

<img src="../../public/docs/screenshots/demo-advanced-filters.png" alt="Advanced filters" width="900">

### Ai filter

`/admin/demo/ai-filter`

<img src="../../public/docs/screenshots/demo-ai-filter.png" alt="Ai filter" width="900">

### Bulk actions

`/admin/demo/bulk-actions`

<img src="../../public/docs/screenshots/demo-bulk-actions.png" alt="Bulk actions" width="900">

### Chart primitives

`/admin/demo/chart-primitives`

<img src="../../public/docs/screenshots/demo-chart-primitives.png" alt="Chart primitives" width="900">

### Code editor

`/admin/demo/code-editor`

<img src="../../public/docs/screenshots/demo-code-editor.png" alt="Code editor" width="900">

### Conditional fields

`/admin/demo/conditional-fields`

<img src="../../public/docs/screenshots/demo-conditional-fields.png" alt="Conditional fields" width="900">

### Conversation

`/admin/demo/conversation`

<img src="../../public/docs/screenshots/demo-conversation.png" alt="Conversation" width="900">

### Dashboard editor

`/admin/demo/dashboard-editor`

<img src="../../public/docs/screenshots/demo-dashboard-editor.png" alt="Dashboard editor" width="900">

### Empty and item

`/admin/demo/empty-and-item`

<img src="../../public/docs/screenshots/demo-empty-and-item.png" alt="Empty and item" width="900">

### Field types

`/admin/demo/field-types`

<img src="../../public/docs/screenshots/demo-field-types.png" alt="Field types" width="900">

### Form builder

`/admin/demo/form-builder`

<img src="../../public/docs/screenshots/demo-form-builder.png" alt="Form builder" width="900">

### Global search

`/admin/demo/global-search`

<img src="../../public/docs/screenshots/demo-global-search.png" alt="Global search" width="900">

### Grouping

`/admin/demo/grouping`

<img src="../../public/docs/screenshots/demo-grouping.png" alt="Grouping" width="900">

### Import export

`/admin/demo/import-export`

<img src="../../public/docs/screenshots/demo-import-export.png" alt="Import export" width="900">

### Infolist

`/admin/demo/infolist`

<img src="../../public/docs/screenshots/demo-infolist.png" alt="Infolist" width="900">

### Inline editing

`/admin/demo/inline-editing`

<img src="../../public/docs/screenshots/demo-inline-editing.png" alt="Inline editing" width="900">

### Landing templates

`/admin/demo/landing-templates`

<img src="../../public/docs/screenshots/demo-landing-templates.png" alt="Landing templates" width="900">

### Live widgets

`/admin/demo/live-widgets`

<img src="../../public/docs/screenshots/demo-live-widgets.png" alt="Live widgets" width="900">

### Map

`/admin/demo/map`

<img src="../../public/docs/screenshots/demo-map.png" alt="Map" width="900">

### Map markers

`/admin/demo/map-markers`

<img src="../../public/docs/screenshots/demo-map-markers.png" alt="Map markers" width="900">

### Offline shell

`/admin/demo/offline-shell`

<img src="../../public/docs/screenshots/demo-offline-shell.png" alt="Offline shell" width="900">

### Otp and combobox

`/admin/demo/otp-and-combobox`

<img src="../../public/docs/screenshots/demo-otp-and-combobox.png" alt="Otp and combobox" width="900">

### Playground

`/admin/demo/playground`

<img src="../../public/docs/screenshots/demo-playground.png" alt="Playground" width="900">

### Plugins

`/admin/demo/plugins`

<img src="../../public/docs/screenshots/demo-plugins.png" alt="Plugins" width="900">

### Questionnaire

`/admin/demo/questionnaire`

<img src="../../public/docs/screenshots/demo-questionnaire.png" alt="Questionnaire" width="900">

### Relation manager

`/admin/demo/relation-manager`

<img src="../../public/docs/screenshots/demo-relation-manager.png" alt="Relation manager" width="900">

### Reordering

`/admin/demo/reordering`

<img src="../../public/docs/screenshots/demo-reordering.png" alt="Reordering" width="900">

### Repeater field

`/admin/demo/repeater-field`

<img src="../../public/docs/screenshots/demo-repeater-field.png" alt="Repeater field" width="900">

### Report delivery

`/admin/demo/report-delivery`

<img src="../../public/docs/screenshots/demo-report-delivery.png" alt="Report delivery" width="900">

### Reports

`/admin/demo/reports`

<img src="../../public/docs/screenshots/demo-reports.png" alt="Reports" width="900">

### Rich text editor

`/admin/demo/rich-text-editor`

<img src="../../public/docs/screenshots/demo-rich-text-editor.png" alt="Rich text editor" width="900">

### Saved views

`/admin/demo/saved-views`

<img src="../../public/docs/screenshots/demo-saved-views.png" alt="Saved views" width="900">

### Scale

`/admin/demo/scale`

<img src="../../public/docs/screenshots/demo-scale.png" alt="Scale" width="900">

### Scale cursor

`/admin/demo/scale-cursor`

<img src="../../public/docs/screenshots/demo-scale-cursor.png" alt="Scale cursor" width="900">

### Soft deletes

`/admin/demo/soft-deletes`

<img src="../../public/docs/screenshots/demo-soft-deletes.png" alt="Soft deletes" width="900">

### Tenancy

`/admin/demo/tenancy`

<img src="../../public/docs/screenshots/demo-tenancy.png" alt="Tenancy" width="900">

### Widgets

`/admin/demo/widgets`

<img src="../../public/docs/screenshots/demo-widgets.png" alt="Widgets" width="900">

### Wizard

`/admin/demo/wizard`

<img src="../../public/docs/screenshots/demo-wizard.png" alt="Wizard" width="900">

