<template>
  <q-page class="travel-expenses-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-top">
        <q-btn flat icon="arrow_back" to="/" dense color="white" />
        <h1 class="page-title">Travel Expenses</h1>
        <q-btn flat icon="print" dense color="white" @click="printPage" />
      </div>
    </div>

    <!-- Content -->
    <div class="q-pa-md">
      <!-- Top Action Buttons -->
      <div class="row q-col-gutter-sm q-mb-md">
        <div class="col-7">
          <q-btn
            color="primary"
            icon="add"
            label="Add Vehicle"
            class="full-width"
            no-caps
            unelevated
            style="border-radius: 12px; min-height: 48px"
            @click="showAddVehicle = true"
          />
        </div>
        <div class="col-5">
          <q-btn
            outline
            color="primary"
            icon="document_scanner"
            label="From Doc"
            class="full-width"
            no-caps
            style="border-radius: 12px; min-height: 48px"
            @click="showImportVehicle = true"
          />
        </div>
      </div>

      <!-- Unassigned Trips Banner -->
      <q-banner
        v-if="unassignedTripCount > 0"
        class="bg-warning text-white q-mb-md"
        rounded
        dense
      >
        <template v-slot:avatar>
          <q-icon name="warning" />
        </template>
        {{ unassignedTripCount }} trips have no vehicle assigned
        <template v-slot:action>
          <q-btn
            flat
            label="Bulk Assign"
            no-caps
            dense
            @click="showBulkAssign = true"
          />
        </template>
      </q-banner>

      <!-- Loading -->
      <div v-if="vehiclesLoading" class="loading-container">
        <q-spinner-dots color="primary" size="40px" />
      </div>

      <!-- Empty State -->
      <div v-else-if="vehicles.length === 0" class="text-center q-pa-xl text-grey-6">
        <q-icon name="directions_car" size="48px" class="q-mb-md" />
        <div class="text-subtitle1">No vehicles configured</div>
        <div class="text-caption q-mb-md">Add your vehicle to start tracking travel expenses</div>
      </div>

      <!-- Vehicle Cards -->
      <template v-else>
        <q-card
          v-for="vehicle in vehicles"
          :key="vehicle.id"
          flat
          bordered
          class="vehicle-card q-mb-md"
        >
          <q-card-section class="q-pa-md">
            <!-- Vehicle Header -->
            <div class="row items-center q-mb-sm">
              <q-icon
                :name="vehicle.type === 'owned' ? 'directions_car' : 'car_rental'"
                :color="vehicle.type === 'owned' ? 'teal' : 'orange'"
                size="sm"
                class="q-mr-sm"
              />
              <div class="col">
                <div class="text-subtitle1 text-weight-bold" style="color: #004D40">
                  {{ vehicle.name }}
                  <q-badge v-if="vehicle.is_default" color="teal" label="Default" class="q-ml-sm" />
                </div>
                <div class="text-caption text-grey">
                  {{ vehicle.type === 'owned' ? 'Owned' : 'Rental' }}
                  <template v-if="vehicle.make"> &middot; {{ vehicle.year }} {{ vehicle.make }} {{ vehicle.model }}</template>
                  <template v-if="vehicle.registration"> &middot; {{ vehicle.registration }}</template>
                </div>
              </div>
              <q-btn flat round icon="more_vert" size="sm">
                <q-menu>
                  <q-list dense style="min-width: 160px">
                    <q-item clickable v-close-popup @click="editVehicle(vehicle)">
                      <q-item-section avatar><q-icon name="edit" size="sm" /></q-item-section>
                      <q-item-section>Edit</q-item-section>
                    </q-item>
                    <q-item v-if="!vehicle.is_default" clickable v-close-popup @click="setDefault(vehicle.id)">
                      <q-item-section avatar><q-icon name="star" size="sm" /></q-item-section>
                      <q-item-section>Set Default</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="syncVehicleKm(vehicle.id)">
                      <q-item-section avatar><q-icon name="sync" size="sm" /></q-item-section>
                      <q-item-section>Sync KM</q-item-section>
                    </q-item>
                    <q-separator />
                    <q-item clickable v-close-popup @click="deleteVehicle(vehicle.id)" class="text-negative">
                      <q-item-section avatar><q-icon name="delete" size="sm" color="negative" /></q-item-section>
                      <q-item-section>Remove</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>

            <!-- Stats Grid -->
            <div class="row q-col-gutter-sm text-center q-mt-sm">
              <div class="col-4">
                <div class="text-caption text-grey">Trips</div>
                <div class="text-body2 text-weight-bold">{{ vehicle.trips_count }}</div>
              </div>
              <div class="col-4">
                <div class="text-caption text-grey">Total KM</div>
                <div class="text-body2 text-weight-bold">{{ formatKm(vehicle.total_km_year) }}</div>
              </div>
              <div class="col-4">
                <div class="text-caption text-grey">Business %</div>
                <div class="text-body2 text-weight-bold">{{ vehicle.business_use_percent.toFixed(1) }}%</div>
              </div>
            </div>

            <!-- SARS Deduction (owned) or Rental Total -->
            <div v-if="vehicle.type === 'owned' && vehicle.calculation" class="sars-bar q-mt-sm">
              <div v-if="vehicle.calculation.days_in_use < 365" class="row justify-between q-mb-xs">
                <span class="text-caption text-grey">Fixed Cost ({{ vehicle.calculation.days_in_use }}/365 days)</span>
                <span class="text-caption">R{{ formatAmount(vehicle.calculation.fixed_cost_prorated) }}</span>
              </div>
              <div class="row justify-between q-mb-xs">
                <span class="text-caption text-grey">Deemed Cost</span>
                <span class="text-caption">R{{ formatAmount(vehicle.calculation.deemed_cost) }}</span>
              </div>
              <div class="row justify-between">
                <span class="text-caption text-grey">SARS Deduction ({{ vehicle.calculation.business_percent.toFixed(1) }}%)</span>
                <span class="text-body2 text-weight-bold text-positive">R{{ formatAmount(vehicle.calculation.annual_deduction) }}</span>
              </div>
            </div>
            <div v-if="vehicle.type === 'rental' && vehicle.rental_total" class="sars-bar q-mt-sm">
              <div class="row justify-between">
                <span class="text-caption text-grey">Rental Total</span>
                <span class="text-body2 text-weight-bold text-orange">R{{ formatAmount(vehicle.rental_total) }}</span>
              </div>
            </div>

            <!-- Expenses Total -->
            <div v-if="vehicle.expenses_sum_amount > 0" class="sars-bar q-mt-xs">
              <div class="row justify-between">
                <span class="text-caption text-grey">Total Expenses</span>
                <span class="text-body2 text-weight-bold">R{{ formatAmount(vehicle.expenses_sum_amount) }}</span>
              </div>
            </div>

            <!-- Action Bar -->
            <div class="row q-col-gutter-xs q-mt-md">
              <div class="col">
                <q-btn
                  :color="expandedSections[vehicle.id]?.trips ? 'primary' : 'grey-7'"
                  :outline="!expandedSections[vehicle.id]?.trips"
                  :flat="expandedSections[vehicle.id]?.trips"
                  icon="route"
                  label="Trips"
                  no-caps
                  dense
                  class="full-width"
                  style="border-radius: 8px; min-height: 40px"
                  @click="toggleSection(vehicle.id, 'trips')"
                />
              </div>
              <div class="col">
                <q-btn
                  :color="expandedSections[vehicle.id]?.expenses ? 'primary' : 'grey-7'"
                  :outline="!expandedSections[vehicle.id]?.expenses"
                  :flat="expandedSections[vehicle.id]?.expenses"
                  icon="receipt_long"
                  label="Expenses"
                  no-caps
                  dense
                  class="full-width"
                  style="border-radius: 8px; min-height: 40px"
                  @click="toggleSection(vehicle.id, 'expenses')"
                />
              </div>
              <div class="col-auto">
                <q-btn
                  outline
                  color="primary"
                  icon="add"
                  dense
                  style="border-radius: 8px; min-height: 40px; min-width: 40px"
                  @click="openAddExpense(vehicle.id)"
                  title="Add Expense"
                />
              </div>
              <div class="col-auto">
                <q-btn
                  outline
                  color="primary"
                  icon="camera_alt"
                  dense
                  style="border-radius: 8px; min-height: 40px; min-width: 40px"
                  @click="openScanReceipt(vehicle.id)"
                  title="Scan Receipt"
                />
              </div>
            </div>

            <!-- Trips Section (collapsible) -->
            <q-slide-transition>
              <div v-if="expandedSections[vehicle.id]?.trips" class="q-mt-md">
                <q-separator class="q-mb-sm" />
                <div class="text-subtitle2 text-weight-bold q-mb-sm" style="color: #004D40">
                  Trips
                </div>

                <!-- Category Toggle -->
                <q-btn-toggle
                  :model-value="vehicleTripCategory[vehicle.id] || 'Business'"
                  toggle-color="primary"
                  :options="[
                    { label: 'Business', value: 'Business' },
                    { label: 'Private', value: 'Private' },
                  ]"
                  class="q-mb-sm full-width"
                  spread
                  no-caps
                  unelevated
                  dense
                  @update:model-value="(val: 'Business' | 'Private') => switchVehicleTripCategory(vehicle.id, val)"
                />

                <!-- Trip Loading -->
                <div v-if="vehicleTripsLoading[vehicle.id]" class="text-center q-pa-md">
                  <q-spinner-dots color="primary" size="30px" />
                </div>

                <template v-else-if="vehicleTrips[vehicle.id]">
                  <!-- Trip Summary -->
                  <q-card flat bordered class="summary-card q-mb-sm">
                    <div class="summary-row">
                      <div class="summary-item">
                        <span class="summary-label">Trips</span>
                        <span class="summary-value" style="font-size: 16px">{{ vehicleTrips[vehicle.id].total_trips }}</span>
                      </div>
                      <div class="summary-item">
                        <span class="summary-label">Distance</span>
                        <span class="summary-value" style="font-size: 16px">{{ formatKm(vehicleTrips[vehicle.id].total_km) }} km</span>
                      </div>
                      <div v-if="(vehicleTripCategory[vehicle.id] || 'Business') === 'Business'" class="summary-item highlight">
                        <span class="summary-label">Deductible</span>
                        <span class="summary-value text-positive" style="font-size: 16px">R{{ formatAmount(vehicleTrips[vehicle.id].tax_deductible) }}</span>
                      </div>
                    </div>
                  </q-card>

                  <!-- Trips Table -->
                  <q-card v-if="vehicleTrips[vehicle.id].trips.length > 0" flat bordered class="trips-table-card">
                    <q-table
                      :rows="computeTripsWithOdo(vehicleTrips[vehicle.id].trips)"
                      :columns="tripColumns"
                      row-key="id"
                      flat
                      dense
                      :pagination="{ rowsPerPage: 0 }"
                      hide-pagination
                      class="trips-table"
                    >
                      <template v-slot:body-cell-date="props">
                        <q-td :props="props">
                          {{ formatDate(props.row.date) }}
                        </q-td>
                      </template>
                      <template v-slot:body-cell-time="props">
                        <q-td :props="props">
                          {{ props.row.start_time || '-' }}
                        </q-td>
                      </template>
                      <template v-slot:body-cell-distance_km="props">
                        <q-td :props="props" class="text-right text-weight-medium">
                          {{ props.row.distance_km.toFixed(1) }}
                        </q-td>
                      </template>
                      <template v-slot:body-cell-odo_reading="props">
                        <q-td :props="props" class="text-right">
                          <template v-if="props.row.odometer_end">
                            <span class="text-weight-medium">{{ props.row.odometer_end.toLocaleString() }}</span>
                          </template>
                          <template v-else>
                            <span class="text-grey-6">{{ props.row._running_odo.toLocaleString('en-ZA', { maximumFractionDigits: 0 }) }}</span>
                          </template>
                        </q-td>
                      </template>
                    </q-table>

                    <div class="table-footer">
                      <div class="footer-label">TOTAL</div>
                      <div class="footer-value">{{ formatKm(vehicleTrips[vehicle.id].total_km) }} km</div>
                    </div>
                  </q-card>

                  <div v-else class="text-center q-pa-md text-grey-6">
                    <div class="text-caption">No {{ (vehicleTripCategory[vehicle.id] || 'Business').toLowerCase() }} trips recorded</div>
                  </div>
                </template>
              </div>
            </q-slide-transition>

            <!-- Expenses Section (collapsible) -->
            <q-slide-transition>
              <div v-if="expandedSections[vehicle.id]?.expenses" class="q-mt-md">
                <q-separator class="q-mb-sm" />
                <div class="text-subtitle2 text-weight-bold q-mb-sm" style="color: #004D40">
                  Expenses
                </div>

                <!-- Expense Loading -->
                <div v-if="vehicleExpensesLoading[vehicle.id]" class="text-center q-pa-md">
                  <q-spinner-dots color="primary" size="30px" />
                </div>

                <template v-else>
                  <!-- Summary -->
                  <q-card v-if="vehicleExpenseSummaries[vehicle.id]" flat bordered class="summary-card q-mb-sm">
                    <div class="summary-row">
                      <div class="summary-item">
                        <span class="summary-label">Total Expenses</span>
                        <span class="summary-value" style="font-size: 16px">R{{ formatAmount(vehicleExpenseSummaries[vehicle.id]!.grand_total) }}</span>
                      </div>
                    </div>
                  </q-card>

                  <!-- Expense List -->
                  <div v-if="(vehicleExpensesList[vehicle.id] || []).length === 0" class="text-center q-pa-md text-grey-6">
                    <div class="text-caption">No expenses recorded</div>
                  </div>

                  <q-card
                    v-for="expense in (vehicleExpensesList[vehicle.id] || [])"
                    :key="expense.id"
                    flat
                    bordered
                    class="expense-card q-mb-sm"
                  >
                    <q-card-section class="q-pa-sm row items-center">
                      <q-icon
                        :name="expenseCategoryIcon(expense.category)"
                        :color="expenseCategoryColor(expense.category)"
                        size="sm"
                        class="q-mr-sm"
                      />
                      <div class="col">
                        <div class="row items-center">
                          <span class="text-body2 text-weight-medium">{{ expense.category }}</span>
                          <q-badge
                            v-if="expense.source === 'ai_extracted'"
                            color="purple"
                            label="AI"
                            class="q-ml-xs"
                            dense
                          />
                        </div>
                        <div class="text-caption text-grey">
                          {{ formatDate(expense.date) }}
                          <template v-if="expense.vendor"> &middot; {{ expense.vendor }}</template>
                        </div>
                        <div v-if="expense.litres" class="text-caption text-grey">
                          {{ expense.litres }}L @ R{{ expense.price_per_litre }}/L
                        </div>
                      </div>
                      <div class="text-body1 text-weight-bold" style="color: #004D40">
                        R{{ formatAmount(expense.amount) }}
                      </div>
                      <q-icon v-if="expense.receipt_path" name="image" color="teal" size="xs" class="q-ml-xs" title="Has receipt" />
                      <q-btn flat round icon="more_vert" size="sm" class="q-ml-xs">
                        <q-menu>
                          <q-list dense style="min-width: 140px">
                            <q-item clickable v-close-popup @click="editExpense(expense)">
                              <q-item-section avatar><q-icon name="edit" size="sm" /></q-item-section>
                              <q-item-section>Edit</q-item-section>
                            </q-item>
                            <q-item v-if="expense.receipt_path" clickable v-close-popup @click="viewReceipt(expense.id)">
                              <q-item-section avatar><q-icon name="receipt" size="sm" color="teal" /></q-item-section>
                              <q-item-section>View Receipt</q-item-section>
                            </q-item>
                            <q-item clickable v-close-popup class="text-negative" @click="deleteExpense(expense.id, vehicle.id)">
                              <q-item-section avatar><q-icon name="delete" size="sm" color="negative" /></q-item-section>
                              <q-item-section>Delete</q-item-section>
                            </q-item>
                          </q-list>
                        </q-menu>
                      </q-btn>
                    </q-card-section>
                  </q-card>
                </template>
              </div>
            </q-slide-transition>
          </q-card-section>
        </q-card>
      </template>
    </div>

    <!-- ================================ -->
    <!-- ADD VEHICLE DIALOG -->
    <!-- ================================ -->
    <q-dialog v-model="showAddVehicle" persistent maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-toolbar class="bg-primary text-white">
          <q-btn flat icon="close" v-close-popup />
          <q-toolbar-title>{{ editingVehicleId ? 'Edit Vehicle' : 'Add Vehicle' }}</q-toolbar-title>
          <q-btn flat label="Save" no-caps @click="saveVehicle" :loading="vehicleSaving" />
        </q-toolbar>

        <q-card-section class="q-pa-md">
          <q-btn-toggle
            v-model="vehicleForm.type"
            toggle-color="primary"
            :options="[
              { label: 'Owned Vehicle', value: 'owned' },
              { label: 'Rental Vehicle', value: 'rental' },
            ]"
            class="q-mb-md full-width"
            spread
            no-caps
            unelevated
          />

          <q-input v-model="vehicleForm.name" label="Vehicle Name *" outlined dense class="q-mb-sm" placeholder="e.g. Renault Duster" />
          <div class="row q-col-gutter-sm q-mb-sm">
            <div class="col-6"><q-input v-model="vehicleForm.make" label="Make" outlined dense placeholder="e.g. Renault" /></div>
            <div class="col-6"><q-input v-model="vehicleForm.model" label="Model" outlined dense placeholder="e.g. Duster" /></div>
          </div>
          <div class="row q-col-gutter-sm q-mb-sm">
            <div class="col-6"><q-input v-model.number="vehicleForm.year" label="Year" outlined dense type="number" /></div>
            <div class="col-6"><q-input v-model="vehicleForm.registration" label="Registration" outlined dense /></div>
          </div>

          <!-- Owned fields -->
          <template v-if="vehicleForm.type === 'owned'">
            <q-separator class="q-my-md" />
            <div class="text-subtitle2 text-weight-bold q-mb-sm" style="color: #004D40">SARS Details</div>
            <q-input v-model.number="vehicleForm.purchase_price" label="Purchase Price (R)" outlined dense type="number" class="q-mb-sm" />
            <q-input v-model="vehicleForm.purchase_date" label="Purchase Date" outlined dense type="date" class="q-mb-sm" />
            <div class="text-caption text-grey q-mb-xs">Use period (for proration — leave blank for full year)</div>
            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6"><q-input v-model="vehicleForm.use_start_date" label="Use Start Date" outlined dense type="date" /></div>
              <div class="col-6"><q-input v-model="vehicleForm.use_end_date" label="Use End Date" outlined dense type="date" /></div>
            </div>
          </template>

          <!-- Rental fields -->
          <template v-if="vehicleForm.type === 'rental'">
            <q-separator class="q-my-md" />
            <div class="text-subtitle2 text-weight-bold q-mb-sm" style="color: #FF8F00">Rental Details</div>
            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6"><q-input v-model="vehicleForm.rental_start" label="Start Date" outlined dense type="date" /></div>
              <div class="col-6"><q-input v-model="vehicleForm.rental_end" label="End Date" outlined dense type="date" /></div>
            </div>
            <q-input v-model.number="vehicleForm.rental_total" label="Total Rental Cost (R)" outlined dense type="number" class="q-mb-sm" />
          </template>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- ================================ -->
    <!-- ADD EXPENSE DIALOG -->
    <!-- ================================ -->
    <q-dialog v-model="showAddExpense" persistent maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-toolbar class="bg-primary text-white">
          <q-btn flat icon="close" v-close-popup />
          <q-toolbar-title>{{ editingExpenseId ? 'Edit Expense' : 'Add Expense' }}</q-toolbar-title>
          <q-btn flat label="Save" no-caps @click="saveExpense" :loading="expenseSaving" />
        </q-toolbar>

        <q-card-section class="q-pa-md">
          <!-- AI Extraction Confidence Badge -->
          <q-banner v-if="extractionConfidence !== null" class="q-mb-md rounded-borders" :class="extractionConfidence >= 0.8 ? 'bg-green-1' : extractionConfidence >= 0.5 ? 'bg-orange-1' : 'bg-red-1'">
            <template v-slot:avatar>
              <q-icon :name="extractionConfidence >= 0.8 ? 'verified' : 'psychology'" :color="extractionConfidence >= 0.8 ? 'positive' : 'warning'" />
            </template>
            AI extracted ({{ Math.round(extractionConfidence * 100) }}% confidence) — please verify
          </q-banner>

          <!-- Receipt Preview -->
          <div v-if="editReceiptUrl" class="q-mb-md text-center">
            <img
              v-if="editReceiptUrl && !editReceiptUrl.endsWith('.pdf')"
              :src="editReceiptUrl"
              class="receipt-preview"
              @click="showReceiptFullscreen = true"
            />
            <div v-else-if="editReceiptUrl && editReceiptUrl.endsWith('.pdf')" class="text-center">
              <q-icon name="picture_as_pdf" size="48px" color="red" />
              <div class="text-caption">PDF Receipt attached</div>
            </div>
          </div>

          <!-- Warnings -->
          <q-banner v-for="(warning, idx) in expenseWarnings" :key="idx" class="q-mb-sm rounded-borders" :class="warning.severity === 'warning' ? 'bg-orange-1' : 'bg-blue-1'" dense>
            <template v-slot:avatar>
              <q-icon :name="warning.severity === 'warning' ? 'warning' : 'info'" :color="warning.severity === 'warning' ? 'warning' : 'info'" size="sm" />
            </template>
            {{ warning.message }}
          </q-banner>

          <q-select
            v-model="expenseForm.vehicle_id"
            :options="vehicleSelectOptions"
            emit-value
            map-options
            outlined
            dense
            label="Vehicle *"
            class="q-mb-sm"
          />
          <q-input v-model="expenseForm.date" label="Date *" outlined dense type="date" class="q-mb-sm" />
          <q-select
            v-model="expenseForm.category"
            :options="expenseCategories"
            outlined
            dense
            label="Category *"
            class="q-mb-sm"
          />
          <q-input v-model.number="expenseForm.amount" label="Amount (R) *" outlined dense type="number" step="0.01" class="q-mb-sm" />
          <q-input v-model="expenseForm.vendor" label="Vendor/Station" outlined dense class="q-mb-sm" />
          <q-input v-model="expenseForm.description" label="Description" outlined dense type="textarea" rows="2" class="q-mb-sm" />

          <!-- Fuel-specific fields -->
          <template v-if="expenseForm.category === 'Fuel'">
            <q-separator class="q-my-md" />
            <div class="text-subtitle2 text-weight-bold q-mb-sm" style="color: #004D40">Fuel Details</div>
            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6"><q-input v-model.number="expenseForm.litres" label="Litres" outlined dense type="number" step="0.01" /></div>
              <div class="col-6"><q-input v-model.number="expenseForm.price_per_litre" label="R/Litre" outlined dense type="number" step="0.01" /></div>
            </div>
            <q-input v-model.number="expenseForm.odometer_km" label="Odometer (km)" outlined dense type="number" class="q-mb-sm" />
          </template>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- ================================ -->
    <!-- SCAN RECEIPT DIALOG -->
    <!-- ================================ -->
    <q-dialog v-model="showScanReceipt" persistent maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-toolbar class="bg-primary text-white">
          <q-btn flat icon="close" v-close-popup />
          <q-toolbar-title>Scan Receipt</q-toolbar-title>
        </q-toolbar>

        <q-card-section class="q-pa-md text-center">
          <q-select
            v-model="scanVehicleId"
            :options="vehicleSelectOptions"
            emit-value
            map-options
            outlined
            dense
            label="Vehicle *"
            class="q-mb-md"
          />

          <q-file
            v-model="scanFile"
            outlined
            label="Take photo or select file"
            accept="image/*,application/pdf"
            :max-file-size="10485760"
            class="q-mb-md"
          >
            <template v-slot:prepend>
              <q-icon name="camera_alt" />
            </template>
          </q-file>

          <q-btn
            color="primary"
            label="Upload & Extract"
            icon="upload"
            class="full-width"
            no-caps
            unelevated
            :disable="!scanFile || !scanVehicleId"
            :loading="scanUploading"
            @click="uploadReceipt"
            style="border-radius: 12px; min-height: 48px"
          />

          <div class="text-caption text-grey q-mt-md">
            Supports: Engen, Shell, Sasol, TotalEnergies, BP fuel slips. <br />
            Also: service invoices, toll receipts, parking tickets.
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- ================================ -->
    <!-- IMPORT VEHICLE FROM DOCUMENT DIALOG -->
    <!-- ================================ -->
    <q-dialog v-model="showImportVehicle" persistent maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-toolbar class="bg-primary text-white">
          <q-btn flat icon="close" v-close-popup />
          <q-toolbar-title>Import Vehicle from Document</q-toolbar-title>
        </q-toolbar>

        <q-card-section class="q-pa-md">
          <div v-if="!importExtracted" class="text-center">
            <q-icon name="document_scanner" size="64px" color="primary" class="q-mb-md" />
            <div class="text-subtitle1 q-mb-sm">Upload a purchase invoice or finance contract</div>
            <div class="text-caption text-grey q-mb-lg">We'll extract vehicle details (make, model, year, price) automatically</div>

            <q-file
              v-model="importFile"
              outlined
              label="Select document"
              accept="image/*,application/pdf"
              :max-file-size="10485760"
              class="q-mb-md"
            >
              <template v-slot:prepend>
                <q-icon name="upload_file" />
              </template>
            </q-file>

            <q-btn
              color="primary"
              label="Extract Vehicle Details"
              icon="auto_awesome"
              class="full-width"
              no-caps
              unelevated
              :disable="!importFile"
              :loading="importExtracting"
              @click="extractVehicleFromDoc"
              style="border-radius: 12px; min-height: 48px"
            />
          </div>

          <div v-else>
            <q-banner class="bg-green-1 q-mb-md rounded-borders">
              <template v-slot:avatar>
                <q-icon name="verified" color="positive" />
              </template>
              Vehicle details extracted ({{ importExtracted.confidence }}% confidence) — verify and save
            </q-banner>

            <q-input v-model="vehicleForm.name" label="Vehicle Name *" outlined dense class="q-mb-sm" />
            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6"><q-input v-model="vehicleForm.make" label="Make" outlined dense /></div>
              <div class="col-6"><q-input v-model="vehicleForm.model" label="Model" outlined dense /></div>
            </div>
            <div class="row q-col-gutter-sm q-mb-sm">
              <div class="col-6"><q-input v-model.number="vehicleForm.year" label="Year" outlined dense type="number" /></div>
              <div class="col-6"><q-input v-model="vehicleForm.registration" label="Registration" outlined dense /></div>
            </div>
            <q-input v-model.number="vehicleForm.purchase_price" label="Purchase Price (R)" outlined dense type="number" class="q-mb-sm" />
            <q-input v-model="vehicleForm.purchase_date" label="Purchase Date" outlined dense type="date" class="q-mb-sm" />

            <div v-if="importExtracted.dealer_name" class="text-caption text-grey q-mb-md">
              Dealer: {{ importExtracted.dealer_name }}
            </div>

            <div class="row q-col-gutter-sm">
              <div class="col-6">
                <q-btn
                  outline
                  color="grey"
                  label="Re-scan"
                  class="full-width"
                  no-caps
                  @click="importExtracted = null; importFile = null"
                  style="border-radius: 12px; min-height: 48px"
                />
              </div>
              <div class="col-6">
                <q-btn
                  color="primary"
                  label="Save Vehicle"
                  class="full-width"
                  no-caps
                  unelevated
                  :loading="vehicleSaving"
                  @click="saveImportedVehicle"
                  style="border-radius: 12px; min-height: 48px"
                />
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- ================================ -->
    <!-- RECEIPT PREVIEW DIALOG -->
    <!-- ================================ -->
    <q-dialog v-model="showReceiptPreview" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="dialog-card">
        <q-toolbar class="bg-primary text-white">
          <q-btn flat icon="close" v-close-popup />
          <q-toolbar-title>Receipt</q-toolbar-title>
        </q-toolbar>
        <q-card-section class="q-pa-md text-center">
          <div v-if="receiptLoading" class="loading-container">
            <q-spinner-dots color="primary" size="40px" />
          </div>
          <img v-else-if="receiptPreviewUrl && !receiptPreviewUrl.endsWith('.pdf')" :src="receiptPreviewUrl" style="max-width: 100%; border-radius: 8px" />
          <div v-else-if="receiptPreviewUrl && receiptPreviewUrl.endsWith('.pdf')">
            <q-icon name="picture_as_pdf" size="64px" color="red" class="q-mb-md" />
            <div class="text-subtitle1 q-mb-md">PDF Receipt</div>
            <q-btn color="primary" label="Open PDF" icon="open_in_new" no-caps @click="window.open(receiptPreviewUrl, '_blank')" />
          </div>
          <div v-else class="text-grey text-center q-pa-xl">
            <q-icon name="image_not_supported" size="48px" class="q-mb-md" />
            <div>Receipt not available</div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- ================================ -->
    <!-- BULK ASSIGN DIALOG -->
    <!-- ================================ -->
    <q-dialog v-model="showBulkAssign">
      <q-card style="min-width: 300px; border-radius: 12px">
        <q-card-section>
          <div class="text-h6">Bulk Assign Trips</div>
          <div class="text-caption text-grey q-mb-md">
            Assign {{ unassignedTripCount }} unassigned trips to a vehicle
          </div>
          <q-select
            v-model="bulkAssignVehicleId"
            :options="vehicleSelectOptions"
            emit-value
            map-options
            outlined
            dense
            label="Select Vehicle"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            color="primary"
            label="Assign All"
            no-caps
            :disable="!bulkAssignVehicleId"
            :loading="bulkAssigning"
            @click="doBulkAssign"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useQuasar } from 'quasar';
