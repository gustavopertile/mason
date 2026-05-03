import { ref } from 'vue';
import { cachedGet } from './useApi';

// Module-level singleton: every caller of `useCompanies()` shares the same
// reactive ref. The previous version created per-component refs and relied on
// the network cache to dedupe — this avoids that duplication entirely.
const companiesRef = ref([]);
const loadedRef = ref(false);
let loadPromise = null;

/**
 * Loads (and caches) the full company list once for the page lifetime.
 * Multiple components calling `load()` share a single request and a single
 * reactive list.
 */
export function useCompanies() {
  async function load() {
    if (loadedRef.value) return;
    if (!loadPromise) {
      loadPromise = cachedGet('/companies').then((data) => {
        companiesRef.value = data;
        loadedRef.value = true;
      });
    }
    await loadPromise;
  }

  return { companies: companiesRef, load, loaded: loadedRef };
}

export const fetchEmployeesForCompany = (companyId) =>
  cachedGet(`/companies/${companyId}/employees`);

export const fetchProjectsForCompany = (companyId) =>
  cachedGet(`/companies/${companyId}/projects`);

export const fetchTasksForCompany = (companyId) =>
  cachedGet(`/companies/${companyId}/tasks`);

export const fetchEmployeesForProject = (companyId, projectId) =>
  cachedGet(`/companies/${companyId}/projects/${projectId}/employees`);

export const fetchProjectsForEmployee = (companyId, employeeId) =>
  cachedGet(`/companies/${companyId}/employees/${employeeId}/projects`);
