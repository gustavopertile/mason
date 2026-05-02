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
  <label class="flex items-center gap-2 text-sm text-slate-600">
    <span>Company</span>
    <select
      class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
      :value="selectValue"
      @change="onChange"
    >
      <option value="">All</option>
      <option v-for="company in companies" :key="company.id" :value="company.id">
        {{ company.name }}
      </option>
    </select>
  </label>
</template>
