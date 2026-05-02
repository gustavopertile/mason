<script setup>
import { ref, watch, computed } from 'vue';
import {
  fetchEmployeesForCompany,
  fetchProjectsForCompany,
  fetchTasksForCompany,
  fetchEmployeesForProject,
} from '../composables/useCompanyData';

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
    // Shape: { company_id, date, employee_id, project_id, task_id, hours }
  },
  companies: {
    type: Array,
    required: true,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
  index: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(['update:modelValue', 'duplicate', 'remove']);

const row = computed(() => props.modelValue);

function patch(values) {
  emit('update:modelValue', { ...row.value, ...values });
}

const projects = ref([]);
const tasks = ref([]);
const employees = ref([]);
const loadingCompanyData = ref(false);

async function refreshCompanyLists(companyId) {
  if (!companyId) {
    projects.value = [];
    tasks.value = [];
    employees.value = [];
    return;
  }
  loadingCompanyData.value = true;
  try {
    const [p, t, e] = await Promise.all([
      fetchProjectsForCompany(companyId),
      fetchTasksForCompany(companyId),
      fetchEmployeesForCompany(companyId),
    ]);
    projects.value = p;
    tasks.value = t;
    employees.value = e;
  } finally {
    loadingCompanyData.value = false;
  }
}

async function refreshProjectEmployees(companyId, projectId) {
  if (!companyId) return;
  if (!projectId) {
    employees.value = await fetchEmployeesForCompany(companyId);
    return;
  }
  employees.value = await fetchEmployeesForProject(companyId, projectId);
}

watch(
  () => row.value.company_id,
  async (newId, prevId) => {
    if (newId === prevId) return;
    await refreshCompanyLists(newId);
    // Wipe child selections that no longer make sense.
    if (prevId !== undefined) {
      patch({ employee_id: null, project_id: null, task_id: null });
    }
  },
  { immediate: true },
);

watch(
  () => row.value.project_id,
  async (projectId) => {
    await refreshProjectEmployees(row.value.company_id, projectId);
    // Drop the employee selection if it's no longer valid for the project.
    if (row.value.employee_id && !employees.value.some((e) => e.id === row.value.employee_id)) {
      patch({ employee_id: null });
    }
  },
);

const fieldClass = (field) => [
  'w-full rounded border bg-white px-2 py-1.5 text-sm text-slate-900 shadow-sm',
  'focus:outline-none focus:ring-1',
  props.errors[field]
    ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
    : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500',
];

function fieldError(field) {
  return props.errors[field]?.[0] ?? null;
}
</script>

<template>
  <tr class="align-top">
    <td class="p-2 min-w-[160px]">
      <select
        :class="fieldClass('company_id')"
        :value="row.company_id ?? ''"
        :data-row="index"
        data-field="company_id"
        @change="(e) => patch({ company_id: e.target.value === '' ? null : Number(e.target.value) })"
      >
        <option value="">Select…</option>
        <option v-for="company in companies" :key="company.id" :value="company.id">
          {{ company.name }}
        </option>
      </select>
      <p v-if="fieldError('company_id')" class="mt-1 text-xs text-red-600">{{ fieldError('company_id') }}</p>
    </td>
    <td class="p-2 min-w-[140px]">
      <input
        type="date"
        :class="fieldClass('date')"
        :value="row.date"
        :data-row="index"
        data-field="date"
        @input="(e) => patch({ date: e.target.value })"
      />
      <p v-if="fieldError('date')" class="mt-1 text-xs text-red-600">{{ fieldError('date') }}</p>
    </td>
    <td class="p-2 min-w-[180px]">
      <select
        :class="fieldClass('employee_id')"
        :value="row.employee_id ?? ''"
        :disabled="!row.company_id"
        :data-row="index"
        data-field="employee_id"
        @change="(e) => patch({ employee_id: e.target.value === '' ? null : Number(e.target.value) })"
      >
        <option value="">{{ row.company_id ? 'Select…' : 'Pick a company first' }}</option>
        <option v-for="employee in employees" :key="employee.id" :value="employee.id">
          {{ employee.name }}
        </option>
      </select>
      <p v-if="fieldError('employee_id')" class="mt-1 text-xs text-red-600">{{ fieldError('employee_id') }}</p>
    </td>
    <td class="p-2 min-w-[180px]">
      <select
        :class="fieldClass('project_id')"
        :value="row.project_id ?? ''"
        :disabled="!row.company_id"
        :data-row="index"
        data-field="project_id"
        @change="(e) => patch({ project_id: e.target.value === '' ? null : Number(e.target.value) })"
      >
        <option value="">{{ row.company_id ? 'Select…' : 'Pick a company first' }}</option>
        <option v-for="project in projects" :key="project.id" :value="project.id">
          {{ project.name }}
        </option>
      </select>
      <p v-if="fieldError('project_id')" class="mt-1 text-xs text-red-600">{{ fieldError('project_id') }}</p>
    </td>
    <td class="p-2 min-w-[160px]">
      <select
        :class="fieldClass('task_id')"
        :value="row.task_id ?? ''"
        :disabled="!row.company_id"
        :data-row="index"
        data-field="task_id"
        @change="(e) => patch({ task_id: e.target.value === '' ? null : Number(e.target.value) })"
      >
        <option value="">{{ row.company_id ? 'Select…' : 'Pick a company first' }}</option>
        <option v-for="task in tasks" :key="task.id" :value="task.id">
          {{ task.name }}
        </option>
      </select>
      <p v-if="fieldError('task_id')" class="mt-1 text-xs text-red-600">{{ fieldError('task_id') }}</p>
    </td>
    <td class="p-2 w-[100px]">
      <input
        type="number"
        step="0.25"
        min="0"
        max="24"
        :class="fieldClass('hours')"
        :value="row.hours ?? ''"
        :data-row="index"
        data-field="hours"
        @input="(e) => patch({ hours: e.target.value === '' ? null : Number(e.target.value) })"
      />
      <p v-if="fieldError('hours')" class="mt-1 text-xs text-red-600">{{ fieldError('hours') }}</p>
    </td>
    <td class="p-2 whitespace-nowrap text-right">
      <button
        type="button"
        class="text-xs text-slate-500 hover:text-slate-900"
        title="Duplicate this row"
        @click="$emit('duplicate')"
      >
        Duplicate
      </button>
      <button
        type="button"
        class="ml-2 text-xs text-red-500 hover:text-red-700"
        title="Remove this row"
        @click="$emit('remove')"
      >
        Remove
      </button>
    </td>
  </tr>
</template>
