<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5">Financial Goals</div>
      <q-space />
    </div>

    <!-- Coming Soon State -->
    <div class="text-center q-pa-xl">
      <q-icon name="flag" size="100px" color="grey-4" />
      <div class="text-h5 text-grey q-mt-lg">Coming Soon</div>
      <div class="text-body1 text-grey q-mt-sm q-mb-lg" style="max-width: 400px; margin: 0 auto;">
        Set and track your financial goals like emergency funds, debt payoff, savings targets, and investment milestones.
      </div>

      <!-- Preview Features -->
      <q-card flat bordered class="q-mt-xl" style="max-width: 500px; margin: 0 auto;">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-md">Planned Features</div>
          <q-list>
            <q-item>
              <q-item-section avatar>
                <q-icon name="savings" color="green" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Emergency Fund</q-item-label>
                <q-item-label caption>Build 3-6 months of expenses</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section avatar>
                <q-icon name="credit_card_off" color="red" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Debt Payoff</q-item-label>
                <q-item-label caption>Track and eliminate debt</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section avatar>
                <q-icon name="beach_access" color="blue" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Savings Goals</q-item-label>
                <q-item-label caption>Holiday, car, house deposit</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section avatar>
                <q-icon name="trending_up" color="purple" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Investment Milestones</q-item-label>
                <q-item-label caption>Portfolio targets and progress</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script setup lang="ts">
// Goals page - placeholder for Phase 2
</script>
