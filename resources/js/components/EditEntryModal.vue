<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick, inject } from 'vue';
import {
  useCompanies,
  fetchEmployeesForCompany,
  fetchProjectsForCompany,
  fetchTasksForCompany,
  fetchProjectsForEmployee,
  fetchEmployeesForProject,
} from '../composables/useCompanyData';
import { updateTimeEntry, deleteTimeEntry } from '../composables/useTimeEntries';
import { useToast } from '../composables/useToast';

const toast = useToast();

const props = defineProps({
  entry: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['close', 'saved', 'deleted']);

const refreshSummary = inject('refreshSummary', () => {});

const { companies, load: loadCompanies } = useCompanies();
onMounted(loadCompanies);

const form = ref({
  company_id: props.entry.company.id,
  date: props.entry.date,
  employee_id: props.entry.employee.id,
  project_id: props.entry.project.id,
  task_id: props.entry.task.id,
  hours: props.entry.hours,
});

const projects = ref([]);
const tasks = ref([]);
const employees = ref([]);
const errors = ref({});
const saving = ref(false);
const confirmingDelete = ref(false);
const modalPanelRef = ref(null);
let previousActiveElement = null;

let companyListsReqId = 0;
let projectsReqId = 0;
let employeesReqId = 0;

const fieldClass = (field) => [
  'field tabular-mono',
  errors.value[field] ? 'field-error' : '',
];

const fieldError = (field) => errors.value[field]?.[0] ?? null;

async function loadCompanyLists(companyId) {
  if (!companyId) return;
  const id = ++companyListsReqId;
  const t = await fetchTasksForCompany(companyId);
  if (id !== companyListsReqId) return;
  tasks.value = t;
}

async function narrowProjectsToEmployee(companyId, employeeId) {
  if (!companyId) {
    projects.value = [];
    return;
  }
  const id = ++projectsReqId;
  const result = !employeeId
    ? await fetchProjectsForCompany(companyId)
    : await fetchProjectsForEmployee(companyId, employeeId);
  if (id !== projectsReqId) return;
  projects.value = result;
}

async function narrowEmployeesToProject(companyId, projectId) {
  if (!companyId) {
    employees.value = [];
    return;
  }
  const id = ++employeesReqId;
  const result = !projectId
    ? await fetchEmployeesForCompany(companyId)
    : await fetchEmployeesForProject(companyId, projectId);
  if (id !== employeesReqId) return;
  employees.value = result;
}

watch(
  () => form.value.company_id,
  async (id, prev) => {
    if (id === prev) return;
    if (prev !== undefined) {
      form.value.employee_id = null;
      form.value.project_id = null;
      form.value.task_id = null;
    }
    // On initial mount the existing entry's employee/project narrow each
    // side. After a company change those are null, so each side ends up as
    // the full per-company list.
    await Promise.all([
      loadCompanyLists(id),
      narrowProjectsToEmployee(id, form.value.employee_id),
      narrowEmployeesToProject(id, form.value.project_id),
    ]);
  },
  { immediate: true },
);

watch(
  () => form.value.employee_id,
  async (employeeId) => {
    await narrowProjectsToEmployee(form.value.company_id, employeeId);
    if (form.value.project_id && !projects.value.some((p) => p.id === form.value.project_id)) {
      form.value.project_id = null;
    }
  },
);

watch(
  () => form.value.project_id,
  async (projectId) => {
    await narrowEmployeesToProject(form.value.company_id, projectId);
    if (form.value.employee_id && !employees.value.some((e) => e.id === form.value.employee_id)) {
      form.value.employee_id = null;
    }
  },
);

async function save() {
  errors.value = {};
  saving.value = true;
  try {
    const updated = await updateTimeEntry(props.entry.id, form.value);
    refreshSummary();
    emit('saved', updated);
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors ?? {};
      toast.error('Fix the highlighted fields and try again.');
    } else {
      toast.error('Something went wrong saving the entry.');
    }
  } finally {
    saving.value = false;
  }
}

async function destroy() {
  saving.value = true;
  try {
    await deleteTimeEntry(props.entry.id);
    refreshSummary();
    emit('deleted', props.entry.id);
  } catch {
    toast.error("Couldn't delete the entry.");
    confirmingDelete.value = false;
  } finally {
    saving.value = false;
  }
}

function getFocusable() {
  if (!modalPanelRef.value) return [];
  return [...modalPanelRef.value.querySelectorAll(
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )];
}

// Trap Tab/Shift-Tab inside the modal and close on Esc. Listening on
// `document` (not the overlay) so it works regardless of where focus is —
// otherwise focus could escape behind the modal and the keys wouldn't fire.
function onKeydown(e) {
  if (e.key === 'Escape') {
    e.preventDefault();
    emit('close');
    return;
  }
  if (e.key !== 'Tab') return;
  const focusable = getFocusable();
  if (focusable.length === 0) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  const active = document.activeElement;
  const insideModal = modalPanelRef.value?.contains(active);

  if (e.shiftKey) {
    if (!insideModal || active === first) {
      e.preventDefault();
      last.focus();
    }
  } else if (!insideModal || active === last) {
    e.preventDefault();
    first.focus();
  }
}

