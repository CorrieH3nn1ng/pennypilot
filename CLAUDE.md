# Claude Code Context

This file provides context for Claude Code when working on the PennyPilot project.

## Project Overview

PennyPilot is an AI-powered personal finance app designed for South African consumers. It features:
- Offline-first architecture with cloud sync
- Intelligent transaction categorization
- CSV import from SA banks
- Freelancer/sole proprietor support (invoicing, tax, clients)
- Mobile-first PWA design

---

## INSTITUTIONAL STANDARDS

### Mission
**Financial inclusion and "Cradle to Grave" support.** PennyPilot aims to serve users from their first paycheck through retirement, providing tools that grow with their financial journey.

### Security
- **POPIA Compliant** - South African data protection standards
- **Zero-Knowledge Encryption** - User data encrypted at rest; server cannot read financial details
- **Sensitive Data Handling** - Never log or expose transaction descriptions, amounts, or personal identifiers

### Data Integrity
- **Merkle-Tree Hashing** - Backend uses hash chains for transaction notarization
- **Immutable Audit Trail** - All financial mutations are logged and verifiable
- **Checksum Validation** - Oplog entries include checksums for sync integrity

### Sync Architecture
- **Offline-First** - App works fully offline; sync when connected
- **Operation Log (Oplog)** - All mutations recorded as operations for reliable sync
- **Conflict Resolution** - Server-wins strategy with local rollback support

---

## MOBILE-NATIVE UI (Quasar)

### Mindset
**Mobile-First PWA with Native Feel.** Every feature should feel like a native app, not a website crammed into a phone.

### Touch Standards
- **44px+ Touch Targets** - All buttons, inputs, and interactive elements minimum 44px
- **Thumb-Zone Placement** - Primary actions in bottom-right quadrant (natural thumb reach)
- **Swipe Gestures** - Use `q-slide-item` for list actions where appropriate

### Visual Style
| Element | Value |
|---------|-------|
| Primary Color | Deep Teal `#004D40` |
| Quest/Gamification | Amber `#FFC107` |
| Card Border Radius | `12px` |
| Spacing Unit | `8px` grid |

### Gamification UI
- **Quest Dialogs** - Use maximized `QDialog` with `transition-show="slide-up"` and `transition-hide="slide-down"`
- **Badge Colors** - Bronze `#CD7F32`, Silver `#C0C0C0`, Gold `#FFD700`
- **Progress Bars** - Always use `q-linear-progress` with `rounded` prop

### Dialog Patterns
```vue
<q-dialog
  v-model="showQuest"
  persistent
  maximized
  transition-show="slide-up"
  transition-hide="slide-down"
>
```

---

## Documentation

- **[Progress](docs/PROGRESS.md)** - Build progress and roadmap (phases 1-4)
- **[Features](docs/FEATURES.md)** - Complete feature documentation (18 features)
- **[Categorization](docs/CATEGORIZATION.md)** - How transaction categorization works
- **[Global Rules](docs/GLOBAL_RULES.md)** - Pattern-based transaction-to-blueprint matching
- **[README](README.md)** - Project setup and development

## Architecture

### Frontend (Vue.js 3 + Quasar)
- **Location:** `apps/pennypilot/frontend/`
- **Framework:** Vue.js 3 with TypeScript, Quasar Framework
- **State Management:** Pinia stores in `src/stores/`
- **Offline Storage:** LocalBase (IndexedDB wrapper) in `src/services/storage/`
- **API Services:** `src/services/api/`
- **Dev Server:** Vite on port 9000-9003

### Backend (Laravel)
- **Location:** `apps/pennypilot/backend/`
- **Framework:** Laravel 12 with PHP 8.4
- **Database:** PostgreSQL 16
- **Dev Server:** `php artisan serve` on port 8000

## Key Backend Services

| Service | Location | Purpose |
|---------|----------|---------|
| `GeminiService` | `app/Services/GeminiService.php` | AI invoice extraction using Google Gemini |
| `InvoiceMatchingService` | `app/Services/InvoiceMatchingService.php` | Match invoices to bank transactions |

