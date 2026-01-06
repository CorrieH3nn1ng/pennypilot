<template>
  <component :is="compact ? 'div' : 'q-card'" class="monthly-trend">
    <q-card-section v-if="!compact">
      <div class="text-h6">Monthly Trend</div>
      <div class="text-caption text-grey">Income vs Expenses over time</div>
    </q-card-section>

    <component :is="compact ? 'div' : 'q-card-section'" v-if="hasData" :class="{ 'compact-content': compact }">
      <div class="chart-container" :class="{ 'chart-compact': compact }">
        <Bar :data="chartData" :options="compactAwareOptions" />
      </div>

      <!-- Compact Summary -->
      <div v-if="compact" class="compact-summary">
        <div class="summary-item income">
          <div class="summary-label">Income</div>
          <div class="summary-value text-positive">R {{ formatCompact(latestIncome) }}</div>
        </div>
        <div class="summary-item expenses">
          <div class="summary-label">Expenses</div>
          <div class="summary-value text-negative">R {{ formatCompact(latestExpenses) }}</div>
        </div>
        <div class="summary-item net">
          <div class="summary-label">Net</div>
          <div
            class="summary-value"
            :class="latestNet >= 0 ? 'text-positive' : 'text-negative'"
          >
            R {{ formatCompact(latestNet) }}
          </div>
        </div>
      </div>
    </component>

    <component :is="compact ? 'div' : 'q-card-section'" v-else class="text-center text-grey" :class="{ 'compact-empty': compact }">
      <q-icon name="bar_chart" size="48px" class="q-mb-md" />
      <div>No transaction data yet</div>
    </component>
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Tooltip,
  Legend,
  type ChartData,
  type ChartOptions,
} from 'chart.js';
import { format, parseISO, startOfMonth } from 'date-fns';
import { useTransactionsStore } from '@/stores/transactions.store';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

const props = withDefaults(
  defineProps<{
    compact?: boolean;
  }>(),
  {
    compact: false,
  }
);

const transactionsStore = useTransactionsStore();

interface MonthlyData {
  month: string;
  label: string;
  income: number;
  expenses: number;
}

const monthlyData = computed<MonthlyData[]>(() => {
  // Exclude transfers from monthly trend
  const transactions = transactionsStore.transactions.filter(
    (t) => !transactionsStore.isTransfer(t)
  );

  // Group by month
  const grouped = new Map<string, { income: number; expenses: number }>();

  transactions.forEach((t) => {
    const date = parseISO(t.transaction_date);
    const monthKey = format(startOfMonth(date), 'yyyy-MM');

    if (!grouped.has(monthKey)) {
      grouped.set(monthKey, { income: 0, expenses: 0 });
    }

    const data = grouped.get(monthKey)!;
    const amount = Number(t.amount) || 0;
    if (amount >= 0) {
      data.income += amount;
    } else {
      data.expenses += Math.abs(amount);
    }
  });

  // Convert to array and sort by month
  const result: MonthlyData[] = [];
  grouped.forEach((data, monthKey) => {
    result.push({
      month: monthKey,
      label: format(parseISO(monthKey + '-01'), 'MMM yyyy'),
      income: data.income,
      expenses: data.expenses,
    });
  });

  return result.sort((a, b) => a.month.localeCompare(b.month)).slice(-6); // Last 6 months
});

const hasData = computed(() => monthlyData.value.length > 0);

const chartData = computed<ChartData<'bar'>>(() => ({
  labels: monthlyData.value.map((m) => m.label),
  datasets: [
    {
      label: 'Income',
      data: monthlyData.value.map((m) => m.income),
      backgroundColor: '#4CAF50',
      borderRadius: 4,
    },
    {
      label: 'Expenses',
      data: monthlyData.value.map((m) => m.expenses),
      backgroundColor: '#F44336',
      borderRadius: 4,
    },
  ],
}));

// Latest month values for compact summary
const latestIncome = computed(() => {
  const latest = monthlyData.value[monthlyData.value.length - 1];
  return latest?.income || 0;
});

const latestExpenses = computed(() => {
  const latest = monthlyData.value[monthlyData.value.length - 1];
  return latest?.expenses || 0;
});

const latestNet = computed(() => latestIncome.value - latestExpenses.value);

const chartOptions: ChartOptions<'bar'> = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
    },
    tooltip: {
      callbacks: {
        label: (context) => {
          const value = context.parsed.y;
          return ` R ${formatAmount(value)}`;
        },
      },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: (value) => `R ${formatAmount(Number(value))}`,
      },
    },
  },
};

// Compact-aware options
const compactAwareOptions = computed<ChartOptions<'bar'>>(() => {
  if (!props.compact) return chartOptions;

  return {
    ...chartOptions,
    plugins: {
      ...chartOptions.plugins,
      legend: {
        display: false,
      },
    },
    scales: {
      ...chartOptions.scales,
      x: {
        ticks: {
          maxRotation: 0,
          font: { size: 10 },
        },
      },
      y: {
        ...chartOptions.scales?.y,
        ticks: {
          font: { size: 10 },
          callback: (value) => `${formatAmount(Number(value))}`,
        },
      },
    },
  };
});

function formatAmount(amount: number): string {
  if (amount >= 1000000) {
    return (amount / 1000000).toFixed(1) + 'M';
  }
  if (amount >= 1000) {
    return (amount / 1000).toFixed(0) + 'K';
  }
  return amount.toFixed(0);
}

function formatCompact(amount: number): string {
  const abs = Math.abs(amount);
  if (abs >= 1000000) {
    return (amount / 1000000).toFixed(1) + 'M';
  }
  if (abs >= 1000) {
    return (amount / 1000).toFixed(1) + 'K';
  }
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}
</script>

<style scoped>
.monthly-trend {
  border-radius: 12px;
}

.chart-container {
  height: 250px;
  position: relative;
}

.chart-compact {
  height: 160px;
}

.compact-content {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
}

.compact-summary {
  display: flex;
  justify-content: space-around;
  padding-top: 12px;
  margin-top: 12px;
  border-top: 1px solid #ECEFF1;
}

.summary-item {
  text-align: center;
}

.summary-label {
  font-size: 11px;
  color: #90A4AE;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.summary-value {
  font-size: 16px;
  font-weight: 700;
}

.text-positive {
  color: #2E7D32;
}

.text-negative {
  color: #C62828;
}

.compact-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
}
</style>
