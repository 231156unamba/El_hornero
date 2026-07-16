<script setup>
import { ref } from 'vue';

const props = defineProps({
  cajaForm: {
    type: Object,
    required: true,
    default: () => ({ nombre_comercial: '', ruc: '', direccion: '', telefono: '', yape_numero: '' })
  },
  cierreAdmin: {
    type: Object,
    required: true,
    default: () => ({ from: '', to: '', data: [] })
  }
});

const emit = defineEmits(['submit', 'update:cajaForm', 'update:cierreAdmin', 'export']);

const yapeQrFile = ref(null);

const onYapeQrChange = (e) => {
  const files = e?.target?.files;
  yapeQrFile.value = files && files[0] ? files[0] : null;
};
</script>

<template>
  <div>
    <form @submit.prevent="emit('submit')" style="padding: 20px;">
      <div class="form-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
        <div class="form-group">
          <label>Nombre Comercial</label>
          <input :value="cajaForm.nombre_comercial" @input="emit('update:cajaForm', { ...cajaForm, nombre_comercial: $event.target.value })" class="form-control" required>
        </div>
        <div class="form-group">
          <label>RUC</label>
          <input :value="cajaForm.ruc" @input="emit('update:cajaForm', { ...cajaForm, ruc: $event.target.value })" class="form-control" required>
        </div>
        <div class="form-group full-width">
          <label>Dirección</label>
          <input :value="cajaForm.direccion" @input="emit('update:cajaForm', { ...cajaForm, direccion: $event.target.value })" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Teléfono</label>
          <input :value="cajaForm.telefono" @input="emit('update:cajaForm', { ...cajaForm, telefono: $event.target.value })" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Número Yape</label>
          <input :value="cajaForm.yape_numero" @input="emit('update:cajaForm', { ...cajaForm, yape_numero: $event.target.value })" class="form-control" required>
        </div>
        <div class="form-group full-width">
          <label>QR Yape</label>
          <input type="file" class="form-control" accept="image/*" @change="onYapeQrChange">
        </div>
      </div>
      <div class="form-buttons" style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="submit" class="btn btn-solid-orange">Guardar</button>
      </div>
    </form>
    <div class="report-controls" style="margin-top: 16px;">
      <div class="control">
        <label>Desde</label>
        <input :value="cierreAdmin.from" @input="emit('update:cierreAdmin', { ...cierreAdmin, from: $event.target.value })" type="date" class="form-control">
      </div>
      <div class="control">
        <label>Hasta</label>
        <input :value="cierreAdmin.to" @input="emit('update:cierreAdmin', { ...cierreAdmin, to: $event.target.value })" type="date" class="form-control">
      </div>
      <div class="control">
        <label>&nbsp;</label>
        <button class="btn btn-solid-orange" @click="emit('export')">Exportar Cierre</button>
      </div>
    </div>
  </div>
</template>
