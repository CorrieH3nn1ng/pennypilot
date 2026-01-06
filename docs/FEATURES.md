# PennyPilot Features

## Overview

PennyPilot is an AI-powered personal finance app designed for South African consumers, with offline-first architecture, intelligent transaction categorization, and comprehensive freelancer/sole proprietor support including invoicing, tax provisioning, and income tracking.

## Target Users

1. **Personal Finance Users** - Track spending, categorize transactions, budget
2. **Freelancers/Contractors** - Invoice clients, track income, provision for tax
3. **Sole Proprietors** - Manage business profile, clients, and cash flow

---

## Core Features

### 1. CSV Import (Bank Statements)

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

#### Transfer Detection
- Mark transactions as transfers between accounts
- Transfers excluded from income/expense calculations

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

### 6. Sync to Server (Premium)

Push local transactions to the backend for backup and multi-device access.

- Click sync icon in header
- Pull from server to restore data
- Transactions marked as synced
- Duplicate detection prevents double-syncing

---

## Financial Setup

### 7. Income Sources

Define your expected income streams for budgeting.

**Income Types:**
- Salary (fixed monthly)
- Freelance/Contract (hourly, daily, or fixed rates)
- Investment income
- Rental income
- Side hustle
- Other

**Features:**
- Set gross amount and frequency
- Calculate estimated monthly income
- Track net amount after deductions
- Support for hourly/daily rate calculation
- Activate/deactivate income sources

### 8. Fixed Expenses

Track recurring monthly expenses for budgeting.

- Rent/mortgage
- Insurance premiums
- Subscriptions
- Loan payments

**Features:**
- Set due day of month
- Assign to budget bucket (Needs/Wants/Savings)
- Link to categories
- Monthly and annual totals

### 9. Tax Settings

Configure tax provisioning for freelancers and self-employed.

**Employment Types:**
- Employed (PAYE handled by employer)
- Self-employed (need to provision for tax)
- Mixed (both employment and freelance income)

**Features:**
- Set estimated annual income
- Calculate provisional tax payments
- Track monthly tax provisions
- SA tax bracket calculations
- Provisional payment reminders (August/February)

---

## Budgeting

### 10. Budget (50-30-20 Methodology)

Create monthly budgets using the 50-30-20 rule.

**Buckets:**
- **Needs (50%):** Essential expenses (rent, utilities, groceries)
- **Wants (30%):** Discretionary spending (entertainment, dining out)
- **Savings (20%):** Savings, investments, debt repayment

**Features:**
- Auto-populate from fixed expenses
- Track actual vs planned spending
- Visual progress indicators
- Rollover options for unused budget
- Link budget items to categories

### 11. Goals (Coming Soon)

Set and track financial goals.

- Emergency fund targets
- Savings goals
- Debt payoff tracking

---

## Invoicing (Freelancers)

### 12. Clients

Manage your client database.

**Client Information:**
- Name and contact details
- Client code (for invoice numbering)
- Email and phone

**Billing Settings:**
- Billing period (e.g., 1st to last day of month)
- Payment day (e.g., 25th)
- Payment terms (e.g., 30 days)
- Default hourly/daily rate

### 13. Business Profile

Configure your business details for invoices.

**Business Information:**
- Business/trading name
- Registration number
- Tax number (for SARS)
- VAT number (if registered)

**Contact Details:**
- Business email and phone
- Website
- Physical address

**Banking Details:**
- Bank name and branch
- Account number
- Account type

**Invoice Settings:**
- Invoice prefix
- Default tax rate (e.g., 15% VAT)
- Payment terms text
- Default notes

### 14. Invoices

Create and manage invoices for clients.

**Invoice Types:**
- **Regular:** Create new invoices with line items
- **Historical:** Upload existing PDF invoices

**Invoice Lifecycle:**
- Draft → Sent → Paid (or Overdue → Paid)
- Auto-detect overdue invoices

**Features:**
- Auto-generated invoice numbers (YYMMDDCCC-NNN format)
- Line items with quantity, unit price, descriptions
- Tax calculation (VAT)
- PDF upload for historical invoices
- Notes and payment terms

**Invoice Number Format:**
`YYMMDDCCC-NNN`
- YY = Year
- MM = Month
- DD = Due day
- CCC = Client code
- NNN = Sequence number

### 15. AI Invoice Extraction

Extract data from uploaded invoice PDFs using AI.

**Powered by:** Google Gemini Vision AI

**Extracted Fields:**
- Invoice number
- Invoice date and due date
- Client name and email
- Total amount
- Line items

**Features:**
- Upload PDF or image
- Auto-match to existing clients
- Pre-fill invoice form
- Suggest potential transaction matches

### 16. Invoice-Transaction Matching

Link invoices to bank transactions to track payments.

**Auto-Matching:**
- Match by amount (exact or within tolerance)
- Match by invoice number in description
- Match by client name in description
- Score-based confidence ranking

**Manual Matching:**
- View potential matches for an invoice
- Select correct transaction
- Unmatch if needed

**When Matched:**
- Invoice marked as paid
- Payment date recorded
- Income entry created

---

## Settings

### 17. User Account

- Profile management (name, email)
- Password change
- Logout

### 18. Subscription Tiers

**Free Tier:**
- Local storage only
- All core features
- Single device

**Premium Tier:**
- Cloud sync and backup
- Multi-device access
- Pull/restore from server

---

## Technical Architecture

### Offline-First

- All data stored locally in IndexedDB (LocalBase)
- Works without internet connection
- Sync when online (Premium)

### Mobile-First Design

- PWA (Progressive Web App) ready
- Responsive layouts for all screen sizes
- Full-screen dialogs on mobile
- Touch-friendly interactions

### Data Flow

1. **Import:** CSV → Parse → LocalBase
2. **Categorize:** Auto-rules → User confirmation → Save
3. **Sync:** LocalBase → API → MySQL (Premium)
4. **Invoicing:** Create → Send → Match to Transaction → Mark Paid

---

## Categories Reference

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
