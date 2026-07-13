# Pond Manager

A self-hosted business management tool for a pond/fishery operation, built with vanilla PHP. Tracks ponds, expenses, sales, partner ownership, cash advances, and profit-sharing settlements — deployed as a lean 28-file flat structure with modal-based AJAX CRUD throughout.

## Features

- **Pond tracking** — per-pond detail view with full activity history
- **Expenses & sales** — logged per pond, feeding the profit calculation
- **Partners** — ownership tracking with a per-partner detail view
- **Advances** — cash advances against future profit share
- **Profit settlement** — automatic owner/worker pool split (1/3 owner, 2/3 workers)
- **Reports** — date-filtered financial reporting
- **Modal-based CRUD** — every create/edit action happens in an AJAX modal, no page reloads or separate create/edit pages

## Tech Stack

- PHP (PDO, prepared statements)
- MySQL
- Vanilla JS (AJAX, JSON endpoints under `/ajax`)

## Architecture

A flat, framework-free structure — condensed from an original 50+ file/folder layout down to 28 files by merging config and consolidating each domain (ponds, expenses, sales, partners, advances) into a single page + a matching AJAX handler.

```
├── ajax/                  # JSON API endpoints (ponds, expenses, sales, partners, advances)
├── assets/css/style.css
├── assets/js/main.js
├── includes/               # layout_header.php, layout_footer.php
├── config.php              # DB + auth + session bootstrap (reads from env, falls back to local defaults)
├── functions.php           # helpers: e(), redirect(), formatCurrency(), jsonOk()/jsonErr(), flash()
├── install.php              # one-time DB installer (creates tables + default admin)
├── login.php / logout.php / index.php
├── dashboard.php / ponds.php / expenses.php / sales.php
├── partners.php / advances.php / profit.php / reports.php
```

## Setup

1. Create a MySQL database.
2. Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` as environment variables (or edit the defaults in `config.php`).
3. Visit `install.php` once to create the schema and default admin account, then delete or rename it.

## Screenshots

_Dashboard, pond detail, and profit settlement views — add screenshots here._
