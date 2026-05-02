import { apiClient, invalidate } from './useApi';

export async function listTimeEntries(companyId = null) {
  const params = companyId ? { company_id: companyId } : {};
  const { data } = await apiClient.get('/time-entries', { params });
  return data.data;
}

export async function createTimeEntries(entries) {
  const { data } = await apiClient.post('/time-entries', { entries });
  // Lookup data may have changed indirectly — keep the lookup cache,
  // but if we ever cache time-entry GETs, we'd evict them here.
  invalidate('/time-entries');
  return data.data;
}