## Key Frontend Services

| Service | Location | Purpose |
|---------|----------|---------|
| `CategorizationService` | `src/services/categorization/` | Auto-categorize with SA merchant keywords |
| `LocalBaseService` | `src/services/storage/` | IndexedDB storage for offline-first |
| `NedbankParser` | `src/services/parsers/` | Parse Nedbank CSV format |

## API Endpoints

### Auth
- `POST /auth/register` - Register new user
- `POST /auth/login` - Login
- `GET /auth/user` - Get current user
- `PUT /auth/profile` - Update profile
- `PUT /auth/subscription` - Update subscription tier (admin)

### Transactions
- `GET /transactions` - List transactions
- `POST /transactions` - Create transaction
- `POST /transactions/sync` - Sync from local
- `GET /transactions/summary` - Get category summary

### Categories
- `GET /categories` - List categories
- `POST /categories` - Create custom category

### Invoices
- `GET /invoices` - List invoices
- `POST /invoices` - Create invoice
- `POST /invoices/upload-historical` - Upload PDF invoice
- `GET /invoices/{id}/matches` - Find transaction matches
- `POST /invoices/{id}/match` - Link to transaction
- `POST /invoices/auto-match` - Auto-match all invoices

### Clients
- `GET /clients` - List clients
- `POST /clients` - Create client

### Income/Budget/Tax
- `GET /income` - List income sources
- `GET /budget/{year}/{month}` - Get budget for month
- `GET /tax/settings` - Get tax settings
- `GET /tax/summary/{year}` - Get tax year summary

### AI Extraction
- `POST /ai/extract-invoice` - Extract data from PDF
- `GET /ai/status` - Check if AI is configured

## Database Tables

### Core
- `users` - User accounts with subscription tier
- `categories` - Transaction categories (system + custom)
- `transactions` - Financial transactions with sync support

### Invoicing
- `clients` - Client database with billing settings
- `invoices` - Invoices with transaction matching
- `invoice_items` - Line items for invoices
- `business_profiles` - Business details for invoicing

### Financial Setup
- `income_sources` - Expected income streams
- `fixed_expenses` - Recurring monthly expenses
- `budget_periods` - Monthly budgets
- `budget_items` - Budget line items
- `tax_settings` - Tax configuration
- `tax_provisions` - Monthly tax provisions

### Local Only (IndexedDB)
- `category_rules` - User-defined categorization rules

## Common Tasks

### Adding a new category
1. Update `CategorySeeder.php` for default categories
2. Add keywords to `CategorizationService.ts` for auto-categorization

### Adding a new bank parser
1. Create parser in `src/services/parsers/`
2. Extend the base parser pattern
3. Register in import flow

### Adding a new API endpoint
1. Create/update controller in `app/Http/Controllers/Api/`
2. Add route in `routes/api.php`
3. Add frontend service in `src/services/api/`
4. Update types in `src/types/index.ts`

### Modifying transactions store
- File: `src/stores/transactions.store.ts`
- Uses Pinia with async actions
- Integrates with LocalBaseService and CategorizationService

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
cd apps/pennypilot/frontend
npm run lint

# Type check
npx vue-tsc --noEmit

# Backend tests
cd apps/pennypilot/backend
php artisan test
```

## Environment Variables

### Frontend (.env)
```
VITE_API_URL=http://localhost:8000/api
```

### Backend (.env)
```
GEMINI_API_KEY=your_api_key  # For AI invoice extraction
```

## Mobile-First Guidelines

> See **MOBILE-NATIVE UI** section above for complete standards.

- All dialogs should be full-screen on mobile (`maximized` or `$q.screen.lt.sm`)
- Use `q-scroll-area` for scrollable content
- Keep forms compact with dense inputs
- Primary actions in thumb-zone (bottom-right) or sticky bottom bar
- Test on 412x915 viewport (mobile developer tools)
- Minimum 44px touch targets on all interactive elements
