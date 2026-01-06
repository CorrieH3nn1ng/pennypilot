# PennyPilot Build Progress

## Core Architectural Directives

All code must adhere to these 5 pillars:

| Directive | Description | Status |
|-----------|-------------|--------|
| **1. Modular Domain Logic** | Pluggable bank integrations via interfaces | In Progress |
| **2. Zero-Knowledge Security** | Encrypted-at-rest, POPIA compliant | Phase 3 |
| **3. Oplog Sync** | Operation Log for offline-first syncing | In Progress |
| **4. Penny's Soul** | Fuzzy AI persona via n8n webhooks | Phase 2 |
| **5. Gamification** | Financial Quests to unlock premium features | Phase 2 |

---

## Phase 1: MVP - The Foundation (Current)

### Core App
- [x] Basic Laravel Backend / Vue Frontend
- [x] User Authentication (Register, Login, Profile)
- [x] CSV Import for Nedbank
- [x] Transaction Management (List, Filter, Search)
- [x] Smart Categorization (SA Merchants, Learning Rules)
- [x] Custom Categories
- [x] Dashboard with Charts
- [x] Offline-First Architecture (LocalBase/IndexedDB)
- [x] **Composite Key Duplicate Detection** (Prevents duplicate imports)

### Financial Setup
- [x] Income Sources Setup
- [x] Fixed Expenses Setup
- [x] Budget Setup UI (50/30/20 Rule)
- [x] Tax Settings & Provisioning (SA Provisional Tax Aug/Feb)

### Invoicing
- [x] Client Management (with billing settings)
- [x] Business Profile
- [x] Invoice Creation & Management
- [x] AI Invoice Extraction (Gemini Vision)
- [x] Invoice-Transaction Matching (Backend + Frontend)
- [x] Unmatch All utility endpoint

### Sync Infrastructure (Directive #3: Oplog)
- [x] **Oplog table in LocalBase** (Operation Log for mutations)
- [x] **SyncManager.ts** (Optimistic UI, background sync)
- [x] **Backend sync/apply endpoint** (Oplog processing)
- [x] **Tier-based sync gating** (Free/Cruiser/Captain)

### Blockchain Prep ("Black Box" slots)
- [x] **metadata_hash field** on Transaction model
- [x] **Hash computation methods** (SHA-256)
- [x] **blockchain_anchor field** ready for future integration

### Pricing Tiers
- [x] **Updated tier system** (Free, Cruiser, Captain)
- [x] **Tier features configuration**
- [x] **Feature gating** (sync, OCR, limits)

### Modular Bank Support (Directive #1: Pluggable)
- [x] **BaseBankParser interface** (Abstract parser)
- [x] **NedbankParser implementation**
- [ ] **FnbParser** (Planned)
- [ ] **StandardBankParser** (Planned)
- [ ] **CapitecParser** (Planned)
- [ ] **AbsaParser** (Planned)

### Remaining Phase 1 Items
- [ ] **Update stores to use oplog pattern** (All entity types)
- [ ] **Subscription Upgrade UI** (Tier comparison, upgrade flow)
- [ ] CSV Import for additional banks

---

## Phase 2: Penny's Soul & Gamification

### Penny's Soul (Directive #4: Fuzzy AI)
- [ ] **n8n Webhook Integration** (Memory layer)
- [ ] **Penny Persona Service** (Empathetic responses)
- [ ] **Proactive Insights** ("You spent more on dining...")
- [ ] **Contextual Encouragement** (Celebrate savings wins)
- [ ] **Frustration Detection** (Softer tone when user struggles)

### Financial Quests (Directive #5: Gamification)
- [ ] **Quest System Backend** (Quest definitions, progress tracking)
- [ ] **Quest UI Components** (Quest cards, progress bars)
- [ ] **Onboarding Quests**
  - [ ] "Complete your profile"
  - [ ] "Import your first statement"
  - [ ] "Categorize 10 transactions"
  - [ ] "Set up your budget"
- [ ] **Achievement Badges** (Milestones unlocked)
- [ ] **Streak Tracking** (Daily engagement bonus)
- [ ] **Quest-Based Tier Unlocks** (Earn premium features)

### Automation & Extraction
- [ ] **Slip OCR via n8n** (Receipt scanning)
- [ ] **"Goal Impact" Alerts** (Exception notifications)
- [ ] **PDF Invoice Generation** (For freelancers)
- [ ] **Recurring Transaction Detection** (Pattern analysis)
- [ ] **Email Invoice Sending** (SMTP integration)

---

## Phase 3: Zero-Knowledge Security (Directive #2)

### Client-Side Encryption
- [ ] **Web Crypto API Integration** (AES-256-GCM)
- [ ] **Key Derivation** (PBKDF2 from password)
- [ ] **12-Word Recovery Phrase** (BIP39 mnemonic)
- [ ] **Key Storage** (Encrypted in IndexedDB)
- [ ] **Encrypt Before Sync** (Server sees only ciphertext)

### POPIA Compliance
- [ ] **Data Export** (JSON/CSV of all user data)
- [ ] **Data Deletion** (Complete account purge)
- [ ] **Consent Management** (Granular permissions)
- [ ] **Audit Trail** (Access logs for user)

### Blockchain Notary
- [ ] **Merkle Tree Hashing** (Transaction integrity proof)
- [ ] **Private Notary** (Self-hosted verification)
- [ ] **Layer 2 Anchoring** (Public blockchain hash)
- [ ] **Proof Generation** (Verifiable certificates)

---

## Phase 4: Growth & Polish

### Platform
- [ ] **PWA Installation Flow** (Add to homescreen)
- [ ] **Push Notifications** (Reminders, alerts)
- [ ] **Mobile App** (Capacitor/Native wrapper)

### Features
- [ ] **Multi-Currency Support** (ZAR, USD, EUR)
- [ ] **Reports & Export** (PDF, Excel)
- [ ] **Shared Budgets** (Household accounts)

### Community
- [ ] **Leaderboards** (Optional comparison)
- [ ] **Referral Program** (Invite friends)
- [ ] **Community Quests** (Group challenges)

---

## Tier Features Matrix

| Feature | Free | Cruiser | Captain |
|---------|------|---------|---------|
| Local Storage | Yes | Yes | Yes |
| CSV Import | Yes | Yes | Yes |
| Categorization | Yes | Yes | Yes |
| Budgeting | Yes | Yes | Yes |
| Basic Quests | Yes | Yes | Yes |
| Cloud Sync | - | Yes | Yes |
| Multi-Device | - | Yes | Yes |
| PDF Export | - | Yes | Yes |
| Advanced Quests | - | Yes | Yes |
| Penny Insights | - | Basic | Full |
| OCR/AI Extraction | - | - | Yes |
| Priority Support | - | - | Yes |
| Max Transactions | 500 | Unlimited | Unlimited |
| Max Invoices | 10 | 100 | Unlimited |

---

## Quest Examples

| Quest Name | Description | Reward |
|------------|-------------|--------|
| **First Steps** | Complete profile setup | 50 XP |
| **Statement Explorer** | Import first bank statement | 100 XP + Badge |
| **Category Master** | Categorize 50 transactions | Unlock Insights |
| **Budget Builder** | Create and follow a budget for 1 month | Cruiser Trial |
| **Invoice Pro** | Create 5 invoices | 200 XP |
| **Tax Savvy** | Set up tax provisioning | Badge + Guide |
| **Streak Week** | Log in 7 consecutive days | Bonus Quest |
| **Zero Balance** | Categorize all uncategorized | Unlock Reports |

---

## Legend
- [x] Complete
- [ ] Pending
- **Bold** = Recently completed or priority item
