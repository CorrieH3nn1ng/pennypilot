<template>
  <q-card class="chart-carousel-card" flat bordered>
    <!-- Carousel Header with Dots -->
    <div class="carousel-header">
      <div class="carousel-title">
        <q-icon :name="currentSlideIcon" size="18px" class="q-mr-sm" />
        {{ currentSlideTitle }}
      </div>
      <div class="carousel-dots">
        <span
          v-for="(slide, index) in slides"
          :key="slide.name"
          class="dot"
          :class="{ active: currentSlide === slide.name }"
          @click="currentSlide = slide.name"
        />
      </div>
    </div>

    <!-- Swipeable Carousel -->
    <q-carousel
      v-model="currentSlide"
      animated
      swipeable
      transition-prev="slide-right"
      transition-next="slide-left"
      class="chart-carousel"
      control-color="teal"
    >
      <!-- Spending by Category -->
      <q-carousel-slide name="spending" class="carousel-slide">
        <SpendingByCategory :compact="true" />
      </q-carousel-slide>

      <!-- Monthly Trend -->
      <q-carousel-slide name="trend" class="carousel-slide">
        <MonthlyTrend :compact="true" />
      </q-carousel-slide>

      <!-- Variance Trend (Wants comparison) -->
      <q-carousel-slide name="variance" class="carousel-slide">
        <VarianceTrend :compact="true" />
      </q-carousel-slide>

      <!-- Budget Overview (if available) -->
      <q-carousel-slide v-if="hasBudget" name="budget" class="carousel-slide">
        <BudgetSnapshot />
      </q-carousel-slide>
    </q-carousel>

    <!-- Swipe Hint -->
    <div class="swipe-hint">
      <q-icon name="swipe" size="14px" />
      <span>Swipe for more insights</span>
    </div>
  </q-card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useBudgetStore } from '@/stores/budget.store';
import SpendingByCategory from '@/components/charts/SpendingByCategory.vue';
import MonthlyTrend from '@/components/charts/MonthlyTrend.vue';
import VarianceTrend from '@/components/charts/VarianceTrend.vue';
import BudgetSnapshot from '@/components/dashboard/BudgetSnapshot.vue';

const budgetStore = useBudgetStore();

const currentSlide = ref('spending');

const hasBudget = computed(() => budgetStore.hasBudget);

interface Slide {
  name: string;
  title: string;
  icon: string;
}

const slides = computed<Slide[]>(() => {
  const baseSlides: Slide[] = [
    { name: 'spending', title: 'Spending by Category', icon: 'pie_chart' },
    { name: 'trend', title: 'Monthly Trend', icon: 'show_chart' },
    { name: 'variance', title: 'Wants Reduction', icon: 'trending_down' },
  ];

  if (hasBudget.value) {
    baseSlides.push({ name: 'budget', title: 'Budget Snapshot', icon: 'account_balance_wallet' });
  }

  return baseSlides;
});

const currentSlideTitle = computed(() => {
  return slides.value.find((s) => s.name === currentSlide.value)?.title || '';
});

const currentSlideIcon = computed(() => {
  return slides.value.find((s) => s.name === currentSlide.value)?.icon || '';
});
</script>

<style scoped>
.chart-carousel-card {
  border-radius: 12px;
  border-color: #B2DFDB;
  overflow: hidden;
}

.carousel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #E0F2F1;
  border-bottom: 1px solid #B2DFDB;
}

.carousel-title {
  display: flex;
  align-items: center;
  font-size: 14px;
  font-weight: 600;
  color: #004D40;
}

.carousel-dots {
  display: flex;
  gap: 6px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #B2DFDB;
  cursor: pointer;
  transition: all 0.2s ease;
}

.dot.active {
  background: #004D40;
  transform: scale(1.2);
}

.dot:hover:not(.active) {
  background: #80CBC4;
}

.chart-carousel {
  height: 280px;
  background: white;
}

.carousel-slide {
  padding: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.swipe-hint {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 8px;
  background: #FAFAFA;
  border-top: 1px solid #ECEFF1;
  font-size: 11px;
  color: #90A4AE;
}

/* Deep links for chart components */
:deep(.spending-chart),
:deep(.trend-chart),
:deep(.variance-trend) {
  width: 100%;
  height: 100%;
}
</style>
