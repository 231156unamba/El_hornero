<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import SessionGuard from '../components/common/SessionGuard.vue';
import UserMenu from '../components/common/UserMenu.vue';
import KitchenMenuView from '../components/cocina/KitchenMenuView.vue';

const router = useRouter();
const pedidos = ref([]);
const avisos = ref([]);
let intervalId = null;
const activeView = ref('pedidos');

onMounted(() => {
  const rol = localStorage.getItem('rol');
  if (rol !== 'cocina') {
    router.push('/login');
  } else {
    fetchPedidos();
    intervalId = setInterval(fetchPedidos, 5000);
  }
});

const setActiveView = (view) => {
  activeView.value = view;
  if (view === 'pedidos' && !intervalId) {
    fetchPedidos();
    intervalId = setInterval(fetchPedidos, 5000);
  } else if (view === 'menu' && intervalId) {
    clearInterval(intervalId);
    intervalId = null;
  }
};

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
});

const fetchPedidos = async () => {
  try {
    const response = await api.get('/pedidos');
    pedidos.value = response.data.sort((a, b) => new Date(a.fecha) - new Date(b.fecha));
  } catch (error) {
    console.error('Error fetching pedidos:', error);
  }
};

const cambiarEstado = async (id, nuevoEstado) => {
  try {
    await api.post('/pedidos/actualizar', {
      id: id,
      estado: nuevoEstado
    });

    if (nuevoEstado === 'preparado') {
      const pedido = pedidos.value.find(p => p.id === id);
      mostrarAviso(`Pedido listo para la mesa ${pedido ? pedido.mesa : '?'}`);
    }

    fetchPedidos();
  } catch (error) {
    console.error('Error actualizando estado:', error);
  }
};

