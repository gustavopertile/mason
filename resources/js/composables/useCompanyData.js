import { ref } from 'vue';
import { cachedGet } from './useApi';

/**
 * Loads (and caches) the full company list once for the page lifetime.
 * Multiple components calling `load()` share a single request.
 */
export function useCompanies() {
  const companies = ref([]);
  const loaded = ref(false);
  let loadPromise = null;

  async function load() {
    if (loaded.value) return;
    if (!loadPromise) {
      loadPromise = cachedGet('/companies').then((data) => {
        companies.value = data;
        loaded.value = true;
      });
    }
    await loadPromise;
  }

  return { companies, load, loaded };
}

export const fetchEmployeesForCompany = (companyId) =>
  cachedGet(`/companies/${companyId}/employees`);

export const fetchProjectsForCompany = (companyId) =>
  cachedGet(`/companies/${companyId}/projects`);

export const fetchTasksForCompany = (companyId) =>
  cachedGet(`/companies/${companyId}/tasks`);

export const fetchEmployeesForProject = (companyId, projectId) =>
  cachedGet(`/companies/${companyId}/projects/${projectId}/employees`);