import { vehiclesApi, type Vehicle, type VehicleExtraction } from '@/services/api/vehicles.api';
import { vehicleExpensesApi, type VehicleExpense, type ExpenseSummary, type ExpenseWarning, type UploadResponse, EXPENSE_CATEGORIES } from '@/services/api/vehicle-expenses.api';
import { telemetryApi, type LogbookResponse, type LogbookTrip } from '@/services/api/telemetry.api';

const $q = useQuasar();

// =========================================
// State
// =========================================

// Vehicles
const vehicles = ref<Vehicle[]>([]);
const vehiclesLoading = ref(true);
const showAddVehicle = ref(false);
const editingVehicleId = ref<string | null>(null);
const vehicleSaving = ref(false);
const vehicleForm = ref({
  type: 'owned' as 'owned' | 'rental',
  name: '',
  make: '',
  model: '',
  year: new Date().getFullYear(),
  registration: '',
  purchase_price: 0,
  purchase_date: '',
  use_start_date: '',
  use_end_date: '',
  rental_start: '',
  rental_end: '',
  rental_total: 0,
});

// Per-vehicle expandable sections
const expandedSections = reactive<Record<string, { trips: boolean; expenses: boolean }>>({});

// Per-vehicle trip data
const vehicleTrips = reactive<Record<string, LogbookResponse>>({});
const vehicleTripCategory = reactive<Record<string, 'Business' | 'Private'>>({});
const vehicleTripsLoading = reactive<Record<string, boolean>>({});