const formatHora = (fechaStr) => {
  if (!fechaStr) return '--:--';
  const isoStr = fechaStr.includes(' ') ? fechaStr.replace(' ', 'T') + 'Z' : fechaStr;
  const date = new Date(isoStr);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const mostrarAviso = (mensaje) => {
  avisos.value.push(mensaje);
  setTimeout(() => {
    avisos.value.shift();
  }, 5000);
};

const pendientes = computed(() => pedidos.value.filter(p => p.estado === 'pedido'));
const enCocinando = computed(() => pedidos.value.filter(p => p.estado === 'cocinando'));
const preparados = computed(() => pedidos.value.filter(p => p.estado === 'preparado'));
</script>

<template>
  <div class="cocina-page">
    <header class="topbar">
      <div class="logo">
        <h1>EL HORNERO</h1>
      </div>
      <div class="user-actions">
        <UserMenu />
      </div>
    </header>

    <main class="content-area">
      <!-- Navigation Tabs -->
      <nav class="kitchen-nav">
        <button
          :class="['kitchen-nav-btn', { 'kitchen-nav-btn--active': activeView === 'pedidos' }]"
          @click="setActiveView('pedidos')"
        >
          🍳 Pedidos
        </button>
        <button
          :class="['kitchen-nav-btn', { 'kitchen-nav-btn--active': activeView === 'menu' }]"
          @click="setActiveView('menu')"
        >
          📋 Menú
        </button>
      </nav>

      <div class="main-panel">
        <!-- Vista de Pedidos -->
        <div v-if="activeView === 'pedidos'" class="kitchen-grid">

          <!-- Columna 1: En Cocina -->
          <div class="kitchen-col">
            <div class="col-header col-header--pending">
              <span>En Cocina</span>
              <span class="col-count">{{ pendientes.length }}</span>
            </div>
            <div class="col-body">
              <div v-if="pendientes.length === 0" class="empty-state">
                <span class="empty-state__icon">🍳</span>
                No hay pedidos pendientes
              </div>
              <article
                v-for="p in pendientes"
                :key="p.id"
                class="order-card order-card--pending"
              >
                <div class="order-card__header">
                  <div class="order-card__mesa">
                    <span class="order-card__mesa-label">Mesa</span>
                    {{ p.mesa }}
                  </div>
                  <span class="order-card__time">🕐 {{ formatHora(p.fecha) }}</span>
                </div>
                <div class="order-card__body">
                  <p class="order-card__detail">{{ p.detalle }}</p>
                  <div class="order-card__meta">
                    <span
                      class="order-card__tipo"
                      :class="{ 'order-card__tipo--llevar': p.tipo_servicio === 'llevar' }"
                    >
                      {{ p.tipo_servicio === 'llevar' ? '🥡 Para llevar' : '🍽 Local' }}
                    </span>
                  </div>
                </div>
                <div class="order-card__actions">
                  <button class="btn-action btn-cocinando" @click="cambiarEstado(p.id, 'cocinando')">
                    Cocinando
                  </button>
                </div>
              </article>
            </div>
          </div>

          <!-- Columna 2: Cocinando -->
          <div class="kitchen-col">
            <div class="col-header col-header--cooking">
              <span>Cocinando</span>
              <span class="col-count">{{ enCocinando.length }}</span>
            </div>
            <div class="col-body">
              <div v-if="enCocinando.length === 0" class="empty-state">
                <span class="empty-state__icon">🔥</span>
                No hay pedidos en preparación
              </div>
              <article
                v-for="p in enCocinando"
                :key="p.id"
                class="order-card order-card--cooking"
              >
                <div class="order-card__header">
                  <div class="order-card__mesa">
                    <span class="order-card__mesa-label">Mesa</span>
                    {{ p.mesa }}
                  </div>
                  <span class="order-card__time">🕐 {{ formatHora(p.fecha) }}</span>
                </div>
                <div class="order-card__body">
                  <p class="order-card__detail">{{ p.detalle }}</p>
                  <div class="order-card__meta">
                    <span class="order-card__status-tag order-card__status-tag--cooking">● En preparación</span>
                    <span
                      class="order-card__tipo"
                      :class="{ 'order-card__tipo--llevar': p.tipo_servicio === 'llevar' }"
                    >
                      {{ p.tipo_servicio === 'llevar' ? '🥡 Para llevar' : '🍽 Local' }}
                    </span>
                  </div>
                </div>
                <div class="order-card__actions">
                  <button class="btn-action btn-preparado" @click="cambiarEstado(p.id, 'preparado')">
                    Preparado
                  </button>
                </div>
              </article>
            </div>
          </div>

          <!-- Columna 3: Preparado -->
          <div class="kitchen-col">
            <div class="col-header col-header--ready">
              <span>Preparado</span>
              <span class="col-count">{{ preparados.length }}</span>
            </div>
            <div class="col-body">
              <div v-if="preparados.length === 0" class="empty-state">
                <span class="empty-state__icon">✅</span>
                Nada por servir
              </div>
              <article
                v-for="p in preparados"
                :key="p.id"
                class="order-card order-card--ready"
              >
                <div class="order-card__header">
                  <div class="order-card__mesa">
                    <span class="order-card__mesa-label">Mesa</span>
                    {{ p.mesa }}
                  </div>
                  <span class="order-card__time">🕐 {{ formatHora(p.fecha) }}</span>
                </div>
                <div class="order-card__body">
                  <p class="order-card__detail">{{ p.detalle }}</p>
                  <div class="order-card__meta">
                    <span
                      class="order-card__tipo"
                      :class="{ 'order-card__tipo--llevar': p.tipo_servicio === 'llevar' }"
                    >
                      {{ p.tipo_servicio === 'llevar' ? '🥡 Para llevar' : '🍽 Local' }}
                    </span>
                  </div>
                </div>
                <div class="order-card__actions">
                  <span class="status-badge badge-ready">Preparado</span>
                </div>
              </article>
            </div>
          </div>

        </div>

        <!-- Vista de Menú -->
        <div v-if="activeView === 'menu'">
          <KitchenMenuView />
        </div>
      </div>
    </main>

    <div class="toast-container">
      <transition-group name="toast">
        <div v-for="(aviso, idx) in avisos" :key="idx" class="toast">
          🔔 {{ aviso }}
        </div>
      </transition-group>
    </div>
  </div>

  <SessionGuard />
</template>

<style src="../styles/cocina.css" scoped></style>
