<script setup>
import { ref, provide } from 'vue';
import CompanyFilter from './components/CompanyFilter.vue';
import Tabs from './components/Tabs.vue';
import NewEntriesTab from './components/NewEntriesTab.vue';
import HistoryTab from './components/HistoryTab.vue';

// `null` means the global "All" view; otherwise a specific company id.
const selectedCompanyId = ref(null);
provide('selectedCompanyId', selectedCompanyId);

const activeTab = ref('new');
const tabs = [
  { id: 'new', label: 'New Entries' },
  { id: 'history', label: 'History' },
];
</script>

<template>
  <div class="min-h-screen">
    <div class="max-w-6xl mx-auto px-6 py-8 space-y-6">
      <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Time Entries</h1>
          <p class="text-sm text-slate-500 mt-1">
            Log hours per company, employee, and project.
          </p>
        </div>
        <CompanyFilter v-model="selectedCompanyId" />
      </header>

      <Tabs v-model="activeTab" :tabs="tabs" />

      <NewEntriesTab v-if="activeTab === 'new'" />
      <HistoryTab v-else />
    </div>
  </div>
</template>
