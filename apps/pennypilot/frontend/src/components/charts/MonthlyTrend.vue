<template>
  <q-card class="monthly-trend">
    <q-card-section>
      <div class="text-h6">Monthly Trend</div>
      <div class="text-caption text-grey">Income vs Expenses over time</div>
    </q-card-section>

    <q-card-section v-if="hasData">
      <div class="chart-container">
        <Bar :data="chartData" :options="chartOptions" />
      </div>
    </q-card-section>

    <q-card-section v-else class="text-center text-grey">
      <q-icon name="bar_chart" size="48px" class="q-mb-md" />
      <div>No transaction data yet</div>
    </q-card-section>
  </q-card>
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

const transactionsStore = useTransactionsStore();

interface MonthlyData {
  month: string;
  label: string;
  income: number;
  expenses: number;
}

const monthlyData = computed<MonthlyData[]>(() => {
  const transactions = transactionsStore.transactions;

  // Group by month
  const grouped = new Map<string, { income: number; expenses: number }>();

  transactions.forEach((t) => {
    const date = parseISO(t.transaction_date);
    const monthKey = format(startOfMonth(date), 'yyyy-MM');

    if (!grouped.has(monthKey)) {
      grouped.set(monthKey, { income: 0, expenses: 0 });
    }

    const data = grouped.get(monthKey)!;
    if (t.amount >= 0) {
      data.income += t.amount;
    } else {
      data.expenses += Math.abs(t.amount);
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

function formatAmount(amount: number): string {
  if (amount >= 1000000) {
    return (amount / 1000000).toFixed(1) + 'M';
  }
  if (amount >= 1000) {
    return (amount / 1000).toFixed(0) + 'K';
  }
  return amount.toFixed(0);
}
</script>

<style scoped>
.chart-container {
  height: 250px;
  position: relative;
}
</style>
