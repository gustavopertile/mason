<script setup>
import { ref, watch, inject, computed } from 'vue';
import { listTimeEntries } from '../composables/useTimeEntries';

const selectedCompanyId = inject('selectedCompanyId');
const entries = ref([]);
const loading = ref(false);
const error = ref(null);

async function reload() {
  loading.value = true;
  error.value = null;
  try {
    entries.value = await listTimeEntries(selectedCompanyId.value);
  } catch (err) {
    error.value = err.message ?? 'Failed to load entries.';
  } finally {
    loading.value = false;
  }
}

watch(selectedCompanyId, reload, { immediate: true });

const totalHours = computed(() =>
  entries.value.reduce((sum, entry) => sum + Number(entry.hours), 0),
);

function formatDate(iso) {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between text-sm text-slate-600">
      <span v-if="loading">Loading…</span>
      <span v-else-if="error" class="text-red-600">{{ error }}</span>
      <span v-else>
        {{ entries.length }} {{ entries.length === 1 ? 'entry' : 'entries' }}
        ·
        <span class="font-medium text-slate-900">{{ totalHours.toFixed(2) }} h total</span>
      </span>
      <button
        type="button"
        class="text-xs text-slate-500 hover:text-slate-900"
        :disabled="loading"
        @click="reload"
      >
        Refresh
      </button>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-3 py-2 font-medium">Date</th>
            <th class="px-3 py-2 font-medium">Company</th>
            <th class="px-3 py-2 font-medium">Employee</th>
            <th class="px-3 py-2 font-medium">Project</th>
            <th class="px-3 py-2 font-medium">Task</th>
            <th class="px-3 py-2 font-medium text-right">Hours</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="entry in entries" :key="entry.id" class="hover:bg-slate-50">
            <td class="px-3 py-2 tabular-nums">{{ formatDate(entry.date) }}</td>
            <td class="px-3 py-2">{{ entry.company.name }}</td>
            <td class="px-3 py-2">{{ entry.employee.name }}</td>
            <td class="px-3 py-2">{{ entry.project.name }}</td>
            <td class="px-3 py-2">{{ entry.task.name }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ Number(entry.hours).toFixed(2) }}</td>
          </tr>
          <tr v-if="!loading && entries.length === 0">
            <td colspan="6" class="px-3 py-12 text-center text-sm text-slate-500">
              No entries yet.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
