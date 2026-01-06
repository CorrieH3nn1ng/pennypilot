<template>
  <div class="milestone-alerts" v-if="hasAlerts">
    <!-- Critical/Overdue Alerts -->
    <q-card
      v-for="milestone in criticalMilestones"
      :key="milestone.id"
      class="milestone-card critical q-mb-sm"
      flat
      bordered
    >
      <q-card-section class="q-pa-md">
        <div class="milestone-header">
          <div class="milestone-icon">
            <q-icon :name="getTypeIcon(milestone.type)" size="20px" />
          </div>
          <div class="milestone-content">
            <div class="milestone-title">{{ milestone.title }}</div>
            <div class="milestone-due" :class="{ overdue: isOverdue(milestone) }">
              <q-icon name="event" size="12px" class="q-mr-xs" />
              {{ formatDueDate(milestone) }}
            </div>
          </div>
          <q-badge
            :color="getPriorityColor(milestone.priority)"
            text-color="white"
            class="priority-badge"
          >
            {{ milestone.priority.toUpperCase() }}
          </q-badge>
        </div>

        <div v-if="milestone.description" class="milestone-description q-mt-sm">
          {{ truncateDescription(milestone.description) }}
        </div>

        <!-- Holiday details if applicable -->
        <div v-if="milestone.meta?.total_resuming" class="holiday-details q-mt-sm">
          <div class="detail-row">
            <span class="label">Payments resuming:</span>
            <span class="value text-negative">
              {{ formatCurrency(milestone.meta.total_resuming as number) }}/month
            </span>
          </div>
          <div class="detail-row" v-if="milestone.meta?.holiday_end_date">
            <span class="label">Holiday ends:</span>
            <span class="value">{{ formatDate(milestone.meta.holiday_end_date as string) }}</span>
          </div>
        </div>

        <div class="milestone-actions q-mt-sm">
          <q-btn
            flat
            dense
            color="positive"
            icon="check"
            label="Done"
            @click="completeMilestone(milestone)"
          />
          <q-btn
            flat
            dense
            color="grey"
            icon="snooze"
            label="Snooze"
            @click="snoozeMilestone(milestone)"
          />
          <q-btn
            flat
            dense
            color="grey-6"
            icon="close"
            @click="dismissMilestone(milestone)"
          />
        </div>
      </q-card-section>
    </q-card>

    <!-- Upcoming Alerts (collapsed) -->
    <q-expansion-item
      v-if="upcomingMilestones.length > 0"
      class="upcoming-expansion"
      icon="schedule"
      :label="`${upcomingMilestones.length} upcoming reminder${upcomingMilestones.length > 1 ? 's' : ''}`"
      header-class="text-grey-7"
    >
      <q-card
        v-for="milestone in upcomingMilestones"
        :key="milestone.id"
        class="milestone-card upcoming q-mb-xs"
        flat
      >
        <q-card-section class="q-pa-sm">
          <div class="milestone-row">
            <q-icon :name="getTypeIcon(milestone.type)" size="16px" color="grey-6" class="q-mr-sm" />
            <div class="milestone-info">
              <div class="milestone-title-sm">{{ milestone.title }}</div>
              <div class="milestone-due-sm">{{ formatDueDate(milestone) }}</div>
            </div>
            <q-btn
              flat
              round
              dense
              size="sm"
              icon="more_vert"
              color="grey-6"
              @click.stop
            >
              <q-menu>
                <q-list dense>
                  <q-item clickable v-close-popup @click="completeMilestone(milestone)">
                    <q-item-section avatar><q-icon name="check" /></q-item-section>
                    <q-item-section>Complete</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="snoozeMilestone(milestone)">
                    <q-item-section avatar><q-icon name="snooze" /></q-item-section>
                    <q-item-section>Snooze 7 days</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="dismissMilestone(milestone)">
                    <q-item-section avatar><q-icon name="close" /></q-item-section>
                    <q-item-section>Dismiss</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </div>
        </q-card-section>
      </q-card>
    </q-expansion-item>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { milestoneApi, type Milestone } from '@/services/api/milestone.api';

const $q = useQuasar();

const milestones = ref<Milestone[]>([]);
const loading = ref(false);

const hasAlerts = computed(() => milestones.value.length > 0);

const criticalMilestones = computed(() =>
  milestones.value.filter(m =>
    m.priority === 'critical' || m.priority === 'high' || isOverdue(m)
  ).slice(0, 3)
);

const upcomingMilestones = computed(() =>
  milestones.value.filter(m =>
    m.priority !== 'critical' && m.priority !== 'high' && !isOverdue(m)
  )
);

function isOverdue(milestone: Milestone): boolean {
  return new Date(milestone.due_date) < new Date();
}

