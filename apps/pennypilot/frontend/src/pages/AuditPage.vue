<template>
  <q-page class="q-pa-md">
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <div class="col">
        <h5 class="q-ma-none">Historical Budget Audit</h5>
        <p class="text-grey q-ma-none">Ground Truth Reconciliation</p>
      </div>
      <div class="col-auto q-gutter-sm row items-center">
        <!-- MONTH SELECTOR DROPDOWN -->
        <q-select
          v-model="selectedMonthKey"
          :options="availableMonths"
          label="Select Month"
          dense
          outlined
          emit-value
          map-options
          style="min-width: 180px"
          class="q-mr-md"
          @update:model-value="onMonthSelected"
        >
          <template v-slot:prepend>
            <q-icon name="calendar_month" color="primary" />
          </template>
        </q-select>

        <!-- View Mode Toggle -->
        <q-btn-toggle
          v-model="viewMode"
          toggle-color="primary"
          :options="[
            { label: 'Monthly', value: 'monthly' },
            { label: 'Tax Year', value: 'tax-year' }
          ]"
          dense
          class="q-mr-md"
          @update:model-value="handleViewModeChange"
        />

        <!-- Monthly Navigation (when in monthly mode) -->
        <q-btn-group v-if="viewMode === 'monthly'">
          <q-btn
            :icon="monthOffset === 0 ? 'calendar_today' : 'chevron_left'"
            flat
            dense
            @click="previousMonth"
          />
          <q-btn flat dense :label="periodLabel" />
          <q-btn
            icon="chevron_right"
            flat
            dense
            :disable="monthOffset >= 0"
            @click="nextMonth"
          />
        </q-btn-group>

        <!-- Tax Year Selector (when in tax-year mode) -->
        <q-btn-group v-else>
          <q-btn
            icon="chevron_left"
            flat
            dense
            @click="selectedTaxYear--"
          />
          <q-btn flat dense :label="taxYearLabel" />
          <q-btn
            icon="chevron_right"
            flat
            dense
            :disable="selectedTaxYear >= currentTaxYear"
            @click="selectedTaxYear++"
          />
        </q-btn-group>
      </div>
    </div>

    <!-- Empty State Banner (when viewing month with no data) -->
    <q-banner
      v-if="initialLoadComplete && !hasDataForCurrentMonth"
      class="bg-blue-1 text-blue-9 q-mb-md rounded-borders"
      rounded
    >
      <template #avatar>
        <q-icon name="info" color="blue" />
      </template>
      <div class="text-body2">{{ emptyStateMessage }}</div>
      <template #action>
        <q-btn
          v-if="dataRange?.has_data && dataRange.most_recent_year && dataRange.most_recent_month"
          flat
          color="primary"
          label="Go to Latest"
          @click="goToLatestMonth"
        />
        <q-btn
          v-if="!dataRange?.has_data"
          flat
          color="primary"
          label="Import Statement"
          to="/import"
        />
      </template>
    </q-banner>

    <!-- Data Range Info (subtle hint about available data) -->
    <div
      v-if="viewMode === 'monthly' && dataRange?.has_data && dataRange.months_with_data?.length > 1"
      class="text-caption text-grey q-mb-md"
    >
      Data available: {{ dataRange.months_with_data[dataRange.months_with_data.length - 1]?.label }}
      - {{ dataRange.months_with_data[0]?.label }}
      ({{ dataRange.total_transactions }} transactions)
    </div>

    <!-- TAX YEAR SUMMARY VIEW -->
    <template v-if="viewMode === 'tax-year'">
      <q-card v-if="loadingTaxYear" class="q-mb-lg">
        <q-card-section class="text-center q-py-xl">
          <q-spinner-gears size="50px" color="primary" />
          <div class="q-mt-md text-grey">Loading tax year summary...</div>
        </q-card-section>
      </q-card>

      <template v-else-if="taxYearData">
        <!-- Tax Year Header -->
        <q-banner class="bg-deep-purple-1 text-deep-purple-9 q-mb-md rounded-borders">
          <template #avatar>
            <q-icon name="account_balance" color="deep-purple" />
          </template>
          <div class="text-subtitle1">{{ taxYearData.period?.label }}</div>
          <div class="text-caption">
            {{ taxYearData.period?.start }} to {{ taxYearData.period?.end }}
          </div>
        </q-banner>

        <!-- Tax Year Summary Cards -->
        <div class="row q-col-gutter-md q-mb-lg">
          <div class="col-12 col-sm-3">
            <q-card class="bg-positive text-white">
              <q-card-section>
                <div class="text-overline">TOTAL INCOME</div>
                <div class="text-h5">{{ formatCurrency(taxYearData.summary?.total_income || 0) }}</div>
              </q-card-section>
            </q-card>
          </div>
          <div class="col-12 col-sm-3">
            <q-card class="bg-negative text-white">
              <q-card-section>
                <div class="text-overline">TOTAL EXPENSES</div>
                <div class="text-h5">{{ formatCurrency(taxYearData.summary?.total_expenses || 0) }}</div>
              </q-card-section>
            </q-card>
          </div>
          <div class="col-12 col-sm-3">
            <q-card class="bg-blue text-white">
              <q-card-section>
                <div class="text-overline">BUSINESS EXPENSES</div>
                <div class="text-h5">{{ formatCurrency(taxYearData.summary?.business_expenses || 0) }}</div>
                <div class="text-caption">Tax Deductible</div>
              </q-card-section>
            </q-card>
          </div>
          <div class="col-12 col-sm-3">
            <q-card class="bg-amber text-white">
              <q-card-section>
                <div class="text-overline">EST. TAX @ {{ taxYearData.tax?.tax_rate }}</div>
                <div class="text-h5">{{ formatCurrency(taxYearData.tax?.estimated_tax || 0) }}</div>
                <div class="text-caption">On R{{ formatNumber(taxYearData.tax?.taxable_income || 0) }} taxable</div>
              </q-card-section>
            </q-card>
          </div>
        </div>

        <!-- Provisional Payments -->
        <q-expansion-item
          v-if="taxYearData.provisional_payments?.length > 0"
          icon="payment"
          label="Provisional Tax Payments"
          header-class="bg-amber-1 text-amber-10"
          class="q-mb-md rounded-borders"
          default-opened
        >
          <q-card>
            <q-card-section>
              <q-list separator>
                <q-item v-for="payment in taxYearData.provisional_payments" :key="payment.period">
                  <q-item-section avatar>
                    <q-icon name="event" color="amber-10" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label>{{ payment.period }}</q-item-label>
                    <q-item-label caption>Due: {{ payment.due_date }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-item-label class="text-amber-10 text-weight-bold">
                      {{ formatCurrency(payment.amount) }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card-section>
          </q-card>
        </q-expansion-item>

        <!-- Monthly Breakdown Table -->
        <q-expansion-item
          icon="table_chart"
          label="Monthly Breakdown"
          header-class="bg-primary text-white"
          class="q-mb-md rounded-borders"
          default-opened
        >
          <q-card>
            <q-card-section class="q-pa-none">
              <q-table
                :rows="taxYearData.monthly_breakdown || []"
                :columns="taxYearColumns"
                row-key="label"
                flat
                dense
                :pagination="{ rowsPerPage: 0 }"
                hide-pagination
              >
                <template #body-cell-net="props">
                  <q-td :props="props">
                    <span :class="props.value >= 0 ? 'text-positive' : 'text-negative'">
                      {{ formatCurrency(props.value) }}
                    </span>
                  </q-td>
                </template>
              </q-table>
            </q-card-section>
          </q-card>
        </q-expansion-item>
      </template>

      <q-banner v-else class="bg-grey-2 q-mb-md rounded-borders">
        <template #avatar>
          <q-icon name="info" color="grey" />
        </template>
        No data available for tax year {{ selectedTaxYear }}/{{ selectedTaxYear + 1 }}.
      </q-banner>
    </template>

    <!-- MONTHLY VIEW (existing content) -->
    <template v-if="viewMode === 'monthly'">

    <!-- Next Steps Guidance Card (show when unmatched > 0) -->
    <q-card
      v-if="showGuidance"
      class="bg-amber-1 q-mb-md"
      bordered
    >
      <q-card-section class="q-py-sm">
        <div class="row items-center">
          <q-icon name="lightbulb" color="amber-8" size="sm" class="q-mr-sm" />
          <span class="text-subtitle2 text-amber-10">Next Steps to Improve Your Match Rate</span>
          <q-space />
          <q-btn flat dense icon="close" size="sm" @click="dismissGuidance" />
        </div>
        <q-stepper
          v-model="guidanceStep"
          flat
          vertical
          animated
          done-color="positive"
          active-color="amber-8"
          class="bg-transparent q-mt-sm"
        >
          <q-step
            :name="1"
            title="Map your Income"
            icon="payments"
            :done="hasIncomeBlueprints"
          >
            Ensure your salary/invoices are linked to Income blueprints.
            <q-stepper-navigation>
              <q-btn flat color="primary" label="Go to Blueprints" to="/blueprint" size="sm" />
            </q-stepper-navigation>
          </q-step>
          <q-step
            :name="2"
            title="Run Reconciliation"
            icon="sync"
            :done="(report?.summary?.matched || 0) > 0"
          >
            Click "Run Reconciliation" to auto-match transactions to blueprints.
            <q-stepper-navigation>
              <q-btn flat color="primary" label="Run Now" @click="runReconciliation" size="sm" :loading="reconciling" />
            </q-stepper-navigation>
          </q-step>
          <q-step
            :name="3"
            title="Review Unmatched"
            icon="help_outline"
            :done="(report?.summary?.unmatched || 0) === 0"
          >
            Check the Unmatched section below for potential matches and create new blueprints.
          </q-step>
        </q-stepper>
      </q-card-section>
    </q-card>

    <!-- Holiday Surplus Banner (when there are paused items) -->
    <q-banner
      v-if="holidaySurplus > 0"
      class="bg-teal-1 text-teal-9 q-mb-md rounded-borders"
      rounded
    >
      <template #avatar>
        <q-icon name="savings" color="teal" size="md" />
      </template>
      <div class="row items-center">
        <div class="col">
          <div class="text-subtitle2 text-weight-bold">
            Investment Holiday Surplus: {{ formatCurrency(holidaySurplus) }}
          </div>
          <div class="text-caption">
            {{ holidayItems.length }} payment{{ holidayItems.length > 1 ? 's' : '' }} paused
            <template v-if="holidayEndDate"> until {{ holidayEndDate }}</template>
          </div>
        </div>
        <div class="col-auto">
          <q-btn
            flat
            dense
            color="teal"
            label="View Details"
            icon-right="expand_more"
            @click="showHolidayDetails = !showHolidayDetails"
          />
        </div>
      </div>
      <q-slide-transition>
        <div v-if="showHolidayDetails" class="q-mt-sm">
          <q-list dense class="bg-white rounded-borders q-pa-sm">
            <q-item v-for="item in holidayItems" :key="item.id" dense>
              <q-item-section>
                <q-item-label>{{ item.name }}</q-item-label>
                <q-item-label caption v-if="item.pause_reason">{{ item.pause_reason }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-item-label class="text-positive text-weight-bold">
                  +{{ formatCurrency(item.amount) }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </div>
      </q-slide-transition>
    </q-banner>

    <!-- Summary Cards - Ground Truth Calculation -->
    <div class="row q-col-gutter-md q-mb-lg">
      <!-- Opening Balance (Anchor from Excel) - EDITABLE -->
      <div class="col-6 col-sm-3">
        <q-card class="bg-blue-grey-8 text-white cursor-pointer" @click="showOpeningBalanceDialog = true">
          <q-card-section class="q-py-sm">
            <div class="text-overline row items-center">
              OPENING BALANCE
              <q-icon name="edit" size="xs" class="q-ml-xs" />
            </div>
            <div class="text-h6">{{ formatCurrency(openingBalance) }}</div>
            <div class="text-caption">Click to edit</div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Opening Balance Edit Dialog -->
      <q-dialog v-model="showOpeningBalanceDialog">
        <q-card style="min-width: 300px">
          <q-card-section>
            <div class="text-h6">Set Opening Balance</div>
            <div class="text-caption text-grey">Enter your opening balance from your Excel statement for {{ periodLabel }}</div>
          </q-card-section>
          <q-card-section>
            <q-input
              v-model.number="editingOpeningBalance"
              type="number"
              label="Opening Balance (R)"
              prefix="R"
              outlined
              autofocus
              @keyup.enter="saveOpeningBalance"
            />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancel" v-close-popup />
            <q-btn color="primary" label="Save" @click="saveOpeningBalance" v-close-popup />
          </q-card-actions>
        </q-card>
      </q-dialog>

      <!-- Net Spendable = Opening + Income - Expenses -->
      <div class="col-6 col-sm-3">
        <q-card :class="realNetSpendable >= 0 ? 'bg-positive text-white' : 'bg-negative text-white'">
          <q-card-section class="q-py-sm">
            <div class="text-overline row items-center">
              NET SPENDABLE
              <q-icon name="info" size="xs" class="q-ml-xs cursor-pointer">
                <q-tooltip class="bg-grey-9 text-body2" :offset="[0, 8]" max-width="280px">
                  <strong>Net Spendable =</strong><br>
                  Opening Balance<br>
                  + Income (deposits)<br>
                  − Expenses (debits)<br><br>
                  <em>What you actually have left this month.</em>
                </q-tooltip>
              </q-icon>
            </div>
            <div class="text-h6">{{ formatCurrency(realNetSpendable) }}</div>
            <div class="text-caption">Open + Income − Expenses</div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Month Cashflow (Income - Expenses) - RED if loss -->
      <div class="col-6 col-sm-3">
        <q-card :class="actualNet >= 0 ? 'bg-positive text-white' : 'bg-negative text-white'">
          <q-card-section class="q-py-sm">
            <div class="text-overline row items-center">
              MONTH CASHFLOW
              <q-icon name="info" size="xs" class="q-ml-xs cursor-pointer">
                <q-tooltip class="bg-grey-9 text-body2" :offset="[0, 8]" max-width="280px">
                  <strong>Month Cashflow =</strong><br>
                  Income (deposits)<br>
                  − Expenses (debits)<br><br>
                  <em>Did you earn more than you spent this month?</em>
                </q-tooltip>
              </q-icon>
            </div>
            <div class="text-h6">{{ formatCurrency(actualNet) }}</div>
            <div class="text-caption">Income − Expenses</div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Variance (Budget vs Actual) -->
      <div class="col-6 col-sm-3">
        <q-card :class="varianceClass">
          <q-card-section class="q-py-sm">
            <div class="text-overline row items-center">
              VARIANCE
              <q-icon name="info" size="xs" class="q-ml-xs cursor-pointer">
                <q-tooltip class="bg-grey-9 text-body2" :offset="[0, 8]" max-width="280px">
                  <strong>Variance =</strong><br>
                  Budget Expected − Actual Spent<br><br>
                  <span class="text-positive">GREEN:</span> Spent less than budget<br>
                  <span class="text-negative">RED:</span> Overspent budget
                </q-tooltip>
              </q-icon>
            </div>
            <div class="text-h6">{{ formatCurrency(report?.variance || 0) }}</div>
            <div class="text-caption">{{ varianceMessage }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Reconcile Button -->
    <div class="row q-mb-lg">
      <q-btn
        color="primary"
        icon="sync"
        label="Run Reconciliation"
        :loading="reconciling"
        @click="runReconciliation"
      />
      <q-space />
      <q-chip :color="report?.summary?.matched ? 'positive' : 'grey'" text-color="white" icon="check_circle">
        {{ report?.summary?.matched || 0 }} matched
      </q-chip>
      <q-chip :color="report?.summary?.unmatched ? 'warning' : 'grey'" text-color="white" icon="help">
        {{ report?.summary?.unmatched || 0 }} unmatched
      </q-chip>
      <q-chip :color="report?.summary?.leak_count ? 'negative' : 'grey'" text-color="white" icon="water_drop">
        {{ report?.summary?.leak_count || 0 }} leaks
      </q-chip>
    </div>

    <!-- Zombies Section -->
    <q-expansion-item
      v-if="report?.zombies && report.zombies.length > 0"
      icon="warning"
      label="Zombie Subscriptions"
      header-class="bg-orange-2 text-orange-10"
      class="q-mb-md rounded-borders"
      default-opened
    >
      <q-card>
        <q-card-section>
          <p class="text-caption text-grey q-mb-sm">
            These services were cancelled but are still charging you.
          </p>
          <q-list separator>
            <q-item v-for="zombie in report.zombies" :key="zombie.blueprint_id">
              <q-item-section avatar>
                <q-icon name="warning" color="orange" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ zombie.blueprint }}</q-item-label>
                <q-item-label caption>
                  Cancelled {{ formatDate(zombie.cancelled_at) }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-item-label class="text-negative text-weight-bold">
                  {{ formatCurrency(zombie.charges) }}
                </q-item-label>
                <q-item-label caption>still charged</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-btn flat dense icon="gavel" label="Dispute" color="negative" />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-expansion-item>

    <!-- Ghost Fees Section -->
    <q-expansion-item
      v-if="report?.ghost_fees && report.ghost_fees.length > 0"
      icon="visibility_off"
      label="Ghost Fees"
      header-class="bg-red-2 text-red-10"
      class="q-mb-md rounded-borders"
      default-opened
    >
      <q-card>
        <q-card-section>
          <p class="text-caption text-grey q-mb-sm">
            Unexpected expenses not in your Blueprint.
          </p>
          <q-list separator>
            <q-item v-for="ghost in report.ghost_fees" :key="ghost.id">
              <q-item-section avatar>
                <q-icon name="visibility_off" color="red" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ ghost.description }}</q-item-label>
                <q-item-label caption>{{ formatDate(ghost.date) }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-item-label class="text-negative text-weight-bold">
                  {{ formatCurrency(ghost.amount) }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-btn-dropdown
                  flat
                  dense
                  icon="add"
                  label="Add to Blueprint"
                  color="primary"
                  dropdown-icon="arrow_drop_down"
                >
                  <q-list dense style="min-width: 220px">
                    <!-- HOUSING -->
                    <q-item-label header class="text-weight-bold text-deep-purple">HOUSING</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Home Bond')">
                      <q-item-section avatar><q-icon name="home" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Home Bond</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Levies')">
                      <q-item-section avatar><q-icon name="apartment" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Levies</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Rates & Taxes')">
                      <q-item-section avatar><q-icon name="account_balance" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Rates & Taxes</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Electricity')">
                      <q-item-section avatar><q-icon name="bolt" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Electricity</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Home Repairs')">
                      <q-item-section avatar><q-icon name="handyman" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Home Repairs</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- LIFESTYLE -->
                    <q-item-label header class="text-weight-bold text-teal">LIFESTYLE</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Groceries')">
                      <q-item-section avatar><q-icon name="shopping_cart" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Groceries</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Fuel')">
                      <q-item-section avatar><q-icon name="local_gas_station" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Fuel</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Medical')">
                      <q-item-section avatar><q-icon name="medical_services" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Medical</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Domestic Help')">
                      <q-item-section avatar><q-icon name="cleaning_services" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Domestic Help</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Gardener')">
                      <q-item-section avatar><q-icon name="grass" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Gardener</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Dining Out')">
                      <q-item-section avatar><q-icon name="restaurant" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Dining Out</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Education')">
                      <q-item-section avatar><q-icon name="school" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Education</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- TRANSPORT -->
                    <q-item-label header class="text-weight-bold text-orange">TRANSPORT</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Toll Gates')">
                      <q-item-section avatar><q-icon name="toll" color="orange" size="xs" /></q-item-section>
                      <q-item-section>Toll Gates</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Fines')">
                      <q-item-section avatar><q-icon name="gavel" color="orange" size="xs" /></q-item-section>
                      <q-item-section>Fines</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- BUSINESS -->
                    <q-item-label header class="text-weight-bold text-blue">BUSINESS</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Business Expenses')">
                      <q-item-section avatar><q-icon name="business_center" color="blue" size="xs" /></q-item-section>
                      <q-item-section>Business Expenses</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- WANTS -->
                    <q-item-label header class="text-weight-bold text-pink">WANTS</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Purchases')">
                      <q-item-section avatar><q-icon name="shopping_bag" color="pink" size="xs" /></q-item-section>
                      <q-item-section>Purchases</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Online Purchases')">
                      <q-item-section avatar><q-icon name="local_shipping" color="pink" size="xs" /></q-item-section>
                      <q-item-section>Online Purchases</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Subscriptions')">
                      <q-item-section avatar><q-icon name="subscriptions" color="pink" size="xs" /></q-item-section>
                      <q-item-section>Subscriptions</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Charity Private')">
                      <q-item-section avatar><q-icon name="volunteer_activism" color="pink" size="xs" /></q-item-section>
                      <q-item-section>Charity Private</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- FINANCIAL -->
                    <q-item-label header class="text-weight-bold text-amber-9">FINANCIAL</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Investment/Savings')">
                      <q-item-section avatar><q-icon name="savings" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Investment/Savings</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Vehicle Payments')">
                      <q-item-section avatar><q-icon name="directions_car" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Vehicle Payments</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Vehicle Maintenance')">
                      <q-item-section avatar><q-icon name="car_repair" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Vehicle Maintenance</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Bank Fees')">
                      <q-item-section avatar><q-icon name="account_balance_wallet" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Bank Fees</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(ghost, 'Insurance')">
                      <q-item-section avatar><q-icon name="security" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Insurance</q-item-section>
                    </q-item>
                  </q-list>
                </q-btn-dropdown>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-expansion-item>

    <!-- Blueprint vs Actual Table -->
    <q-expansion-item
      icon="compare_arrows"
      label="Blueprint vs Actual"
      header-class="bg-primary text-white"
      class="q-mb-md rounded-borders"
      default-opened
    >
      <q-card>
        <q-card-section class="q-pa-none">
          <q-table
            :rows="blueprintDetails"
            :columns="tableColumns"
            row-key="id"
            flat
            dense
            :pagination="{ rowsPerPage: 0 }"
            hide-pagination
          >
            <template #body-cell-variance="props">
              <q-td :props="props">
                <span :class="props.value < 0 ? 'text-negative' : props.value > 0 ? 'text-positive' : ''">
                  {{ formatCurrency(props.value) }}
                </span>
              </q-td>
            </template>
            <template #body-cell-is_cancelled="props">
              <q-td :props="props">
                <q-badge v-if="props.value" color="orange" label="CANCELLED" />
              </q-td>
            </template>
          </q-table>
        </q-card-section>
      </q-card>
    </q-expansion-item>

    <!-- Unmapped Leaks Section -->
    <q-expansion-item
      v-if="report?.unmapped_leaks && report.unmapped_leaks.length > 0"
      icon="water_drop"
      label="Unmapped Leaks"
      header-class="bg-grey-3"
      class="q-mb-md rounded-borders"
    >
      <q-card>
        <q-card-section>
          <p class="text-caption text-grey q-mb-sm">
            Bank fees, service charges, and interest that don't need Blueprint matching.
          </p>
          <q-list separator>
            <q-item v-for="(leak, idx) in report.unmapped_leaks" :key="idx">
              <q-item-section avatar>
                <q-icon name="water_drop" color="grey" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ leak.description }}</q-item-label>
                <q-item-label caption>{{ leak.count }} transaction(s)</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-item-label class="text-grey text-weight-bold">
                  {{ formatCurrency(leak.amount) }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
          <div class="text-right q-mt-sm">
            <span class="text-weight-bold">Total Leaks: {{ formatCurrency(totalLeaks) }}</span>
          </div>
        </q-card-section>
      </q-card>
    </q-expansion-item>

    <!-- Unmatched Transactions Section - HISTORICAL BUDGET ALLOCATION -->
    <q-expansion-item
      v-if="unmatchedTransactions.length > 0"
      icon="help_outline"
      :label="`Unmatched Transactions (${unmatchedTransactions.length})`"
      header-class="bg-warning text-white"
      class="q-mb-md rounded-borders"
      default-opened
    >
      <q-card>
        <q-card-section class="q-pa-none">
          <!-- Transaction Table with Balance Column -->
          <q-table
            :rows="unmatchedTransactions"
            :columns="unmatchedColumns"
            row-key="id"
            flat
            dense
            :pagination="{ rowsPerPage: 25 }"
            class="unmatched-table"
          >
            <!-- Date Column -->
            <template v-slot:body-cell-date="props">
              <q-td :props="props">
                <span class="text-caption">{{ formatDate(props.row.transaction_date) }}</span>
              </q-td>
            </template>

            <!-- Description Column -->
            <template v-slot:body-cell-description="props">
              <q-td :props="props">
                <div class="row items-center no-wrap">
                  <q-icon
                    :name="props.row.is_income ? 'arrow_downward' : 'arrow_upward'"
                    :color="props.row.is_income ? 'positive' : 'negative'"
                    size="xs"
                    class="q-mr-xs"
                  />
                  <span class="ellipsis" style="max-width: 250px">{{ props.row.description }}</span>
                  <q-badge
                    v-if="getPotentialMatch(props.row.description)"
                    :color="getPotentialMatch(props.row.description)?.color || 'grey'"
                    class="q-ml-sm"
                    outline
                    dense
                  >
                    {{ getPotentialMatch(props.row.description)?.label }}
                  </q-badge>
                </div>
              </q-td>
            </template>

            <!-- Amount Column -->
            <template v-slot:body-cell-amount="props">
              <q-td :props="props">
                <span
                  :class="props.row.is_income ? 'text-positive' : 'text-negative'"
                  class="text-weight-bold"
                >
                  {{ props.row.is_income ? '+' : '-' }}{{ formatCurrency(Math.abs(props.row.amount)) }}
                </span>
              </q-td>
            </template>

            <!-- Balance Column (Ground Truth) -->
            <template v-slot:body-cell-balance="props">
              <q-td :props="props">
                <span class="text-weight-medium text-blue-grey-7">
                  {{ props.row.balance ? formatCurrency(props.row.balance) : '—' }}
                </span>
              </q-td>
            </template>

            <!-- Action Column - Blueprint Dropdown with INTELLIGENT SUGGESTIONS -->
            <template v-slot:body-cell-action="props">
              <q-td :props="props">
                <q-btn-dropdown
                  flat
                  dense
                  :color="getPotentialMatch(props.row.description) ? 'positive' : 'primary'"
                  :icon="getPotentialMatch(props.row.description) ? 'auto_awesome' : 'add'"
                  dropdown-icon="arrow_drop_down"
                  size="sm"
                >
                  <q-list dense style="min-width: 250px; max-height: 400px" class="scroll">
                    <!-- INTELLIGENT SUGGESTION (if match found) -->
                    <template v-if="getPotentialMatch(props.row.description)">
                      <q-item-label header class="text-weight-bold text-positive bg-green-1">
                        <q-icon name="auto_awesome" size="xs" class="q-mr-xs" /> SUGGESTED
                      </q-item-label>
                      <q-item
                        clickable
                        v-close-popup
                        @click="assignToBlueprint(props.row, getPotentialMatch(props.row.description)!.blueprint)"
                        class="bg-green-1"
                      >
                        <q-item-section avatar>
                          <q-icon name="check_circle" :color="getPotentialMatch(props.row.description)!.color" />
                        </q-item-section>
                        <q-item-section>
                          <q-item-label class="text-weight-bold">{{ getPotentialMatch(props.row.description)!.blueprint }}</q-item-label>
                          <q-item-label caption>Click to assign & learn pattern</q-item-label>
                        </q-item-section>
                      </q-item>
                      <q-separator />
                    </template>

                    <!-- HOUSING -->
                    <q-item-label header class="text-weight-bold text-deep-purple">HOUSING</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Home Bond')">
                      <q-item-section avatar><q-icon name="home" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Home Bond</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Levies')">
                      <q-item-section avatar><q-icon name="apartment" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Levies</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Rates & Taxes')">
                      <q-item-section avatar><q-icon name="account_balance" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Rates & Taxes</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Electricity')">
                      <q-item-section avatar><q-icon name="bolt" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Electricity</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Home Repairs')">
                      <q-item-section avatar><q-icon name="handyman" color="deep-purple" size="xs" /></q-item-section>
                      <q-item-section>Home Repairs</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- LIFESTYLE -->
                    <q-item-label header class="text-weight-bold text-teal">LIFESTYLE</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Groceries')">
                      <q-item-section avatar><q-icon name="shopping_cart" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Groceries</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Fuel')">
                      <q-item-section avatar><q-icon name="local_gas_station" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Fuel</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Dining Out')">
                      <q-item-section avatar><q-icon name="restaurant" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Dining Out</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Medical')">
                      <q-item-section avatar><q-icon name="medical_services" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Medical</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Domestic Help')">
                      <q-item-section avatar><q-icon name="cleaning_services" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Domestic Help</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Gardener')">
                      <q-item-section avatar><q-icon name="grass" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Gardener</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Dining Out')">
                      <q-item-section avatar><q-icon name="restaurant" color="teal" size="xs" /></q-item-section>
                      <q-item-section>Dining Out</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- EDUCATION & FAMILY -->
                    <q-item-label header class="text-weight-bold text-indigo">EDUCATION & FAMILY</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Education')">
                      <q-item-section avatar><q-icon name="school" color="indigo" size="xs" /></q-item-section>
                      <q-item-section>Education / School</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- ENTERTAINMENT -->
                    <q-item-label header class="text-weight-bold text-purple">ENTERTAINMENT</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Entertainment')">
                      <q-item-section avatar><q-icon name="movie" color="purple" size="xs" /></q-item-section>
                      <q-item-section>Entertainment</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Gambling')">
                      <q-item-section avatar><q-icon name="casino" color="red" size="xs" /></q-item-section>
                      <q-item-section>Gambling / Lotto</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Subscriptions')">
                      <q-item-section avatar><q-icon name="subscriptions" color="purple" size="xs" /></q-item-section>
                      <q-item-section>Subscriptions</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- BUSINESS -->
                    <q-item-label header class="text-weight-bold text-blue">BUSINESS</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Business Expenses')">
                      <q-item-section avatar><q-icon name="business_center" color="blue" size="xs" /></q-item-section>
                      <q-item-section>Business Expenses</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- SHOPPING -->
                    <q-item-label header class="text-weight-bold text-pink">SHOPPING</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Purchases')">
                      <q-item-section avatar><q-icon name="shopping_bag" color="pink" size="xs" /></q-item-section>
                      <q-item-section>Purchases</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Online Purchases')">
                      <q-item-section avatar><q-icon name="local_shipping" color="pink" size="xs" /></q-item-section>
                      <q-item-section>Online Purchases</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Online Payments')">
                      <q-item-section avatar><q-icon name="payment" color="pink" size="xs" /></q-item-section>
                      <q-item-section>Online Payments</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Charity Private')">
                      <q-item-section avatar><q-icon name="volunteer_activism" color="pink" size="xs" /></q-item-section>
                      <q-item-section>Charity Private</q-item-section>
                    </q-item>

                    <q-separator />

                    <!-- FINANCIAL -->
                    <q-item-label header class="text-weight-bold text-amber-9">FINANCIAL</q-item-label>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Insurance')">
                      <q-item-section avatar><q-icon name="security" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Insurance</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Investment/Savings')">
                      <q-item-section avatar><q-icon name="savings" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Investment/Savings</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Vehicle Payments')">
                      <q-item-section avatar><q-icon name="directions_car" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Vehicle Payments</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Vehicle Maintenance')">
                      <q-item-section avatar><q-icon name="car_repair" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Vehicle Maintenance</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Toll Gates')">
                      <q-item-section avatar><q-icon name="toll" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Toll Gates</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Fines')">
                      <q-item-section avatar><q-icon name="gavel" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Fines</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="assignToBlueprint(props.row, 'Bank Fees')">
                      <q-item-section avatar><q-icon name="account_balance_wallet" color="amber-9" size="xs" /></q-item-section>
                      <q-item-section>Bank Fees</q-item-section>
                    </q-item>
                  </q-list>
                </q-btn-dropdown>
              </q-td>
            </template>
          </q-table>
        </q-card-section>
      </q-card>
    </q-expansion-item>

    </template>
    <!-- END MONTHLY VIEW -->

    <!-- Loading State -->
    <q-inner-loading :showing="loading || loadingTaxYear">
      <q-spinner-gears size="50px" color="primary" />
    </q-inner-loading>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useQuasar } from 'quasar';
import { auditApi, type VarianceReport, type GhostFee, type DataRangeInfo, type TaxYearSummary, type UnmatchedTransaction } from '@/services/api/audit.api';
import { useRouter } from 'vue-router';

const $q = useQuasar();
const router = useRouter();

// State
const loading = ref(false);
const loadingTaxYear = ref(false);
const reconciling = ref(false);
const monthOffset = ref(0);
const report = ref<VarianceReport | null>(null);
const dataRange = ref<DataRangeInfo | null>(null);
const taxYearData = ref<TaxYearSummary | null>(null);
const viewMode = ref<'monthly' | 'tax-year'>('monthly');
const selectedTaxYear = ref(2025); // SA Tax year 2025/2026
const initialLoadComplete = ref(false);
const isEmptyMonth = ref(false);
const unmatchedTransactions = ref<UnmatchedTransaction[]>([]);
const guidanceStep = ref(1);
const guidanceDismissed = ref(false);

// Opening Balance - anchored from Excel statement (first transaction of month)
const openingBalance = ref<number>(0);
const showOpeningBalanceDialog = ref(false);
const editingOpeningBalance = ref<number>(0);

// Save opening balance from dialog
function saveOpeningBalance() {
  openingBalance.value = editingOpeningBalance.value;
  showOpeningBalanceDialog.value = false;
}

// Watch for dialog open to pre-populate the current value
watch(showOpeningBalanceDialog, (isOpen) => {
  if (isOpen) {
    editingOpeningBalance.value = openingBalance.value;
  }
});

// Month Selector for Historical Budget
const selectedMonthKey = ref<string | null>(null);

// Available months for dropdown (Feb 2025 - Dec 2025 for historical audit)
const availableMonths = computed(() => {
  const months = [];
  // Generate months from Feb 2025 to Dec 2025
  const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  for (let y = 2025; y <= 2025; y++) {
    for (let m = 2; m <= 12; m++) { // Start from Feb (index 2)
      const key = `${y}-${String(m).padStart(2, '0')}`;
      months.push({
        label: `${monthNames[m - 1]} ${y}`,
        value: key,
      });
    }
  }
  return months;
});

// Table columns for unmatched transactions
const unmatchedColumns = [
  { name: 'date', label: 'Date', field: 'transaction_date', align: 'left' as const, sortable: true },
  { name: 'description', label: 'Description', field: 'description', align: 'left' as const },
  { name: 'amount', label: 'Amount', field: 'amount', align: 'right' as const, sortable: true },
  { name: 'balance', label: 'Balance', field: 'balance', align: 'right' as const },
  { name: 'action', label: 'Assign', field: 'action', align: 'center' as const },
];

// Handle month selection from dropdown
function onMonthSelected(monthKey: string) {
  if (!monthKey) return;

  const [yearStr, monthStr] = monthKey.split('-');
  const targetYear = parseInt(yearStr);
  const targetMonth = parseInt(monthStr);

  // Calculate the offset from current month
  const now = new Date();
  const currentYear = now.getFullYear();
  const currentMonth = now.getMonth() + 1;

  const targetDate = new Date(targetYear, targetMonth - 1, 1);
  const currentDate = new Date(currentYear, currentMonth - 1, 1);

  const diffMonths = (targetDate.getFullYear() - currentDate.getFullYear()) * 12 +
                     (targetDate.getMonth() - currentDate.getMonth());

  monthOffset.value = diffMonths;
  loadVarianceReport();
}

// Potential match patterns for keyword detection
interface PotentialMatch {
  keywords: string[];
  label: string;
  blueprint: string;
  color: string;
}

// INTELLIGENT PATTERN MATCHING - Auto-suggest categories based on description
const potentialMatchPatterns: PotentialMatch[] = [
  // === HOUSING ===
  { keywords: ['NEDBHL', 'NED BHL', 'BOND', 'MORTGAGE', 'HOME LOAN'], label: 'Home Bond', blueprint: 'Home Bond', color: 'deep-purple' },
  { keywords: ['LEVY', 'LEVIES', 'BODY CORP', 'HOA'], label: 'Levies', blueprint: 'Levies', color: 'deep-purple' },
  { keywords: ['RATES', 'MUNICIPAL', 'CITY OF', 'COUNCIL'], label: 'Rates & Taxes', blueprint: 'Rates & Taxes', color: 'deep-purple' },
  { keywords: ['ESKOM', 'PREPAID ELEC', 'ELECTRICITY', 'POWER'], label: 'Electricity', blueprint: 'Electricity', color: 'deep-purple' },

  // === LIFESTYLE ===
  { keywords: ['SPAR', 'SUPERSPAR', 'KWIKSPAR', 'PNP', 'PICK N PAY', 'CHECKERS', 'SHOPRITE', 'WOOLWORTHS', 'WOOLIES', 'FOOD LOVERS', 'MAKRO'], label: 'Groceries', blueprint: 'Groceries', color: 'teal' },
  { keywords: ['SHELL', 'TOTAL', 'ENGEN', 'SASOL', 'CALTEX', 'BP ', 'FUEL', 'PETROL', 'GARAGE'], label: 'Fuel', blueprint: 'Fuel', color: 'teal' },
  { keywords: ['MEDIHELP', 'DISCOVERY', 'MEDICAL AID', 'CLICKS', 'DISCHEM', 'PHARMACY', 'DR ', 'DOCTOR'], label: 'Medical', blueprint: 'Medical', color: 'teal' },
  { keywords: ['STEVIN', 'GARDENER', 'GARDEN SERVICE'], label: 'Gardener', blueprint: 'Gardener', color: 'teal' },
  { keywords: ['DOMESTIC', 'CLEANER', 'MAID'], label: 'Domestic Help', blueprint: 'Domestic Help', color: 'teal' },

  // === DINING OUT (NEW) ===
  { keywords: ['CAFE', 'RESTAURANT', 'COFFEE', 'WIMPY', 'SPUR', 'STEERS', 'NANDOS', 'KFC', 'MCDONALDS', 'BURGER', 'PIZZA', 'KOSMOS', 'OCEAN BASKET', 'FISHAWAYS', 'DEBONAIRS'], label: 'Dining Out', blueprint: 'Dining Out', color: 'pink' },

  // === EDUCATION (NEW) ===
  { keywords: ['SCHOOL', 'ALMASCHO', 'EDUCATION', 'TUITION', 'COLLEGE', 'UNIVERSITY', 'ACADEMY'], label: 'Education', blueprint: 'Education', color: 'indigo' },

  // === ENTERTAINMENT (NEW) ===
  { keywords: ['CINEMA', 'MOVIES', 'STER KINEKOR', 'NETFLIX', 'SHOWMAX', 'DSTV', 'MULTICHOICE'], label: 'Entertainment', blueprint: 'Entertainment', color: 'purple' },

  // === GAMBLING (NEW) ===
  { keywords: ['LOTTO', 'LOTTERY', 'GAMING', 'CASINO', 'BETWAY', 'HOLLYWOODBETS', 'SPORTINGBET', 'SUPABETS', 'QUICK PICK'], label: 'Gambling', blueprint: 'Gambling', color: 'red' },

  // === TOLL GATES (NEW) ===
  { keywords: ['TOLL', 'SANRAL', 'N1', 'N2', 'N3', 'N4', 'E-TOLL', 'ETOLL', 'BAKWENA', 'TRAC', 'TAP N PAY', 'MACHADO'], label: 'Toll Gates', blueprint: 'Toll Gates', color: 'orange' },

  // === FINES ===
  { keywords: ['FINE', 'AARTO', 'METRO POLICE', 'JMPD', 'TRAFFIC', 'SPEEDING', 'PARKING FINE'], label: 'Fines', blueprint: 'Fines', color: 'red' },

  // === INSURANCE ===
  { keywords: ['MOMENTUM', 'OLD MUTUAL', 'SANLAM', 'LIBERTY', 'INSURANCE', 'LIFE ', 'COVER', 'PREMIUM', 'UIM SA', 'BIDTRACK'], label: 'Insurance', blueprint: 'Insurance', color: 'amber-9' },

  // === ONLINE PAYMENTS (NEW) ===
  { keywords: ['PAYSTACK', 'PAYPAL', 'PAYFAST', 'OZOW', 'SNAPSCAN', 'ZAPPER'], label: 'Online Payments', blueprint: 'Online Payments', color: 'cyan' },

  // === SUBSCRIPTIONS ===
  { keywords: ['SUBSCRIPTION', 'GROUND NEWS', 'SPOTIFY', 'APPLE', 'GOOGLE', 'MICROSOFT', 'AMAZON'], label: 'Subscriptions', blueprint: 'Subscriptions', color: 'pink' },

  // === CHARITY ===
  { keywords: ['CHARITY', 'DONATION', 'GIFT OF GIVERS', 'RED CROSS'], label: 'Charity Private', blueprint: 'Charity Private', color: 'pink' },

  // === BANK FEES ===
  { keywords: ['MAINTENANCE FEE', 'BANK FEE', 'SERVICE FEE', 'INSTANT PAYMENT', 'PAYSHAP', 'SASWITCH'], label: 'Bank Fees', blueprint: 'Bank Fees', color: 'amber-9' },

  // === INCOME ===
  { keywords: ['NUCLEUS', 'SALARY', 'INVOICE PAYMENT', 'PAYMENT RECEIVED', 'DEPOSIT'], label: 'Income', blueprint: 'Income', color: 'green' },
];

// Determine current SA tax year (March to February)
// If we're in Jan-Feb, we're still in the previous tax year
const currentTaxYear = computed(() => {
  const now = new Date();
  const month = now.getMonth() + 1; // 1-12
  const year = now.getFullYear();
  // Tax year 2025 = March 2025 to February 2026
  // If we're in Jan-Feb of 2026, we're still in tax year 2025
  return month >= 3 ? year : year - 1;
});

const taxYearLabel = computed(() => {
  return `Tax Year ${selectedTaxYear.value}/${selectedTaxYear.value + 1}`;
});

// Computed
const currentDate = computed(() => {
  const date = new Date();
  date.setMonth(date.getMonth() + monthOffset.value);
  return date;
});

const year = computed(() => currentDate.value.getFullYear());
const month = computed(() => currentDate.value.getMonth() + 1);

// Check if we're viewing a month with no data
const hasDataForCurrentMonth = computed(() => {
  if (!dataRange.value?.months_with_data) return true;
  return dataRange.value.months_with_data.some(
    m => m.year === year.value && m.month === month.value
  );
});

// Empty state message
const emptyStateMessage = computed(() => {
  if (!dataRange.value?.has_data) {
    return 'No transaction data found. Import your bank statement to get started.';
  }
  const minMonth = dataRange.value.months_with_data[dataRange.value.months_with_data.length - 1];
  const maxMonth = dataRange.value.months_with_data[0];
  return `No data for ${periodLabel.value}. Use the arrows to view your ${minMonth?.label} - ${maxMonth?.label} audit history.`;
});

const periodLabel = computed(() => {
  return currentDate.value.toLocaleDateString('en-ZA', { month: 'long', year: 'numeric' });
});

const actualNet = computed(() => {
  return (report.value?.actual?.income || 0) - (report.value?.actual?.expenses || 0);
});

// Real Net Spendable: Opening Balance + Income - Expenses
const realNetSpendable = computed(() => {
  const income = report.value?.actual?.income || 0;
  const expenses = report.value?.actual?.expenses || 0;
  return openingBalance.value + income - expenses;
});

const varianceClass = computed(() => {
  // Variance = Expected - Actual
  // NEGATIVE variance = overspent = RED
  // POSITIVE variance = underspent = GREEN
  const variance = report.value?.variance || 0;
  if (variance > 0) return 'bg-positive text-white';  // Underspent - GREEN
  if (variance < 0) return 'bg-negative text-white';  // Overspent - RED
  return 'bg-grey-6 text-white'; // neutral
});

const varianceMessage = computed(() => {
  const variance = report.value?.variance || 0;
  const holidaySurplusAmount = report.value?.holiday_surplus || 0;
  if (variance >= 0) {
    if (holidaySurplusAmount > 0) {
      return `Surplus (includes ${formatCurrency(holidaySurplusAmount)} from payment holiday)`;
    }
    return 'On track or under budget';
  }
  if (variance > -1000) return 'Slightly over budget';
  return 'Significantly over budget';
});

// Holiday surplus computed properties
const showHolidayDetails = ref(false);

const holidaySurplus = computed(() => {
  return report.value?.holiday_surplus || 0;
});

const holidayItems = computed(() => {
  return report.value?.variance_breakdown?.contractual_holiday_items || [];
});

const holidayEndDate = computed(() => {
  const items = holidayItems.value;
  if (items.length === 0) return null;
  // Get the earliest end date from all holiday items
  const endDates = items
    .map((item: { pause_end_date?: string }) => item.pause_end_date)
    .filter((d: string | undefined): d is string => !!d)
    .sort();
  if (endDates.length === 0) return null;
  return new Date(endDates[0]).toLocaleDateString('en-ZA', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
});

const blueprintDetails = computed(() => {
  return report.value?.blueprint_details || [];
});

const totalLeaks = computed(() => {
  return report.value?.unmapped_leaks?.reduce((sum, leak) => sum + leak.amount, 0) || 0;
});

// Show guidance when there are unmatched transactions and user hasn't dismissed
const showGuidance = computed(() => {
  return !guidanceDismissed.value &&
         initialLoadComplete.value &&
         (report.value?.summary?.unmatched || 0) > 0;
});

// Check if user has income blueprints
const hasIncomeBlueprints = computed(() => {
  return blueprintDetails.value.some(bp => bp.item_type === 'income' && bp.expected > 0);
});

/**
 * Get potential match for a transaction description
 */
function getPotentialMatch(description: string): PotentialMatch | null {
  const upperDesc = description.toUpperCase();
  for (const pattern of potentialMatchPatterns) {
    for (const keyword of pattern.keywords) {
      if (upperDesc.includes(keyword.toUpperCase())) {
        return pattern;
      }
    }
  }
  return null;
}

/**
 * Dismiss the guidance card
 */
function dismissGuidance() {
  guidanceDismissed.value = true;
}

/**
 * Quick match a transaction to a blueprint
 */
async function quickMatch(tx: UnmatchedTransaction, blueprintName: string | undefined) {
  if (!blueprintName) return;

  // For now, navigate to blueprint page with suggestion
  router.push({
    path: '/blueprint',
    query: {
      highlight: blueprintName,
      transaction: tx.id,
    },
  });

  $q.notify({
    type: 'info',
    message: `Go to Blueprints and add "${tx.description.substring(0, 30)}..." to ${blueprintName}`,
  });
}

/**
 * Load unmatched transactions for the current month
 * Also extracts the opening balance from the first transaction
 */
async function loadUnmatchedTransactions() {
  try {
    const startDate = new Date(year.value, month.value - 1, 1);
    const endDate = new Date(year.value, month.value, 0);

    const result = await auditApi.getUnmatched(
      startDate.toISOString().split('T')[0],
      endDate.toISOString().split('T')[0]
    );

    unmatchedTransactions.value = result.transactions || [];

    // Extract opening balance from first transaction of the month
    // Opening balance = first transaction balance + first transaction amount (reverse the effect)
    if (result.transactions && result.transactions.length > 0) {
      // Sort by date ascending to get earliest transaction
      const sorted = [...result.transactions].sort((a, b) =>
        new Date(a.transaction_date).getTime() - new Date(b.transaction_date).getTime()
      );
      const firstTx = sorted[0];
      if (firstTx.balance !== undefined && firstTx.balance !== null) {
        // If first tx is expense (negative), opening = balance + abs(amount)
        // If first tx is income (positive), opening = balance - amount
        const txAmount = firstTx.is_income ? Math.abs(firstTx.amount) : -Math.abs(firstTx.amount);
        openingBalance.value = firstTx.balance - txAmount;
      }
    }
  } catch (error) {
    console.error('Failed to load unmatched transactions:', error);
    unmatchedTransactions.value = [];
  }
}

const tableColumns = [
  { name: 'name', label: 'Item', field: 'name', align: 'left' as const, sortable: true },
  { name: 'item_type', label: 'Type', field: 'item_type', align: 'center' as const },
  { name: 'bucket', label: 'Bucket', field: 'bucket', align: 'center' as const },
  { name: 'expected', label: 'Expected', field: 'expected', align: 'right' as const, format: (v: number) => formatCurrency(v) },
  { name: 'actual', label: 'Actual', field: 'actual', align: 'right' as const, format: (v: number) => formatCurrency(v) },
  { name: 'variance', label: 'Variance', field: 'variance', align: 'right' as const },
  { name: 'is_cancelled', label: 'Status', field: 'is_cancelled', align: 'center' as const },
];

const taxYearColumns = [
  { name: 'label', label: 'Month', field: 'label', align: 'left' as const },
  { name: 'income', label: 'Income', field: 'income', align: 'right' as const, format: (v: number) => formatCurrency(v) },
  { name: 'expenses', label: 'Expenses', field: 'expenses', align: 'right' as const, format: (v: number) => formatCurrency(v) },
  { name: 'net', label: 'Net', field: 'net', align: 'right' as const },
  { name: 'transaction_count', label: 'Transactions', field: 'transaction_count', align: 'center' as const },
];

// Methods
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-ZA', {
    style: 'currency',
    currency: 'ZAR',
    minimumFractionDigits: 2,
  }).format(amount);
}

