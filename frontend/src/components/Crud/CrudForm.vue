<script setup>
import { ref, watch } from 'vue';
import './CrudForm.css';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Formulario'
  },
  size: {
    type: String,
    default: 'medium', // small, medium, large
    validator: (value) => ['small', 'medium', 'large'].includes(value)
  }
});

const emit = defineEmits(['close', 'submit']);

const isAnimating = ref(false);

// Detecta si el mousedown comenzó dentro del modal para evitar cierre accidental
let mousedownInsideModal = false;

watch(() => props.show, (newVal) => {
  if (newVal) {
    setTimeout(() => {
      isAnimating.value = true;
    }, 10);
  } else {
    isAnimating.value = false;
  }
});

const handleClose = () => {
  isAnimating.value = false;
  setTimeout(() => {
    emit('close');
  }, 200);
};

const handleSubmit = () => {
  emit('submit');
};

// El overlay sólo cierra si el mousedown Y el click ocurrieron en el overlay
// (no si el usuario arrastró desde dentro del modal hasta el overlay)
const handleOverlayMousedown = (e) => {
  mousedownInsideModal = e.target !== e.currentTarget;
};

const handleOverlayClick = (e) => {
  if (e.target === e.currentTarget && !mousedownInsideModal) {
    handleClose();
  }
  mousedownInsideModal = false;
};
</script>

<template>
  <div
    v-if="show"
    class="crud-modal-overlay"
    @mousedown="handleOverlayMousedown"
    @click="handleOverlayClick"
  >
    <div
      :class="['crud-modal', `crud-modal-${size}`, { 'crud-modal-open': isAnimating }]"
      @mousedown.stop
    >
      <div class="crud-modal-header">
        <h2 class="crud-modal-title">{{ title }}</h2>
        <button class="crud-modal-close" type="button" @click="handleClose">&times;</button>
      </div>
      <div class="crud-modal-body">
        <slot></slot>
      </div>
      <div class="crud-modal-footer">
        <slot name="footer">
          <button class="crud-btn crud-btn-secondary" type="button" @click="handleClose">Cancelar</button>
          <button class="crud-btn crud-btn-primary" type="button" @click="handleSubmit">Guardar</button>
        </slot>
      </div>
    </div>
  </div>
</template>