function getTypeIcon(type: string): string {
  switch (type) {
    case 'review': return 'rate_review';
    case 'payment': return 'payment';
    case 'goal': return 'flag';
    default: return 'notifications';
  }
}

function getPriorityColor(priority: string): string {
  switch (priority) {
    case 'critical': return 'red';
    case 'high': return 'orange';
    case 'medium': return 'blue';
    default: return 'grey';
  }
}

function formatDueDate(milestone: Milestone): string {
  const due = new Date(milestone.due_date);
  const now = new Date();
  const diffDays = Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

  if (diffDays < 0) {
    return `${Math.abs(diffDays)} day${Math.abs(diffDays) > 1 ? 's' : ''} overdue`;
  } else if (diffDays === 0) {
    return 'Due today';
  } else if (diffDays === 1) {
    return 'Due tomorrow';
  } else if (diffDays <= 7) {
    return `Due in ${diffDays} days`;
  } else {
    return due.toLocaleDateString('en-ZA', { month: 'short', day: 'numeric', year: 'numeric' });
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-ZA', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-ZA', {
    style: 'currency',
    currency: 'ZAR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

function truncateDescription(desc: string): string {
  if (desc.length <= 100) return desc;
  return desc.substring(0, 100) + '...';
}

async function loadMilestones() {
  loading.value = true;
  try {
    const response = await milestoneApi.getUpcoming(90);
    milestones.value = [...response.overdue, ...response.upcoming];
  } catch (error) {
    console.error('Failed to load milestones:', error);
  } finally {
    loading.value = false;
  }
}

async function completeMilestone(milestone: Milestone) {
  try {
    await milestoneApi.complete(milestone.id);
    milestones.value = milestones.value.filter(m => m.id !== milestone.id);
    $q.notify({
      type: 'positive',
      message: 'Milestone completed',
      icon: 'check_circle',
    });
  } catch (error) {
    console.error('Failed to complete milestone:', error);
    $q.notify({
      type: 'negative',
      message: 'Failed to complete milestone',
    });
  }
}

async function snoozeMilestone(milestone: Milestone) {
  try {
    const updated = await milestoneApi.snooze(milestone.id, 7);
    const index = milestones.value.findIndex(m => m.id === milestone.id);
    if (index >= 0) {
      milestones.value[index] = updated;
    }
    $q.notify({
      type: 'info',
      message: 'Snoozed for 7 days',
      icon: 'snooze',
    });
  } catch (error) {
    console.error('Failed to snooze milestone:', error);
  }
}

async function dismissMilestone(milestone: Milestone) {
  try {
    await milestoneApi.dismiss(milestone.id);
    milestones.value = milestones.value.filter(m => m.id !== milestone.id);
    $q.notify({
      type: 'info',
      message: 'Milestone dismissed',
      icon: 'close',
    });
  } catch (error) {
    console.error('Failed to dismiss milestone:', error);
  }
}

onMounted(() => {
  loadMilestones();
});
</script>

<style scoped>
.milestone-alerts {
  margin-bottom: 16px;
}

.milestone-card {
  border-radius: 12px;
}

.milestone-card.critical {
  border-left: 4px solid #C62828;
  background: #FFF8F8;
}

.milestone-card.upcoming {
  background: #FAFAFA;
}

.milestone-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.milestone-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #FFEBEE;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #C62828;
}

.milestone-content {
  flex: 1;
}

.milestone-title {
  font-size: 14px;
  font-weight: 600;
  color: #37474F;
}

.milestone-due {
  font-size: 12px;
  color: #78909C;
  display: flex;
  align-items: center;
  margin-top: 2px;
}

.milestone-due.overdue {
  color: #C62828;
  font-weight: 500;
}

.priority-badge {
  font-size: 9px;
  padding: 2px 6px;
}

.milestone-description {
  font-size: 12px;
  color: #607D8B;
  line-height: 1.4;
  padding: 8px;
  background: white;
  border-radius: 6px;
  white-space: pre-line;
}

.holiday-details {
  background: #FFF3E0;
  padding: 8px 12px;
  border-radius: 6px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  padding: 2px 0;
}

.detail-row .label {
  color: #78909C;
}

.detail-row .value {
  font-weight: 600;
}

.milestone-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.milestone-row {
  display: flex;
  align-items: center;
}

.milestone-info {
  flex: 1;
}

.milestone-title-sm {
  font-size: 13px;
  font-weight: 500;
  color: #455A64;
}

.milestone-due-sm {
  font-size: 11px;
  color: #90A4AE;
}

.upcoming-expansion {
  background: #FAFAFA;
  border-radius: 8px;
}

.upcoming-expansion :deep(.q-expansion-item__container) {
  border-radius: 8px;
}
</style>
