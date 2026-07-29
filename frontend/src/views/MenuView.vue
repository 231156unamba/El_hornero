<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import api from '../api';

const menu    = ref([]);
const loading = ref(true);
const apiOrigin = new URL(api.defaults.baseURL).origin;
const activeTab = ref('comida'); // New variable for tabs

// ── Categorías (valores exactos de la BD) ────────────────────
const tabs = [
  { key: 'comida',    label: 'Platos',     icon: '🍽️' },
  { key: 'bebida',    label: 'Bebidas',     icon: '🥤' },
  { key: 'promocion', label: 'Promociones', icon: '🎉' },
];

const itemsFiltrados = computed(() => {
  return menu.value.filter(p => (p.categoria || '').toLowerCase() === activeTab.value);
});

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
      <div class="header-title">Nuestro Menú</div>
    </header>

    <div class="menu-container">
      <div v-if="loading" class="menu-loading">
        <div class="spinner"></div>
        Cargando las delicias...
      </div>

      <div v-else>
        <!-- Tabs de Navegación -->
        <div class="tabs-nav">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            class="tab-btn"
            :class="{ active: activeTab === tab.key }"
            @click="activeTab = tab.key"
          >
            <span class="tab-icon">{{ tab.icon }}</span>
            <span class="tab-label">{{ tab.label }}</span>
          </button>
        </div>

        <!-- Lista de Artículos -->
        <div class="menu-grid-wrapper">
          <transition-group name="fade-list" tag="div" class="menu-grid">
            <div
              v-for="p in itemsFiltrados"
              :key="p.id"
              class="modern-card"
            >
              <!-- Imagen -->
              <div class="card-img-wrapper">
                <img :src="menuImageUrl(p)" :alt="p.nombre" @error="imgFallback" />
                <div v-if="p.discount_percentage" class="card-badge">
                  −{{ p.discount_percentage }}%
                </div>
              </div>

              <!-- Info -->
              <div class="card-content">
                <h3 class="card-title">{{ p.nombre }}</h3>
                <p class="card-desc">{{ p.descripcion }}</p>
                <div class="card-bottom">
                  <div class="card-price-block">
                    <span v-if="p.discount_percentage" class="price-old">
                      S/. {{ parseFloat(p.precio).toFixed(2) }}
                    </span>
                    <span class="price-new">S/. {{ precioFinal(p) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </transition-group>
          
          <div v-if="itemsFiltrados.length === 0" class="empty-state">
            <span class="empty-icon">🍽️</span>
            <p>No hay artículos disponibles en esta categoría por ahora.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Variables & Globals ── */
.menu-page {
  font-family: 'Inter', 'Outfit', sans-serif;
  background: #fdfbf7;
  min-height: 100vh;
  padding-bottom: 60px;
}

/* ── Header ── */
.menu-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 20px 20px;
  background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
  border-bottom: 3px solid #e65100;
  box-shadow: 0 4px 15px rgba(230, 81, 0, 0.15);
  position: relative;
}

.logo {
  height: clamp(50px, 12vw, 80px);
  width: auto;
  filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
  transition: transform 0.3s;
}
.logo:hover {
  transform: scale(1.05);
}

.header-title {
  font-weight: 800;
  color: #fff;
  font-size: clamp(24px, 6vw, 36px);
  letter-spacing: 1px;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
  margin-top: -5px;
}

/* ── Container ── */
.menu-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 30px 20px;
}

/* ── Loading State ── */
.menu-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 15px;
  padding: 100px 20px;
  color: #fb8c00;
  font-size: 1.2rem;
  font-weight: 600;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #ffe0b2;
  border-top-color: #fb8c00;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Tabs Navigation ── */
.tabs-nav {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-bottom: 40px;
  flex-wrap: wrap;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border: none;
  background: #fff;
  border-radius: 30px;
  font-size: 1.05rem;
  font-weight: 700;
  color: #6b7280;
  cursor: pointer;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.tab-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(0,0,0,0.08);
  color: #fb8c00;
}

.tab-btn.active {
  background: #fb8c00;
  color: #fff;
  box-shadow: 0 8px 20px rgba(251, 140, 0, 0.3);
}

.tab-icon {
  font-size: 1.2rem;
}

/* ── Artículos Grid ── */
.menu-grid-wrapper {
  position: relative;
  min-height: 400px;
}

.menu-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 25px;
}

/* ── Transition Group ── */
.fade-list-move,
.fade-list-enter-active,
.fade-list-leave-active {
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-list-enter-from,
.fade-list-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.95);
}
.fade-list-leave-active {
  position: absolute;
}

/* ── Modern Card ── */
.modern-card {
  background: #fff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0,0,0,0.06);
  border: 1px solid rgba(251, 140, 0, 0.1);
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
}

.modern-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 15px 40px rgba(251, 140, 0, 0.15);
}

.card-img-wrapper {
  position: relative;
  width: 100%;
  aspect-ratio: 4 / 3;
  overflow: hidden;
  background: #fdfaf6;
}

.card-img-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}

.modern-card:hover .card-img-wrapper img {
  transform: scale(1.08);
}

.card-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  background: #ef5350;
  color: #fff;
  font-weight: 800;
  font-size: 0.85rem;
  padding: 6px 12px;
  border-radius: 20px;
  box-shadow: 0 4px 10px rgba(239, 83, 80, 0.3);
}

.card-content {
  padding: 20px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.card-title {
  margin: 0 0 8px 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #1f2937;
  line-height: 1.3;
}

.card-desc {
  margin: 0 0 15px 0;
  font-size: 0.9rem;
  color: #6b7280;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  flex: 1;
}

.card-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  border-top: 1px solid #f3f4f6;
  padding-top: 15px;
}

.card-price-block {
  display: flex;
  flex-direction: column;
}

.price-old {
  font-size: 0.8rem;
  color: #9ca3af;
  text-decoration: line-through;
}

.price-new {
  font-size: 1.2rem;
  font-weight: 800;
  color: #e65100;
}

.action-btn {
  background: #fff3e0;
  color: #e65100;
  border: none;
  padding: 8px 16px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #ffb74d;
  color: #fff;
  box-shadow: 0 4px 10px rgba(255, 183, 77, 0.4);
}

/* ── Empty State ── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #9ca3af;
  text-align: center;
  grid-column: 1 / -1;
}
.empty-icon {
  font-size: 3rem;
  margin-bottom: 15px;
  opacity: 0.5;
}
.empty-state p {
  font-size: 1.1rem;
  font-weight: 500;
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .menu-grid {
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
  }
  .tab-btn {
    padding: 10px 18px;
    font-size: 0.95rem;
  }
}
</style>
