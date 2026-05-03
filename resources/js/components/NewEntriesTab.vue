<script setup>
import { ref, inject, onMounted, onBeforeUnmount, computed, watch } from "vue";
import EntryRow from "./EntryRow.vue";
import { useCompanies } from "../composables/useCompanyData";
import { createTimeEntries } from "../composables/useTimeEntries";
import { useToast } from "../composables/useToast";

const toast = useToast();

const selectedCompanyId = inject("selectedCompanyId");
const refreshSummary = inject("refreshSummary", () => {});
const { companies, load: loadCompanies } = useCompanies();
onMounted(loadCompanies);

// Local-date YYYY-MM-DD. `toISOString()` would emit UTC, so around midnight in
// non-UTC timezones the default would be off by one day.
const today = () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
};

function focusedRowIndex() {
    const el = document.activeElement;
    const rowEl = el?.closest?.("[data-row]");
    if (!rowEl) return null;
    const idx = Number(rowEl.dataset.row);
    return Number.isFinite(idx) ? idx : null;
}

const blankRow = () => ({
    company_id: selectedCompanyId.value ?? null,
    date: today(),
    employee_id: null,
    project_id: null,
    task_id: null,
    hours: 0,
});

const rows = ref([blankRow()]);
const errors = ref({});
const submitting = ref(false);

const totalHours = computed(() =>
    rows.value.reduce((sum, row) => sum + (Number(row.hours) || 0), 0),
);

// When the global dropdown changes to a specific company, every row's
// company is set to it as a default. The user can still change the company
// per row afterwards — the global dropdown only seeds new rows and re-seeds
// existing ones. Switching back to "All" leaves the rows untouched.
watch(selectedCompanyId, (next) => {
    if (next == null) return;
    rows.value = rows.value.map((row) =>
        row.company_id === next ? row : { ...row, company_id: next },
    );
});

function addRow() {
    rows.value.push(blankRow());
}

function duplicateRow(index) {
    rows.value.splice(index + 1, 0, { ...rows.value[index] });
}

function removeRow(index) {
    rows.value.splice(index, 1);
    if (rows.value.length === 0) addRow();
    const next = {};
    Object.entries(errors.value).forEach(([key, value]) => {
        const idx = Number(key);
        if (idx < index) next[idx] = value;
        if (idx > index) next[idx - 1] = value;
    });
    errors.value = next;
}

function onKeydown(event) {
    const mod = event.metaKey || event.ctrlKey;
    if (!mod) return;
    if (event.key === "Enter") {
        event.preventDefault();
        submit();
    } else if (event.key.toLowerCase() === "d") {
        event.preventDefault();
        const idx = focusedRowIndex();
        duplicateRow(idx ?? rows.value.length - 1);
    } else if (event.key.toLowerCase() === "b") {
        event.preventDefault();
        addRow();
    }
}

onMounted(() => window.addEventListener("keydown", onKeydown));
onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));

async function submit() {
    errors.value = {};
    submitting.value = true;
    try {
        const created = await createTimeEntries(rows.value);
        toast.success(
            `Saved ${created.length} ${created.length === 1 ? "entry" : "entries"}.`,
        );
        rows.value = [blankRow()];
        refreshSummary();
    } catch (err) {
        if (err.response?.status === 422) {
            const flat = err.response.data.errors ?? {};
            const grouped = {};
            for (const key of Object.keys(flat)) {
                const match = key.match(/^entries\.(\d+)\.(.+)$/);
                if (!match) continue;
                const [, idx, field] = match;
                grouped[idx] ??= {};
                grouped[idx][field] = flat[key];
            }
            errors.value = grouped;
            toast.error(
                "Some rows have problems. Fix the highlighted fields and resubmit.",
            );
        } else {
            toast.error("Something went wrong saving your entries.");
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="space-y-4">
        <div
            class="overflow-x-auto rounded-lg border border-paper-line bg-paper"
        >
            <table class="w-full table-fixed text-sm">
                <colgroup>
                    <col style="width: 60px" />
                    <col style="width: 60px" />
                    <col style="width: 60px" />
                    <col style="width: 60px" />
                    <col style="width: 60px" />
                    <col style="width: 24px" />
                    <col style="width: 30px" />
                </colgroup>
                <thead>
                    <tr class="border-b border-paper-line bg-paper-tint/60">
                        <th
                            v-for="header in [
                                'Company',
                                'Date',
                                'Employee',
                                'Project',
                                'Task',
                                'Hours',
                                '',
                            ]"
                            :key="header"
                            class="px-3 py-2.5 text-left text-xs font-medium text-ink-soft"
                        >
                            {{ header }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <EntryRow
                        v-for="(row, i) in rows"
                        :key="i"
                        :index="i"
                        :model-value="row"
                        :companies="companies"
                        :errors="errors[i] ?? {}"
                        :can-remove="rows.length > 1"
                        @update:model-value="rows[i] = $event"
                        @duplicate="duplicateRow(i)"
                        @remove="removeRow(i)"
                    />
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button type="button" class="btn btn-secondary" @click="addRow">
                    <span aria-hidden="true">+</span> Add row
                </button>
                <p class="text-xs text-ink-mute">
                    <kbd
                        class="rounded border border-paper-line bg-paper-tint px-1 py-0.5 text-[10px] font-medium text-ink-soft"
                        >⌘B</kbd
                    >
                    new
                    <span class="mx-1">·</span>
                    <kbd
                        class="rounded border border-paper-line bg-paper-tint px-1 py-0.5 text-[10px] font-medium text-ink-soft"
                        >⌘D</kbd
                    >
                    duplicate
                    <span class="mx-1">·</span>
                    <kbd
                        class="rounded border border-paper-line bg-paper-tint px-1 py-0.5 text-[10px] font-medium text-ink-soft"
                        >⌘↵</kbd
                    >
                    submit
                </p>
            </div>

            <div class="flex items-center gap-5">
                <div class="text-right">
                    <p class="text-xs text-ink-mute">
                        {{ rows.length }}
                        {{ rows.length === 1 ? "row" : "rows" }} · Total
                    </p>
                    <p
                        class="text-xl font-semibold leading-tight text-ink tabular-nums"
                    >
                        {{ totalHours
                        }}<span class="ml-0.5 text-xs font-normal text-ink-mute"
                            >h</span
                        >
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="submitting"
                    class="btn btn-primary"
                    @click="submit"
                >
                    {{ submitting ? "Saving…" : "Submit entries" }}
                </button>
            </div>
        </div>
    </section>
</template>
