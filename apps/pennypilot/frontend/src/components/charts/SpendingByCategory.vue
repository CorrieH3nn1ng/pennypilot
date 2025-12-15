<template>
  <q-card class="spending-chart">
    <q-card-section>
      <div class="text-h6">Spending by Category</div>
      <div class="text-caption text-grey">Where your money goes</div>
    </q-card-section>

    <q-card-section v-if="hasData">
      <div class="chart-container">
        <Doughnut :data="chartData" :options="chartOptions" />
      </div>

      <q-list dense class="q-mt-md">
        <q-item v-for="item in topCategories" :key="item.category_id || 'uncategorized'" dense>
          <q-item-section avatar>
            <q-avatar size="24px" :style="{ backgroundColor: item.color }">
              <q-icon :name="item.icon" color="white" size="14px" />
            </q-avatar>
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ item.name }}</q-item-label>
          </q-item-section>
          <q-item-section side>
            <q-item-label class="text-weight-medium">
              R {{ formatAmount(item.total) }}
            </q-item-label>
          </q-item-section>
          <q-item-section side>
            <q-item-label caption>
              {{ item.percentage }}%
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
    </q-card-section>

    <q-card-section v-else class="text-center text-grey">
      <q-icon name="pie_chart" size="48px" class="q-mb-md" />
      <div>No expense data yet</div>
      <div class="text-caption">Import transactions to see spending breakdown</div>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  type ChartData,
  type ChartOptions,
} from 'chart.js';
import { useTransactionsStore } from '@/stores/transactions.store';
import { useCategoriesStore } from '@/stores/categories.store';

ChartJS.register(ArcElement, Tooltip, Legend);

const transactionsStore = useTransactionsStore();
const categoriesStore = useCategoriesStore();

interface CategorySpending {
  category_id: string | null;
  name: string;
  icon: string;
  color: string;
  total: number;
  percentage: number;
}

const spendingByCategory = computed<CategorySpending[]>(() => {
  const expenses = transactionsStore.transactions.filter((t) => t.amount < 0);

  // Group by category
  const grouped = new Map<string | null, number>();
  expenses.forEach((t) => {
    const key = t.category_id;
    const current = grouped.get(key) || 0;
    grouped.set(key, current + Math.abs(t.amount));
  });

  // Calculate total
  const total = Array.from(grouped.values()).reduce((sum, val) => sum + val, 0);

  // Convert to array with category details
  const result: CategorySpending[] = [];
  grouped.forEach((amount, categoryId) => {
    const category = categoryId ? categoriesStore.getCategoryById(categoryId) : null;
    result.push({
      category_id: categoryId,
      name: category?.name || 'Uncategorized',
      icon: category?.icon || 'category',
      color: category?.color || '#9E9E9E',
      total: amount,
      percentage: total > 0 ? Math.round((amount / total) * 100) : 0,
    });
  });

  // Sort by total descending
  return result.sort((a, b) => b.total - a.total);
});

const topCategories = computed(() => spendingByCategory.value.slice(0, 8));

const hasData = computed(() => spendingByCategory.value.length > 0);

const chartData = computed<ChartData<'doughnut'>>(() => ({
  labels: topCategories.value.map((c) => c.name),
  datasets: [
    {
      data: topCategories.value.map((c) => c.total),
      backgroundColor: topCategories.value.map((c) => c.color),
      borderWidth: 2,
      borderColor: '#ffffff',
    },
  ],
}));

const chartOptions: ChartOptions<'doughnut'> = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      callbacks: {
        label: (context) => {
          const value = context.parsed;
          return ` R ${formatAmount(value)}`;
        },
      },
    },
  },
  cutout: '60%',
};

function formatAmount(amount: number): string {
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>

<style scoped>
.chart-container {
  height: 200px;
  position: relative;
}
</style>
