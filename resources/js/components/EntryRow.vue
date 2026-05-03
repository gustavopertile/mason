<script setup>
import { ref, watch, computed } from "vue";
import {
    fetchEmployeesForCompany,
    fetchProjectsForCompany,
    fetchTasksForCompany,
    fetchProjectsForEmployee,
    fetchEmployeesForProject,
} from "../composables/useCompanyData";

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
        // { company_id, date, employee_id, project_id, task_id, hours }
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
    canRemove: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(["update:modelValue", "duplicate", "remove"]);

const row = computed(() => props.modelValue);
const patch = (values) =>
    emit("update:modelValue", { ...row.value, ...values });

const projects = ref([]);
const tasks = ref([]);
const employees = ref([]);

async function refreshCompanyLists(companyId) {
    if (!companyId) {
        projects.value = [];
        tasks.value = [];
        employees.value = [];
        return;
    }
    // Loading employees + tasks always; projects load from the wider company
    // list initially, then narrow once an employee is picked.
    const [t, e, p] = await Promise.all([
        fetchTasksForCompany(companyId),
        fetchEmployeesForCompany(companyId),
        fetchProjectsForCompany(companyId),
    ]);
    tasks.value = t;
    employees.value = e;
    projects.value = p;
}

async function narrowProjectsToEmployee(companyId, employeeId) {
    if (!companyId) return;
    if (!employeeId) {
        // No employee selected: show every project on the company.
        projects.value = await fetchProjectsForCompany(companyId);
        return;
    }
    projects.value = await fetchProjectsForEmployee(companyId, employeeId);
}

async function narrowEmployeesToProject(companyId, projectId) {
    if (!companyId) return;
    if (!projectId) {
        // No project selected: show every employee on the company.
        employees.value = await fetchEmployeesForCompany(companyId);
        return;
    }
    employees.value = await fetchEmployeesForProject(companyId, projectId);
}

watch(
    () => row.value.company_id,
    async (id, prev) => {
        if (id === prev) return;
        await refreshCompanyLists(id);
        if (prev !== undefined) {
            patch({ employee_id: null, project_id: null, task_id: null });
        }
    },
    { immediate: true },
);

// Employee ↔ project narrow each other symmetrically: picking either side
// filters the opposite list and silently drops a now-invalid selection.
watch(
    () => row.value.employee_id,
    async (employeeId) => {
        await narrowProjectsToEmployee(row.value.company_id, employeeId);
        if (
            row.value.project_id &&
            !projects.value.some((p) => p.id === row.value.project_id)
        ) {
            patch({ project_id: null });
        }
    },
);

watch(
    () => row.value.project_id,
    async (projectId) => {
        await narrowEmployeesToProject(row.value.company_id, projectId);
        if (
            row.value.employee_id &&
            !employees.value.some((e) => e.id === row.value.employee_id)
        ) {
            patch({ employee_id: null });
        }
    },
);

const fieldClass = (field) => [
    "cell-field tabular-nums",
    props.errors[field] ? "cell-field-error" : "",
];

const fieldError = (field) => props.errors[field]?.[0] ?? null;
</script>