// Per-vehicle expense data
const vehicleExpensesList = reactive<Record<string, VehicleExpense[]>>({});
const vehicleExpenseSummaries = reactive<Record<string, ExpenseSummary | null>>({});
const vehicleExpensesLoading = reactive<Record<string, boolean>>({});

// Unassigned trips
const unassignedTripCount = ref(0);
const showBulkAssign = ref(false);
const bulkAssignVehicleId = ref<string | null>(null);
const bulkAssigning = ref(false);

// Expense dialog
const showAddExpense = ref(false);
const editingExpenseId = ref<string | null>(null);
const expenseSaving = ref(false);
const contextVehicleId = ref<string | null>(null);
const expenseForm = ref({
  vehicle_id: '',
  date: new Date().toISOString().slice(0, 10),
  category: 'Fuel',
  amount: 0,
  vendor: '',
  description: '',
  litres: null as number | null,
  price_per_litre: null as number | null,
  odometer_km: null as number | null,
});

// Scan
const showScanReceipt = ref(false);
const scanVehicleId = ref<string | null>(null);
const scanFile = ref<File | null>(null);
const scanUploading = ref(false);

// AI extraction state
const extractionConfidence = ref<number | null>(null);
const editReceiptUrl = ref<string | null>(null);
const expenseWarnings = ref<ExpenseWarning[]>([]);
const showReceiptFullscreen = ref(false);

