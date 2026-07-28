<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import api from '../api';

const menu    = ref([]);
const loading = ref(true);
const apiOrigin = new URL(api.defaults.baseURL).origin;

// ── Categorías (valores exactos de la BD) ────────────────────
const columnas = [
  { key: 'comida',    label: 'Comidas',     icon: '🍽️' },
  { key: 'bebida',    label: 'Bebidas',     icon: '🥤' },
  { key: 'promocion', label: 'Promociones', icon: '🎉' },
];

const byCategoria = (cat) =>
  menu.value.filter(p => (p.categoria || '').toLowerCase() === cat);

// ── BroadcastChannel — actualizar menú desde admin ───────────
const menuChannel = new BroadcastChannel('menu-updates');

onMounted(() => {
  fetchMenu();
  menuChannel.addEventListener('message', (e) => {
    if (e.data.type === 'menu-changed') fetchMenu();
  });
});
onUnmounted(() => menuChannel.close());

const menuImageUrl = (p) => {
  if (!p) return '';
  if (p.imagen_url) return p.imagen_url;
  const img = p.imagen;
  if (!img) return '';
  if (/^https?:\/\//i.test(img)) return img;
  if (img.startsWith('/')) return apiOrigin + img;
  return `${apiOrigin}/images/menu/${img}`;
};
const imgFallback = (e) => { e.target.src = '/logo.png'; };

const precioFinal = (p) =>
  p.discount_percentage
    ? (parseFloat(p.precio) * (1 - p.discount_percentage / 100)).toFixed(2)
    : parseFloat(p.precio).toFixed(2);

const fetchMenu = async () => {
  try {
    const r = await api.get('/menu');
    menu.value = r.data;
  } catch (e) {
    console.error('Error cargando menú:', e);
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="menu-page">

    <!-- Header -->
    <header class="menu-header">
      <img src="/logo.png" alt="El Hornero" class="logo" />
      <div class="header-title">Menú</div>
    </header>

    <div class="menu-container">
      <div v-if="loading" class="menu-loading">Cargando menú...</div>

      <div v-else class="menu-triptych">
        <div
          v-for="col in columnas"
          :key="col.key"
          class="menu-column"
        >
          <!-- Cabecera de columna -->
          <div class="menu-col-header">
            <span class="menu-col-icon">{{ col.icon }}</span>
            <h2 class="menu-col-title">{{ col.label }}</h2>
            <span class="menu-col-count">{{ byCategoria(col.key).length }} artículos</span>
          </div>

          <!-- Lista de artículos -->
          <div class="menu-col-items">
            <div
              v-for="p in byCategoria(col.key)"
              :key="p.id"
              class="menu-card"
            >
              <!-- Imagen -->
              <div class="menu-card-thumb">
                <img :src="menuImageUrl(p)" :alt="p.nombre" @error="imgFallback" />
                <div v-if="p.discount_percentage" class="menu-card-discount">
                  −{{ p.discount_percentage }}%
                </div>
              </div>

              <!-- Info -->
              <div class="menu-card-body">
                <div class="menu-card-name">{{ p.nombre }}</div>
                <div class="menu-card-desc">{{ p.descripcion }}</div>
                <div class="menu-card-footer">
                  <div class="menu-card-price">
                    <span v-if="p.discount_percentage" class="price-old">
                      S/. {{ parseFloat(p.precio).toFixed(2) }}
                    </span>
                    <span class="price-final">S/. {{ precioFinal(p) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Columna vacía -->
            <div v-if="byCategoria(col.key).length === 0" class="menu-col-empty">
              <span>{{ col.icon }}</span>
              <span>Sin artículos disponibles</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Variables ── */
.menu-page {
  font-family: 'Inter', Arial, sans-serif;
  background: linear-gradient(180deg, #fff6f0 0%, #ffffff 100%);
  min-height: 100vh;
  padding-bottom: 40px;
}

/* ── Header ── */
.menu-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 14px 20px;
  background: linear-gradient(135deg, #ffcc80 0%, #ffb74d 40%, #ffa726 100%);
  border-bottom: 2px solid #e65100;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}

.logo {
  height: clamp(44px, 10vw, 68px);
  width: auto;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
}

.header-title {
  font-weight: 800;
  color: #e65100;
  font-size: clamp(20px, 5vw, 28px);
  letter-spacing: 0.5px;
  text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

/* ── Contenedor principal ── */
.menu-container {
  max-width: 1300px;
  margin: 0 auto;
  padding: 28px 20px;
}

.menu-loading {
  text-align: center;
  padding: 60px;
  color: #9ca3af;
  font-size: 1rem;
}

/* ── Triptych 3 columnas ── */
.menu-triptych {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  align-items: start;
}

/* ── Columna ── */
.menu-column {
  background: #fff;
  border-radius: 18px;
  border: 1px solid #f0f0f0;
  box-shadow: 0 4px 20px rgba(0,0,0,0.07);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* Cabecera de columna */
.menu-col-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 20px;
  border-bottom: 1px solid #f5f5f5;
  background: linear-gradient(135deg, #fff8f0, #fff3e0);
  position: sticky;
  top: 96px;
  z-index: 10;
}

.menu-col-icon  { font-size: 1.5rem; flex-shrink: 0; }
.menu-col-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  color: #111827;
}
.menu-col-count {
  margin-left: auto;
  background: rgba(0,0,0,0.06);
  color: #6b7280;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  white-space: nowrap;
}

/* Items */
.menu-col-items {
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Card de artículo */
.menu-card {
  display: flex;
  flex-direction: column;
  border-bottom: 1px solid #f9fafb;
  transition: background 0.15s;
}
.menu-card:last-child { border-bottom: none; }
.menu-card:hover      { background: #fff7f0; }

/* Imagen */
.menu-card-thumb {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: #f5f5f5;
}
.menu-card-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.3s;
}
.menu-card:hover .menu-card-thumb img {
  transform: scale(1.04);
}
.menu-card-discount {
  position: absolute;
  top: 8px; right: 8px;
  background: #ef5350;
  color: white;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 8px;
}

/* Cuerpo */
.menu-card-body {
  padding: 12px 16px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.menu-card-name {
  font-weight: 800;
  font-size: 0.95rem;
  color: #111827;
  line-height: 1.3;
}
.menu-card-desc {
  font-size: 0.8rem;
  color: #6b7280;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.menu-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
  flex-wrap: wrap;
  gap: 6px;
}
.menu-card-price {
  display: flex;
  align-items: baseline;
  gap: 6px;
}
.price-old {
  text-decoration: line-through;
  color: #9ca3af;
  font-size: 0.78rem;
}
.price-final {
  font-weight: 800;
  font-size: 1rem;
  color: #e65100;
}

/* Columna vacía */
.menu-col-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 32px 16px;
  color: #d1d5db;
  font-size: 0.88rem;
}
.menu-col-empty span:first-child { font-size: 2rem; opacity: 0.4; }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .menu-triptych { grid-template-columns: 1fr 1fr; gap: 18px; }
}
@media (max-width: 640px) {
  .menu-triptych { grid-template-columns: 1fr; gap: 16px; }
  .menu-col-header { top: 80px; }
}
</style>