<template>
    <tr
        class="align-top border-b border-paper-line last:border-b-0 hover:bg-paper-tint/40 transition-colors"
    >
        <td class="px-2 py-2">
            <select
                :class="fieldClass('company_id')"
                :value="row.company_id ?? ''"
                :data-row="index"
                data-field="company_id"
                @change="
                    (e) =>
                        patch({
                            company_id:
                                e.target.value === ''
                                    ? null
                                    : Number(e.target.value),
                        })
                "
            >
                <option value="">—</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">
                    {{ c.name }}
                </option>
            </select>
            <p
                v-if="fieldError('company_id')"
                class="mt-1 text-[11px] text-danger break-words"
            >
                {{ fieldError("company_id") }}
            </p>
        </td>
        <td class="px-2 py-2">
            <input
                type="date"
                :class="fieldClass('date')"
                :value="row.date"
                :data-row="index"
                data-field="date"
                @input="(e) => patch({ date: e.target.value })"
            />
            <p
                v-if="fieldError('date')"
                class="mt-1 text-[11px] text-danger break-words"
            >
                {{ fieldError("date") }}
            </p>
        </td>
        <td class="px-2 py-2">
            <select
                :class="fieldClass('employee_id')"
                :value="row.employee_id ?? ''"
                :disabled="!row.company_id"
                :data-row="index"
                data-field="employee_id"
                @change="
                    (e) =>
                        patch({
                            employee_id:
                                e.target.value === ''
                                    ? null
                                    : Number(e.target.value),
                        })
                "
            >
                <option value="">
                    {{ row.company_id ? "—" : "pick a company" }}
                </option>
                <option v-for="e in employees" :key="e.id" :value="e.id">
                    {{ e.name }}
                </option>
            </select>
            <p
                v-if="fieldError('employee_id')"
                class="mt-1 text-[11px] text-danger break-words"
            >
                {{ fieldError("employee_id") }}
            </p>
        </td>
        <td class="px-2 py-2">
            <select
                :class="fieldClass('project_id')"
                :value="row.project_id ?? ''"
                :disabled="!row.company_id"
                :data-row="index"
                data-field="project_id"
                @change="
                    (e) =>
                        patch({
                            project_id:
                                e.target.value === ''
                                    ? null
                                    : Number(e.target.value),
                        })
                "
            >
                <option value="">
                    {{ !row.company_id ? "pick a company" : "—" }}
                </option>
                <option v-for="p in projects" :key="p.id" :value="p.id">
                    {{ p.name }}
                </option>
            </select>
            <p
                v-if="fieldError('project_id')"
                class="mt-1 text-[11px] text-danger break-words"
            >
                {{ fieldError("project_id") }}
            </p>
        </td>
        <td class="px-2 py-2">
            <select
                :class="fieldClass('task_id')"
                :value="row.task_id ?? ''"
                :disabled="!row.company_id"
                :data-row="index"
                data-field="task_id"
                @change="
                    (e) =>
                        patch({
                            task_id:
                                e.target.value === ''
                                    ? null
                                    : Number(e.target.value),
                        })
                "
            >
                <option value="">
                    {{ row.company_id ? "—" : "pick a company" }}
                </option>
                <option v-for="t in tasks" :key="t.id" :value="t.id">
                    {{ t.name }}
                </option>
            </select>
            <p
                v-if="fieldError('task_id')"
                class="mt-1 text-[11px] text-danger break-words"
            >
                {{ fieldError("task_id") }}
            </p>
        </td>
        <td class="px-2 py-2">
            <input
                type="number"
                step="1"
                min="0"
                max="24"
                inputmode="numeric"
                :class="[fieldClass('hours'), 'pr-2']"
                :value="row.hours ?? ''"
                :data-row="index"
                data-field="hours"
                @input="
                    (e) =>
                        patch({
                            hours:
                                e.target.value === ''
                                    ? null
                                    : Math.trunc(Number(e.target.value)),
                        })
                "
            />
            <p
                v-if="fieldError('hours')"
                class="mt-1 text-[11px] text-danger break-words"
            >
                {{ fieldError("hours") }}
            </p>
        </td>
        <td class="px-2 py-2 whitespace-nowrap text-right">
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-ink-soft transition-colors hover:bg-paper-tint hover:text-ink"
                title="Duplicate this row"
                @click="$emit('duplicate')"
            >
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 16 16"
                    fill="none"
                    aria-hidden="true"
                >
                    <rect
                        x="5.25"
                        y="5.25"
                        width="8.5"
                        height="8.5"
                        rx="1.5"
                        stroke="currentColor"
                        stroke-width="1.4"
                    />
                    <path
                        d="M3 11V3.75A1.75 1.75 0 014.75 2H11"
                        stroke="currentColor"
                        stroke-width="1.4"
                        stroke-linecap="round"
                    />
                </svg>
            </button>

            <button
                type="button"
                class="ml-1 inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-ink-soft transition-colors hover:bg-danger-bg hover:text-danger disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-ink-soft"
                :title="
                    canRemove
                        ? 'Remove this row'
                        : 'At least one row is required'
                "
                :disabled="!canRemove"
                @click="$emit('remove')"
            >
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 16 16"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M2.75 4.5h10.5M6.5 4.5V3.25A1.25 1.25 0 017.75 2h.5A1.25 1.25 0 019.5 3.25V4.5M5 4.5l.5 8.6A1.5 1.5 0 007 14.5h2A1.5 1.5 0 0010.5 13.1L11 4.5"
                        stroke="currentColor"
                        stroke-width="1.4"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>
        </td>
    </tr>
</template>
