<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../api';

const menu = ref([]);
const loading = ref(true);
const selectedCat = ref('comida');
const apiOrigin = new URL(api.defaults.baseURL).origin;
const filteredMenu = computed(() => {
  return menu.value.filter(m => (m.categoria || 'comida') === selectedCat.value);
});

const menuImageUrl = (plato) => {
  if (!plato) return '';
  if (plato.imagen_url) return plato.imagen_url;
  const img = plato.imagen;
  if (!img) return '';
  if (/^https?:\/\//i.test(img)) return img;
  if (img.startsWith('/')) return apiOrigin + img;
  return `${apiOrigin}/images/menu/${img}`;
};

const imgFallback = (e) => {
  e.target.src = '/logo.png';
};

const fetchMenu = async () => {
  try {
    const response = await api.get('/menu');
    menu.value = response.data;
  } catch (error) {
    console.error('Error cargando menú:', error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchMenu();
});
</script>

<template>
  <div class="menu-page">
    <div class="menu-header">
      <img src="/logo.png" alt="El Hornero" class="logo">
      <div class="title">Menú</div>
      <div class="category-tabs">
        <button :class="['cat-btn', selectedCat==='comida'?'active':'']" @click="selectedCat='comida'">Comidas</button>
        <button :class="['cat-btn', selectedCat==='bebidas'?'active':'']" @click="selectedCat='bebidas'">Bebidas</button>
      </div>
    </div>
    <div class="container">
      
      <div v-if="loading" style="text-align:center;">Cargando...</div>

      <div v-else class="menu-list">
        <div v-for="plato in filteredMenu" :key="plato.id" class="menu-item">
          <div class="thumb">
            <img :src="menuImageUrl(plato)" :alt="plato.nombre" @error="imgFallback">
            <div class="price-badge">S/. {{ parseFloat(plato.precio).toFixed(2) }}</div>
          </div>
          <div class="menu-content">
            <div class="chip" v-if="plato.categoria">{{ plato.categoria }}</div>
            <div class="nombre">{{ plato.nombre }}</div>
            <div class="descripcion">{{ plato.descripcion }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.menu-page {
  font-family: Arial, sans-serif;
  background: linear-gradient(180deg, #fff6f0 0%, #ffffff 100%);
  min-height: 100vh;
  padding-bottom: 30px;
}

.menu-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 10px 12px;
  background: linear-gradient(135deg, #ffcc80 0%, #ffb74d 40%, #ffa726 100%);
  border-bottom: 2px solid #e65100;
  margin-bottom: 20px;
}
.menu-header .logo {
  height: clamp(48px, 12vw, 80px);
  width: auto;
  display: block;
  filter: drop-shadow(0 2px 6px rgba(0,0,0,0.15));
}
.menu-header .title {
  font-weight: 700;
  color: #e65100;
  letter-spacing: 0.5px;
  font-size: clamp(18px, 5vw, 26px);
  text-shadow: 0 1px 2px rgba(0,0,0,0.12);
}
.category-tabs {
  margin-left: auto;
  display: flex;
  gap: 8px;
}
.cat-btn {
  padding: 8px 12px;
  border-radius: 999px;
  background: #fff3e0;
  border: 1px solid #f3c49b;
  color: #6b4e3d;
  font-weight: 700;
  cursor: pointer;
  transition: transform .15s ease, background .15s ease, box-shadow .15s ease, color .15s ease, border-color .15s ease;
}
.cat-btn.active {
  background: linear-gradient(135deg, #e65100 0%, #ff6f00 100%);
  color: #fff;
  border-color: #e65100;
  transform: scale(1.06);
  box-shadow: 0 3px 8px rgba(230, 81, 0, 0.35);
}
.cat-btn:active {
  transform: scale(1.08);
}

.container { 
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 16px 24px;
}

.menu-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
  align-items: start;
  margin-top: 8px;
}

.menu-item {
  background: #fff;
  border: 1px solid #f3c49b;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 3px 12px rgba(0,0,0,0.08);
  transition: transform .2s ease, box-shadow .2s ease;
}
.menu-item:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 18px rgba(0,0,0,0.12);
}

.thumb {
  position: relative;
  background: linear-gradient(180deg, #fff3e0 0%, #fff 100%);
}
.thumb img {
  width: 100%;
  display: block;
  aspect-ratio: 4/3;
  object-fit: cover;
}
.price-badge {
  position: absolute;
  right: 10px;
  bottom: 10px;
  background: linear-gradient(135deg, #1e7e34 0%, #28a745 100%);
  color: #fff;
  padding: 6px 10px;
  border-radius: 16px;
  font-weight: 700;
  font-size: clamp(12px, 3.5vw, 14px);
  box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

.menu-content {
  padding: 12px 14px 16px;
}
.chip {
  display: inline-block;
  padding: 6px 10px;
  border-radius: 999px;
  background: linear-gradient(135deg, #ffe0b2 0%, #ffcc80 100%);
  color: #6b4e3d;
  font-weight: 600;
  font-size: clamp(11px, 3.5vw, 12px);
  border: 1px solid #f3c49b;
  margin-bottom: 8px;
}
.nombre {
  font-weight: 800;
  font-size: clamp(16px, 4.8vw, 18px);
  color: #1b1b1b;
  margin-bottom: 6px;
}
.descripcion {
  color: #6b4e3d;
  font-size: clamp(13px, 3.8vw, 14px);
  line-height: 1.45;
  min-height: 48px;
}

@media (min-width: 768px) {
  .menu-list {
    gap: 18px;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  }
}
@media (min-width: 1024px) {
  .menu-list {
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  }
}
</style>
