<template>
  <div class="variance-trend">
    <div v-if="loading" class="loading-state">
      <q-spinner-dots color="teal" size="32px" />
    </div>

    <div v-else-if="!hasData" class="no-data-state">
      <q-icon name="trending_down" size="32px" color="grey-5" />
      <div class="text-caption text-grey">No variance data available</div>
    </div>

    <template v-else>
      <!-- Month Comparison Header -->
      <div class="comparison-header">
        <div class="month-label prev-month">
          <q-icon name="calendar_today" size="14px" class="q-mr-xs" />
          {{ prevMonthLabel }}
        </div>
        <q-icon name="arrow_forward" size="20px" color="grey-6" />
        <div class="month-label curr-month">
          <q-icon name="calendar_today" size="14px" class="q-mr-xs" />
          {{ currMonthLabel }}
        </div>
      </div>

      <!-- Wants Spending Comparison Bars -->
      <div class="comparison-bars">
        <div class="bar-row">
          <div class="bar-label">WANTS Spending</div>
          <div class="bar-container">
            <div
              class="bar prev-bar"
              :style="{ width: prevBarWidth + '%' }"
            >
              <span class="bar-value">{{ formatCurrency(prevWantsTotal) }}</span>
            </div>
            <div
              class="bar curr-bar"
              :style="{ width: currBarWidth + '%' }"
            >
              <span class="bar-value">{{ formatCurrency(currWantsTotal) }}</span>
            </div>
          </div>
        </div>

        <!-- Reduction Callout -->
        <div class="reduction-callout" :class="reductionClass">
          <q-icon :name="reductionIcon" size="24px" />
          <div class="reduction-text">
            <div class="reduction-percent">{{ reductionPercent }}%</div>
            <div class="reduction-label">{{ reductionLabel }}</div>
          </div>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="category-breakdown">
        <div class="breakdown-title">Key Changes</div>
        <div class="breakdown-items">
          <div
            v-for="item in topChanges"
            :key="item.name"
            class="breakdown-item"
          >
            <div class="item-name">
              <q-badge
                :color="item.change < 0 ? 'positive' : item.change > 0 ? 'negative' : 'grey'"
                text-color="white"
                class="q-mr-xs"
              >
                {{ item.change > 0 ? '+' : '' }}{{ Math.round(item.changePercent) }}%
              </q-badge>
              {{ item.name }}
            </div>
            <div class="item-values">
              <span class="text-grey-6">{{ formatCurrency(item.prev) }}</span>
              <q-icon name="arrow_right" size="12px" color="grey-5" class="q-mx-xs" />
              <span :class="item.change <= 0 ? 'text-positive' : 'text-negative'">
                {{ formatCurrency(item.curr) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { auditApi } from '@/services/api/audit.api';

interface Props {
  compact?: boolean;
}

defineProps<Props>();

const loading = ref(true);
const prevMonthData = ref<Record<string, number>>({});
const currMonthData = ref<Record<string, number>>({});
const prevWantsTotal = ref(0);
const currWantsTotal = ref(0);

// Calculate comparison months (Dec 2025 vs Jan 2026)
const prevYear = 2025;
const prevMonth = 12;
const currYear = 2026;
const currMonth = 1;

const prevMonthLabel = computed(() => {
  return new Date(prevYear, prevMonth - 1).toLocaleDateString('en-ZA', {
    month: 'short',
    year: 'numeric'
  });
});

const currMonthLabel = computed(() => {
  return new Date(currYear, currMonth - 1).toLocaleDateString('en-ZA', {
    month: 'short',
    year: 'numeric'
  });
});

const hasData = computed(() => {
  return prevWantsTotal.value > 0 || currWantsTotal.value > 0;
});

const maxValue = computed(() => {
  return Math.max(prevWantsTotal.value, currWantsTotal.value, 1);
});

const prevBarWidth = computed(() => {
  return (prevWantsTotal.value / maxValue.value) * 100;
});

const currBarWidth = computed(() => {
  return (currWantsTotal.value / maxValue.value) * 100;
});

const reductionPercent = computed(() => {
  if (prevWantsTotal.value === 0) return 0;
  const reduction = ((prevWantsTotal.value - currWantsTotal.value) / prevWantsTotal.value) * 100;
  return Math.round(reduction);
});

const reductionClass = computed(() => {
  if (reductionPercent.value >= 50) return 'excellent';
  if (reductionPercent.value >= 20) return 'good';
  if (reductionPercent.value > 0) return 'moderate';
  return 'increase';
});

const reductionIcon = computed(() => {
  if (reductionPercent.value >= 50) return 'trending_down';
  if (reductionPercent.value > 0) return 'arrow_downward';
  return 'arrow_upward';
});

const reductionLabel = computed(() => {
  if (reductionPercent.value >= 100) return 'ELIMINATED!';
  if (reductionPercent.value >= 50) return 'Major reduction';
  if (reductionPercent.value > 0) return 'Spending down';
  if (reductionPercent.value < 0) return 'Spending up';
  return 'No change';
});

interface CategoryChange {
  name: string;
  prev: number;
  curr: number;
  change: number;
  changePercent: number;
}

const topChanges = computed<CategoryChange[]>(() => {
  const changes: CategoryChange[] = [];

  // Combine all categories from both months
  const allCategories = new Set([
    ...Object.keys(prevMonthData.value),
    ...Object.keys(currMonthData.value),
  ]);

  for (const cat of allCategories) {
    const prev = prevMonthData.value[cat] || 0;
    const curr = currMonthData.value[cat] || 0;
    const change = curr - prev;
    const changePercent = prev > 0 ? ((curr - prev) / prev) * 100 : (curr > 0 ? 100 : 0);

    if (Math.abs(change) > 0) {
      changes.push({ name: cat, prev, curr, change, changePercent });
    }
  }

  // Sort by absolute change descending
  changes.sort((a, b) => Math.abs(b.change) - Math.abs(a.change));

  return changes.slice(0, 4);
});

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-ZA', {
    style: 'currency',
    currency: 'ZAR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

async function loadVarianceData() {
  loading.value = true;

  try {
    // Load variance reports for both months
    const [prevReport, currReport] = await Promise.all([
      auditApi.getVariance(prevYear, prevMonth).catch(() => null),
      auditApi.getVariance(currYear, currMonth).catch(() => null),
    ]);

    // Extract Wants spending by category
    if (prevReport?.blueprint_details) {
      for (const bp of prevReport.blueprint_details) {
        if (bp.bucket === 'wants') {
          prevMonthData.value[bp.name] = Math.abs(bp.actual || 0);
          prevWantsTotal.value += Math.abs(bp.actual || 0);
        }
      }
    }

    if (currReport?.blueprint_details) {
      for (const bp of currReport.blueprint_details) {
        if (bp.bucket === 'wants') {
          currMonthData.value[bp.name] = Math.abs(bp.actual || 0);
          currWantsTotal.value += Math.abs(bp.actual || 0);
        }
      }
    }

    // Special handling for Temu/Shopping - ensure it shows even if zero
    if (!currMonthData.value['Temu/Shopping']) {
      currMonthData.value['Temu/Shopping'] = 0;
    }
    if (!prevMonthData.value['Temu/Shopping'] && prevWantsTotal.value > 0) {
      // Estimate from ghost fees or unmatched
      prevMonthData.value['Temu/Shopping'] = 0;
    }

  } catch (error) {
    console.error('Failed to load variance data:', error);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadVarianceData();
});
</script>

<style scoped>
.variance-trend {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.loading-state,
.no-data-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 8px;
}

.comparison-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #ECEFF1;
}

.month-label {
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 12px;
}

.prev-month {
  background: #FFEBEE;
  color: #C62828;
}

.curr-month {
  background: #E8F5E9;
  color: #2E7D32;
}

.comparison-bars {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.bar-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bar-label {
  font-size: 11px;
  font-weight: 600;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.bar-container {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bar {
  height: 24px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  padding: 0 8px;
  min-width: 60px;
  transition: width 0.5s ease;
}

.prev-bar {
  background: linear-gradient(90deg, #FFCDD2 0%, #EF9A9A 100%);
}

.curr-bar {
  background: linear-gradient(90deg, #C8E6C9 0%, #A5D6A7 100%);
}

.bar-value {
  font-size: 11px;
  font-weight: 600;
  color: #37474F;
  white-space: nowrap;
}

.reduction-callout {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 8px;
  margin-top: 4px;
}

.reduction-callout.excellent {
  background: #E8F5E9;
  color: #2E7D32;
}

.reduction-callout.good {
  background: #E3F2FD;
  color: #1565C0;
}

.reduction-callout.moderate {
  background: #FFF3E0;
  color: #EF6C00;
}

.reduction-callout.increase {
  background: #FFEBEE;
  color: #C62828;
}

.reduction-text {
  display: flex;
  flex-direction: column;
}

.reduction-percent {
  font-size: 18px;
  font-weight: 700;
  line-height: 1;
}

.reduction-label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.category-breakdown {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 8px;
  border-top: 1px solid #ECEFF1;
  overflow-y: auto;
}

.breakdown-title {
  font-size: 11px;
  font-weight: 600;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.breakdown-items {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
}

.item-name {
  display: flex;
  align-items: center;
  font-size: 12px;
  color: #37474F;
}

.item-values {
  display: flex;
  align-items: center;
  font-size: 11px;
}

.text-positive {
  color: #2E7D32;
  font-weight: 600;
}

.text-negative {
  color: #C62828;
  font-weight: 600;
}
</style>
