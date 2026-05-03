import { ref } from 'vue';

let nextId = 0;
const toasts = ref([]);

function push(type, message, options = {}) {
    const id = ++nextId;
    toasts.value.push({ id, type, message });
    const duration = options.duration ?? (type === 'error' ? 6000 : 4000);
    if (duration > 0) {
        setTimeout(() => dismiss(id), duration);
    }
    return id;
}

function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

export function useToast() {
    return {
        toasts,
        success: (message, options) => push('success', message, options),
        error: (message, options) => push('error', message, options),
        dismiss,
    };
}
