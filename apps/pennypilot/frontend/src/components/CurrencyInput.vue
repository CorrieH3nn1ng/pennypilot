<template>
  <q-input
    :model-value="displayValue"
    @update:model-value="handleInput"
    @blur="handleBlur"
    v-bind="filteredAttrs"
    :rules="numericRules"
    :prefix="showPrefix ? 'R' : undefined"
    inputmode="decimal"
  >
    <template v-for="(_, slot) in $slots" v-slot:[slot]="scope">
      <slot :name="slot" v-bind="scope || {}" />
    </template>
  </q-input>
</template>

<script setup lang="ts">
import { ref, watch, computed, useAttrs } from 'vue';
import { parseNumber, formatNumber } from '@/utils/currency';

interface Props {
  modelValue: number | null | undefined;
  showPrefix?: boolean;
  formatOnBlur?: boolean;
  rules?: ((v: number) => boolean | string)[];
}

const props = withDefaults(defineProps<Props>(), {
  showPrefix: true,
  formatOnBlur: true,
  rules: () => [],
});

const emit = defineEmits<{
  'update:modelValue': [value: number];
}>();

const attrs = useAttrs();

// Filter out 'rules' from attrs since we handle it separately
const filteredAttrs = computed(() => {
  const { rules, ...rest } = attrs;
  return rest;
});

// Convert rules to validate the numeric value, not the display string
const numericRules = computed(() => {
  return props.rules.map(rule => {
    return () => {
      const numericValue = parseNumber(displayValue.value);
      return rule(numericValue);
    };
  });
});

// Internal display value (what the user sees/types)
const displayValue = ref('');

// Initialize display value from model
watch(
  () => props.modelValue,
  (newValue) => {
    const parsed = parseNumber(newValue);
    // Only update if significantly different (avoid rounding loops)
    if (Math.abs(parsed - parseNumber(displayValue.value)) > 0.001) {
      displayValue.value = formatNumber(parsed);
    }
  },
  { immediate: true }
);

// Handle user input - allow typing with comma or point
function handleInput(value: string | number | null) {
  if (value === null || value === undefined) {
    displayValue.value = '';
    emit('update:modelValue', 0);
    return;
  }

  const strValue = String(value);
  displayValue.value = strValue;

  // Parse and emit the numeric value
  const parsed = parseNumber(strValue);
  emit('update:modelValue', parsed);
}

// Format nicely on blur
function handleBlur() {
  if (props.formatOnBlur && displayValue.value) {
    const parsed = parseNumber(displayValue.value);
    displayValue.value = formatNumber(parsed);
  }
}
</script>
