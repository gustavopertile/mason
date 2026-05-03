<script setup>
import { onMounted, watch, computed, inject } from "vue";
import { useSummary } from "../composables/useSummary";

const selectedCompanyId = inject("selectedCompanyId");
const { summary, load: loadSummary, loading } = useSummary();

onMounted(() => loadSummary(selectedCompanyId.value));

watch(selectedCompanyId, (id) => loadSummary(id));

defineExpose({ refresh: () => loadSummary(selectedCompanyId.value) });

const scopeLabel = computed(
    () => summary.value?.company_name ?? "all companies",
);

function formatHours(value) {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

const stats = computed(() => {
    const s = summary.value;
    if (!s) return [];
    const isAll = s.scope === "all";
    return [
        {
            key: "month",
            label: s.month_label,
            value: formatHours(s.hours_this_month),
            unit: "hrs",
            sub: `${s.entries_this_month} ${s.entries_this_month === 1 ? "entry" : "entries"}`,
        },
        {
            key: "total",
            label: "All time",
            value: formatHours(s.total_hours),
            unit: "hrs",
            sub: `${s.total_entries} ${s.total_entries === 1 ? "entry" : "entries"}`,
        },
        {
            key: "people",
            label: "Employees",
            value: String(s.employees_count),
            unit: "",
            sub: isAll
                ? `across ${s.companies_count} ${s.companies_count === 1 ? "company" : "companies"}`
                : `on ${s.company_name}`,
        },
        {
            key: "projects",
            label: "Projects",
            value: String(s.projects_count),
            unit: "",
            sub: `${s.tasks_count} ${s.tasks_count === 1 ? "task" : "tasks"} defined`,
        },
    ];
});
</script>

<template>
    <section
        class="rounded-lg border border-paper-line bg-paper px-5 py-5 sm:px-6 sm:py-6"
    >
        <div
            class="flex items-center justify-between border-b border-paper-line pb-4"
        >
            <p class="text-xs text-ink-mute">
                Showing {{ scopeLabel }}
            </p>
            <p v-if="loading" class="text-xs text-ink-mute">Loading…</p>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-4">
            <div
                v-for="(stat, i) in stats"
                :key="stat.key"
                class="rise"
                :style="{ 'animation-delay': `${i * 50}ms` }"
            >
                <p class="text-xs text-ink-mute">{{ stat.label }}</p>
                <p class="mt-1 flex items-baseline gap-1">
                    <span
                        class="text-2xl font-semibold leading-none tracking-[var(--tracking-tight)] text-ink tabular-nums"
                    >
                        {{ stat.value }}
                    </span>
                    <span v-if="stat.unit" class="text-xs text-ink-mute">{{
                        stat.unit
                    }}</span>
                </p>
                <p class="mt-1.5 text-xs text-ink-soft">{{ stat.sub }}</p>
            </div>
        </div>
    </section>
</template>
