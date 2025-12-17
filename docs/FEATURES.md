# PennyPilot Features

## Overview

PennyPilot is an AI-powered personal finance app designed for South African consumers, with offline-first architecture and intelligent transaction categorization.

## Core Features

### 1. CSV Import (Nedbank Support)

Import bank statements directly from CSV files.

- **Supported Banks:** Nedbank (more coming soon)
- **Date Format:** Handles `ddMMMyyyy` format (e.g., `25Jan2025`)
- **Auto-detection:** Skips metadata rows and summary lines
- **Duplicate Prevention:** Uses bank reference to prevent duplicate imports

**Usage:**
1. Go to Import CSV
2. Select your Nedbank CSV file
3. Review the preview
4. Click Import

### 2. Dashboard

Real-time overview of your finances.

- **Current Balance:** Calculated from opening balance + transactions
- **Income/Expenses Summary:** Total income and expenses
- **Opening Balance:** Set your bank balance to calculate correct totals
- **Uncategorized Alert:** Shows count of transactions needing categorization

#### Charts
- **Spending by Category:** Doughnut chart showing expense breakdown
- **Monthly Trend:** Bar chart comparing income vs expenses over 6 months

### 3. Transaction Management

Full control over your transactions.

#### Tabs
- **All:** View all transactions with monthly summary cards
- **Uncategorized:** Focus on transactions needing categorization
- **Categorized:** View already categorized transactions

#### Monthly Summary Cards
- Click a month card to filter transactions
- Shows income, expenses, and transaction count per month

#### Filters
- Search by description
- Filter by category
- Date range filtering

### 4. Smart Categorization

#### Auto-Categorization
Automatically categorizes transactions based on merchant keywords.

**Built-in South African Merchants:**
| Category | Examples |
|----------|----------|
| Groceries | Checkers, Pick n Pay, Woolworths, Shoprite |
| Transport | Uber, Bolt, Gautrain, E-toll |
| Fuel | Engen, Shell, Caltex, Sasol |
| Dining Out | Uber Eats, Nandos, KFC, Steers |
| Entertainment | Netflix, DStv, Showmax |
| Business Expenses | Claude AI, GitHub, AWS, Slack |
| ... | 20+ categories |

#### Learning Categorization
The system learns from your manual categorizations.

1. Categorize a transaction manually
2. Toggle "Apply to similar transactions"
3. Adjust the pattern if needed
4. All matching transactions are categorized
5. Rule is saved for future auto-categorization

### 5. Custom Categories

Create your own categories for specific tracking.

- **From Categories Page:** Full customization with icon and color
- **Quick Add:** Create categories while categorizing a transaction

**Use Cases:**
- Track specific clients
- Monitor project expenses
- Separate business from personal

### 6. Balance Management

Set your current bank balance to calculate accurate totals.

**How it works:**
1. Enter your current bank balance
2. System calculates: Opening Balance = Current Balance - Sum of Transactions
3. Dashboard shows accurate current balance

### 7. Sync to Server

Push local transactions to the backend for backup and multi-device access.

- Click sync icon in header
- Transactions marked as synced
- Duplicate detection prevents double-syncing

## Offline-First Architecture

- All data stored locally in IndexedDB (LocalBase)
- Works without internet connection
- Sync when online

## Categories

### Expense Categories
- Groceries
- Transport
- Fuel
- Utilities
- Entertainment
- Dining Out
- Healthcare
- Shopping
- Insurance
- Bank Fees
- Subscriptions
- Medical Aid
- Pension
- Domestic Help
- Rates & Taxes
- Gambling/Lotto
- Business Expenses
- Other Expense

### Income Categories
- Salary
- Freelance/Contract
- Investment
- Refund
- Interest
- Other Income