// Receipt preview
const showReceiptPreview = ref(false);
const receiptPreviewUrl = ref<string | null>(null);
const receiptLoading = ref(false);

// Import Vehicle from Document
const showImportVehicle = ref(false);
const importFile = ref<File | null>(null);
const importExtracting = ref(false);
const importExtracted = ref<VehicleExtraction['data'] | null>(null);

// =========================================
// Computed
// =========================================
const expenseCategories = EXPENSE_CATEGORIES as unknown as string[];

const vehicleSelectOptions = computed(() =>
  vehicles.value.map((v) => ({
    label: `${v.name}${v.is_default ? ' (Default)' : ''}`,
    value: v.id,
  }))
);

const tripColumns = [
  { name: 'date', label: 'Date', field: 'date', align: 'left' as const, sortable: true },
  { name: 'time', label: 'Time', field: 'start_time', align: 'left' as const },
  { name: 'origin', label: 'From', field: 'origin', align: 'left' as const },
  { name: 'destination', label: 'To', field: 'destination', align: 'left' as const },
  { name: 'purpose', label: 'Purpose', field: 'purpose', align: 'left' as const },
  { name: 'distance_km', label: 'KM', field: 'distance_km', align: 'right' as const, sortable: true },
  { name: 'odo_reading', label: 'Odo Reading', field: '_running_odo', align: 'right' as const },
];

