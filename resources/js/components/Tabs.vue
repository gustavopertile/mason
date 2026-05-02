<script setup>
defineProps({
  tabs: {
    type: Array,
    required: true,
  },
  modelValue: {
    type: String,
    required: true,
  },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <nav class="flex gap-1 border-b border-slate-200">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      type="button"
      :class="[
        'px-4 py-2 text-sm font-medium -mb-px border-b-2 transition-colors',
        modelValue === tab.id
          ? 'border-slate-900 text-slate-900'
          : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300',
      ]"
      @click="$emit('update:modelValue', tab.id)"
    >
      {{ tab.label }}
    </button>
  </nav>
</template>
