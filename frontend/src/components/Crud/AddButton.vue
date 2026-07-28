<script setup>
import './AddButton.css';

const props = defineProps({
  label: {
    type: String,
    default: 'Agregar nuevo'
  }
});

const emit = defineEmits(['click']);
</script>

<template>
  <div class="add-button-wrapper">
    <div class="add-button-container" @click="emit('click')">
      <div class="add-button-icon">+</div>
      <div class="add-button-label">{{ label }}</div>
    </div>
  </div>
</template>