// =========================================
// Formatters
// =========================================
function formatDate(dateStr: string): string {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-ZA', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatKm(km: number): string {
  return km.toLocaleString('en-ZA', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function formatAmount(amount: number): string {
  return amount.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function expenseCategoryIcon(cat: string): string {
  const map: Record<string, string> = {
    Fuel: 'local_gas_station',
    Service: 'build',
    Tyres: 'tire_repair',
    Insurance: 'shield',
    Toll: 'toll',
    Licence: 'badge',
    Parking: 'local_parking',
    Other: 'more_horiz',
  };
  return map[cat] || 'receipt';
}

function expenseCategoryColor(cat: string): string {
  const map: Record<string, string> = {
    Fuel: 'orange',
    Service: 'blue',
    Tyres: 'brown',
    Insurance: 'purple',
    Toll: 'teal',
    Licence: 'cyan',
    Parking: 'indigo',
    Other: 'grey',
  };
  return map[cat] || 'grey';
}

function printPage() {
  window.print();
}

/**
 * Compute trips with running odometer (same logic as before but as a function for per-vehicle use)
 */
function computeTripsWithOdo(trips: LogbookTrip[]): (LogbookTrip & { _running_odo: number })[] {
  if (!trips.length) return [];

  const oldest = [...trips].reverse();
  let runningOdo = 0;

  const withOdo = oldest.map((t) => {
    if (t.odometer_end) {
      runningOdo = t.odometer_end;
    } else {
      runningOdo += t.distance_km;
    }
    return { ...t, _running_odo: Math.round(runningOdo) };
  });

  return withOdo.reverse();
}

// =========================================
// Section Toggle & Lazy Loading
// =========================================
function toggleSection(vehicleId: string, section: 'trips' | 'expenses') {
  if (!expandedSections[vehicleId]) {
    expandedSections[vehicleId] = { trips: false, expenses: false };
  }

  const isExpanding = !expandedSections[vehicleId][section];
  expandedSections[vehicleId][section] = isExpanding;

  if (isExpanding) {
    if (section === 'trips' && !vehicleTrips[vehicleId]) {
      loadVehicleTrips(vehicleId);
    }
    if (section === 'expenses' && !vehicleExpensesList[vehicleId]) {
      loadVehicleExpenses(vehicleId);
    }
  }
}

async function loadVehicleTrips(vehicleId: string) {
  vehicleTripsLoading[vehicleId] = true;
  try {
    const category = vehicleTripCategory[vehicleId] || 'Business';
    vehicleTrips[vehicleId] = await telemetryApi.getTrips(category, vehicleId);
  } catch (error) {
    console.error('Failed to load trips for vehicle:', error);
  } finally {
    vehicleTripsLoading[vehicleId] = false;
  }
}

function switchVehicleTripCategory(vehicleId: string, category: 'Business' | 'Private') {
  vehicleTripCategory[vehicleId] = category;
  loadVehicleTrips(vehicleId);
}

async function loadVehicleExpenses(vehicleId: string) {
  vehicleExpensesLoading[vehicleId] = true;
  try {
    const [expRes, sumRes] = await Promise.all([
      vehicleExpensesApi.list({ vehicle_id: vehicleId }),
      vehicleExpensesApi.summary(vehicleId),
    ]);
    vehicleExpensesList[vehicleId] = expRes.data;
    vehicleExpenseSummaries[vehicleId] = sumRes;
  } catch (error) {
    console.error('Failed to load expenses for vehicle:', error);
  } finally {
    vehicleExpensesLoading[vehicleId] = false;
  }
}

// =========================================
// Context-aware dialog openers
// =========================================
function openAddExpense(vehicleId: string) {
  contextVehicleId.value = vehicleId;
  resetExpenseForm(vehicleId);
  showAddExpense.value = true;
}

function openScanReceipt(vehicleId: string) {
  scanVehicleId.value = vehicleId;
  scanFile.value = null;
  showScanReceipt.value = true;
}

// =========================================
// Vehicles
// =========================================
async function loadVehicles() {
  try {
    vehiclesLoading.value = true;
    const res = await vehiclesApi.list();
    vehicles.value = res.data;

    // Load calculation for each owned vehicle
    for (const v of vehicles.value) {
      if (v.type === 'owned') {
        try {
          const detail = await vehiclesApi.show(v.id);
          v.calculation = detail.data.calculation;
        } catch { /* ignore */ }
      }
    }
  } catch (error) {
    console.error('Failed to load vehicles:', error);
    $q.notify({ type: 'negative', message: 'Failed to load vehicles', position: 'bottom' });
  } finally {
    vehiclesLoading.value = false;
  }
}

function editVehicle(vehicle: Vehicle) {
  editingVehicleId.value = vehicle.id;
  vehicleForm.value = {
    type: vehicle.type,
    name: vehicle.name,
    make: vehicle.make || '',
    model: vehicle.model || '',
    year: vehicle.year || new Date().getFullYear(),
    registration: vehicle.registration || '',
    purchase_price: vehicle.purchase_price,
    purchase_date: vehicle.purchase_date || '',
    use_start_date: vehicle.use_start_date || '',
    use_end_date: vehicle.use_end_date || '',
    rental_start: vehicle.rental_start || '',
    rental_end: vehicle.rental_end || '',
    rental_total: vehicle.rental_total || 0,
  };
  showAddVehicle.value = true;
}

async function saveVehicle() {
  if (!vehicleForm.value.name) {
    $q.notify({ type: 'warning', message: 'Vehicle name is required', position: 'bottom' });
    return;
  }

  vehicleSaving.value = true;
  try {
    const data: Record<string, unknown> = { ...vehicleForm.value };
    for (const key of Object.keys(data)) {
      if (data[key] === '') data[key] = null;
    }

    if (editingVehicleId.value) {
      await vehiclesApi.update(editingVehicleId.value, data as never);
      $q.notify({ type: 'positive', message: 'Vehicle updated', position: 'bottom' });
    } else {
      await vehiclesApi.create(data as never);
      $q.notify({ type: 'positive', message: 'Vehicle added', position: 'bottom' });
    }

    showAddVehicle.value = false;
    editingVehicleId.value = null;
    resetVehicleForm();
    await loadVehicles();
  } catch (error) {
    console.error('Failed to save vehicle:', error);
    $q.notify({ type: 'negative', message: 'Failed to save vehicle', position: 'bottom' });
  } finally {
    vehicleSaving.value = false;
  }
}

async function deleteVehicle(id: string) {
  $q.dialog({
    title: 'Remove Vehicle',
    message: 'This will deactivate the vehicle. Existing trips and expenses will be preserved.',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await vehiclesApi.destroy(id);
      $q.notify({ type: 'positive', message: 'Vehicle removed', position: 'bottom' });
      await loadVehicles();
    } catch {
      $q.notify({ type: 'negative', message: 'Failed to remove vehicle', position: 'bottom' });
    }
  });
}

async function setDefault(id: string) {
  try {
    await vehiclesApi.setDefault(id);
    $q.notify({ type: 'positive', message: 'Default vehicle set', position: 'bottom' });
    await loadVehicles();
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to set default', position: 'bottom' });
  }
}

async function syncVehicleKm(id: string) {
  try {
    await vehiclesApi.syncKm(id);
    $q.notify({ type: 'positive', message: 'KM synced from trips', position: 'bottom' });
    await loadVehicles();
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to sync KM', position: 'bottom' });
  }
}

function resetVehicleForm() {
  vehicleForm.value = {
    type: 'owned',
    name: '',
    make: '',
    model: '',
    year: new Date().getFullYear(),
    registration: '',
    purchase_price: 0,
    purchase_date: '',
    use_start_date: '',
    use_end_date: '',
    rental_start: '',
    rental_end: '',
    rental_total: 0,
  };
}

// =========================================
// Unassigned Trips
// =========================================
async function checkUnassignedTrips() {
  unassignedTripCount.value = 0;
}

async function doBulkAssign() {
  if (!bulkAssignVehicleId.value) return;
  bulkAssigning.value = true;
  try {
    const result = await vehiclesApi.bulkAssignTrips(bulkAssignVehicleId.value);
    $q.notify({ type: 'positive', message: result.message, position: 'bottom' });
    showBulkAssign.value = false;
    unassignedTripCount.value = 0;
    await loadVehicles();
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to assign trips', position: 'bottom' });
  } finally {
    bulkAssigning.value = false;
  }
}

// =========================================
// Expenses
// =========================================
function editExpense(expense: VehicleExpense) {
  editingExpenseId.value = expense.id;
  contextVehicleId.value = expense.vehicle_id;
  expenseForm.value = {
    vehicle_id: expense.vehicle_id,
    date: expense.date.slice(0, 10),
    category: expense.category,
    amount: expense.amount,
    vendor: expense.vendor || '',
    description: expense.description || '',
    litres: expense.litres,
    price_per_litre: expense.price_per_litre,
    odometer_km: expense.odometer_km,
  };
  showAddExpense.value = true;
}

async function saveExpense() {
  if (!expenseForm.value.vehicle_id || !expenseForm.value.date || !expenseForm.value.category || !expenseForm.value.amount) {
    $q.notify({ type: 'warning', message: 'Please fill required fields', position: 'bottom' });
    return;
  }

  expenseSaving.value = true;
  try {
    const data: Record<string, unknown> = { ...expenseForm.value };
    for (const key of Object.keys(data)) {
      if (data[key] === '' || data[key] === null) delete data[key];
    }
    data.vehicle_id = expenseForm.value.vehicle_id;
    data.date = expenseForm.value.date;
    data.category = expenseForm.value.category;
    data.amount = expenseForm.value.amount;

    let result;
    if (editingExpenseId.value) {
      result = await vehicleExpensesApi.update(editingExpenseId.value, data as never);
      $q.notify({ type: 'positive', message: 'Expense updated', position: 'bottom' });
    } else {
      result = await vehicleExpensesApi.create(data as never);
      $q.notify({ type: 'positive', message: 'Expense added', position: 'bottom' });
    }

    if (result.warnings?.length) {
      for (const w of result.warnings) {
        $q.notify({
          type: w.severity === 'warning' ? 'warning' : 'info',
          message: w.message,
          position: 'bottom',
          timeout: 5000,
        });
      }
    }

    // Reload expenses for the affected vehicle
    const affectedVehicleId = expenseForm.value.vehicle_id;
    showAddExpense.value = false;
    editingExpenseId.value = null;
    extractionConfidence.value = null;
    editReceiptUrl.value = null;
    expenseWarnings.value = [];
    contextVehicleId.value = null;
    resetExpenseForm();

    if (vehicleExpensesList[affectedVehicleId]) {
      await loadVehicleExpenses(affectedVehicleId);
    }
    await loadVehicles(); // Refresh expense totals on cards
  } catch (error) {
    console.error('Failed to save expense:', error);
    $q.notify({ type: 'negative', message: 'Failed to save expense', position: 'bottom' });
  } finally {
    expenseSaving.value = false;
  }
}

async function deleteExpense(id: string, vehicleId: string) {
  $q.dialog({
    title: 'Delete Expense',
    message: 'Are you sure you want to delete this expense?',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await vehicleExpensesApi.destroy(id);
      $q.notify({ type: 'positive', message: 'Expense deleted', position: 'bottom' });
      if (vehicleExpensesList[vehicleId]) {
        await loadVehicleExpenses(vehicleId);
      }
      await loadVehicles();
    } catch {
      $q.notify({ type: 'negative', message: 'Failed to delete expense', position: 'bottom' });
    }
  });
}

function resetExpenseForm(vehicleId?: string) {
  const targetVehicleId = vehicleId || contextVehicleId.value || vehicles.value.find((v) => v.is_default)?.id || '';
  expenseForm.value = {
    vehicle_id: targetVehicleId,
    date: new Date().toISOString().slice(0, 10),
    category: 'Fuel',
    amount: 0,
    vendor: '',
    description: '',
    litres: null,
    price_per_litre: null,
    odometer_km: null,
  };
}

async function uploadReceipt() {
  if (!scanFile.value || !scanVehicleId.value) return;
  scanUploading.value = true;
  try {
    const result: UploadResponse = await vehicleExpensesApi.upload(scanVehicleId.value, scanFile.value);

    showScanReceipt.value = false;
    scanFile.value = null;

    if (result.extraction?.success && result.extraction.confidence && result.extraction.confidence >= 0.3) {
      extractionConfidence.value = result.extraction.confidence;
      editReceiptUrl.value = result.receipt_url;
      $q.notify({ type: 'positive', message: `AI extracted (${Math.round(result.extraction.confidence * 100)}% confidence) — please verify`, position: 'bottom' });
    } else {
      extractionConfidence.value = null;
      editReceiptUrl.value = result.receipt_url;
      $q.notify({ type: 'info', message: 'Receipt uploaded — fill in details manually', position: 'bottom' });
    }

    editExpense(result.data);

    // Reload expenses for the vehicle
    const vid = scanVehicleId.value;
    if (vehicleExpensesList[vid]) {
      await loadVehicleExpenses(vid);
    }
  } catch {
    $q.notify({ type: 'negative', message: 'Upload failed', position: 'bottom' });
  } finally {
    scanUploading.value = false;
  }
}

async function viewReceipt(expenseId: string) {
  receiptLoading.value = true;
  showReceiptPreview.value = true;
  receiptPreviewUrl.value = null;
  try {
    const result = await vehicleExpensesApi.getReceipt(expenseId);
    receiptPreviewUrl.value = result.receipt_url;
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to load receipt', position: 'bottom' });
    showReceiptPreview.value = false;
  } finally {
    receiptLoading.value = false;
  }
}

async function extractVehicleFromDoc() {
  if (!importFile.value) return;
  importExtracting.value = true;
  try {
    const result = await vehiclesApi.extractFromDocument(importFile.value);
    if (result.success && result.data) {
      importExtracted.value = result.data;
      vehicleForm.value = {
        type: 'owned',
        name: [result.data.make, result.data.model].filter(Boolean).join(' ') || '',
        make: result.data.make || '',
        model: result.data.model || '',
        year: result.data.year || new Date().getFullYear(),
        registration: result.data.registration || '',
        purchase_price: result.data.purchase_price || 0,
        purchase_date: result.data.purchase_date || '',
        use_start_date: '',
        use_end_date: '',
        rental_start: '',
        rental_end: '',
        rental_total: 0,
      };
    } else {
      $q.notify({ type: 'negative', message: result.error || 'Failed to extract vehicle details', position: 'bottom' });
    }
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to extract vehicle details', position: 'bottom' });
  } finally {
    importExtracting.value = false;
  }
}

async function saveImportedVehicle() {
  if (!vehicleForm.value.name) {
    $q.notify({ type: 'warning', message: 'Vehicle name is required', position: 'bottom' });
    return;
  }

  vehicleSaving.value = true;
  try {
    const data: Record<string, unknown> = { ...vehicleForm.value };
    for (const key of Object.keys(data)) {
      if (data[key] === '') data[key] = null;
    }

    await vehiclesApi.create(data as never);
    $q.notify({ type: 'positive', message: 'Vehicle added from document', position: 'bottom' });

    showImportVehicle.value = false;
    importExtracted.value = null;
    importFile.value = null;
    resetVehicleForm();
    await loadVehicles();
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to save vehicle', position: 'bottom' });
  } finally {
    vehicleSaving.value = false;
  }
}

// =========================================
// Watchers
// =========================================
watch(showAddVehicle, (val) => {
  if (!val) {
    editingVehicleId.value = null;
    resetVehicleForm();
  }
});

watch(showAddExpense, (val) => {
  if (!val) {
    editingExpenseId.value = null;
    extractionConfidence.value = null;
    editReceiptUrl.value = null;
    expenseWarnings.value = [];
    contextVehicleId.value = null;
    resetExpenseForm();
  }
});

watch(showImportVehicle, (val) => {
  if (!val) {
    importExtracted.value = null;
    importFile.value = null;
    resetVehicleForm();
  }
});

// =========================================
// Lifecycle
// =========================================
onMounted(async () => {
  await loadVehicles();
  checkUnassignedTrips();
});
</script>

<style scoped>
.travel-expenses-page {
  padding: 0;
  background: #FAFAFA;
  min-height: 100vh;
}

.page-header {
  background: #004D40;
  padding: 12px 16px;
  position: sticky;
  top: 0;
  z-index: 10;
}

.header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 18px;
  font-weight: 600;
  color: white;
  margin: 0;
}

