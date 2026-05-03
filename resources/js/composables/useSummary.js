import { ref } from 'vue';
import { apiClient, invalidate } from './useApi';

export function useSummary() {
  const summary = ref(null);
  const loading = ref(false);
  const error = ref(null);

  async function load(companyId = null) {
    loading.value = true;
    error.value = null;
    try {
      const params = companyId ? { company_id: companyId } : {};
      const { data } = await apiClient.get('/summary', { params });
      summary.value = data.data;
    } catch (err) {
      error.value = err.response?.data?.message ?? err.message ?? 'Failed to load summary.';
    } finally {
      loading.value = false;
    }
  }

  // Allows other features (after a save / delete) to invalidate any cached
  // summary fetch the page might have made.
  function reset() {
    invalidate('/summary');
  }

  return { summary, loading, error, load, reset };
}
