<script setup>
import { ref, watch } from 'vue';
import CrudForm from '../Crud/CrudForm.vue';
import api from '../../api';
import './UserForm.css';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  user: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['success', 'close']);

const form = ref({
  id: null,
  usuario: '',
  nombres: '',
  apellidos: '',
  clave: '',
  tipo: 'pedido'
});

const errors = ref({});

// Cargar datos del usuario y limpiar errores cada vez que el modal se abre
watch(() => props.show, (newVal) => {
  if (newVal && props.user) {
    form.value = {
      id:        props.user.id,
      usuario:   props.user.usuario   || '',
      nombres:   props.user.nombres   || '',
      apellidos: props.user.apellidos || '',
      clave:     '',
      tipo:      props.user.tipo      || 'pedido'
    };
    errors.value = {};
  }
});

const validate = () => {
  errors.value = {};
  
  if (!form.value.usuario.trim()) {
    errors.value.usuario = 'El usuario es requerido';
  }
  if (!form.value.nombres.trim()) {
    errors.value.nombres = 'Los nombres son requeridos';
  }
  if (!form.value.apellidos.trim()) {
    errors.value.apellidos = 'Los apellidos son requeridos';
  }
  
  return Object.keys(errors.value).length === 0;
};

const handleSubmit = async () => {
  if (!validate()) return;
  
  try {
    const payload = {
      usuario: form.value.usuario,
      nombres: form.value.nombres,
      apellidos: form.value.apellidos,
      tipo: form.value.tipo
    };
    
    if (form.value.clave.trim()) {
      payload.clave = form.value.clave;
    }
    
    await api.put(`/admin/usuarios/${form.value.id}`, payload);
    
    emit('success');
    handleClose();
  } catch (err) {
    const errorData = err?.response?.data;
    
    if (errorData?.errors) {
      errors.value = {};
      Object.keys(errorData.errors).forEach(key => {
        errors.value[key] = Array.isArray(errorData.errors[key]) 
          ? errorData.errors[key][0] 
          : errorData.errors[key];
      });
    } else {
      alert(errorData?.error || errorData?.message || 'Error al actualizar usuario');
    }
  }
};

const handleClose = () => {
  emit('close');
};
</script>

<template>
  <CrudForm
    :show="props.show"
    title="Editar Usuario"
    size="medium"
    @close="handleClose"
    @submit="handleSubmit"
  >
    <div class="user-form-grid">
      <div class="user-form-group">
        <label class="user-form-label">Usuario</label>
        <input
          v-model="form.usuario"
          type="text"
          class="user-form-input"
          :class="{ 'user-form-input-error': errors.usuario }"
          placeholder="Ej. jsmith"
        />
        <span v-if="errors.usuario" class="user-form-error">{{ errors.usuario }}</span>
      </div>
      
      <div class="user-form-group">
        <label class="user-form-label">Tipo</label>
        <select
          v-model="form.tipo"
          class="user-form-input"
          :class="{ 'user-form-input-error': errors.tipo }"
        >
          <option value="admin">Administrador</option>
          <option value="cocina">Cocinero</option>
          <option value="pedido">Mesero</option>
          <option value="caja">Cajero</option>
        </select>
        <span v-if="errors.tipo" class="user-form-error">{{ errors.tipo }}</span>
      </div>
      
      <div class="user-form-group">
        <label class="user-form-label">Nombres</label>
        <input
          v-model="form.nombres"
          type="text"
          class="user-form-input"
          :class="{ 'user-form-input-error': errors.nombres }"
          placeholder="Ej. Jesús"
        />
        <span v-if="errors.nombres" class="user-form-error">{{ errors.nombres }}</span>
      </div>
      
      <div class="user-form-group">
        <label class="user-form-label">Apellidos</label>
        <input
          v-model="form.apellidos"
          type="text"
          class="user-form-input"
          :class="{ 'user-form-input-error': errors.apellidos }"
          placeholder="Ej. Morales"
        />
        <span v-if="errors.apellidos" class="user-form-error">{{ errors.apellidos }}</span>
      </div>
      
      <div class="user-form-group user-form-group-full">
        <label class="user-form-label">Clave</label>
        <input
          v-model="form.clave"
          type="password"
          class="user-form-input"
          :class="{ 'user-form-input-error': errors.clave }"
          placeholder="Dejar en blanco para mantener actual"
        />
        <span v-if="errors.clave" class="user-form-error">{{ errors.clave }}</span>
        <small class="user-form-hint">Dejar en blanco para mantener la clave actual</small>
      </div>
    </div>
  </CrudForm>
</template>
