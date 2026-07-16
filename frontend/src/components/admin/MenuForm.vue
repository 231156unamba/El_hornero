<script setup>
const props = defineProps({
  form: {
    type: Object,
    required: true,
    default: () => ({ id: null, nombre: '', precio: '', categoria: 'comida', descripcion: '' })
  },
  editing: {
    type: Boolean,
    default: false
  },
  currentImageUrl: {
    type: String,
    default: ''
  },
  imagenName: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['submit', 'clear', 'update:form', 'imageChange']);
</script>

<template>
  <form class="add-form" @submit.prevent="emit('submit')" style="padding: 30px;">
    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px;">
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Nombre del Plato</label>
        <input :value="form.nombre" @input="emit('update:form', { ...form, nombre: $event.target.value })" class="form-control" placeholder="Ej. Monstrito" required>
      </div>
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Categoría</label>
        <select :value="form.categoria" @input="emit('update:form', { ...form, categoria: $event.target.value })" class="form-control" required>
          <option value="comida">Comida</option>
          <option value="bebidas">Bebidas</option>
          <option value="promociones">Promociones</option>
        </select>
      </div>
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Precio (S/.)</label>
        <input :value="form.precio" @input="emit('update:form', { ...form, precio: $event.target.value })" type="number" step="0.01" class="form-control" placeholder="0.00" required>
      </div>
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Imagen del Plato</label>
        <input type="file" class="form-control" accept="image/*" :required="!editing" @change="emit('imageChange', $event)">
        <div v-if="editing && currentImageUrl" style="margin-top:8px; display:flex; align-items:center; gap:10px;">
          <img :src="currentImageUrl" alt="" style="width:64px; height:64px; object-fit:cover; border:1px solid #ddd;">
          <small style="color:#777;">Imagen actual. Si no subes una nueva, se mantiene.</small>
        </div>
        <div v-if="imagenName" style="margin-top:6px; color:#555; font-size:12px;">Seleccionado: {{ imagenName }}</div>
      </div>
      <div class="form-group" style="grid-column: span 2;">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Descripción</label>
        <textarea :value="form.descripcion" @input="emit('update:form', { ...form, descripcion: $event.target.value })" class="form-control" rows="3" placeholder="Detalla los ingredientes y la presentación del plato..." required></textarea>
      </div>
    </div>
    <div class="form-buttons" style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
      <button type="button" class="btn" style="background: #ef5350; color: white; border: none; font-weight: 600;" @click="emit('clear')">Limpiar</button>
      <button type="button" class="btn btn-secondary" v-if="editing" @click="emit('clear')" style="background: #757575; color: white;">Cancelar</button>
      <button type="submit" class="btn btn-solid-orange">{{ editing ? 'Guardar Cambios' : 'Agregar Plato' }}</button>
    </div>
  </form>
</template>
