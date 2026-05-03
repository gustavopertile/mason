<script setup>
import { ref, provide, computed } from "vue";
import SummaryCard from "./components/SummaryCard.vue";
import Tabs from "./components/Tabs.vue";
import NewEntriesTab from "./components/NewEntriesTab.vue";
import HistoryTab from "./components/HistoryTab.vue";

// `null` => All companies; otherwise a specific company id.
const selectedCompanyId = ref(null);
provide("selectedCompanyId", selectedCompanyId);

const summaryRef = ref(null);
// Children that mutate time entries call this so the SummaryCard refreshes.
provide("refreshSummary", () => summaryRef.value?.refresh());

const activeTab = ref("new");
const tabs = [
    { id: "new", label: "New Entries" },
    { id: "history", label: "History" },
];

const today = computed(() =>
    new Date().toLocaleDateString(undefined, {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }),
);
</script>

<template>
    <div class="min-h-screen">
        <div class="mx-auto max-w-7xl px-6 py-10 sm:py-14 space-y-8">
            <header class="flex items-start justify-between gap-6">
                <div>
                    <h1
                        class="text-[2rem] font-semibold leading-tight tracking-[var(--tracking-display)] text-ink sm:text-[2.25rem]"
                    >
                        Time Entries
                    </h1>
                    <p class="mt-1.5 text-sm text-ink-soft">
                        Hours worked, by company, employee, project, and task.
                    </p>
                </div>
                <p class="hidden text-sm text-ink-mute tabular-mono sm:block">
                    {{ today }}
                </p>
            </header>

            <SummaryCard ref="summaryRef" v-model="selectedCompanyId" />

            <Tabs v-model="activeTab" :tabs="tabs" />

            <NewEntriesTab v-if="activeTab === 'new'" />
            <HistoryTab v-else />
        </div>
    </div>
</template>
