<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import {
  useCompanies,
  fetchEmployeesForCompany,
  fetchProjectsForCompany,
  fetchTasksForCompany,
  fetchEmployeesForProject,
} from '../composables/useCompanyData';
import { updateTimeEntry, deleteTimeEntry } from '../composables/useTimeEntries';

const props = defineProps({
  entry: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['close', 'saved', 'deleted']);

const { companies, load: loadCompanies } = useCompanies();
onMounted(loadCompanies);

const form = ref({
  company_id: props.entry.company.id,
  date: props.entry.date,
  employee_id: props.entry.employee.id,
  project_id: props.entry.project.id,
  task_id: props.entry.task.id,
  hours: Number(props.entry.hours),
});

const projects = ref([]);
const tasks = ref([]);
const employees = ref([]);
const errors = ref({});
const saving = ref(false);
const flash = ref(null);

const fieldClass = (field) => [
  'w-full rounded border bg-white px-2 py-1.5 text-sm text-slate-900 shadow-sm',
  'focus:outline-none focus:ring-1',
  errors.value[field]
    ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
    : 'border-slate-300 focus:border-slate-500 focus:ring-slate-500',
];

const fieldError = (field) => errors.value[field]?.[0] ?? null;

async function loadCompanyLists(companyId) {
  if (!companyId) return;
  const [p, t, e] = await Promise.all([
    fetchProjectsForCompany(companyId),
    fetchTasksForCompany(companyId),
    fetchEmployeesForCompany(companyId),
  ]);
  projects.value = p;
  tasks.value = t;
  employees.value = e;
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
  () => form.value.company_id,
  async (id, prev) => {
    if (id === prev) return;
    await loadCompanyLists(id);
    if (prev !== undefined) {
      // Wipe child selections that no longer make sense (only when the
      // user changes the company themselves — not on the initial load).
      form.value.employee_id = null;
      form.value.project_id = null;
      form.value.task_id = null;
    } else {
      // Initial load — narrow employees to the project the entry already uses.
      await refreshProjectEmployees(id, form.value.project_id);
    }
  },
  { immediate: true },
);

watch(
  () => form.value.project_id,
  async (projectId) => {
    await refreshProjectEmployees(form.value.company_id, projectId);
    if (form.value.employee_id && !employees.value.some((e) => e.id === form.value.employee_id)) {
      form.value.employee_id = null;
    }
  },
);

async function save() {
  errors.value = {};
  flash.value = null;
  saving.value = true;
  try {
    const updated = await updateTimeEntry(props.entry.id, form.value);
    emit('saved', updated);
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors ?? {};
      flash.value = { type: 'error', message: 'Fix the highlighted fields and try again.' };
    } else {
      flash.value = { type: 'error', message: 'Something went wrong saving the entry.' };
    }
  } finally {
    saving.value = false;
  }
}

async function destroy() {
  if (!window.confirm('Delete this entry? This cannot be undone.')) return;
  saving.value = true;
  try {
    await deleteTimeEntry(props.entry.id);
    emit('deleted', props.entry.id);
  } catch {
    flash.value = { type: 'error', message: "Couldn't delete the entry." };
  } finally {
    saving.value = false;
  }
}

function onBackdropClick(event) {
  if (event.target === event.currentTarget) emit('close');
}
</script>

<template>
  <div
    class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/40 px-4 py-12"
    @click="onBackdropClick"
    @keydown.esc="$emit('close')"
  >
    <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-start justify-between">
        <div>
          <h2 class="text-lg font-semibold">Edit time entry</h2>
          <p class="mt-0.5 text-xs text-slate-500">Adjust any field. Validation runs server-side.</p>
        </div>
        <button
          type="button"
          class="text-slate-400 hover:text-slate-700"
          aria-label="Close"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <div
        v-if="flash"
        :class="[
          'mb-4 rounded border px-3 py-2 text-sm',
          flash.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800',
        ]"
      >
        {{ flash.message }}
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600">Company</label>
          <select
            :class="fieldClass('company_id')"
            :value="form.company_id ?? ''"
            @change="(e) => (form.company_id = e.target.value ? Number(e.target.value) : null)"
          >
            <option value="">Select…</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
          <p v-if="fieldError('company_id')" class="mt-1 text-xs text-red-600">{{ fieldError('company_id') }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600">Date</label>
          <input
            type="date"
            :class="fieldClass('date')"
            :value="form.date"
            @input="(e) => (form.date = e.target.value)"
          />
          <p v-if="fieldError('date')" class="mt-1 text-xs text-red-600">{{ fieldError('date') }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600">Project</label>
          <select
            :class="fieldClass('project_id')"
            :value="form.project_id ?? ''"
            :disabled="!form.company_id"
            @change="(e) => (form.project_id = e.target.value ? Number(e.target.value) : null)"
          >
            <option value="">Select…</option>
            <option v-for="project in projects" :key="project.id" :value="project.id">
              {{ project.name }}
            </option>
          </select>
          <p v-if="fieldError('project_id')" class="mt-1 text-xs text-red-600">{{ fieldError('project_id') }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600">Employee</label>
          <select
            :class="fieldClass('employee_id')"
            :value="form.employee_id ?? ''"
            :disabled="!form.company_id"
            @change="(e) => (form.employee_id = e.target.value ? Number(e.target.value) : null)"
          >
            <option value="">Select…</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
              {{ employee.name }}
            </option>
          </select>
          <p v-if="fieldError('employee_id')" class="mt-1 text-xs text-red-600">{{ fieldError('employee_id') }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600">Task</label>
          <select
            :class="fieldClass('task_id')"
            :value="form.task_id ?? ''"
            :disabled="!form.company_id"
            @change="(e) => (form.task_id = e.target.value ? Number(e.target.value) : null)"
          >
            <option value="">Select…</option>
            <option v-for="task in tasks" :key="task.id" :value="task.id">
              {{ task.name }}
            </option>
          </select>
          <p v-if="fieldError('task_id')" class="mt-1 text-xs text-red-600">{{ fieldError('task_id') }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600">Hours</label>
          <input
            type="number"
            step="0.25"
            min="0"
            max="24"
            :class="fieldClass('hours')"
            :value="form.hours ?? ''"
            @input="(e) => (form.hours = e.target.value === '' ? null : Number(e.target.value))"
          />
          <p v-if="fieldError('hours')" class="mt-1 text-xs text-red-600">{{ fieldError('hours') }}</p>
        </div>
      </div>

      <div class="mt-6 flex items-center justify-between">
        <button
          type="button"
          class="text-sm text-red-600 hover:text-red-800"
          :disabled="saving"
          @click="destroy"
        >
          Delete
        </button>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            :disabled="saving"
            @click="$emit('close')"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded bg-slate-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
