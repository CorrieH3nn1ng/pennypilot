# Global Transaction Rules

PennyPilot uses a pattern-based rule system to automatically match transactions to blueprints across all months.

## How It Works

### Rule Creation Flow

1. **Manual Assignment**: When you assign a transaction to a blueprint via the Audit page
2. **Anchor Extraction**: System extracts the first meaningful word (e.g., `MOMENTUM` from `MOMENTUM 01010908391 7466TU`)
3. **Rule Persistence**: Rule is saved to `transaction_rules` table with `blueprint_id`
4. **Auto-Apply**: All matching transactions across ALL months are automatically assigned

### Pattern Extraction Logic

The system extracts the **first alphabetic word** (3+ characters) as the anchor:

| Transaction Description | Extracted Anchor |
|------------------------|------------------|
| `MOMENTUM 01010908391 7466TU` | `MOMENTUM` |
| `UIM SA 123456789` | `UIM` |
| `PIZZA PERFECT 456789` | `PIZZA` |
| `C*KOSMOS CAFE BETHAL` | `KOSMOS` |
| `MACHADO TAP N PAY` | `MACHADO` |

**Ignored patterns:**
- Numbers and reference codes
- Card numbers (masked or full)
- Dates in any format
- Special characters (`*`, `#`, `-`, etc.)

## Priority Order

1. **User-Defined Rules** (highest) - From `transaction_rules` table
2. **Blueprint Pattern Matching** - Fuzzy matching via `match_pattern` on blueprints
3. **Leak Detection** - Known bank fee patterns marked as "Unmapped Leaks"

## API Endpoints

### Apply Rules
```http
POST /api/audit/apply-rules
Content-Type: application/json

{
  "start_date": "2025-01-01",  // optional
  "end_date": "2025-12-31"     // optional
}
```

Response:
```json
{
  "success": true,
  "data": {
    "matched": 15,
    "rules_applied": {
      "MOMENTUM": 5,
      "UIM": 3,
      "PIZZA": 7
    }
  }
}
```

### List Rules
```http
GET /api/audit/rules
```

Response:
```json
{
  "success": true,
  "data": {
    "rules": [
      {
        "id": "uuid",
        "pattern": "MOMENTUM",
        "match_type": "contains",
        "blueprint_id": "uuid",
        "blueprint_name": "Insurance",
        "is_active": true,
        "hit_count": 45,
        "priority": 10
      }
    ],
    "count": 12,
    "active_count": 10
  }
}
```

### Create Rule
```http
POST /api/audit/rules
Content-Type: application/json

{
  "pattern": "MOMENTUM",
  "match_type": "contains",  // contains, starts_with, exact
  "blueprint_id": "uuid",
  "priority": 10             // optional, default 10
}
```

### Update Rule
```http
PUT /api/audit/rules/{id}
Content-Type: application/json

{
  "pattern": "MOMENTUM LIFE",
  "is_active": true,
  "priority": 20
}
```

### Delete Rule
```http
DELETE /api/audit/rules/{id}
```

## Database Schema

### transaction_rules table

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| user_id | uuid | Owner |
| pattern | varchar(100) | The keyword to match |
| match_type | enum | `contains`, `starts_with`, `exact` |
| blueprint_id | uuid | Target blueprint |
| category_id | uuid | Optional category override |
| bucket | enum | `needs`, `wants`, `savings` |
| is_active | boolean | Rule enabled/disabled |
| hit_count | integer | Times rule was applied |
| priority | integer | Higher = checked first |

## Reconciliation Flow

When `POST /api/audit/reconcile` is called:

```
1. PHASE 1: Apply Global Rules
   └── Load active TransactionRules (ordered by priority, hit_count)
   └── For each unmatched transaction:
       └── Check each rule's pattern against description
       └── If match + type compatible → assign blueprint_id
       └── Record hit, mark as match_status='rule'

2. PHASE 2: Fuzzy Matching (remaining transactions)
   └── Compare against blueprint match_pattern and match_aliases
   └── Calculate match score based on amount, date, description
   └── Auto-match if score >= 75

3. PHASE 3: Leak Detection
   └── Check remaining against LEAK_PATTERNS (bank fees, etc.)
   └── Mark as match_status='unmapped'
```

## Frontend Integration

The Audit page dropdown includes categories organized by section:

- **HOUSING**: Home Bond, Levies, Rates & Taxes, Electricity, Home Repairs
- **LIFESTYLE**: Groceries, Fuel, Medical, Domestic Help, Gardener, Dining Out, Education
- **TRANSPORT**: Toll Gates, Fines
- **BUSINESS**: Business Expenses
- **WANTS**: Purchases, Online Purchases, Subscriptions, Charity Private
- **FINANCIAL**: Insurance, Investment/Savings, Vehicle Payments, Vehicle Maintenance, Bank Fees

## Best Practices

1. **Use short, unique anchors** - "MOMENTUM" not "MOMENTUM LIFE INS 123"
2. **Check hit counts** - High hit count = reliable rule
3. **Review before bulk apply** - Use date range to test on small set first
4. **Disable instead of delete** - Set `is_active=false` to preserve history
