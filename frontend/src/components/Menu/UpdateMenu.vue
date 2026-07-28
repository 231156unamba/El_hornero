<script setup>
import { ref, computed, watch } from 'vue';
import CrudForm from '../Crud/CrudForm.vue';
import api from '../../api';
import './MenuForm.css';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  item: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['success', 'close']);

// ── Categorías dinámicas ──
// Valores exactos según el ENUM de la BD: comida, bebida, promocion
const categorias = ref([
  { value: 'comida',    label: 'Comida'    },
  { value: 'bebida',    label: 'Bebida'    },
  { value: 'promocion', label: 'Promoción' }
]);

const loadCategorias = async () => {
  try {
    const r = await api.get('/menu');
    const raw = r.data || [];
    const seen = new Set();
    const found = [];
    raw.forEach(item => {
      const v = (item.categoria || '').toLowerCase().trim();
      if (v && !seen.has(v)) {
        seen.add(v);
        const labelMap = { comida: 'Comida', bebida: 'Bebida', promocion: 'Promoción' };
        found.push({ value: v, label: labelMap[v] ?? (v.charAt(0).toUpperCase() + v.slice(1)) });
      }
    });
    if (found.length > 0) categorias.value = found;
  } catch {
    // mantiene valores por defecto
  }
};

// ── Textos dinámicos según categoría ──
const categoryMeta = {
  comida:    { tituloAgregar: 'Agregar nueva comida',    tituloEditar: 'Editar comida',    labelNombre: 'Nombre de la comida',    labelImagen: 'Imagen de la comida'    },
  bebida:    { tituloAgregar: 'Agregar nueva bebida',    tituloEditar: 'Editar bebida',    labelNombre: 'Nombre de la bebida',    labelImagen: 'Imagen de la bebida'    },
  promocion: { tituloAgregar: 'Agregar nueva promoción', tituloEditar: 'Editar promoción', labelNombre: 'Nombre de la promoción', labelImagen: 'Imagen de la promoción' },
};

const defaultMeta = { tituloAgregar: 'Agregar nuevo artículo', tituloEditar: 'Editar artículo', labelNombre: 'Nombre del artículo', labelImagen: 'Imagen del artículo' };

const getMeta = (cat) => categoryMeta[(cat ?? '').toLowerCase().trim()] ?? defaultMeta;

const form = ref({
  id: null,
  nombre: '',
  precio: '',
  categoria: 'comida',
  descripcion: ''
});

const imagenFile   = ref(null);
const imagenName   = ref('');
const imagenPreview = ref(null);
const currentImageUrl = ref(null);
const errors = ref({});

const apiOrigin = new URL(api.defaults.baseURL).origin;