.loading-container {
  display: flex;
  justify-content: center;
  padding: 60px;
}

/* Vehicle Card */
.vehicle-card {
  border-radius: 12px;
  border-color: #B2DFDB;
}

.sars-bar {
  padding: 8px 12px;
  background: #E8F5E9;
  border-radius: 8px;
}

/* Summary Card */
.summary-card {
  border-radius: 12px;
  border-color: #B2DFDB;
  padding: 12px;
}

.summary-row {
  display: flex;
  justify-content: space-around;
  gap: 16px;
}

.summary-item {
  text-align: center;
}

.summary-label {
  display: block;
  font-size: 11px;
  color: #78909C;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.summary-value {
  display: block;
  font-size: 20px;
  font-weight: 700;
  color: #004D40;
  margin-top: 4px;
}

.summary-item.highlight .summary-value {
  color: #2E7D32;
}

/* Trips Table */
.trips-table-card {
  border-radius: 12px;
  border-color: #B2DFDB;
  overflow: hidden;
}

.trips-table {
  font-size: 12px;
}

.trips-table :deep(th) {
  font-size: 10px;
  font-weight: 600;
  color: #78909C;
  text-transform: uppercase;
  background: #FAFAFA;
}

.trips-table :deep(td) {
  padding: 8px 12px;
}

.table-footer {
  display: flex;
  justify-content: space-between;
  padding: 12px 16px;
  background: #E0F2F1;
  border-top: 2px solid #004D40;
}

.footer-label {
  font-size: 12px;
  font-weight: 700;
  color: #004D40;
  text-transform: uppercase;
}

.footer-value {
  font-size: 14px;
  font-weight: 700;
  color: #004D40;
}

/* Expense Card */
.expense-card {
  border-radius: 12px;
  border-color: #E0E0E0;
}

/* Receipt Preview */
.receipt-preview {
  max-width: 100%;
  max-height: 200px;
  border-radius: 8px;
  border: 1px solid #E0E0E0;
  cursor: pointer;
  object-fit: contain;
}

/* Dialog */
.dialog-card {
  background: #FAFAFA;
}

/* Print */
@media print {
  .page-header {
    position: static;
    background: white;
    color: black;
  }
  .page-title {
    color: black;
  }
}
</style>