onMounted(async () => {
  previousActiveElement = document.activeElement;
  document.addEventListener('keydown', onKeydown);
  await nextTick();
  // Place focus on the first form field rather than the close button so the
  // user can start editing immediately.
  const firstField = modalPanelRef.value?.querySelector('select, input');
  if (firstField) firstField.focus();
  else getFocusable()[0]?.focus();
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  // Restore focus to whatever launched the modal so keyboard navigation feels
  // continuous for screen-reader and keyboard users.
  if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
    previousActiveElement.focus();
  }
});

function onBackdropClick(event) {
  if (event.target === event.currentTarget) emit('close');
}
</script>

<template>
  <div
    class="modal-overlay fixed inset-0 z-40 flex items-start justify-center overflow-y-auto px-4 py-12"
    style="background-color: rgba(15, 15, 15, 0.45);"
    role="dialog"
    aria-modal="true"
    aria-labelledby="edit-entry-title"
    @click="onBackdropClick"
  >
    <div
      ref="modalPanelRef"
      class="modal-panel w-full max-w-xl rounded-lg border border-paper-line bg-paper p-6 shadow-xl"
    >
      <div class="mb-5 flex items-start justify-between">
        <div>
          <p class="text-xs text-ink-mute">Entry #{{ entry.id }}</p>
          <h2
            id="edit-entry-title"
            class="mt-0.5 text-xl font-semibold leading-tight tracking-[var(--tracking-tight)] text-ink"
          >
            Edit entry
          </h2>
        </div>
        <button
          type="button"
          class="rounded-md p-1 text-ink-mute transition-colors hover:bg-paper-tint hover:text-ink"
          aria-label="Close"
          @click="$emit('close')"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M3.5 3.5L12.5 12.5M12.5 3.5L3.5 12.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <div class="grid grid-cols-1 gap-x-4 gap-y-3.5 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs text-ink-mute">
            Company
          </label>
          <select
            :class="fieldClass('company_id')"
            :value="form.company_id ?? ''"
            aria-label="Company"
            @change="(e) => (form.company_id = e.target.value ? Number(e.target.value) : null)"
          >
            <option value="">—</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <p v-if="fieldError('company_id')" class="mt-1 text-[11px] text-danger">{{ fieldError('company_id') }}</p>
        </div>

        <div>
          <label class="mb-1 block text-xs text-ink-mute">
            Date
          </label>
          <input
            type="date"
            :class="fieldClass('date')"
            :value="form.date"
            aria-label="Date"
            @input="(e) => (form.date = e.target.value)"
          />
          <p v-if="fieldError('date')" class="mt-1 text-[11px] text-danger">{{ fieldError('date') }}</p>
        </div>

        <div>
          <label class="mb-1 block text-xs text-ink-mute">
            Employee
          </label>
          <select
            :class="fieldClass('employee_id')"
            :value="form.employee_id ?? ''"
            :disabled="!form.company_id"
            aria-label="Employee"
            @change="(e) => (form.employee_id = e.target.value ? Number(e.target.value) : null)"
          >
            <option value="">—</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
          <p v-if="fieldError('employee_id')" class="mt-1 text-[11px] text-danger">{{ fieldError('employee_id') }}</p>
        </div>

        <div>
          <label class="mb-1 block text-xs text-ink-mute">
            Project
          </label>
          <select
            :class="fieldClass('project_id')"
            :value="form.project_id ?? ''"
            :disabled="!form.company_id"
            aria-label="Project"
            @change="(e) => (form.project_id = e.target.value ? Number(e.target.value) : null)"
          >
            <option value="">—</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <p v-if="fieldError('project_id')" class="mt-1 text-[11px] text-danger">{{ fieldError('project_id') }}</p>
        </div>

        <div>
          <label class="mb-1 block text-xs text-ink-mute">
            Task
          </label>
          <select
            :class="fieldClass('task_id')"
            :value="form.task_id ?? ''"
            :disabled="!form.company_id"
            aria-label="Task"
            @change="(e) => (form.task_id = e.target.value ? Number(e.target.value) : null)"
          >
            <option value="">—</option>
            <option v-for="t in tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <p v-if="fieldError('task_id')" class="mt-1 text-[11px] text-danger">{{ fieldError('task_id') }}</p>
        </div>

        <div>
          <label class="mb-1 block text-xs text-ink-mute">
            Hours
          </label>
          <input
            type="number"
            step="1"
            min="1"
            max="24"
            inputmode="numeric"
            :class="[fieldClass('hours'), 'text-right']"
            :value="form.hours ?? ''"
            aria-label="Hours"
            @input="(e) => (form.hours = e.target.value === '' ? null : Math.trunc(Number(e.target.value)))"
          />
          <p v-if="fieldError('hours')" class="mt-1 text-[11px] text-danger">{{ fieldError('hours') }}</p>
        </div>
      </div>

      <div class="mt-6 flex items-center justify-between border-t border-paper-line pt-4">
        <template v-if="!confirmingDelete">
          <button
            type="button"
            class="btn btn-danger"
            :disabled="saving"
            @click="confirmingDelete = true"
          >
            Delete entry
          </button>
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="btn btn-secondary"
              :disabled="saving"
              @click="$emit('close')"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="saving"
              @click="save"
            >
              {{ saving ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </template>
        <template v-else>
          <span class="text-sm font-medium text-danger">
            Delete this entry? This cannot be undone.
          </span>
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="btn btn-secondary"
              :disabled="saving"
              @click="confirmingDelete = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-danger"
              :disabled="saving"
              @click="destroy"
            >
              {{ saving ? 'Deleting…' : 'Confirm delete' }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
