<template>
  <q-page class="onboarding-page">
    <div class="wizard-container">
      <!-- Progress -->
      <div class="wizard-progress q-mb-lg">
        <q-linear-progress
          :value="progressValue"
          color="teal-8"
          track-color="teal-2"
          size="8px"
          rounded
        />
        <div class="text-caption text-grey-7 q-mt-xs text-center">
          Step {{ currentStep }} of {{ totalSteps }}
        </div>
      </div>

      <!-- Step 1: Income Type -->
      <div v-if="currentStep === 1" class="wizard-step">
        <div class="step-header">
          <q-icon name="person" size="32px" color="teal-8" />
          <div class="text-h6">How do you earn your income?</div>
          <div class="text-caption text-grey-7">This helps us tailor your experience</div>
        </div>

        <div class="income-options q-mt-lg">
          <q-card
            v-for="option in incomeTypeOptions"
            :key="option.value"
            flat
            bordered
            class="income-option"
            :class="{ selected: form.income_type === option.value }"
            @click="form.income_type = option.value"
          >
            <q-card-section class="row items-center no-wrap">
              <q-avatar :color="form.income_type === option.value ? 'teal-8' : 'grey-4'" text-color="white" size="48px">
                <q-icon :name="option.icon" />
              </q-avatar>
              <div class="q-ml-md">
                <div class="text-subtitle1 text-weight-medium">{{ option.label }}</div>
                <div class="text-caption text-grey-7">{{ option.description }}</div>
              </div>
              <q-space />
              <q-icon
                v-if="form.income_type === option.value"
                name="check_circle"
                color="teal-8"
                size="24px"
              />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Step 2: Monthly Base Amount -->
      <div v-if="currentStep === 2" class="wizard-step">
        <div class="step-header">
          <q-icon name="account_balance_wallet" size="32px" color="teal-8" />
          <div class="text-h6">{{ labels.amountTitle }}</div>
          <div class="text-caption text-grey-7">{{ labels.amountSubtitle }}</div>
        </div>

        <!-- AI Document Upload Zone -->
        <div class="upload-section q-mt-lg">
          <q-card
            flat
            bordered
            class="upload-zone"
            :class="{ 'upload-zone-active': isDragging, 'upload-zone-success': extractedData }"
          >
            <q-card-section v-if="!isExtracting && !extractedData" class="text-center">
              <q-icon name="cloud_upload" size="48px" :color="isDragging ? 'teal-8' : 'grey-5'" />
              <div class="text-subtitle2 q-mt-sm">Upload your latest Invoice or Payslip</div>
              <div class="text-caption text-grey-6">PDF, PNG, or JPG - Penny will read it for you</div>
              <q-file
                v-model="uploadedFile"
                accept=".pdf,.png,.jpg,.jpeg"
                outlined
                dense
                class="q-mt-md upload-input"
                label="Choose file or drag here"
                @update:model-value="handleFileUpload"
                @dragenter="isDragging = true"
                @dragleave="isDragging = false"
                @drop="isDragging = false"
              >
                <template v-slot:prepend>
                  <q-icon name="attach_file" />
                </template>
              </q-file>
            </q-card-section>

            <!-- Extracting State -->
            <q-card-section v-else-if="isExtracting" class="text-center">
              <q-spinner-dots color="teal-8" size="48px" />
              <div class="text-subtitle2 q-mt-sm text-teal-8">Penny is reading your document...</div>
              <div class="text-caption text-grey-6">Using AI to extract income details</div>
            </q-card-section>

            <!-- Extraction Success -->
            <q-card-section v-else-if="extractedData" class="extraction-result">
              <div class="result-header">
                <q-icon
                  :name="extractedData.is_payslip ? 'badge' : 'receipt'"
                  size="24px"
                  color="teal-8"
                />
                <div>
                  <div class="text-subtitle2 text-teal-8">
                    {{ extractedData.is_payslip ? 'Payslip Detected' : 'Invoice Detected' }}
                  </div>
                  <div class="text-caption text-grey-7">
                    {{ extractedData.entity_name || 'Document scanned' }}
                    <span v-if="extractedData.confidence">
                      ({{ extractedData.confidence }}% confidence)
                    </span>
                  </div>
                </div>
                <q-space />
                <q-btn flat dense icon="close" size="sm" @click="clearExtraction" />
              </div>

              <!-- Extracted Amount Display -->
              <div class="extracted-amount q-mt-md">
                <div class="text-caption text-grey-6">
                  {{ extractedData.is_payslip ? 'Net Pay Detected' : 'Invoice Amount Detected' }}
                </div>
                <div class="text-h5 text-teal-8 text-weight-bold">
                  {{ currencySymbol }} {{ formatAmount(extractedData.monthly_base_amount) }}
                </div>
              </div>

              <q-btn
                flat
                dense
                size="sm"
                color="grey-6"
                label="Upload different document"
                class="q-mt-sm"
                @click="clearExtraction"
              />
            </q-card-section>
          </q-card>

          <!-- OR Divider -->
          <div class="or-divider q-my-md">
            <span>or enter manually</span>
          </div>
        </div>

        <div class="amount-input">
          <q-input
            v-model.number="form.monthly_base_amount"
            type="number"
            :label="labels.amountLabel"
            :prefix="currencySymbol"
            outlined
            class="amount-field"
            :rules="[val => val > 0 || 'Please enter a valid amount']"
          />

          <div class="budget-preview q-mt-md" v-if="form.monthly_base_amount > 0">
            <div class="text-subtitle2 text-grey-8 q-mb-sm">50/30/20 Budget Preview</div>

            <!-- Tax Set-Aside (Self-Employed Only) -->
            <div v-if="!isSalaried" class="budget-row tax-row">
              <span class="budget-label">
                <q-icon name="account_balance" size="14px" class="q-mr-xs" />
                Est. Tax Set-Aside ({{ taxRate }}%)
              </span>
              <span class="budget-value">- {{ currencySymbol }} {{ formatAmount(taxSetAside) }}</span>
            </div>
            <div v-if="!isSalaried" class="budget-row net-row">
              <span class="budget-label text-weight-medium">Net After Tax</span>
              <span class="budget-value text-weight-bold">{{ currencySymbol }} {{ formatAmount(netAmount) }}</span>
            </div>
            <q-separator v-if="!isSalaried" class="q-my-sm" />

            <div class="budget-row">
              <span class="budget-label">Needs (50%)</span>
              <span class="budget-value">{{ currencySymbol }} {{ formatAmount(budgetPreview.needs) }}</span>
            </div>
            <div class="budget-row">
              <span class="budget-label">Wants (30%)</span>
              <span class="budget-value">{{ currencySymbol }} {{ formatAmount(budgetPreview.wants) }}</span>
            </div>
            <div class="budget-row text-teal-8">
              <span class="budget-label">Savings (20%)</span>
              <span class="budget-value text-weight-bold">{{ currencySymbol }} {{ formatAmount(budgetPreview.savings) }}</span>
            </div>

            <!-- Spendable info for self-employed -->
            <div v-if="!isSalaried" class="tax-disclaimer q-mt-sm">
              <q-icon name="info" size="12px" color="deep-purple-4" />
              <span>Tax is set aside first - budget from what remains</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 3: December Statement Interview (Universal Factory) -->
      <div v-if="currentStep === 3" class="wizard-step">
        <div class="step-header">
          <q-icon name="calendar_month" size="32px" color="teal-8" />
          <div class="text-h6">December Statement Setup</div>
          <div class="text-caption text-grey-7">
            Upload your December bank statement to anchor your budget
          </div>
        </div>

        <!-- Universal Statement Upload Zone -->
        <div class="upload-section q-mt-lg" v-if="!decemberTransactions.length && !isParsingStatement">
          <q-card flat bordered class="upload-zone">
            <q-card-section class="text-center">
              <q-icon name="upload_file" size="48px" color="grey-5" />
              <div class="text-subtitle2 q-mt-sm">Upload December Bank Statement</div>
              <div class="text-caption text-grey-6">PDF, CSV, OFX, QIF, or XLSX from your bank</div>
              <q-file
                v-model="decemberStatementFile"
                accept=".csv,.ofx,.ofc,.qif,.xlsx,.xls,.pdf"
                outlined
                dense
                class="q-mt-md upload-input"
                label="Choose statement file"
                @update:model-value="handleStatementUpload"
              >
                <template v-slot:prepend>
                  <q-icon name="attach_file" />
                </template>
              </q-file>
              <div v-if="detectedFormat" class="text-caption text-teal-7 q-mt-sm">
                Format: {{ detectedFormat.toUpperCase() }}
              </div>
            </q-card-section>
          </q-card>

          <!-- Skip Option -->
          <div class="text-center q-mt-md">
            <q-btn
              flat
              color="grey-6"
              label="Skip - I'll set up manually later"
              size="sm"
              @click="skipDecemberStatement"
            />
          </div>
        </div>

        <!-- Parsing State -->
        <div v-if="isParsingStatement" class="text-center q-my-xl">
          <q-spinner-dots color="teal-8" size="48px" />
          <div class="text-subtitle2 q-mt-sm text-teal-8">Parsing your {{ detectedFormat?.toUpperCase() || 'statement' }}...</div>
          <div class="text-caption text-grey-6">Penny is auto-categorizing your transactions</div>
        </div>

        <!-- December SME Summary Display (after parsing, before interrogation) -->
        <div v-if="decemberSummary && !isInterrogationComplete" class="sme-summary q-mb-lg">
          <q-card flat bordered class="summary-card">
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold text-teal-8 q-mb-md">
                {{ summaryDisplay?.title }}
              </div>

              <!-- Income Section -->
              <div class="summary-section">
                <div class="section-header text-caption text-grey-7">
                  Income (After 39.1% Tax Set-Aside)
                </div>
                <div class="summary-item" v-for="payment in decemberSummary.income.recognizedPayments" :key="payment.clientName">
                  <q-icon name="business" color="teal" size="16px" />
                  <span>{{ payment.clientName }}</span>
                  <span class="text-weight-bold">{{ currencySymbol }} {{ payment.amount.toLocaleString('en-ZA') }}</span>
                </div>
                <div class="summary-item highlight-row">
                  <q-icon name="savings" color="deep-purple" size="16px" />
                  <span>Tax Set-Aside (39.1%)</span>
                  <span class="text-deep-purple text-weight-bold">-{{ currencySymbol }} {{ decemberSummary.income.taxSetAside.toLocaleString('en-ZA') }}</span>
                </div>
                <div class="summary-item net-row">
                  <span class="text-weight-bold">Net After Tax</span>
                  <span class="text-teal-8 text-weight-bold text-h6">
                    {{ currencySymbol }} {{ decemberSummary.income.netAfterTax.toLocaleString('en-ZA') }}
                  </span>
                </div>

                <!-- Pending Business Deductions (Ghost Numbers) -->
                <template v-if="decemberSummary.income.pendingBusinessDeductions > 0">
                  <q-separator class="q-my-sm" />
                  <div class="text-caption text-grey-6 q-mb-xs">
                    ─── Pending Accountant Review ───
                  </div>
                  <div class="summary-item ghost-row">
                    <q-icon name="work" color="amber" size="16px" />
                    <span class="text-grey-6">Business Expenses Claimed</span>
                    <span class="text-grey-6">{{ currencySymbol }} {{ decemberSummary.income.pendingBusinessDeductions.toLocaleString('en-ZA') }}</span>
                  </div>
                  <div class="summary-item ghost-row">
                    <q-icon name="savings" color="amber" size="16px" />
                    <span class="text-amber-8">💰 Estimated Refund</span>
                    <span class="text-amber-8 text-weight-bold">+{{ currencySymbol }} {{ decemberSummary.income.estimatedRefund.toLocaleString('en-ZA') }}</span>
                  </div>
                </template>

                <!-- Approved Refund (if any) -->
                <template v-if="decemberSummary.income.confirmedRefund > 0">
                  <div class="summary-item">
                    <q-icon name="check_circle" color="positive" size="16px" />
                    <span class="text-positive">✅ Accountant Approved Refund</span>
                    <span class="text-positive text-weight-bold">+{{ currencySymbol }} {{ decemberSummary.income.confirmedRefund.toLocaleString('en-ZA') }}</span>
                  </div>
                </template>
              </div>

              <!-- Expenses Section -->
              <q-separator class="q-my-md" />
              <div class="summary-section">
                <div class="section-header text-caption text-grey-7">
                  Solo Survival Costs
                </div>
                <div class="summary-item">
                  <q-icon name="home" color="blue" size="16px" />
                  <span>Essential (Bond, Rates, Utilities)</span>
                  <span>{{ currencySymbol }} {{ decemberSummary.expenses.essential.toLocaleString('en-ZA') }}</span>
                </div>
                <div class="summary-item">
                  <q-icon name="build" color="orange" size="16px" />
                  <span>Repairs & Maintenance</span>
                  <span>{{ currencySymbol }} {{ decemberSummary.expenses.repairs.toLocaleString('en-ZA') }}</span>
                </div>
                <div class="summary-item survival-total">
                  <span class="text-weight-medium">Survival Total</span>
                  <span class="text-weight-bold">{{ currencySymbol }} {{ decemberSummary.expenses.survivalTotal.toLocaleString('en-ZA') }}</span>
                </div>
              </div>

              <!-- Net Position -->
              <q-separator class="q-my-md" />
              <div class="net-position" :class="decemberSummary.netPosition.netSMEPosition >= 0 ? 'positive' : 'negative'">
                <div class="text-caption">Net SME Position</div>
                <div class="text-h5 text-weight-bold">
                  {{ decemberSummary.netPosition.netSMEPosition >= 0 ? '+' : '' }}{{ currencySymbol }} {{ decemberSummary.netPosition.netSMEPosition.toLocaleString('en-ZA') }}
                </div>
                <div class="text-caption">
                  Survival Ratio: {{ decemberSummary.netPosition.survivalRatio }}%
                  <q-icon
                    :name="decemberSummary.netPosition.survivalRatio <= 50 ? 'check_circle' : 'warning'"
                    :color="decemberSummary.netPosition.survivalRatio <= 50 ? 'positive' : 'warning'"
                    size="14px"
                  />
                </div>
              </div>

              <!-- Categorization Status -->
              <div class="categorization-status q-mt-md">
                <q-linear-progress
                  :value="decemberSummary.categorization.completionRate / 100"
                  color="teal"
                  track-color="grey-3"
                  rounded
                  size="8px"
                />
                <div class="text-caption text-grey-7 q-mt-xs">
                  {{ decemberSummary.categorization.completionRate }}% auto-categorized
                  <span v-if="decemberSummary.categorization.needsInterrogation > 0">
                    ({{ decemberSummary.categorization.needsInterrogation }} need review)
                  </span>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Continue to Interrogation if needed -->
          <q-btn
            v-if="decemberSummary.categorization.needsInterrogation > 0"
            color="teal-8"
            label="Review Uncategorized Items"
            icon="category"
            class="full-width q-mt-md"
            unelevated
            @click="startInterrogation"
          />
          <q-btn
            v-else
            color="teal-8"
            label="Continue to Balance Setup"
            icon-right="arrow_forward"
            class="full-width q-mt-md"
            unelevated
            @click="completeInterrogation"
          />
        </div>

        <!-- Pattern Interrogator (for uncategorized items only) -->
        <div v-if="decemberSummary && decemberSummary.categorization.needsInterrogation > 0 && showInterrogator && !isInterrogationComplete">
          <div class="q-mb-md">
            <q-banner class="bg-amber-1" rounded>
              <template v-slot:avatar>
                <q-icon name="help_outline" color="amber-8" />
              </template>
              {{ decemberSummary.categorization.needsInterrogation }} items need your input.
              Penny couldn't auto-categorize these.
            </q-banner>
          </div>

          <WizardPatternInterrogator
            :transactions="filteredDecemberTransactions"
            @complete="handlePatternComplete"
            @progress="handlePatternProgress"
          />
        </div>

        <!-- Balance Entry (after interrogation complete) -->
        <div v-if="isInterrogationComplete && dec30Balance === null" class="balance-entry">
          <q-card flat bordered class="balance-card">
            <q-card-section>
              <div class="text-center">
                <q-icon name="account_balance" size="40px" color="teal-8" />
                <div class="text-h6 q-mt-sm">What's your current bank balance?</div>
                <div class="text-caption text-grey-7">
                  Enter your balance as of December 30th, {{ decemberYear }}
                </div>
              </div>

              <q-input
                v-model.number="dec30Balance"
                type="number"
                outlined
                class="q-mt-lg balance-input"
                :prefix="currencySymbol"
                placeholder="0.00"
                :input-style="{ textAlign: 'right', fontSize: '24px', fontWeight: '600' }"
              />

              <q-btn
                color="teal-8"
                label="Calculate My Anchor"
                icon="anchor"
                class="full-width q-mt-lg"
                size="lg"
                unelevated
                :disable="!dec30Balance || dec30Balance <= 0"
                @click="calculateAnchor"
              />
            </q-card-section>
          </q-card>
        </div>

        <!-- Anchor Confirmed State -->
        <div v-if="isInterrogationComplete && dec30Balance !== null && dec30Balance > 0" class="anchor-confirmed">
          <q-card flat bordered class="success-card">
            <q-card-section class="text-center">
              <q-icon name="check_circle" size="48px" color="positive" />
              <div class="text-h6 text-positive q-mt-sm">December Anchor Set!</div>
              <div class="text-caption text-grey-7">
                Your 50/30/20 budget is now anchored to December {{ decemberYear }}
              </div>

              <div class="anchor-summary q-mt-md">
                <div class="summary-row">
                  <span>Opening Balance (Dec 1):</span>
                  <span class="text-weight-bold">
                    {{ currencySymbol }} {{ (anchorResult?.dec1OpeningBalance ?? 0).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                  </span>
                </div>
                <div class="summary-row">
                  <span>Net SME Position:</span>
                  <span class="text-weight-bold" :class="(decemberSummary?.netPosition.netSMEPosition ?? 0) >= 0 ? 'text-positive' : 'text-negative'">
                    {{ currencySymbol }} {{ (decemberSummary?.netPosition.netSMEPosition ?? 0).toLocaleString('en-ZA') }}
                  </span>
                </div>
                <div class="summary-row">
                  <span>Auto-Categorized:</span>
                  <span class="text-weight-bold">{{ decemberSummary?.categorization.completionRate ?? 0 }}%</span>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Year Selection Dialog -->
      <q-dialog v-model="showYearDialog" persistent>
        <q-card style="min-width: 300px">
          <q-card-section>
            <div class="text-h6">Which year is your primary benchmark?</div>
            <div class="text-caption text-grey-7">
              Penny detected transactions from multiple years
            </div>
          </q-card-section>

          <q-card-section class="q-pt-none">
            <q-list>
              <q-item
                v-for="year in yearDetection?.detectedYears ?? []"
                :key="year"
                clickable
                @click="handleYearSelection(year)"
              >
                <q-item-section avatar>
                  <q-icon name="calendar_today" color="teal" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>December {{ year }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-icon name="chevron_right" />
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>
      </q-dialog>

      <!-- December Anchor Modal -->
      <DecemberAnchorModal
        v-if="anchorResult"
        v-model="showAnchorModal"
        :anchor-result="anchorResult"
        @confirm="handleAnchorConfirm"
        @cancel="() => {}"
      />

      <!-- Step 4: VAT/Tax Registration (Self-Employed Only) -->
      <div v-if="currentStep === 4 && !isSalaried" class="wizard-step">
        <div class="step-header">
          <q-icon name="receipt_long" size="32px" color="teal-8" />
          <div class="text-h6">Tax & VAT Status</div>
          <div class="text-caption text-grey-7">Help us track your tax obligations</div>
        </div>

        <div class="vat-options q-mt-lg">
          <q-card
            v-for="option in vatOptions"
            :key="option.value"
            flat
            bordered
            class="vat-option"
            :class="{ selected: form.vat_status === option.value }"
            @click="form.vat_status = option.value"
          >
            <q-card-section class="row items-center">
              <q-radio
                v-model="form.vat_status"
                :val="option.value"
                color="teal-8"
              />
              <div class="q-ml-sm">
                <div class="text-subtitle2">{{ option.label }}</div>
                <div class="text-caption text-grey-7">{{ option.description }}</div>
              </div>
            </q-card-section>
          </q-card>

          <q-banner v-if="form.vat_status === 'approaching'" class="q-mt-md bg-warning-1" rounded>
            <template v-slot:avatar>
              <q-icon name="info" color="warning" />
            </template>
            We'll track your rolling 12-month turnover and alert you when you approach R1M.
          </q-banner>
        </div>
      </div>

      <!-- Step 4 (or 3 for salaried): Budget Methodology -->
      <div v-if="currentStep === methodologyStep" class="wizard-step">
        <div class="step-header">
          <q-icon name="pie_chart" size="32px" color="teal-8" />
          <div class="text-h6">Choose Your Budget Method</div>
          <div class="text-caption text-grey-7">Select what works best for you</div>
        </div>

        <div class="method-options q-mt-lg">
          <q-card
            v-for="method in methodologyOptions"
            :key="method.value"
            flat
            bordered
            class="method-option"
            :class="{ selected: form.selected_methodology === method.value }"
            @click="form.selected_methodology = method.value"
          >
            <q-card-section>
              <div class="row items-center no-wrap">
                <q-avatar :color="form.selected_methodology === method.value ? 'teal-8' : 'grey-4'" text-color="white" size="40px">
                  <q-icon :name="method.icon" size="20px" />
                </q-avatar>
                <div class="q-ml-md col">
                  <div class="row items-center">
                    <div class="text-subtitle1 text-weight-medium">{{ method.label }}</div>
                    <q-badge
                      v-if="method.highlight"
                      :color="method.value === '50-30-20' ? 'teal' : 'amber-8'"
                      class="q-ml-sm"
                    >
                      {{ method.highlight }}
                    </q-badge>
                  </div>
                  <div class="text-caption text-grey-7">{{ method.description }}</div>
                </div>
                <q-icon
                  v-if="form.selected_methodology === method.value"
                  name="check_circle"
                  color="teal-8"
                  size="24px"
                />
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Navigation -->
      <div class="wizard-nav q-mt-xl">
        <q-btn
          v-if="currentStep > 1"
          flat
          color="grey-7"
          label="Back"
          icon="arrow_back"
          @click="prevStep"
        />
        <q-space />
        <q-btn
          v-if="currentStep < totalSteps"
          color="teal-8"
          :label="currentStep === 1 ? 'Get Started' : 'Continue'"
          icon-right="arrow_forward"
          :disable="!canProceed"
          @click="nextStep"
        />
        <q-btn
          v-else
          color="teal-8"
          label="Complete Setup"
          icon-right="check"
          :loading="isSubmitting"
          :disable="!canProceed"
          @click="completeOnboarding"
        />
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { useUserStore } from '@/stores/user.store';
import { useConfigStore } from '@/stores/config.store';
import { useAccountStore } from '@/stores/account.store';
import { apiClient } from '@/services/api/client';
import { extractOnboardingDocument, type DocumentExtractionResult } from '@/services/api/onboarding.api';
import { taxApi, type EffectiveRateResponse } from '@/services/api/tax.api';
import { SyncService } from '@/services/sync/SyncService';
import { UniversalStatementParser } from '@/services/parsers/UniversalStatementParser';
import {
  DecemberSMESummaryBuilder,
  formatSummaryForDisplay,
  type DecemberSummary,
} from '@/services/sme';
import {
  getDecemberAnchorBalance,
  detectYearFromTransactions,
  filterToMonth,
  validateDecemberAnchor,
  type DecemberAnchorResult,
  type YearDetectionResult,
} from '@/services/balancing/BalancingEngine';
import WizardPatternInterrogator, { type PatternAssignment } from '@/components/wizard/WizardPatternInterrogator.vue';
import DecemberAnchorModal from '@/components/wizard/DecemberAnchorModal.vue';
import type { IncomeType, BudgetMethodology, Transaction } from '@/types';

const router = useRouter();
const $q = useQuasar();
const userStore = useUserStore();
const configStore = useConfigStore();
const accountStore = useAccountStore();

// Load config on mount
onMounted(async () => {
  if (!configStore.isLoaded) {
    await configStore.loadConfig();
  }
});

// ===========================================
// December Statement Loop State (Universal Factory)
// ===========================================
const decemberStatementFile = ref<File | null>(null);
const isParsingStatement = ref(false);
const decemberTransactions = ref<Transaction[]>([]);
const yearDetection = ref<YearDetectionResult | null>(null);
const selectedAnchorYear = ref<number | null>(null);
const patternAssignments = ref<PatternAssignment[]>([]);
const isInterrogationComplete = ref(false);

// December SME Summary (the goal!)
const decemberSummary = ref<DecemberSummary | null>(null);
const summaryDisplay = ref<ReturnType<typeof formatSummaryForDisplay> | null>(null);

// December balance entry
const dec30Balance = ref<number | null>(null);
const parserOpeningBalance = ref<number | null>(null);
const anchorResult = ref<DecemberAnchorResult | null>(null);
const showAnchorModal = ref(false);

// Year selection dialog (shown when multiple years detected)
const showYearDialog = ref(false);

// Statement format detection
const detectedFormat = ref<string | null>(null);

// Interrogation state (for uncategorized items)
const showInterrogator = ref(false);

// Currency symbol from config (single source of truth)
const currencySymbol = computed(() => configStore.currencySymbol);

// ===========================================
// AI Document Extraction State
// ===========================================
const uploadedFile = ref<File | null>(null);
const isDragging = ref(false);
const isExtracting = ref(false);
const extractedData = ref<DocumentExtractionResult | null>(null);
const extractionError = ref<string | null>(null);

// ===========================================
// Task 1: Dynamic State Management
// ===========================================
type WizardMethodology = '50-30-20' | 'zero-based' | 'profit-first';

const form = ref({
  income_type: 'self_employed' as IncomeType,
  monthly_base_amount: null as number | null,
  vat_status: 'not_registered' as 'registered' | 'not_registered' | 'approaching',
  selected_methodology: '50-30-20' as WizardMethodology,
});

const currentStep = ref(1);
const isSubmitting = ref(false);

// ===========================================
// Task 2: The "Label Switcher"
// ===========================================
const labels = computed(() => {
  if (form.value.income_type === 'salaried') {
    return {
      amountTitle: 'What is your monthly take-home pay?',
      amountSubtitle: 'Your net salary after tax and deductions',
      amountLabel: 'Monthly Net Salary',
    };
  }
  return {
    amountTitle: 'What are your average monthly sales?',
    amountSubtitle: 'Your typical monthly revenue before expenses',
    amountLabel: 'Average Monthly Sales',
  };
});

// ===========================================
// Task 3: Step Logic - Updated for December Statement Loop
// ===========================================
const isSalaried = computed(() => form.value.income_type === 'salaried');

// Total steps: 5 for self-employed (includes December + VAT step), 4 for salaried
// Step 1: Income Type
// Step 2: Monthly Amount
// Step 3: December Statement (NEW)
// Step 4: VAT Status (self-employed only) OR Methodology (salaried)
// Step 5: Methodology (self-employed only)
const totalSteps = computed(() => isSalaried.value ? 4 : 5);

// VAT step only for self-employed
const vatStep = computed(() => 4);

// Methodology step number adjusts based on income type
const methodologyStep = computed(() => isSalaried.value ? 4 : 5);

const progressValue = computed(() => currentStep.value / totalSteps.value);

// December Statement computed helpers
const decemberYear = computed(() => {
  return selectedAnchorYear.value ?? yearDetection.value?.primaryYear ?? new Date().getFullYear();
});

const filteredDecemberTransactions = computed(() => {
  if (!decemberTransactions.value.length) return [];
  return filterToMonth(decemberTransactions.value, decemberYear.value, 12);
});

const decemberValidation = computed(() => {
  return validateDecemberAnchor(filteredDecemberTransactions.value);
});

// Check if we can proceed with December step
const canProceedDecember = computed(() => {
  // Must have parsed transactions AND completed interrogation AND entered balance
  return isInterrogationComplete.value && dec30Balance.value !== null && dec30Balance.value > 0;
});

// Income type options
const incomeTypeOptions = [
  {
    value: 'self_employed',
    label: 'Self-Employed / Freelancer',
    description: 'I invoice clients and manage my own tax',
    icon: 'business_center',
  },
  {
    value: 'salaried',
    label: 'Salaried Employee',
    description: 'I receive a regular paycheck with tax deducted',
    icon: 'badge',
  },
];

// VAT status options - computed to use dynamic threshold from config
const vatOptions = computed(() => {
  const threshold = configStore.vatThreshold;
  const formattedThreshold = configStore.formatCurrency(threshold);

  return [
    {
      value: 'registered',
      label: 'VAT Registered',
      description: `Already registered for ${configStore.config?.vat.name ?? 'VAT'}`,
    },
    {
      value: 'approaching',
      label: 'Approaching Threshold',
      description: `Turnover nearing ${formattedThreshold} per year`,
    },
    {
      value: 'not_registered',
      label: 'Not Required',
      description: `Below ${configStore.config?.vat.name ?? 'VAT'} threshold`,
    },
  ];
});

// Budget methodology options
const methodologyOptions = [
  {
    value: '50-30-20',
    label: '50/30/20 Rule',
    description: '50% needs, 30% wants, 20% savings',
    icon: 'pie_chart',
    highlight: 'Most Popular',
  },
  {
    value: 'zero-based',
    label: 'Zero-Based Budgeting',
    description: 'Every rand has a job - allocate until R0 remains',
    icon: 'assignment',
    highlight: null,
  },
  {
    value: 'profit-first',
    label: 'Profit First',
    description: 'Take profit first, then pay expenses from the rest',
    icon: 'trending_up',
    highlight: 'For Entrepreneurs',
  },
];

// ===========================================
// Tax Calculation - USES BACKEND API (Single Source of Truth)
// No "quick math" in frontend - all calculations from TaxService
// ===========================================

// Tax preview from backend API
const taxPreview = ref<EffectiveRateResponse | null>(null);
const isLoadingTax = ref(false);
let taxDebounceTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * Fetch tax calculation from backend API.
 * This ensures Wizard and Dashboard use identical calculations.
 *
 * Benchmark: R74,900 + self_employed → 39.1% → R29,286.90 set-aside
 */
async function fetchTaxPreview() {
  const amount = form.value.monthly_base_amount;
  if (!amount || amount <= 0) {
    taxPreview.value = null;
    return;
  }

  isLoadingTax.value = true;

  try {
    const result = await taxApi.getEffectiveRate({
      amount,
      persona: form.value.income_type,
    });
    taxPreview.value = result;
  } catch (error) {
    console.error('Failed to fetch tax preview:', error);
    // Fallback: clear preview on error
    taxPreview.value = null;
  } finally {
    isLoadingTax.value = false;
  }
}

/**
 * Debounced tax fetch - avoids excessive API calls while typing
 */
function debouncedFetchTax() {
  if (taxDebounceTimer) {
    clearTimeout(taxDebounceTimer);
  }
  taxDebounceTimer = setTimeout(() => {
    fetchTaxPreview();
  }, 300);
}

// Watch for amount or income type changes to update tax preview
watch(
  () => [form.value.monthly_base_amount, form.value.income_type],
  () => {
    debouncedFetchTax();
  },
  { immediate: false }
);

// Computed properties that read from backend response
const taxRate = computed(() => {
  if (!taxPreview.value) return 0;
  return parseFloat(taxPreview.value.effective_rate) || 0;
});

const taxSetAside = computed(() => {
  if (!taxPreview.value) return 0;
  return parseFloat(taxPreview.value.tax_set_aside) || 0;
});

const netAmount = computed(() => {
  if (!taxPreview.value) {
    return form.value.monthly_base_amount || 0;
  }
  return parseFloat(taxPreview.value.net_after_tax) || 0;
});

// Budget preview from backend - ensures exact same calculation as Dashboard
const budgetPreview = computed(() => {
  if (!taxPreview.value?.budget_preview) {
    const base = form.value.monthly_base_amount || 0;
    return {
      needs: Math.round(base * 0.5),
      wants: Math.round(base * 0.3),
      savings: Math.round(base * 0.2),
    };
  }
  return {
    needs: parseFloat(taxPreview.value.budget_preview.needs) || 0,
    wants: parseFloat(taxPreview.value.budget_preview.wants) || 0,
    savings: parseFloat(taxPreview.value.budget_preview.savings) || 0,
  };
});

// Validation - Updated for December Statement Loop
const canProceed = computed(() => {
  switch (currentStep.value) {
    case 1:
      return !!form.value.income_type;
    case 2:
      return (form.value.monthly_base_amount ?? 0) > 0;
    case 3:
      // December Statement step - need completed interrogation + balance
      return canProceedDecember.value;
    case 4:
      // For salaried: this is methodology step
      // For self-employed: this is VAT step
      if (isSalaried.value) {
        return !!form.value.selected_methodology;
      }
      return !!form.value.vat_status;
    case 5:
      // Self-employed methodology step
      return !!form.value.selected_methodology;
    default:
      return true;
  }
});

// Navigation
function nextStep() {
  if (currentStep.value < totalSteps.value) {
    currentStep.value++;
  }
}

function prevStep() {
  if (currentStep.value > 1) {
    currentStep.value--;
  }
}

// ===========================================
// December Statement Loop Handlers
// ===========================================

/**
 * Universal Statement Upload Handler
 * Handles ALL formats: CSV, OFX, OFC, QIF, XLSX
 * Auto-detects format and builds December SME Summary
 */
async function handleStatementUpload(file: File | null) {
  if (!file) return;

  isParsingStatement.value = true;
  decemberTransactions.value = [];
  yearDetection.value = null;
  isInterrogationComplete.value = false;
  patternAssignments.value = [];
  decemberSummary.value = null;
  summaryDisplay.value = null;

  try {
    // Step 1: Detect format (format-blind!)
    detectedFormat.value = UniversalStatementParser.detectFormat(file);

    $q.notify({
      type: 'info',
      message: `Detected ${detectedFormat.value.toUpperCase()} format. Parsing...`,
      position: 'bottom',
      timeout: 1500,
    });

    // Step 2: Parse with Universal Factory
    const parseResult = await UniversalStatementParser.parse(file);

    if (!parseResult.transactions.length) {
      $q.notify({
        type: 'warning',
        message: 'No transactions found in file.',
        position: 'bottom',
      });
      isParsingStatement.value = false;
      return;
    }

    // Convert to Transaction type (BalancingEngine expects snake_case)
    decemberTransactions.value = parseResult.transactions.map(tx => ({
      id: tx.bankReference || `${tx.transactionDate}-${tx.amount}-${Math.random()}`,
      transaction_date: tx.transactionDate,  // snake_case for BalancingEngine
      transactionDate: tx.transactionDate,   // camelCase for display
      description: tx.description,
      amount: tx.amount,
      balance: tx.balanceAfter ?? 0,
      categoryId: null,
      budgetBucket: null,
      syncedAt: null,
    })) as Transaction[];

    // Step 3: SAVE WITH SYNC SERVICE (Server = Truth, IndexedDB = Cache)
    try {
      const syncResult = await SyncService.saveTransactions(parseResult.transactions);

      if (syncResult.savedTo === 'both') {
        $q.notify({
          type: 'positive',
          message: `✅ Saved ${syncResult.serverCount} transactions to server`,
          position: 'bottom',
          timeout: 2000,
        });
      } else if (syncResult.savedTo === 'local') {
        $q.notify({
          type: 'info',
          message: `📱 Saved ${syncResult.localCount} transactions locally (will sync when online)`,
          caption: syncResult.pendingSync > 0 ? `${syncResult.pendingSync} pending sync` : undefined,
          position: 'bottom',
          timeout: 3000,
        });
      }

      console.log(`Sync result: ${syncResult.savedTo} - Server: ${syncResult.serverCount}, Local: ${syncResult.localCount}, Pending: ${syncResult.pendingSync}`);
    } catch (syncError) {
      console.error('Sync failed:', syncError);
      $q.notify({
        type: 'negative',
        message: 'Failed to save transactions',
        position: 'bottom',
      });
    }

    // Step 4: Auto-detect year from transactions
    yearDetection.value = detectYearFromTransactions(decemberTransactions.value);

    // Auto-populate balances from parser if available
    console.log('Parser result balances - Opening:', parseResult.openingBalance, 'Closing:', parseResult.ledgerBalance);

    if (parseResult.ledgerBalance !== null && parseResult.ledgerBalance > 0) {
      dec30Balance.value = parseResult.ledgerBalance;
      console.log('Auto-detected closing balance (dec30Balance):', parseResult.ledgerBalance);
    }
    if (parseResult.openingBalance !== null) {
      parserOpeningBalance.value = parseResult.openingBalance;
      console.log('Auto-detected opening balance (parserOpeningBalance):', parseResult.openingBalance);
    }

    // If multiple years detected, ask which one to use
    if (yearDetection.value.hasMultipleYears) {
      showYearDialog.value = true;
    } else {
      selectedAnchorYear.value = yearDetection.value.primaryYear;
      // Auto-build summary after year is determined
      await buildDecemberSMESummary(parseResult);
    }

    const validation = validateDecemberAnchor(filteredDecemberTransactions.value);

    $q.notify({
      type: validation.isValid ? 'positive' : 'info',
      message: `Parsed ${parseResult.transactions.length} transactions from ${detectedFormat.value.toUpperCase()}. ${validation.message}`,
      position: 'bottom',
      timeout: 3000,
    });

  } catch (error) {
    console.error('Statement parsing error:', error);
    $q.notify({
      type: 'negative',
      message: `Failed to parse ${detectedFormat.value || 'statement'} file. Please check the format.`,
      position: 'bottom',
    });
  } finally {
    isParsingStatement.value = false;
  }
}

/**
 * Build December SME Summary using the Universal Factory
 * This is the GOAL: Net SME Income minus Solo Survival Costs
 */
async function buildDecemberSMESummary(parseResult: Awaited<ReturnType<typeof UniversalStatementParser.parse>>) {
  try {
    const builder = new DecemberSMESummaryBuilder({
      year: selectedAnchorYear.value || decemberYear.value,
      month: 12,
      taxRate: 0.391, // SME tax rate
    });

    // Build summary from parsed data
    decemberSummary.value = builder.buildFromParsedData(parseResult);

    // Format for display
    summaryDisplay.value = formatSummaryForDisplay(decemberSummary.value);

    // Check auto-categorization rate
    const catRate = decemberSummary.value.categorization.completionRate;
    if (catRate >= 80) {
      $q.notify({
        type: 'positive',
        message: `Penny auto-categorized ${catRate}% of your transactions!`,
        position: 'bottom',
        timeout: 2500,
      });
    }

    // If 100% auto-categorized and balance available, auto-complete and calculate anchor
    if (catRate === 100 && decemberSummary.value.categorization.needsInterrogation === 0) {
      isInterrogationComplete.value = true;
      if (dec30Balance.value && dec30Balance.value > 0) {
        console.log('100% auto-categorized - auto-calculating anchor');
        calculateAnchor();
      }
    }

  } catch (error) {
    console.error('Failed to build December summary:', error);
  }
}

function handleYearSelection(year: number) {
  selectedAnchorYear.value = year;
  showYearDialog.value = false;
}

function handlePatternComplete(assignments: PatternAssignment[]) {
  patternAssignments.value = assignments;
  isInterrogationComplete.value = true;
  showInterrogator.value = false;

  $q.notify({
    type: 'positive',
    message: `${assignments.length} patterns assigned to budget buckets.`,
    position: 'bottom',
    timeout: 2000,
  });

  // Auto-calculate anchor if balance was auto-detected from parser
  if (dec30Balance.value && dec30Balance.value > 0) {
    console.log('Auto-calculating anchor with parser-detected balance:', dec30Balance.value);
    calculateAnchor();
  }
}

function handlePatternProgress(current: number, total: number) {
  // Could update a local progress indicator if needed
  console.log(`Pattern progress: ${current}/${total}`);
}

/**
 * Start interrogation for uncategorized items
 */
function startInterrogation() {
  showInterrogator.value = true;
}

/**
 * Complete interrogation (when no uncategorized items)
 */
function completeInterrogation() {
  isInterrogationComplete.value = true;
  showInterrogator.value = false;

  // Auto-calculate anchor if balance was auto-detected from parser
  if (dec30Balance.value && dec30Balance.value > 0) {
    console.log('Auto-calculating anchor with parser-detected balance:', dec30Balance.value);
    calculateAnchor();
  }
}

function calculateAnchor() {
  if (!dec30Balance.value || dec30Balance.value <= 0) {
    $q.notify({
      type: 'warning',
      message: 'Please enter your December 30th bank balance.',
      position: 'bottom',
    });
    return;
  }

  // Calculate the anchor using BalancingEngine with PAY CYCLE logic
  // Pay cycle starts on the 25th (when income arrives)
  // "December budget" = Nov 25 to Dec 31 (includes Nov income for tax calculation)
  const payCycleStartDay = 25; // TODO: Make this configurable per user

  anchorResult.value = getDecemberAnchorBalance(
    dec30Balance.value,
    decemberTransactions.value,
    decemberYear.value,
    payCycleStartDay
  );

  console.log('Anchor Result:', anchorResult.value);

  // Show the confirmation modal
  showAnchorModal.value = true;
}

async function handleAnchorConfirm(openingBalance: number, year: number) {
  // Save to account store (will persist on wizard completion)
  await accountStore.setDecemberAnchor(openingBalance, year);

  $q.notify({
    type: 'positive',
    message: `December ${year} anchor locked at ${currencySymbol.value} ${openingBalance.toLocaleString('en-ZA', { minimumFractionDigits: 2 })}`,
    position: 'bottom',
    timeout: 3000,
  });
}

function skipDecemberStatement() {
  // Allow skipping - user can set up manually later
  isInterrogationComplete.value = true;
  dec30Balance.value = 0; // Mark as skipped

  $q.notify({
    type: 'info',
    message: 'December setup skipped. You can configure this later in Settings.',
    position: 'bottom',
  });
}

// ===========================================
// AI Document Extraction Handlers
// ===========================================

async function handleFileUpload(file: File | null) {
  if (!file) return;

  isExtracting.value = true;
  extractionError.value = null;
  extractedData.value = null;

  try {
    const result = await extractOnboardingDocument(file);

    if (result.success && result.data) {
      extractedData.value = result.data;

      // Map extracted data to form
      // For payslips: Use net pay directly (tax already deducted)
      // For invoices: Use gross amount (tax will be calculated in preview)
      form.value.monthly_base_amount = result.data.monthly_base_amount;

      // Auto-switch income type based on document type
      if (result.data.suggested_income_type) {
        form.value.income_type = result.data.suggested_income_type;
      }

      // IMMEDIATELY fetch tax preview from backend using the 39.1% logic
      // This ensures the Net After Tax and 50/30/20 preview update instantly
      await fetchTaxPreview();

      $q.notify({
        type: 'positive',
        message: result.data.is_payslip
          ? 'Payslip scanned! Net pay mapped to your budget.'
          : 'Invoice scanned! Amount mapped with 39.1% tax set-aside.',
        position: 'bottom',
        timeout: 3000,
      });
    } else {
      extractionError.value = result.error || 'Failed to extract document';
      $q.notify({
        type: 'warning',
        message: result.error || 'Could not read document. Please enter manually.',
        position: 'bottom',
      });
    }
  } catch (error) {
    console.error('Extraction error:', error);
    extractionError.value = 'Failed to process document';
    $q.notify({
      type: 'negative',
      message: 'Failed to process document. Please try again or enter manually.',
      position: 'bottom',
    });
  } finally {
    isExtracting.value = false;
  }
}

function clearExtraction() {
  uploadedFile.value = null;
  extractedData.value = null;
  extractionError.value = null;
  // Don't clear the amount - user might want to keep it
}

// Format currency
function formatAmount(amount: number): string {
  return amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

// ===========================================
// Task 4: Backend Handshake
// ===========================================
async function completeOnboarding() {
  isSubmitting.value = true;

  try {
    const payload = {
      income_type: form.value.income_type,
      monthly_base_amount: form.value.monthly_base_amount,
      vat_status: isSalaried.value ? null : form.value.vat_status,
      selected_methodology: form.value.selected_methodology,
    };

    await apiClient.post('/onboarding/complete', payload);

    // Refresh user data to get updated persona
    await userStore.fetchUser();

    $q.notify({
      type: 'positive',
      message: 'Welcome to PennyPilot! Your budget is ready.',
      position: 'bottom',
    });

    router.push('/');
  } catch (error) {
    console.error('Onboarding error:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to complete setup. Please try again.',
      position: 'bottom',
    });
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<style scoped>
.onboarding-page {
  background: linear-gradient(180deg, #E0F2F1 0%, #FFFFFF 100%);
  min-height: 100vh;
  padding: 24px 16px;
}

.wizard-container {
  max-width: 480px;
  margin: 0 auto;
}

.step-header {
  text-align: center;
}

.step-header .q-icon {
  margin-bottom: 12px;
}

/* Income Type Options */
.income-option {
  border-radius: 12px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.income-option:hover {
  border-color: #00796B;
}

.income-option.selected {
  border-color: #004D40;
  background: #E0F2F1;
}

/* Amount Input */
.amount-field {
  font-size: 18px;
}

.amount-field :deep(.q-field__native) {
  font-size: 24px;
  font-weight: 600;
}

/* Budget Preview */
.budget-preview {
  background: #FAFAFA;
  border-radius: 12px;
  padding: 16px;
}

.budget-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #ECEFF1;
}

.budget-row:last-child {
  border-bottom: none;
}

/* VAT Options */
.vat-option {
  border-radius: 12px;
  margin-bottom: 8px;
  cursor: pointer;
}

.vat-option.selected {
  border-color: #004D40;
  background: #E0F2F1;
}

/* Method Options */
.method-option {
  border-radius: 12px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.method-option:hover {
  border-color: #00796B;
}

.method-option.selected {
  border-color: #004D40;
  background: #E0F2F1;
}

/* Navigation */
.wizard-nav {
  display: flex;
  align-items: center;
  padding-bottom: 24px;
}

/* Warning banner */
.bg-warning-1 {
  background: #FFF8E1;
}

/* Tax Row Styling - Purple to indicate "untouchable" money */
.tax-row {
  background: linear-gradient(90deg, #EDE7F6 0%, #F3E5F5 100%);
  border-radius: 8px;
  padding: 10px 12px !important;
  margin: 0 -8px 8px;
}

.tax-row .budget-label {
  color: #6A1B9A;
  font-weight: 600;
  display: flex;
  align-items: center;
}

.tax-row .budget-value {
  color: #7B1FA2;
  font-weight: 700;
}

/* Net Row Styling */
.net-row {
  background: #FAFAFA;
  border-radius: 8px;
  padding: 8px 12px !important;
  margin: 0 -8px 4px;
}

.net-row .budget-label {
  color: #37474F;
}

.net-row .budget-value {
  color: #004D40;
}

/* Tax disclaimer */
.tax-disclaimer {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: #7E57C2;
  background: #EDE7F6;
  padding: 8px 12px;
  border-radius: 8px;
  margin: 0 -8px;
}

/* Upload Zone */
.upload-zone {
  border-radius: 12px;
  border: 2px dashed #B2DFDB;
  background: #FAFAFA;
  transition: all 0.2s ease;
}

.upload-zone:hover {
  border-color: #00897B;
  background: #E0F2F1;
}

.upload-zone-active {
  border-color: #004D40;
  background: #E0F2F1;
  border-style: solid;
}

.upload-zone-success {
  border-color: #00897B;
  border-style: solid;
  background: #E8F5E9;
}

.upload-input {
  max-width: 280px;
  margin: 0 auto;
}

.upload-input :deep(.q-field__control) {
  background: white;
}

/* OR Divider */
.or-divider {
  display: flex;
  align-items: center;
  text-align: center;
}

.or-divider::before,
.or-divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid #ECEFF1;
}

.or-divider span {
  padding: 0 16px;
  font-size: 12px;
  color: #90A4AE;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Extraction Result */
.extraction-result {
  padding: 16px;
}

.result-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.extracted-amount {
  text-align: center;
  padding: 16px;
  background: #E0F2F1;
  border-radius: 8px;
}

/* December Statement Step Styles */
.balance-card {
  border-radius: 16px;
  border-color: #B2DFDB;
}

.balance-input :deep(.q-field__native) {
  font-size: 24px;
  font-weight: 600;
}

.success-card {
  border-radius: 16px;
  border-color: #81C784;
  background: #E8F5E9;
}

.anchor-summary {
  background: white;
  border-radius: 12px;
  padding: 16px;
  margin-top: 16px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #E0E0E0;
}

.summary-row:last-child {
  border-bottom: none;
}

/* SME Summary Styles */
.sme-summary .summary-card {
  border-radius: 16px;
  border-color: #B2DFDB;
}

.sme-summary .summary-section {
  margin-bottom: 8px;
}

.sme-summary .section-header {
  font-weight: 600;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.sme-summary .summary-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
  border-bottom: 1px solid #ECEFF1;
}

.sme-summary .summary-item:last-child {
  border-bottom: none;
}

.sme-summary .summary-item span:last-child {
  margin-left: auto;
}

.sme-summary .highlight-row {
  background: linear-gradient(90deg, #EDE7F6 0%, #F3E5F5 100%);
  border-radius: 8px;
  padding: 10px 12px !important;
  margin: 4px -8px;
}

.sme-summary .net-row {
  background: #E0F2F1;
  border-radius: 8px;
  padding: 12px !important;
  margin: 8px -8px 0;
}

.sme-summary .ghost-row {
  background: linear-gradient(90deg, #FFF8E1 0%, #FFFDE7 100%);
  border-radius: 8px;
  padding: 8px 12px !important;
  margin: 2px -8px;
  border: 1px dashed #FFB300;
  opacity: 0.85;
}

.sme-summary .survival-total {
  background: #FFF3E0;
  border-radius: 8px;
  padding: 10px 12px !important;
  margin: 4px -8px 0;
}

.sme-summary .net-position {
  text-align: center;
  padding: 16px;
  border-radius: 12px;
}

.sme-summary .net-position.positive {
  background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
  color: #2E7D32;
}

.sme-summary .net-position.negative {
  background: linear-gradient(135deg, #FFEBEE 0%, #FFCDD2 100%);
  color: #C62828;
}

.sme-summary .categorization-status {
  padding: 12px;
  background: #FAFAFA;
  border-radius: 8px;
}
</style>
