# Archetypes Module

> **Module Path:** `backend/app/Archetypes/`
> **Architectural Directive:** #4 - Penny's Soul + #5 - Gamification

## Purpose

This module implements PennyPilot's "Cradle to Grave" financial lifecycle logic. It models user financial journeys from first job to retirement, providing stage-appropriate guidance, goals, and product recommendations.

## Intended Components

```
Archetypes/
├── Models/
│   ├── LifeStage.php              # Life stage definitions
│   ├── FinancialArchetype.php     # User archetype model
│   └── MilestoneDefinition.php    # Life milestone templates
├── Services/
│   ├── ArchetypeDetector.php      # Analyze user to assign archetype
│   ├── LifeStageTransition.php    # Handle stage progression
│   ├── MilestoneTracker.php       # Track life milestones
│   └── GuidanceEngine.php         # Stage-specific recommendations
├── Data/
│   ├── SouthAfricanLifeStages.php # SA-specific stage definitions
│   ├── FinancialMilestones.php    # Universal milestone list
│   └── ArchetypeRules.php         # Detection rule engine
└── Events/
    ├── LifeStageChanged.php       # User progressed to new stage
    └── MilestoneReached.php       # User hit a financial milestone
```

## Key Concepts

### Life Stages (South African Context)

| Stage | Age Range | Key Financial Focus |
|-------|-----------|---------------------|
| **Student** | 18-24 | Bursaries, part-time income, avoid debt |
| **Early Career** | 25-34 | Emergency fund, retirement start, debt payoff |
| **Establishing** | 35-44 | Property, family costs, insurance review |
| **Peak Earning** | 45-54 | Max retirement contributions, kids' education |
| **Pre-Retirement** | 55-64 | Wealth preservation, healthcare planning |
| **Retirement** | 65+ | Living annuity management, estate planning |

### Financial Archetypes

Beyond age, users exhibit spending personalities:

| Archetype | Behavior Pattern | Penny's Approach |
|-----------|-----------------|------------------|
| **Saver** | Consistently under-spends | Encourage enjoying life |
| **Balancer** | Stable income/expense ratio | Optimize and grow |
| **Spender** | Expenses often exceed income | Gentle budget nudges |
| **Avoider** | Ignores financial tasks | Gamify engagement |
| **Planner** | Detail-oriented, future-focused | Advanced tools |
| **Survivor** | Living paycheck to paycheck | Empathy first, small wins |

### Milestone Tracking

Life events that trigger financial guidance:

```php
$milestones = [
    'first_salary' => 'Welcome to earning! Let\'s set up your foundation.',
    'emergency_fund_complete' => 'Amazing! You have 3 months of safety net.',
    'first_property' => 'Homeowner! Let\'s optimize your bond.',
    'first_child' => 'Congratulations! Time to review life cover.',
    'retirement_eligible' => 'You\'ve reached retirement age. Let\'s plan.',
];
```

## Cradle to Grave Flow

```
User Onboarding
      ↓
ArchetypeDetector::analyze($user)
      ↓
Assign: LifeStage + FinancialArchetype
      ↓
GuidanceEngine::getRecommendations($user)
      ↓
Display: Personalized quests, tips, product suggestions
      ↓
User Actions (transactions, categorization, budgets)
      ↓
MilestoneTracker::checkProgress($user)
      ↓
Event: MilestoneReached / LifeStageChanged
      ↓
Update guidance, unlock new features
```

## Integration with Penny's Soul

The archetype informs Penny's personality and tone:

```php
// Survivor archetype + missed budget
$tone = 'empathetic';
$message = "I know things are tight right now. Let's focus on one small win today.";

// Planner archetype + exceeded savings goal
$tone = 'analytical';
$message = "You're 15% ahead of your savings target. Consider increasing your RA contribution.";
```

## South African Specifics

- **Tax-Free Savings Account (TFSA)** milestones
- **Retirement Annuity (RA)** contribution tracking
- **Medical Aid** lifecycle (parents' plan → own plan)
- **Property** (rent vs. buy guidance for SA market)
- **UIF/TERS** awareness for job transitions
- **Black Tax** recognition and planning support

## Data Privacy

- Archetype is derived from anonymized spending patterns
- No explicit demographic collection required
- User can override detected archetype
- All archetype data included in POPIA exports

---

*For technical auditors: This module contains business logic only. It processes transaction patterns to provide personalized guidance. No financial transactions are initiated by this module.*
