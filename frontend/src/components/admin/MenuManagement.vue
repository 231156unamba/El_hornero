<script setup>
import { ref } from 'vue';
import api from '../../api';
import './MenuManagement.css';

const props = defineProps({
  menu: {
    type: Array,
    required: true,
    default: () => []
  }
});

const emit = defineEmits(['edit', 'delete', 'discount', 'toggle']);

const togglingId = ref(null);

const handleToggle = (item) => {
  if (togglingId.value !== null) return;
  togglingId.value = item.id;
  emit('toggle', item);
  setTimeout(() => {
    togglingId.value = null;
  }, 1500);
};

// Definición de las columnas — valor coincide exactamente con el ENUM de la BD
const columnas = [
  { key: 'comida',    label: 'Comidas',     icon: '🍽️' },
  { key: 'bebida',    label: 'Bebidas',     icon: '🥤' },
  { key: 'promocion', label: 'Promociones', icon: '🎉' },
];

const itemsByCategoria = (cat) =>
  props.menu.filter(i => (i.categoria || '').toLowerCase() === cat);

const menuImageUrl = (item) => {
  if (!item) return '';
  if (item.imagen_url) return item.imagen_url;
  const img = item.imagen;
  if (!img) return '';
  if (/^https?:\/\//i.test(img)) return img;
  const apiOrigin = new URL(api.defaults.baseURL).origin;
  if (img.startsWith('/')) return apiOrigin + img;
  return `${apiOrigin}/images/menu/${img}`;
};

const imgFallback = (e) => { e.target.src = '/logo.png'; };

const precioFinal = (item) =>
  item.discount_percentage
    ? (item.precio * (1 - item.discount_percentage / 100)).toFixed(2)
    : item.precio;
</script>

<template>
  <div class="mm-triptych">
    <div
      v-for="col in columnas"
      :key="col.key"
      class="mm-column"
    >
      <!-- Cabecera de la columna -->
      <div :class="['mm-column-header', col.key]">
        <span class="mm-column-icon">{{ col.icon }}</span>
        <h3 class="mm-column-title">{{ col.label }}</h3>
        <span class="mm-column-count">{{ itemsByCategoria(col.key).length }}</span>
      </div>

      <!-- Lista de ítems -->
      <div class="mm-items">
        <!-- Estado vacío -->
        <div v-if="itemsByCategoria(col.key).length === 0" class="mm-empty">
          <span class="mm-empty-icon">{{ col.icon }}</span>
          <span>Sin artículos</span>
        </div>

        <!-- Ítem -->
        <div
          v-for="item in itemsByCategoria(col.key)"
          :key="item.id"
          :class="['mm-item', { 'mm-item-disabled': !item.activo }]"
        >
          <img
            :src="menuImageUrl(item)"
            :alt="item.nombre"
            :class="['mm-item-img', { 'mm-item-img-disabled': !item.activo }]"
            @error="imgFallback"
          />

          <div class="mm-item-info">
            <div :class="['mm-item-name', { 'mm-item-name-disabled': !item.activo }]">
              {{ item.nombre }}
              <span v-if="!item.activo" class="mm-item-disabled-tag">inactivo</span>
            </div>
            <div class="mm-item-desc">{{ item.descripcion }}</div>

            <div class="mm-item-footer">
              <div class="mm-item-price">
                <span v-if="item.discount_percentage" class="mm-item-price-original">
                  S/. {{ item.precio }}
                </span>
                S/. {{ precioFinal(item) }}
                <span v-if="item.discount_percentage" class="mm-discount-badge">
                  -{{ item.discount_percentage }}%
                </span>
              </div>

              <div class="mm-item-actions">
                <button class="mm-btn mm-btn-edit"   @click="emit('edit',     item)">Editar</button>
                <button class="mm-btn mm-btn-offer"  @click="emit('discount', item)">Oferta</button>
                <button
                  :class="['mm-btn', item.activo ? 'mm-btn-disable' : 'mm-btn-enable', { 'mm-btn-loading': togglingId === item.id }]"
                  @click="handleToggle(item)"
                  :disabled="togglingId === item.id"
                >
                  <span v-if="togglingId === item.id" class="mm-btn-spinner"></span>
                  <span>{{ togglingId === item.id ? 'Procesando...' : (item.activo ? 'Inhabilitar' : 'Habilitar') }}</span>
                </button>
                <button class="mm-btn mm-btn-delete" @click="emit('delete',   item)">Eliminar</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
