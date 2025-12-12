# PennyPilot

AI-powered personal finance ecosystem for South African consumers.

## Tech Stack

- **Frontend:** Vue.js 3 + Quasar Framework (TypeScript)
- **Backend:** PHP 8.4 + Slim 4 + MySQL 8.0
- **Offline Storage:** LocalBase (IndexedDB)

## Project Structure

```
pilot/
├── apps/
│   └── pennypilot/
│       ├── frontend/    # Quasar Vue.js app
│       └── backend/     # PHP API
├── packages/            # Shared code
└── scripts/             # Setup scripts
```

## Getting Started

### Prerequisites

- Node.js 22+
- PHP 8.4+
- MySQL 8.0+
- Composer

### Installation

```bash
# Clone the repository
git clone https://github.com/YOUR_USERNAME/pilot.git
cd pilot

# Install dependencies
npm run setup

# Copy environment files
cp apps/pennypilot/frontend/.env.example apps/pennypilot/frontend/.env
cp apps/pennypilot/backend/.env.example apps/pennypilot/backend/.env

# Run database migrations
php scripts/migrate.php

# Start development servers
npm run dev          # Frontend (port 9000)
npm run php:serve    # Backend (port 8080)
```

## Development

### Frontend

```bash
npm run dev      # Start dev server
npm run build    # Production build
npm run lint     # Run linter
npm run test     # Run tests
```

### Backend

```bash
npm run php:serve    # Start PHP server
npm run php:lint     # Run PHP linter
npm run php:test     # Run PHP tests
```

## Git Workflow

- `main` - Production-ready code
- `develop` - Integration branch
- `feature/PP-XXX-description` - New features
- `fix/PP-XXX-description` - Bug fixes

## License

Proprietary - All rights reserved
