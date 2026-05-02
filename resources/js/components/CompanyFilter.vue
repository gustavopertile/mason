<script setup>
defineProps({ modelValue: { type: [Number, null], default: null } });
defineEmits(['update:modelValue']);
</script>

<template>
  <div class="text-sm text-slate-400">[CompanyFilter]</div>
</template>
