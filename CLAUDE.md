# Claude Code Context

This file provides context for Claude Code when working on the PennyPilot project.

## Project Overview

PennyPilot is an AI-powered personal finance app designed for South African consumers. It features offline-first architecture, intelligent transaction categorization, and CSV import from SA banks.

## Architecture

### Frontend (Vue.js 3 + Quasar)
- **Location:** `apps/pennypilot/frontend/`
- **Framework:** Vue.js 3 with TypeScript, Quasar Framework
- **State Management:** Pinia stores in `src/stores/`
- **Offline Storage:** LocalBase (IndexedDB wrapper) in `src/services/storage/`
- **Dev Server:** Vite on port 9000-9003

### Backend (Laravel)
- **Location:** `apps/pennypilot/backend/`
- **Framework:** Laravel 12 with PHP 8.4
- **Database:** MySQL 8.0
- **Dev Server:** `php artisan serve` on port 8000

## Key Services

### Categorization (`src/services/categorization/CategorizationService.ts`)
- Auto-categorization with SA merchant keywords
- User-defined rules stored in IndexedDB
- Pattern extraction removes card numbers, dates, references
- User rules take priority over built-in rules

### CSV Parsing (`src/services/parsers/`)
- `NedbankParser.ts` - Handles Nedbank CSV format
- Supports ddMMMyyyy date format (e.g., 25Jan2025)
- Auto-detects and skips metadata rows

### Offline Storage (`src/services/storage/LocalBaseService.ts`)
- Stores transactions, categories, and user rules locally
- Syncs to backend when online
- Uses IndexedDB via LocalBase library

## Documentation

- **[Features](docs/FEATURES.md)** - Complete feature documentation
- **[Categorization](docs/CATEGORIZATION.md)** - How categorization works

## Common Tasks

### Adding a new category
1. Update `CategorySeeder.php` for default categories
2. Add keywords to `CategorizationService.ts` for auto-categorization

### Adding a new bank parser
1. Create parser in `src/services/parsers/`
2. Extend the base parser pattern
3. Register in import flow

### Modifying transactions store
- File: `src/stores/transactions.store.ts`
- Uses Pinia with async actions
- Integrates with LocalBaseService and CategorizationService

## Database Tables

- `users` - User accounts
- `categories` - Transaction categories (system + custom)
- `transactions` - Financial transactions with sync support
- `category_rules` (IndexedDB) - User-defined categorization rules

## Running the App

```bash
# Frontend
cd apps/pennypilot/frontend
npm run dev

# Backend
cd apps/pennypilot/backend
php artisan serve
```

## Testing

```bash
# Frontend lint
npm run lint

# Backend tests
cd apps/pennypilot/backend
php artisan test
```
