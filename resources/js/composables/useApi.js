import axios from 'axios';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export const apiClient = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken,
  },
});

// Promise cache. Keys are URLs; values are in-flight or resolved promises.
// We cache the *promise* so concurrent callers (e.g. several rows opening
// the same company at once) share a single network request.
const cache = new Map();

/**
 * Cached GET. Falls back to network on cache miss; evicts on failure so the
 * next caller retries.
 */
export function cachedGet(url) {
  if (cache.has(url)) return cache.get(url);

  const promise = apiClient
    .get(url)
    .then(({ data }) => data.data ?? data)
    .catch((err) => {
      cache.delete(url);
      throw err;
    });

  cache.set(url, promise);
  return promise;
}

/** Drop every cache entry whose URL starts with `prefix`. */
export function invalidate(prefix) {
  for (const key of cache.keys()) {
    if (key.startsWith(prefix)) cache.delete(key);
  }
}
