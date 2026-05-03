<script setup>
import { onMounted, computed } from 'vue';
import { useCompanies } from '../composables/useCompanyData';

const props = defineProps({
  modelValue: {
    type: [Number, null],
    default: null,
  },
});

const emit = defineEmits(['update:modelValue']);

const { companies, load } = useCompanies();
onMounted(load);

const selectValue = computed(() => (props.modelValue === null ? '' : String(props.modelValue)));

function onChange(event) {
  const raw = event.target.value;
  emit('update:modelValue', raw === '' ? null : Number(raw));
}
</script>

<template>
  <label class="flex items-center gap-2">
    <span class="text-xs text-ink-mute">Showing</span>
    <div class="relative">
      <select
        :value="selectValue"
        aria-label="Filter by company"
        class="cursor-pointer appearance-none rounded-md border border-paper-line bg-paper py-1.5 pl-3 pr-8 text-sm font-medium text-ink transition-colors hover:bg-paper-tint focus:border-accent focus:outline-none"
        @change="onChange"
      >
        <option value="">All companies</option>
        <option v-for="company in companies" :key="company.id" :value="company.id">
          {{ company.name }}
        </option>
      </select>
      <svg
        aria-hidden="true"
        class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-ink-mute"
        width="10"
        height="10"
        viewBox="0 0 12 12"
        fill="none"
      >
        <path d="M2.5 4.5L6 8l3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>
  </label>
</template>
