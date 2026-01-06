# Gamification Module

> **Module Path:** `frontend/src/modules/gamification/`
> **Architectural Directive:** #5 - Financial Quests

## Purpose

This module implements PennyPilot's gamification layer, transforming financial management from a chore into an engaging journey. Users progress through "Financial Quests" to unlock features, earn rewards, and build healthy money habits.

## Intended Components

```
gamification/
├── components/
│   ├── QuestCard.vue          # Individual quest display
│   ├── QuestProgress.vue      # Progress bar with XP
│   ├── AchievementBadge.vue   # Unlocked achievement display
│   ├── StreakCounter.vue      # Daily login streak
│   └── LevelIndicator.vue     # User level/XP display
├── stores/
│   └── quest.store.ts         # Quest state management
├── services/
│   └── QuestService.ts        # Quest logic and progression
├── types/
│   └── quest.types.ts         # Quest, Achievement, Reward types
└── data/
    └── quests.ts              # Quest definitions
```

## Key Concepts

### Quests
Time-bound or action-based challenges that reward users:
- **Onboarding Quests**: First-time user guidance
- **Daily Quests**: Recurring engagement tasks
- **Achievement Quests**: One-time milestone rewards
- **Community Quests**: Group challenges (future)

### Progression System
- **XP (Experience Points)**: Earned from quest completion
- **Levels**: Unlock features at certain XP thresholds
- **Streaks**: Consecutive daily engagement bonuses
- **Badges**: Visual achievement recognition

### Tier Integration
Quest completion contributes to tier progression:
- Free tier users can earn Cruiser trial periods
- Engagement unlocks premium features before payment
- Creates value demonstration before conversion

## Data Flow

```
User Action → QuestService.checkProgress() → Update Quest State
                                          → Award XP/Badge
                                          → Check Tier Unlock
                                          → Trigger Penny Response
```

## Security Considerations

- Quest progress stored locally (IndexedDB) and synced via Oplog
- Server validates quest completion claims
- Anti-cheat: Server-side verification of action timestamps
- No financial data exposed through quest system

## POPIA Compliance

- Quest history is user data (exportable/deletable)
- No personally identifiable information in quest definitions
- Leaderboards use anonymized identifiers (opt-in only)

---

*For technical auditors: This module contains no financial transaction logic. It observes user actions and rewards engagement patterns.*
