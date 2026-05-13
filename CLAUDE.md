# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Monitor LPG Subsidi is a Laravel 12 application for monitoring subsidized LPG transactions per NIK (Indonesian ID number). It scrapes data from MyPertamina API (`api-map.my-pertamina.id`) and tracks violations of purchase intervals.

## Common Commands

```bash
# Initial setup
composer setup

# Development (runs server, queue, logs, vite concurrently)
composer dev

# Run tests
composer test
php artisan test                    # All tests
php artisan test --filter=TestName  # Single test

# Queue worker (for background scraping jobs)
php artisan queue:work --tries=3

# Database migrations
php artisan migrate

# Code formatting
vendor/bin/pint
```

## Architecture

### Two Main Modules

1. **Monitor NIK** (original module) - Tracks individual NIK purchase patterns
   - Controllers: `DashboardController`, `BatchScrapeController`, `TokenInputController`
   - Service: `NikMonitorService` - violation detection logic
   - Job: `ScrapeTransactionsJob` - background API scraping
   - Key tables: `transactions`, `nik_violations`, `pangkalan_tokens`

2. **Agen** (distribution management module) - Full LPG agent operations
   - Controllers in `app/Http/Controllers/Agen/`
   - Handles: Kitir (delivery slips), Surat Jalan (shipping docs), Distribusi (distribution), Brimola (BRI payments), Tebusan (redemptions)
   - Key tables: `pangkalans`, `kitirs`, `surat_jalan_headers`, `surat_jalan_details`, `distribusi_realisasis`

### Key Services

- `NikMonitorService` - Analyzes transaction patterns, detects violations (interval < 7 days, abnormal hours 05:30-20:00)
- `AutoLoginService` / `PlaywrightLoginService` - Automated MyPertamina login for token capture
- `AuditAlokasiService` - FIFO allocation audit for BRImola payments

### Data Flow

1. Bearer tokens obtained from MyPertamina (manual or auto-login)
2. `ScrapeTransactionsJob` fetches transactions from API
3. `NikMonitorService` analyzes for violations
4. Results displayed in dashboard with status: `aman` (safe), `warn`, `alert`, `new`

### Violation Detection Rules

- Rumah Tangga (household): Max 1 purchase per 7-day interval
- Usaha Mikro (micro business): Different thresholds apply
- Abnormal hours: Transactions outside 05:30-20:00 WIB flagged

## Database

Uses MySQL. Queue connection is `database` (requires `php artisan queue:table` migration).

Key relationships:
- `Transaction` belongs to a pangkalan (identified by `pangkalan_id`)
- `SuratJalanHeader` has many `SuratJalanDetail`
- `Pangkalan` links to `Transaction` records and distribution operations

## Frontend

Uses Blade templates with Tailwind CSS 4 and Vite. Views organized under:
- `resources/views/dashboard/` - NIK monitoring views
- `resources/views/agen/` - Agent operations views
- `resources/views/Map/` - Map dashboard views

## Authentication

Simple auth with role-based access (`role` column in `users` table). Roles control access to different modules.
