<script setup>
import { ref, watch, inject, computed } from 'vue';
import { listTimeEntries } from '../composables/useTimeEntries';
import EditEntryModal from './EditEntryModal.vue';

const selectedCompanyId = inject('selectedCompanyId');

const search = ref('');
const fromDate = ref('');
const toDate = ref('');
const currentPage = ref(1);
const perPage = ref(20);

const entries = ref([]);
const meta = ref({ total: 0, current_page: 1, last_page: 1, per_page: 20 });
const loading = ref(false);
const error = ref(null);

const editing = ref(null);
let searchDebounce = null;

async function reload() {
  loading.value = true;
  error.value = null;
  try {
    const payload = await listTimeEntries({
      company_id: selectedCompanyId.value,
      search: search.value,
      from: fromDate.value,
      to: toDate.value,
      page: currentPage.value,
      per_page: perPage.value,
    });
    entries.value = payload.data;
    meta.value = payload.meta;
  } catch (err) {
    error.value = err.response?.data?.message ?? err.message ?? 'Failed to load entries.';
  } finally {
    loading.value = false;
  }
}

// Reset to page 1 whenever any filter changes; the global company filter
// counts as a filter too.
watch([selectedCompanyId, search, fromDate, toDate], (_, prev) => {
  currentPage.value = 1;
  // Debounce text-based search so we're not firing a request per keystroke.
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(reload, 200);
});

watch(currentPage, reload);
watch(perPage, () => {
  currentPage.value = 1;
  reload();
});

reload(); // initial load

const totalHours = computed(() =>
  entries.value.reduce((sum, e) => sum + Number(e.hours), 0),
);

const pageWindow = computed(() => {
  const last = meta.value.last_page || 1;
  const current = meta.value.current_page || 1;
  const start = Math.max(1, current - 2);
  const end = Math.min(last, start + 4);
  return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

function clearFilters() {
  search.value = '';
  fromDate.value = '';
  toDate.value = '';
}

function goToPage(page) {
  if (page < 1 || page > meta.value.last_page) return;
  currentPage.value = page;
}

function formatDate(iso) {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

function onEdited() {
  editing.value = null;
  reload();
}

function onDeleted() {
  editing.value = null;
  reload();
}
</script>

<template>
  <section class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
      <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
          <label class="block text-xs font-medium text-slate-600">Search</label>
          <input
            v-model="search"
            type="search"
            placeholder="Employee, project, task, or company…"
            class="w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600">From</label>
          <input
            v-model="fromDate"
            type="date"
            class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600">To</label>
          <input
            v-model="toDate"
            type="date"
            class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          />
        </div>
        <button
          v-if="search || fromDate || toDate"
          type="button"
          class="text-xs text-slate-500 hover:text-slate-900"
          @click="clearFilters"
        >
          Clear
        </button>
      </div>
    </div>

    <div class="flex items-center justify-between text-sm text-slate-600">
      <span v-if="loading">Loading…</span>
      <span v-else-if="error" class="text-red-600">{{ error }}</span>
      <span v-else>
        {{ meta.total }} {{ meta.total === 1 ? 'entry' : 'entries' }} ·
        <span class="font-medium text-slate-900">{{ totalHours.toFixed(2) }} h on this page</span>
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
            <th class="px-3 py-2"></th>
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
            <td class="px-3 py-2 text-right">
              <button
                type="button"
                class="text-xs text-slate-500 hover:text-slate-900"
                @click="editing = entry"
              >
                Edit
              </button>
            </td>
          </tr>
          <tr v-if="!loading && entries.length === 0">
            <td colspan="7" class="px-3 py-12 text-center text-sm text-slate-500">
              No entries match these filters.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="meta.last_page > 1" class="flex items-center justify-between text-sm">
      <div class="text-slate-500">
        Page {{ meta.current_page }} of {{ meta.last_page }}
      </div>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-40"
          :disabled="meta.current_page <= 1"
          @click="goToPage(meta.current_page - 1)"
        >
          ‹ Prev
        </button>
        <button
          v-for="page in pageWindow"
          :key="page"
          type="button"
          :class="[
            'rounded border px-2 py-1 text-xs',
            page === meta.current_page
              ? 'border-slate-900 bg-slate-900 text-white'
              : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50',
          ]"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>
        <button
          type="button"
          class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-40"
          :disabled="meta.current_page >= meta.last_page"
          @click="goToPage(meta.current_page + 1)"
        >
          Next ›
        </button>
      </div>
    </nav>

    <EditEntryModal
      v-if="editing"
      :entry="editing"
      @close="editing = null"
      @saved="onEdited"
      @deleted="onDeleted"
    />
  </section>
</template>
