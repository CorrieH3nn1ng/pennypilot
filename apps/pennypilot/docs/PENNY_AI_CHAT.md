# Penny AI Chat - Hybrid Architecture

## Overview

Penny is PennyPilot's AI financial co-pilot. She uses a **hybrid architecture** that keeps your financial data private while still providing intelligent conversation.

## Privacy-First Design

```
┌─────────────────────────────────────────────────────────────┐
│                     LOCAL (Private)                         │
│  - Financial queries (spending, balance, summaries)         │
│  - App help (how to import, create invoice, etc.)           │
│  - Navigation commands                                       │
│  - Speech-to-text recognition                               │
│                                                             │
│              YOUR DATA NEVER LEAVES YOUR MACHINE            │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                   INTERNET (via n8n)                        │
│  - General conversation                                     │
│  - Goal planning advice                                     │
│  - Motivational responses                                   │
│                                                             │
│         Only identity/goals sent - NO financial data        │
└─────────────────────────────────────────────────────────────┘
```

## Features

### 1. Financial Queries (Local)

Ask Penny about your spending - queries your local database directly:

- "How much did I spend on fuel this month?"
- "What did I pay for groceries last month?"
- "Give me a summary"
- "Did I pay my bond?"

**Supported categories:**
- Fuel (petrol, diesel, Engen, Sasol, Shell, BP)
- Groceries (Pick n Pay, Checkers, Woolworths, Spar, Shoprite)
- Entertainment (Netflix, Spotify, DStv, Showmax)
- Transport (Uber, Bolt, e-toll)
- Utilities (electricity, water, Eskom)
- Insurance (Discovery, Outsurance, Santam)
- Medical (Clicks, Dis-Chem, pharmacy)
- Bond/Mortgage
- Rent
- Car payments
- Levies
- Internet/Phone
- Subscriptions
- Education
- Domestic help
- Security
- SARS/Tax
- Alimony/Maintenance

**Time periods:**
- "this month" (default)
- "last month"
- "this year"

### 2. App Navigation (Local)

Penny can navigate you directly to the right page:

- "I need to upload a statement" → Import page
- "Create an invoice" → Invoices page
- "Set up my budget" → Budget page
- "Categorize transactions" → Audit page
- "Help with tax" → Tax page
- "Add a client" → Clients page
- "Log a trip" → Trips page

### 3. Speech-to-Text (Local)

Tap the microphone button to speak to Penny:

- Uses browser's built-in Speech Recognition API
- Set to South African English (en-ZA)
- No data sent to any server
- Auto-sends message when you stop speaking

### 4. General Chat (via n8n/Gemini)

For non-financial conversation, Penny uses Google Gemini via n8n:

- Goal planning and motivation
- General questions
- Advice and guidance

**What gets sent to Gemini:**
- Your name
- Your age
- Your boss goal (name + target)
- Quadrant status (DO/HAVE/BE/LIVE empty or filled)

**What NEVER gets sent:**
- Transaction data
- Spending amounts
- Bank balances
- Invoice details
- Any financial specifics

## Database Schema

Penny's memory is stored in the `penny_memory` table:

```sql
- user_id (FK to users)
- display_name, age, primary_client, occupation
- boss_goal_name, boss_goal_target, boss_goal_current
- has_quests, has_inventory, has_traits, has_destination
- daily_quests (JSON), inventory_items (JSON), character_traits (JSON)
- quest_streak, total_xp
- current_location, target_realm, target_realm_cost
- n8n_session_id
- last_interaction
```

## API Endpoints

### Backend (Laravel)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/penny/memory` | GET | Get user's Penny memory |
| `/api/penny/memory` | PUT | Update memory |
| `/api/penny/chat` | POST | Send message to Penny |
| `/api/penny/sync-avatar` | POST | Sync avatar data to memory |
| `/api/penny/quest` | POST | Add a quest |
| `/api/penny/item` | POST | Add inventory item |
| `/api/penny/boss-goal` | POST | Set boss goal |

### n8n Workflow

The n8n workflow (`docs/n8n-penny-workflow.json`) handles general chat:

1. **Webhook** - Receives chat request
2. **Build Prompt** - Constructs system prompt with user context
3. **AI Agent + Gemini** - Generates response
4. **Parse Response** - Extracts reply text
5. **Respond** - Returns JSON to Laravel

## Configuration

### Backend (.env)

```env
N8N_PENNY_WEBHOOK_URL=http://localhost:5678/webhook/penny-chat
GEMINI_API_KEY=your_api_key
```

### n8n Setup

1. Import `docs/n8n-penny-workflow.json` into n8n
2. Configure Gemini credentials
3. Activate the workflow

## File Structure

```
backend/
├── app/Http/Controllers/Api/PennyController.php  # Main controller
├── app/Models/PennyMemory.php                    # Memory model
├── database/migrations/..._create_penny_memory_table.php
└── database/seeders/PennyMemorySeeder.php

frontend/
├── src/components/PennyChatOverlay.vue           # Chat UI component
├── src/services/api/penny.api.ts                 # API service
└── src/env.d.ts                                  # Speech API types

docs/
├── n8n-penny-workflow.json                       # n8n workflow export
└── PENNY_AI_CHAT.md                              # This file
```

## Security Notes

1. **Financial data stays local** - All spending queries hit your PostgreSQL directly
2. **No transaction descriptions sent** - Gemini never sees your bank statement
3. **Speech recognition is local** - Browser API, no cloud transcription
4. **Session-based memory** - Each user has isolated memory
5. **POPIA compliant** - User data not shared with AI providers
