<script setup>
import { onMounted, watch, computed } from "vue";
import { useCompanies } from "../composables/useCompanyData";
import { useSummary } from "../composables/useSummary";

const props = defineProps({
    modelValue: {
        type: [Number, null],
        default: null,
    },
});

const emit = defineEmits(["update:modelValue"]);

const { companies, load: loadCompanies } = useCompanies();
const { summary, load: loadSummary, loading } = useSummary();

onMounted(async () => {
    await Promise.all([loadCompanies(), loadSummary(props.modelValue)]);
});

watch(
    () => props.modelValue,
    (id) => loadSummary(id),
);

defineExpose({ refresh: () => loadSummary(props.modelValue) });

const selectValue = computed(() =>
    props.modelValue === null ? "" : String(props.modelValue),
);

function onSelect(event) {
    const raw = event.target.value;
    emit("update:modelValue", raw === "" ? null : Number(raw));
}

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
            class="flex flex-wrap items-center justify-between gap-3 border-b border-paper-line pb-4"
        >
            <div class="flex items-center gap-2">
                <span class="text-xs text-ink-mute">Showing</span>
                <div class="relative">
                    <select
                        :value="selectValue"
                        class="cursor-pointer appearance-none rounded-md border border-paper-line bg-paper py-1 pl-2.5 pr-7 text-sm font-medium text-ink transition-colors hover:bg-paper-tint focus:border-accent focus:outline-none"
                        @change="onSelect"
                    >
                        <option value="">All companies</option>
                        <option
                            v-for="c in companies"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-ink-mute"
                        width="10"
                        height="10"
                        viewBox="0 0 12 12"
                        fill="none"
                    >
                        <path
                            d="M2.5 4.5L6 8l3.5-3.5"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
            </div>

            <div v-if="loading" class="text-xs text-ink-mute">Loading…</div>
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
