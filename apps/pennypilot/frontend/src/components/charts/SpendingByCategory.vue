<template>
  <component :is="compact ? 'div' : 'q-card'" class="spending-chart">
    <q-card-section v-if="!compact">
      <div class="text-h6">Spending by Category</div>
      <div class="text-caption text-grey">Where your money goes</div>
    </q-card-section>

    <component :is="compact ? 'div' : 'q-card-section'" v-if="hasData" :class="{ 'compact-content': compact }">
      <div class="chart-container" :class="{ 'chart-compact': compact }">
        <Doughnut :data="chartData" :options="chartOptions" />
      </div>

      <q-list v-if="!compact" dense class="q-mt-md">
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

      <!-- Compact Legend -->
      <div v-if="compact" class="compact-legend">
        <div
          v-for="item in topCategories.slice(0, 4)"
          :key="item.category_id || 'uncategorized'"
          class="legend-item"
        >
          <span class="legend-dot" :style="{ backgroundColor: item.color }" />
          <span class="legend-name">{{ item.name }}</span>
          <span class="legend-value">{{ item.percentage }}%</span>
        </div>
      </div>
    </component>

    <component :is="compact ? 'div' : 'q-card-section'" v-else class="text-center text-grey" :class="{ 'compact-empty': compact }">
      <q-icon name="pie_chart" size="48px" class="q-mb-md" />
      <div>No expense data yet</div>
      <div class="text-caption">Import transactions to see spending breakdown</div>
    </component>
  </component>
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

const props = withDefaults(
  defineProps<{
    compact?: boolean;
  }>(),
  {
    compact: false,
  }
);

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
  // Exclude transfers from spending chart
  const expenses = transactionsStore.transactions.filter(
    (t) => Number(t.amount) < 0 && !transactionsStore.isTransfer(t)
  );

  // Group by category
  const grouped = new Map<string | null, number>();
  expenses.forEach((t) => {
    const key = t.category_id;
    const current = grouped.get(key) || 0;
    grouped.set(key, current + Math.abs(Number(t.amount)));
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
.spending-chart {
  border-radius: 12px;
}

.chart-container {
  height: 200px;
  position: relative;
}

.chart-compact {
  height: 140px;
}

.compact-content {
  display: flex;
  align-items: center;
  gap: 16px;
  width: 100%;
  height: 100%;
}

.compact-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.legend-name {
  flex: 1;
  color: #546E7A;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.legend-value {
  font-weight: 600;
  color: #263238;
}

.compact-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
}
</style>
