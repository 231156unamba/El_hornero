<script setup>
import api from '../../api';
import '../../styles/modal.css';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  discountItem: {
    type: Object,
    default: null
  },
  discountForm: {
    type: Object,
    default: () => ({ discount_percentage: '', discount_expires_at: '' })
  }
});

const emit = defineEmits(['close', 'save', 'update:discountForm']);

const saveDiscount = async () => {
  if (!props.discountItem) return;
  try {
    const fd = new FormData();
    fd.append('discount_percentage', props.discountForm.discount_percentage || '');
    fd.append('discount_expires_at', props.discountForm.discount_expires_at || '');
    
    await api.put(`/menu/${props.discountItem.id}`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    emit('save');
    emit('close');
  } catch (e) {
    console.error('Error al guardar descuento:', e);
    alert('Error al guardar descuento: ' + (e.response?.data?.error || e.message));
  }
};
</script>

<template>
  <div v-if="show" class="modal-overlay" @click.self="emit('close')">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h3>Aplicar Descuento</h3>
        <button class="close-btn" @click="emit('close')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="modal-item-preview">
          <img v-if="discountItem?.imagen_url || discountItem?.imagen" :src="discountItem?.imagen_url || discountItem?.imagen" alt="">
          <div>
            <div class="modal-item-name">{{ discountItem?.nombre }}</div>
            <div class="modal-item-price">S/. {{ discountItem?.precio }}</div>
          </div>
        </div>
        <div class="form-group">
          <label>Porcentaje de descuento (%)</label>
          <input :value="discountForm.discount_percentage" @input="emit('update:discountForm', { ...discountForm, discount_percentage: $event.target.value })" type="number" min="0" max="100" class="form-control" placeholder="Ej. 15">
        </div>
        <div class="form-group">
          <label>Fecha de expiración</label>
          <input :value="discountForm.discount_expires_at" @input="emit('update:discountForm', { ...discountForm, discount_expires_at: $event.target.value })" type="date" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" @click="emit('close')">Cancelar</button>
        <button class="btn btn-solid-orange" @click="saveDiscount">Guardar Descuento</button>
      </div>
    </div>
  </div>
</template>
