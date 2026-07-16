<script setup>
const props = defineProps({
  userForm: {
    type: Object,
    required: true,
    default: () => ({ id: null, usuario: '', nombres: '', apellidos: '', clave: '', tipo: 'pedido' })
  },
  userEditing: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['submit', 'clear', 'update:userForm']);
</script>

<template>
  <form class="add-form" @submit.prevent="emit('submit')" style="padding: 30px;">
    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px;">
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Usuario</label>
        <input :value="userForm.usuario" @input="emit('update:userForm', { ...userForm, usuario: $event.target.value })" class="form-control" placeholder="Ej. Yes" required>
      </div>
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Tipo</label>
        <select :value="userForm.tipo" @input="emit('update:userForm', { ...userForm, tipo: $event.target.value })" class="form-control" required>
          <option value="admin">Admin</option>
          <option value="cocina">Cocina</option>
          <option value="pedido">Pedido</option>
          <option value="caja">Caja</option>
        </select>
      </div>
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Nombres</label>
        <input :value="userForm.nombres" @input="emit('update:userForm', { ...userForm, nombres: $event.target.value })" class="form-control" placeholder="Ej. Jesus" required>
      </div>
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Apellidos</label>
        <input :value="userForm.apellidos" @input="emit('update:userForm', { ...userForm, apellidos: $event.target.value })" class="form-control" placeholder="Ej. Luna" required>
      </div>
      <div class="form-group">
        <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Clave</label>
        <input :value="userForm.clave" @input="emit('update:userForm', { ...userForm, clave: $event.target.value })" type="password" class="form-control" :required="!userEditing" placeholder="******">
        <small style="color:#777; margin-top: 4px; display: block;" v-if="userEditing">Dejar en blanco para mantener actual</small>
      </div>
    </div>
    <div class="form-buttons" style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
      <button type="button" class="btn" style="background: #ef5350; color: white; border: none; font-weight: 600;" @click="emit('clear')">Limpiar</button>
      <button type="button" class="btn btn-secondary" v-if="userEditing" @click="emit('clear')" style="background: #757575; color: white;">Cancelar</button>
      <button type="submit" class="btn btn-solid-orange">{{ userEditing ? 'Guardar Cambios' : 'Crear Usuario' }}</button>
    </div>
  </form>
</template>