const menuImageUrl = (item) => {
  if (!item) return '';
  if (item.imagen_url) return item.imagen_url;
  const img = item.imagen;
  if (!img) return '';
  if (/^https?:\/\//i.test(img)) return img;
  if (img.startsWith('/')) return apiOrigin + img;
  return `${apiOrigin}/images/menu/${img}`;
};

// Textos reactivos
const meta = computed(() => getMeta(form.value.categoria));
const modalTitle  = computed(() => meta.value.tituloEditar);
const labelNombre = computed(() => meta.value.labelNombre);
const labelImagen = computed(() => meta.value.labelImagen);

// Cargar datos del item cuando el modal se abre
watch(() => props.show, (newVal) => {
  if (newVal && props.item) {
    form.value = {
      id:          props.item.id,
      nombre:      props.item.nombre      || '',
      precio:      props.item.precio      || '',
      categoria:   props.item.categoria   || 'comida',
      descripcion: props.item.descripcion || ''
    };
    imagenFile.value    = null;
    imagenName.value    = '';
    imagenPreview.value = null;
    currentImageUrl.value = menuImageUrl(props.item) || null;
    errors.value = {};
    loadCategorias();
  }
});

const validate = () => {
  errors.value = {};
  
  if (!form.value.nombre.trim()) {
    errors.value.nombre = 'El nombre es requerido';
  }
  if (!form.value.precio || parseFloat(form.value.precio) <= 0) {
    errors.value.precio = 'El precio debe ser mayor a 0';
  }
  if (!form.value.descripcion.trim()) {
    errors.value.descripcion = 'La descripción es requerida';
  }
  
  return Object.keys(errors.value).length === 0;
};

const handleImageChange = (e) => {
  const files = e?.target?.files;
  const file  = files && files[0] ? files[0] : null;
  
  if (file) {
    imagenFile.value    = file;
    imagenName.value    = file.name;
    const reader = new FileReader();
    reader.onload = (ev) => {
      imagenPreview.value = ev.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    imagenFile.value    = null;
    imagenName.value    = '';
    imagenPreview.value = null;
  }
};

const handleSubmit = async () => {
  if (!validate()) return;
  
  try {
    const fd = new FormData();
    fd.append('nombre',      form.value.nombre);
    fd.append('precio',      String(form.value.precio));
    fd.append('categoria',   form.value.categoria);
    fd.append('descripcion', form.value.descripcion);
    fd.append('_method',     'PUT');
    
    if (imagenFile.value) {
      fd.append('imagen', imagenFile.value);
    }
    
    const r = await api.post(`/menu/${form.value.id}`, fd);
    
    if (!r.data?.success) {
      alert(r.data?.error || 'No se pudo actualizar el artículo.');
      return;
    }
    
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
      alert(errorData?.error || errorData?.message || 'Error al actualizar artículo');
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
    :title="modalTitle"
    size="large"
    @close="handleClose"
    @submit="handleSubmit"
  >
    <div class="menu-form-grid">
      <div class="menu-form-group">
        <label class="menu-form-label">{{ labelNombre }}</label>
        <input
          v-model="form.nombre"
          type="text"
          class="menu-form-input"
          :class="{ 'menu-form-input-error': errors.nombre }"
          placeholder="Ej. Pollo a la brasa"
        />
        <span v-if="errors.nombre" class="menu-form-error">{{ errors.nombre }}</span>
      </div>

      <div class="menu-form-group">
        <label class="menu-form-label">Categoría</label>
        <select
          v-model="form.categoria"
          class="menu-form-input"
          :class="{ 'menu-form-input-error': errors.categoria }"
        >
          <option
            v-for="cat in categorias"
            :key="cat.value"
            :value="cat.value"
          >{{ cat.label }}</option>
        </select>
        <span v-if="errors.categoria" class="menu-form-error">{{ errors.categoria }}</span>
      </div>

      <div class="menu-form-group">
        <label class="menu-form-label">Precio (S/.)</label>
        <input
          v-model="form.precio"
          type="number"
          step="0.01"
          min="0"
          class="menu-form-input"
          :class="{ 'menu-form-input-error': errors.precio }"
          placeholder="0.00"
        />
        <span v-if="errors.precio" class="menu-form-error">{{ errors.precio }}</span>
      </div>

      <div class="menu-form-group">
        <label class="menu-form-label">{{ labelImagen }}</label>
        <input
          type="file"
          class="menu-form-input"
          accept="image/*"
          @change="handleImageChange"
        />
        <small class="menu-form-hint">Dejar en blanco para mantener la imagen actual</small>
      </div>

      <div class="menu-form-group menu-form-group-full">
        <label class="menu-form-label">Descripción</label>
        <textarea
          v-model="form.descripcion"
          class="menu-form-input menu-form-textarea"
          :class="{ 'menu-form-input-error': errors.descripcion }"
          rows="3"
          placeholder="Describe los ingredientes y la presentación..."
        ></textarea>
        <span v-if="errors.descripcion" class="menu-form-error">{{ errors.descripcion }}</span>
      </div>

      <div v-if="imagenPreview || currentImageUrl" class="menu-form-group menu-form-group-full">
        <label class="menu-form-label">Vista Previa</label>
        <div class="menu-form-image-preview">
          <img :src="imagenPreview || currentImageUrl" alt="Vista previa" />
          <span class="menu-form-image-name">{{ imagenName || 'Imagen actual' }}</span>
        </div>
      </div>
    </div>
  </CrudForm>
</template>
