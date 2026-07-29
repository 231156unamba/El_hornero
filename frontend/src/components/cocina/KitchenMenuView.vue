<script setup>
import { ref, onMounted } from 'vue';
import api from '../../api';

const menu = ref([]);
const togglingId = ref(null);

const loadMenu = async () => {
  try {
    const r = await api.get('/menu');
    menu.value = r.data;
  } catch (e) {
    console.error('Error al cargar menú:', e);
  }
};

const handleToggle = async (item) => {
  if (togglingId.value !== null) return;
  togglingId.value = item.id;
  try {
    await api.patch(`/menu/${item.id}/toggle`);
    await loadMenu();
  } catch (e) {
    console.error('Error al cambiar estado:', e);
    alert('Error al cambiar estado del producto');
  } finally {
    togglingId.value = null;
  }
};

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

const columnas = [
  { key: 'comida',    label: 'Comidas',     icon: '🍽️' },
  { key: 'bebida',    label: 'Bebidas',     icon: '🥤' },
  { key: 'promocion', label: 'Promociones', icon: '🎉' },
];

const itemsByCategoria = (cat) =>
  menu.value.filter(i => (i.categoria || '').toLowerCase() === cat);

onMounted(() => {
  loadMenu();
});
</script>

<template>
  <div class="kitchen-menu-view">
    <div class="kmm-header">
      <h2>📋 Gestión de Menú - Cocina</h2>
      <p class="kmm-subtitle">Activar o desactivar productos rápidamente</p>
    </div>

    <div class="kmm-triptych">
      <div
        v-for="col in columnas"
        :key="col.key"
        class="kmm-column"
      >
        <div :class="['kmm-column-header', col.key]">
          <span class="kmm-column-icon">{{ col.icon }}</span>
          <h3 class="kmm-column-title">{{ col.label }}</h3>
          <span class="kmm-column-count">{{ itemsByCategoria(col.key).length }}</span>
        </div>

        <div class="kmm-items">
          <div v-if="itemsByCategoria(col.key).length === 0" class="kmm-empty">
            <span class="kmm-empty-icon">{{ col.icon }}</span>
            <span>Sin artículos</span>
          </div>

          <div
            v-for="item in itemsByCategoria(col.key)"
            :key="item.id"
            :class="['kmm-item', { 'kmm-item-disabled': !item.activo }]"
          >
            <img
              :src="menuImageUrl(item)"
              :alt="item.nombre"
              :class="['kmm-item-img', { 'kmm-item-img-disabled': !item.activo }]"
              @error="imgFallback"
            />

            <div class="kmm-item-info">
              <div :class="['kmm-item-name', { 'kmm-item-name-disabled': !item.activo }]">
                {{ item.nombre }}
                <span v-if="!item.activo" class="kmm-item-disabled-tag">inactivo</span>
              </div>
              <div class="kmm-item-desc">{{ item.descripcion }}</div>

              <div class="kmm-item-footer">
                <div class="kmm-item-price">
                  <span v-if="item.discount_percentage" class="kmm-item-price-original">
                    S/. {{ item.precio }}
                  </span>
                  S/. {{ precioFinal(item) }}
                  <span v-if="item.discount_percentage" class="kmm-discount-badge">
                    -{{ item.discount_percentage }}%
                  </span>
                </div>

                <div class="kmm-item-actions">
                  <button
                    :class="['kmm-btn', item.activo ? 'kmm-btn-disable' : 'kmm-btn-enable', { 'kmm-btn-loading': togglingId === item.id }]"
                    @click="handleToggle(item)"
                    :disabled="togglingId === item.id"
                  >
                    <span v-if="togglingId === item.id" class="kmm-btn-spinner"></span>
                    <span>{{ togglingId === item.id ? 'Procesando...' : (item.activo ? 'Inhabilitar' : 'Habilitar') }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kitchen-menu-view {
  padding: 24px;
  background: #f8fafc;
  min-height: 100vh;
}

.kmm-header {
  margin-bottom: 24px;
  text-align: center;
}

.kmm-header h2 {
  margin: 0 0 8px 0;
  color: #1e293b;
  font-size: 24px;
}

.kmm-subtitle {
  margin: 0;
  color: #64748b;
  font-size: 14px;
}

.kmm-triptych {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

.kmm-column {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.kmm-column-header {
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid #e2e8f0;
}

.kmm-column-header.comida {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

.kmm-column-header.bebida {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
}

.kmm-column-header.promocion {
  background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
}

.kmm-column-icon {
  font-size: 24px;
}

.kmm-column-title {
  margin: 0;
  flex: 1;
  font-size: 16px;
  color: #1e293b;
}

.kmm-column-count {
  background: rgba(255,255,255,0.8);
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
}

.kmm-items {
  max-height: calc(100vh - 250px);
  overflow-y: auto;
  padding: 12px;
}

.kmm-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #94a3b8;
  gap: 8px;
}

.kmm-empty-icon {
  font-size: 32px;
}

.kmm-item {
  display: flex;
  gap: 12px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 12px;
  background: white;
  transition: all 0.2s;
}

.kmm-item-disabled {
  opacity: 0.6;
  background: #f8fafc;
}

.kmm-item-img {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 6px;
  flex-shrink: 0;
}

.kmm-item-img-disabled {
  filter: grayscale(100%);
}

.kmm-item-info {
  flex: 1;
  min-width: 0;
}

.kmm-item-name {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.kmm-item-name-disabled {
  color: #94a3b8;
}

.kmm-item-disabled-tag {
  background: #ef4444;
  color: white;
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

.kmm-item-desc {
  font-size: 12px;
  color: #64748b;
  margin-bottom: 8px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.kmm-item-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.kmm-item-price {
  font-weight: 600;
  color: #059669;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.kmm-item-price-original {
  text-decoration: line-through;
  color: #94a3b8;
  font-size: 12px;
}

.kmm-discount-badge {
  background: #10b981;
  color: white;
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
}

.kmm-item-actions {
  display: flex;
  gap: 8px;
}

.kmm-btn {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.kmm-btn-enable {
  background: #10b981;
  color: white;
}

.kmm-btn-enable:hover {
  background: #059669;
}

.kmm-btn-disable {
  background: #ef4444;
  color: white;
}

.kmm-btn-disable:hover {
  background: #dc2626;
}

.kmm-btn-loading {
  opacity: 0.7;
  cursor: not-allowed;
}

.kmm-btn-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 1024px) {
  .kmm-triptych {
    grid-template-columns: 1fr;
  }
}
</style>
