import { apiClient, invalidate } from './useApi';

/**
 * Returns the full paginated payload (`{ data, links, meta }`) so the
 * caller can render pagination controls alongside the rows.
 */
export async function listTimeEntries(params = {}) {
  const cleaned = Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== ''),
  );
  const { data } = await apiClient.get('/time-entries', { params: cleaned });
  return data;
}

export async function createTimeEntries(entries) {
  const { data } = await apiClient.post('/time-entries', { entries });
  invalidate('/time-entries');
  return data.data;
}

export async function updateTimeEntry(id, payload) {
  const { data } = await apiClient.put(`/time-entries/${id}`, payload);
  invalidate('/time-entries');
  return data.data;
}

export async function deleteTimeEntry(id) {
  await apiClient.delete(`/time-entries/${id}`);
  invalidate('/time-entries');
}
