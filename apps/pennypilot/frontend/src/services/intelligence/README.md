# Intelligence Services

> **Service Path:** `frontend/src/services/intelligence/`
> **Architectural Directive:** #4 - Penny's Soul (Fuzzy AI)

## Purpose

This service layer provides contextual financial intelligence by analyzing user spending patterns against peer benchmarks and life-stage expectations. It powers Penny's empathetic, personalized insights.

## Intended Components

```
intelligence/
├── PeerBenchmarkService.ts    # Anonymous peer comparison
├── LifeStageService.ts        # Age/life-stage financial norms
├── InsightGenerator.ts        # Natural language insight creation
├── AnomalyDetector.ts         # Unusual spending pattern detection
├── TrendAnalyzer.ts           # Month-over-month pattern analysis
└── types/
    └── intelligence.types.ts  # Benchmark, LifeStage, Insight types
```

## Key Concepts

### Peer Benchmarking
Compare user spending (anonymously) against similar demographics:
- **Income Bracket**: Similar earning levels
- **Location**: Regional cost-of-living adjustments
- **Household Size**: Family vs. single comparisons
- **Industry**: Profession-specific patterns

Example insight: *"You spend 15% less on transport than peers in your income bracket. Great job!"*

### Life-Stage Logic
Financial expectations based on life phase:
- **Student** (18-24): Education costs, low income
- **Early Career** (25-34): Growth, debt repayment
- **Family Building** (35-44): Childcare, property
- **Peak Earning** (45-54): Savings acceleration
- **Pre-Retirement** (55-64): Wealth preservation
- **Retirement** (65+): Income drawdown

Example insight: *"At your life stage, building an emergency fund is a top priority."*

### Insight Types
- **Celebration**: Positive reinforcement for good habits
- **Gentle Nudge**: Soft suggestion for improvement
- **Alert**: Concerning pattern detected
- **Education**: Financial literacy moment

## Data Privacy (Critical)

### Zero-Knowledge Architecture
- All comparisons use **aggregate statistics only**
- No individual user data leaves the device for benchmarking
- Benchmark data is pre-computed, anonymized, and cached
- User demographics are hashed, not stored in plaintext

### Data Sources
- **Local**: User's own transaction history
- **Remote**: Anonymized aggregate statistics (no PII)
- **Static**: Life-stage financial guidelines (SA-specific)

## Integration Points

```
TransactionStore → TrendAnalyzer → InsightGenerator → Penny Persona
                                                    ↓
UserProfile → LifeStageService ────────────────────→
                                                    ↓
RemoteStats → PeerBenchmarkService ────────────────→
```

## POPIA Compliance

- User can opt-out of peer comparison entirely
- No transaction data uploaded for benchmarking
- Demographics are optional and locally stored
- Insights are generated client-side only

---

*For technical auditors: This service performs statistical analysis locally. Remote calls fetch only pre-aggregated, anonymized benchmark datasets.*