function formatDate(dateString: string | null): string {
  if (!dateString) return 'Unknown';
  return new Date(dateString).toLocaleDateString('en-ZA', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function formatNumber(amount: number): string {
  return new Intl.NumberFormat('en-ZA', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

function previousMonth() {
  monthOffset.value--;
}

function nextMonth() {
  if (monthOffset.value < 0) {
    monthOffset.value++;
  }
}

async function loadVarianceReport() {
  loading.value = true;
  try {
    // Load variance report and unmatched transactions in parallel
    const [varianceResult] = await Promise.all([
      auditApi.getVariance(year.value, month.value),
      loadUnmatchedTransactions(),
    ]);
    report.value = varianceResult;
  } catch (error) {
    console.error('Failed to load variance report:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to load variance report',
    });
  } finally {
    loading.value = false;
  }
}

async function runReconciliation() {
  reconciling.value = true;
  try {
    const startDate = new Date(year.value, month.value - 1, 1);
    const endDate = new Date(year.value, month.value, 0);

    const result = await auditApi.reconcile({
      start_date: startDate.toISOString().split('T')[0],
      end_date: endDate.toISOString().split('T')[0],
    });

    $q.notify({
      type: 'positive',
      message: `Reconciliation complete: ${result.matched} matched, ${result.unmatched} unmatched`,
    });

    // Reload the report
    await loadVarianceReport();
  } catch (error) {
    console.error('Reconciliation failed:', error);
    $q.notify({
      type: 'negative',
      message: 'Reconciliation failed',
    });
  } finally {
    reconciling.value = false;
  }
}

function addToBlueprint(ghost: GhostFee) {
  // Navigate to blueprint page with prefilled data
  router.push({
    path: '/blueprint',
    query: {
      add: 'true',
      name: ghost.description,
      amount: ghost.amount.toString(),
      due_day: new Date(ghost.date).getDate().toString(),
    },
  });
}

/**
 * Assign a transaction directly to a blueprint
 * Supports both GhostFee and UnmatchedTransaction types
 * Calls the API to save the mapping and refreshes the list
 */
async function assignToBlueprint(transaction: GhostFee | UnmatchedTransaction, blueprintName: string) {
  try {
    // Get transaction ID - handle both types
    const txId = 'id' in transaction ? transaction.id : (transaction as GhostFee).id;

    // Call API to assign transaction to blueprint (with pattern learning)
    const result = await auditApi.assignToBlueprint(txId, blueprintName);

    // Show success with auto-match count
    const autoMatched = result.auto_matched || 0;
    const pattern = result.anchor_pattern || '';

    if (autoMatched > 0) {
      $q.notify({
        type: 'positive',
        message: `Pattern "${pattern}" → ${blueprintName}. Auto-matched ${autoMatched} more transactions!`,
        icon: 'auto_awesome',
        timeout: 4000,
      });
    } else {
      $q.notify({
        type: 'positive',
        message: `Assigned to ${blueprintName}. Pattern "${pattern}" saved for future matching.`,
        icon: 'check',
        timeout: 3000,
      });
    }

    // Refresh the variance report to update the unmatched list
    await loadVarianceReport();

  } catch (error) {
    console.error('Failed to assign to blueprint:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to assign to blueprint',
    });
  }
}

/**
 * Navigate to the most recent month with data
 */
function goToLatestMonth() {
  if (dataRange.value?.most_recent_year && dataRange.value?.most_recent_month) {
    monthOffset.value = calculateMonthOffset(
      dataRange.value.most_recent_year,
      dataRange.value.most_recent_month
    );
    loadVarianceReport();
  }
}

/**
 * Handle view mode toggle between monthly and tax year
 */
async function handleViewModeChange(newMode: 'monthly' | 'tax-year') {
  if (newMode === 'tax-year') {
    await loadTaxYearSummary();
  }
}

/**
 * Load tax year summary from the API
 */
async function loadTaxYearSummary() {
  loadingTaxYear.value = true;
  try {
    taxYearData.value = await auditApi.getTaxYearSummary(selectedTaxYear.value);
  } catch (error) {
    console.error('Failed to load tax year summary:', error);
    taxYearData.value = null;
    $q.notify({
      type: 'negative',
      message: 'Failed to load tax year summary',
    });
  } finally {
    loadingTaxYear.value = false;
  }
}

// Watch for tax year changes
watch(selectedTaxYear, () => {
  if (viewMode.value === 'tax-year') {
    loadTaxYearSummary();
  }
});

// Watch for month changes
watch([year, month], () => {
  if (initialLoadComplete.value) {
    loadVarianceReport();
  }
});

/**
 * Calculate the month offset to reach a target year/month from today
 */
function calculateMonthOffset(targetYear: number, targetMonth: number): number {
  const now = new Date();
  const currentYear = now.getFullYear();
  const currentMonth = now.getMonth() + 1; // 1-based

  // Offset = (targetYear - currentYear) * 12 + (targetMonth - currentMonth)
  return (targetYear - currentYear) * 12 + (targetMonth - currentMonth);
}

/**
 * Load data range and smart-default to most recent month with data
 */
async function initializeWithSmartDefault() {
  loading.value = true;
  try {
    // First, get the data range to know which months have data
    dataRange.value = await auditApi.getDataRange();

    // If current month has no data, pivot to most recent month with data
    if (dataRange.value?.has_data && dataRange.value.most_recent_year && dataRange.value.most_recent_month) {
      const now = new Date();
      const currentYear = now.getFullYear();
      const currentMonth = now.getMonth() + 1;

      // Check if current month has data
      const currentMonthHasData = dataRange.value.months_with_data?.some(
        m => m.year === currentYear && m.month === currentMonth
      );

      if (!currentMonthHasData) {
        // Pivot to most recent month with data
        const targetOffset = calculateMonthOffset(
          dataRange.value.most_recent_year,
          dataRange.value.most_recent_month
        );
        monthOffset.value = targetOffset;

        $q.notify({
          type: 'info',
          message: `Showing ${dataRange.value.months_with_data?.[0]?.label || 'most recent month'} (no data for current month)`,
          timeout: 3000,
        });
      }
    }

    // Now load the variance report for the selected month
    await loadVarianceReport();
    initialLoadComplete.value = true;
  } catch (error) {
    console.error('Failed to initialize audit page:', error);
    // Fall back to just loading the variance report
    await loadVarianceReport();
    initialLoadComplete.value = true;
  }
}

// Initial load with smart defaulting
onMounted(() => {
  initializeWithSmartDefault();
});
</script>

<style scoped>
.rounded-borders {
  border-radius: 8px;
  overflow: hidden;
}
</style>
