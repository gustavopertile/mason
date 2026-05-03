<script setup>
import { ref, watch, inject, computed } from 'vue';
import { listTimeEntries } from '../composables/useTimeEntries';
import EditEntryModal from './EditEntryModal.vue';

const selectedCompanyId = inject('selectedCompanyId');
const refreshSummary = inject('refreshSummary', () => {});

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

watch([selectedCompanyId, search, fromDate, toDate], () => {
  currentPage.value = 1;
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(reload, 200);
});

watch(currentPage, reload);
watch(perPage, () => {
  currentPage.value = 1;
  reload();
});

reload();

const totalHoursOnPage = computed(() =>
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
  const date = new Date(iso);
  return date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}

function onEdited() {
  editing.value = null;
  reload();
  refreshSummary();
}

function onDeleted() {
  editing.value = null;
  reload();
  refreshSummary();
}

const filtersActive = computed(() => Boolean(search.value || fromDate.value || toDate.value));
</script>

<template>
  <section class="space-y-4">
    <div class="flex flex-wrap items-end gap-x-3 gap-y-3">
      <div class="flex-1 min-w-[240px]">
        <label class="mb-1 block text-xs text-ink-mute">Search</label>
        <input
          v-model="search"
          type="search"
          placeholder="Employee, project, task, or company"
          class="field"
        />
      </div>
      <div>
        <label class="mb-1 block text-xs text-ink-mute">From</label>
        <input v-model="fromDate" type="date" class="field tabular-nums" />
      </div>
      <div>
        <label class="mb-1 block text-xs text-ink-mute">To</label>
        <input v-model="toDate" type="date" class="field tabular-nums" />
      </div>
      <button
        v-if="filtersActive"
        type="button"
        class="btn btn-ghost"
        @click="clearFilters"
      >
        Clear filters
      </button>
    </div>

    <div class="flex items-center justify-between text-xs text-ink-mute">
      <span v-if="loading">Loading…</span>
      <span v-else-if="error" class="text-danger">{{ error }}</span>
      <span v-else>
        {{ meta.total }} {{ meta.total === 1 ? 'entry' : 'entries' }}
        ·
        {{ Math.round(totalHoursOnPage) }}h on this page
      </span>
      <button
        type="button"
        class="text-ink-soft hover:text-ink"
        :disabled="loading"
        @click="reload"
      >
        Refresh
      </button>
    </div>

    <div class="overflow-hidden rounded-lg border border-paper-line bg-paper">
      <table class="w-full table-fixed text-sm">
        <colgroup>
          <col style="width: 12%" />
          <col style="width: 16%" />
          <col style="width: 16%" />
          <col style="width: 20%" />
          <col style="width: 16%" />
          <col style="width: 9%" />
          <col style="width: 11%" />
        </colgroup>
        <thead>
          <tr class="border-b border-paper-line bg-paper-tint/60">
            <th class="px-3 py-2.5 text-left text-xs font-medium text-ink-soft">Date</th>
            <th class="px-3 py-2.5 text-left text-xs font-medium text-ink-soft">Company</th>
            <th class="px-3 py-2.5 text-left text-xs font-medium text-ink-soft">Employee</th>
            <th class="px-3 py-2.5 text-left text-xs font-medium text-ink-soft">Project</th>
            <th class="px-3 py-2.5 text-left text-xs font-medium text-ink-soft">Task</th>
            <th class="px-3 py-2.5 text-right text-xs font-medium text-ink-soft">Hours</th>
            <th class="px-3 py-2.5"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="entry in entries"
            :key="entry.id"
            class="group border-b border-paper-line transition-colors last:border-b-0 hover:bg-paper-tint"
          >
            <td class="px-3 py-2.5 tabular-nums text-ink-soft truncate">{{ formatDate(entry.date) }}</td>
            <td class="px-3 py-2.5 text-ink truncate" :title="entry.company.name">{{ entry.company.name }}</td>
            <td class="px-3 py-2.5 text-ink truncate" :title="entry.employee.name">{{ entry.employee.name }}</td>
            <td class="px-3 py-2.5 text-ink truncate" :title="entry.project.name">{{ entry.project.name }}</td>
            <td class="px-3 py-2.5 text-ink truncate" :title="entry.task.name">{{ entry.task.name }}</td>
            <td class="px-3 py-2.5 text-right tabular-nums text-ink">{{ Math.round(Number(entry.hours)) }}</td>
            <td class="px-3 py-2.5 text-right">
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-ink-soft opacity-0 transition hover:bg-paper hover:text-ink group-hover:opacity-100 focus:opacity-100"
                @click="editing = entry"
              >
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M11 2.5l2.5 2.5-7.5 7.5H3.5V10L11 2.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                </svg>
                Edit
              </button>
            </td>
          </tr>
          <tr v-if="!loading && entries.length === 0">
            <td colspan="7" class="px-6 py-16 text-center">
              <p class="text-sm font-medium text-ink">No entries yet</p>
              <p class="mt-1 text-xs text-ink-mute">
                Nothing matches these filters.
              </p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav
      v-if="meta.last_page > 1"
      class="flex items-center justify-between pt-2 text-xs text-ink-mute"
    >
      <div>Page {{ meta.current_page }} of {{ meta.last_page }}</div>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="rounded-md border border-paper-line bg-paper px-2 py-1 text-ink-soft transition-colors hover:bg-paper-tint hover:text-ink disabled:opacity-30 disabled:hover:bg-paper"
          :disabled="meta.current_page <= 1"
          @click="goToPage(meta.current_page - 1)"
        >
          ‹
        </button>
        <button
          v-for="page in pageWindow"
          :key="page"
          type="button"
          :class="[
            'rounded-md border px-2.5 py-1 transition-colors',
            page === meta.current_page
              ? 'border-ink bg-ink text-paper'
              : 'border-paper-line bg-paper text-ink-soft hover:bg-paper-tint hover:text-ink',
          ]"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>
        <button
          type="button"
          class="rounded-md border border-paper-line bg-paper px-2 py-1 text-ink-soft transition-colors hover:bg-paper-tint hover:text-ink disabled:opacity-30 disabled:hover:bg-paper"
          :disabled="meta.current_page >= meta.last_page"
          @click="goToPage(meta.current_page + 1)"
        >
          ›
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
