<script setup>
import { ref, inject, onMounted, onBeforeUnmount, computed, watch } from "vue";
import EntryRow from "./EntryRow.vue";
import { useCompanies } from "../composables/useCompanyData";
import { createTimeEntries } from "../composables/useTimeEntries";

const selectedCompanyId = inject("selectedCompanyId");
const refreshSummary = inject("refreshSummary", () => {});
const { companies, load: loadCompanies } = useCompanies();
onMounted(loadCompanies);

const today = () => new Date().toISOString().slice(0, 10);

const blankRow = () => ({
    company_id: selectedCompanyId.value ?? null,
    date: today(),
    employee_id: null,
    project_id: null,
    task_id: null,
    hours: null,
});

const rows = ref([blankRow()]);
const errors = ref({});
const submitting = ref(false);
const flash = ref(null);

const totalHours = computed(() =>
    rows.value.reduce((sum, row) => sum + (Number(row.hours) || 0), 0),
);

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

watch(selectedCompanyId, (next) => {
    if (!next) return;
    rows.value = rows.value.map((row) =>
        row.company_id == null ? { ...row, company_id: next } : row,
    );
});

function onKeydown(event) {
    const mod = event.metaKey || event.ctrlKey;
    if (!mod) return;
    if (event.key === "Enter") {
        event.preventDefault();
        submit();
    } else if (event.key.toLowerCase() === "d") {
        event.preventDefault();
        duplicateRow(rows.value.length - 1);
    } else if (event.key.toLowerCase() === "b") {
        event.preventDefault();
        addRow();
    }
}

onMounted(() => window.addEventListener("keydown", onKeydown));
onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));

async function submit() {
    errors.value = {};
    flash.value = null;
    submitting.value = true;
    try {
        const created = await createTimeEntries(rows.value);
        flash.value = {
            type: "success",
            message: `Saved ${created.length} ${created.length === 1 ? "entry" : "entries"}.`,
        };
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
            flash.value = {
                type: "error",
                message:
                    "Some rows have problems. Fix the highlighted fields and resubmit.",
            };
        } else {
            flash.value = {
                type: "error",
                message: "Something went wrong saving your entries.",
            };
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="space-y-4">
        <div
            v-if="flash"
            :class="[
                'rounded-md border px-3.5 py-2 text-sm',
                flash.type === 'success'
                    ? 'border-success bg-success-bg text-success'
                    : 'border-danger bg-danger-bg text-danger',
            ]"
            role="status"
        >
            {{ flash.message }}
        </div>

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
