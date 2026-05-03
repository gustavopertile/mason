<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
  tabs: {
    type: Array,
    required: true,
  },
  modelValue: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['update:modelValue']);

const navRef = ref(null);
const underlineStyle = ref({ width: '0px', transform: 'translateX(0)' });

async function moveUnderline() {
  await nextTick();
  if (!navRef.value) return;
  const active = navRef.value.querySelector(`[data-tab="${props.modelValue}"]`);
  if (!active) return;
  underlineStyle.value = {
    width: `${active.offsetWidth}px`,
    transform: `translateX(${active.offsetLeft}px)`,
  };
}

onMounted(moveUnderline);
watch(() => props.modelValue, moveUnderline);
</script>

<template>
  <nav
    ref="navRef"
    class="relative flex items-end border-b border-paper-line"
  >
    <div class="flex">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        :data-tab="tab.id"
        :class="[
          'relative px-3 py-2.5 text-sm font-medium transition-colors',
          modelValue === tab.id ? 'text-ink' : 'text-ink-soft hover:text-ink',
        ]"
        @click="emit('update:modelValue', tab.id)"
      >
        {{ tab.label }}
      </button>
    </div>
    <span class="tab-underline" :style="underlineStyle"></span>
  </nav>
</template>
