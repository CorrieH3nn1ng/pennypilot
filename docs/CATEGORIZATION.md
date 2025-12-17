# Transaction Categorization

PennyPilot uses a multi-layered categorization system that combines built-in rules, user-defined rules, and learning from manual categorization.

## How It Works

### Priority Order

1. **User-Defined Rules** (highest priority)
   - Rules you create by using "Apply to similar"
   - Stored locally in IndexedDB
   - Persist across sessions

2. **Built-in SA Merchant Rules**
   - Pre-configured South African merchants
   - Updated with app releases

### Auto-Categorization

Run auto-categorization from:
- **On Import:** Automatically runs after CSV import
- **Dashboard:** Click "Auto-Categorize" on the uncategorized alert

### Manual Categorization with Learning

When you manually categorize a transaction:

1. Click on any transaction
2. Select a category
3. If similar transactions exist, you'll see:
   ```
   ☑ Apply to 15 similar transactions
   Pattern to match: [CHECKERS]
   ```
4. Toggle ON to apply to all matching transactions
5. A rule is saved for future auto-categorization

### Pattern Extraction

The system automatically extracts clean patterns from descriptions:

| Original | Extracted Pattern |
|----------|-------------------|
| `CHECKERS SANDTON 1234567890` | `CHECKERS SANDTON` |
| `Google TV 4606390202677923` | `GOOGLE TV` |
| `UBER EATS 25Jan2025` | `UBER EATS` |

**Removed automatically:**
- Card numbers (6+ digits)
- Dates (25Jan2025 format)
- Reference numbers
- Special characters

### Creating Custom Categories

**From Categories Page:**
1. Go to Categories
2. Click "Add Category"
3. Enter name, select color and icon
4. Choose Expense or Income type

**Quick Add (while categorizing):**
1. Click on a transaction
2. Open the Category dropdown
3. Select "+ Create New Category"
4. Enter name and pick color
5. Click "Create & Use"

## Built-in Keywords

### Groceries
```
CHECKERS, PICK N PAY, SHOPRITE, WOOLWORTHS, SPAR,
FOOD LOVERS, MAKRO, BOXER, USAVE, OK FOODS
```

### Transport
```
UBER TRIP, BOLT RIDE, GAUTRAIN, E-TOLL, ETOLL,
SANRAL, PARKING, AVIS, HERTZ
```

### Fuel
```
ENGEN, CALTEX, SHELL, SASOL, BP, TOTAL,
PETROL, DIESEL, SERVICE STATION
```

### Utilities
```
ESKOM, CITY POWER, ELECTRICITY, TELKOM,
VODACOM, MTN, CELL C, FIBRE, AFRIHOST
```

### Entertainment
```
NETFLIX, SHOWMAX, DSTV, MULTICHOICE,
STER KINEKOR, DISNEY+, AMAZON PRIME
```

### Dining Out
```
UBER EATS, MR DELIVERY, NANDOS, KFC, MCDONALDS,
STEERS, SPUR, DEBONAIRS, DOMINOS, RESTAURANT
```

### Healthcare
```
DISCHEM, CLICKS, PHARMACY, DOCTOR, PATHCARE,
LANCET, NETCARE, MEDICLINIC, DENTIST
```

### Shopping
```
TAKEALOT, AMAZON, GAME, INCREDIBLE CONNECTION,
MR PRICE, EDGARS, FOSCHINI, BUILDERS WAREHOUSE
```

### Insurance
```
OLD MUTUAL, SANLAM, DISCOVERY INSURE, MOMENTUM,
OUTSURANCE, SANTAM, HOLLARD, MIWAY
```

### Bank Fees
```
MONTHLY FEE, SERVICE FEE, ATM FEE, LEDGER FEE,
ADMIN FEE, NOTIFICATION FEE
```

### Business Expenses
```
CLAUDE AI, ANTHROPIC, OPENAI, CHATGPT, GITHUB,
DIGITALOCEAN, AWS, AZURE, GOOGLE CLOUD,
SLACK, ZOOM, NOTION, FIGMA, XERO, PAYFAST, YOCO
```

### Medical Aid
```
DISCOVERY HEALTH, BONITAS, GEMS, FEDHEALTH,
MEDIHELP, BESTMED, MEDICAL SCHEME
```

### Income - Salary
```
SALARY, WAGES, NETT PAY, PAYROLL
```

### Income - Interest
```
INTEREST CREDIT, INT CREDIT, INTEREST EARNED
```

### Income - Refund
```
REFUND, REVERSAL, CASHBACK, EBUCKS
```

## User Rules Storage

User-defined rules are stored in LocalBase (IndexedDB):

```typescript
interface CategoryRule {
  id: string;
  pattern: string;        // e.g., "CHECKERS"
  category_id: string;
  category_name: string;
  match_type: 'contains' | 'starts_with' | 'exact';
  is_user_defined: boolean;
  created_at: string;
  hit_count: number;      // Times this rule was applied
}
```

## Tips

1. **Be specific with patterns:** "CHECKERS SANDTON" vs just "CHECKERS"
2. **Review similar count:** Check how many transactions will be affected
3. **Edit patterns:** Adjust the extracted pattern before saving
4. **User rules win:** Your rules always override built-in rules
