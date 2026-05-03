<script setup>
import { useToast } from '../composables/useToast';

const { toasts, dismiss } = useToast();
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end"
        aria-live="polite"
        aria-atomic="true"
    >
        <TransitionGroup name="toast">
            <div
                v-for="t in toasts"
                :key="t.id"
                :class="[
                    'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-md border px-3.5 py-2.5 text-sm shadow-sm',
                    t.type === 'success'
                        ? 'border-success bg-success-bg text-success'
                        : 'border-danger bg-danger-bg text-danger',
                ]"
                role="status"
            >
                <span class="flex-1">{{ t.message }}</span>
                <button
                    type="button"
                    class="-mr-1 shrink-0 rounded p-0.5 opacity-60 transition-opacity hover:opacity-100"
                    aria-label="Dismiss"
                    @click="dismiss(t.id)"
                >
                    <svg
                        width="12"
                        height="12"
                        viewBox="0 0 16 16"
                        fill="none"
                    >
                        <path
                            d="M3.5 3.5L12.5 12.5M12.5 3.5L3.5 12.5"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                        />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-0.5rem);
}
.toast-move {
    transition: transform 0.2s ease;
}
</style>
