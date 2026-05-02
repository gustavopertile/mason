<script setup>
import { ref, inject, onMounted, computed, watch } from 'vue';
import EntryRow from './EntryRow.vue';
import { useCompanies } from '../composables/useCompanyData';
import { createTimeEntries } from '../composables/useTimeEntries';

const selectedCompanyId = inject('selectedCompanyId');
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
const errors = ref({}); // { rowIndex: { field: ['msg'] } }
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
  // Drop errors for the removed row and shift remaining ones.
  const next = {};
  Object.entries(errors.value).forEach(([key, value]) => {
    const idx = Number(key);
    if (idx < index) next[idx] = value;
    if (idx > index) next[idx - 1] = value;
  });
  errors.value = next;
}

// When the global filter changes to a specific company, prefill *empty*
// company slots — but never overwrite a value the user already chose.
watch(selectedCompanyId, (next) => {
  if (!next) return;
  rows.value = rows.value.map((row) =>
    row.company_id == null ? { ...row, company_id: next } : row,
  );
});

function onKeydown(event) {
  const mod = event.metaKey || event.ctrlKey;
  if (!mod) return;
  if (event.key === 'Enter') {
    event.preventDefault();
    submit();
  } else if (event.key.toLowerCase() === 'd') {
    event.preventDefault();
    duplicateRow(rows.value.length - 1);
  }
}

async function submit() {
  errors.value = {};
  flash.value = null;
  submitting.value = true;
  try {
    const created = await createTimeEntries(rows.value);
    flash.value = {
      type: 'success',
      message: `Saved ${created.length} ${created.length === 1 ? 'entry' : 'entries'}.`,
    };
    rows.value = [blankRow()];
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
        type: 'error',
        message: 'Some rows have problems. Fix the highlighted fields and resubmit.',
      };
    } else {
      flash.value = { type: 'error', message: 'Something went wrong saving your entries.' };
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <section class="space-y-4" @keydown="onKeydown">
    <div
      v-if="flash"
      :class="[
        'rounded border px-4 py-3 text-sm',
        flash.type === 'success'
          ? 'border-green-200 bg-green-50 text-green-800'
          : 'border-red-200 bg-red-50 text-red-800',
      ]"
      role="status"
    >
      {{ flash.message }}
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-2 py-2 font-medium">Company</th>
            <th class="px-2 py-2 font-medium">Date</th>
            <th class="px-2 py-2 font-medium">Employee</th>
            <th class="px-2 py-2 font-medium">Project</th>
            <th class="px-2 py-2 font-medium">Task</th>
            <th class="px-2 py-2 font-medium">Hours</th>
            <th class="px-2 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <EntryRow
            v-for="(row, i) in rows"
            :key="i"
            :index="i"
            :model-value="row"
            :companies="companies"
            :errors="errors[i] ?? {}"
            @update:model-value="rows[i] = $event"
            @duplicate="duplicateRow(i)"
            @remove="removeRow(i)"
          />
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
          @click="addRow"
        >
          + Add row
        </button>
        <p class="text-xs text-slate-500">
          <kbd class="rounded border border-slate-300 px-1 py-0.5">⌘D</kbd> duplicate last ·
          <kbd class="rounded border border-slate-300 px-1 py-0.5">⌘↵</kbd> submit
        </p>
      </div>

      <div class="flex items-center gap-4">
        <div class="text-sm text-slate-600">
          {{ rows.length }} {{ rows.length === 1 ? 'row' : 'rows' }} ·
          <span class="font-medium text-slate-900">{{ totalHours.toFixed(2) }} h</span>
        </div>
        <button
          type="button"
          :disabled="submitting"
          class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          @click="submit"
        >
          {{ submitting ? 'Saving…' : 'Submit entries' }}
        </button>
      </div>
    </div>
  </section>
</template>
