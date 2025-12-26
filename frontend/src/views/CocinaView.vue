<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();
const pedidos = ref([]);
const avisos = ref([]);
let intervalId = null;

onMounted(() => {
  const rol = localStorage.getItem('rol');
  if (rol !== 'cocina') {
    router.push('/login');
  } else {
    fetchPedidos();
    // Polling cada 5 segundos
    intervalId = setInterval(fetchPedidos, 5000);
  }
});

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
});

const fetchPedidos = async () => {
  try {
    const response = await api.get('/pedidos');
    pedidos.value = response.data;
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

const mostrarAviso = (mensaje) => {
  avisos.value.push(mensaje);
  setTimeout(() => {
    avisos.value.shift();
  }, 5000);
};

const logout = () => {
  localStorage.clear();
  router.push('/login');
};

const pendientes = computed(() => pedidos.value.filter(p => p.estado === 'pedido'));
const preparados = computed(() => pedidos.value.filter(p => p.estado === 'preparado'));
const entregados = computed(() => pedidos.value.filter(p => p.estado === 'entregado'));
</script>

<template>
  <div class="cocina-page">
    <header class="topbar">
      <div class="logo">
        <img src="/logo.png" alt="El Hornero">
        <h1>EL HORNERO</h1>
      </div>
      <div class="user-actions">
        <div class="user-profile">
          <div class="user-avatar">C</div>
          <span>Cocina</span>
        </div>
        <button class="logout-btn" @click="logout">
          Cerrar Sesión
        </button>
      </div>
    </header>

    <main class="content-area">
      <div class="kitchen-grid">
        
        <!-- Columna 1: Pendientes -->
        <div class="kitchen-col">
          <h2 class="col-title text-orange">Pendientes de Preparación</h2>
          <div class="table-card">
            <table class="kitchen-table">
              <thead>
                <tr>
                  <th>Hora</th>
                  <th>Mesa</th>
                  <th>Detalle del Pedido</th>
                  <th class="text-right">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="pendientes.length === 0">
                  <td colspan="4" class="empty-cell">No hay pedidos pendientes</td>
                </tr>
                <tr v-for="p in pendientes" :key="p.id">
                  <td class="time-cell">{{ p.fecha ? p.fecha.split(' ')[1].substring(0,5) : '' }}</td>
                  <td class="table-cell font-bold">Mesa {{ p.mesa }}</td>
                  <td class="detail-cell">{{ p.detalle }}</td>
                  <td class="action-cell text-right">
                    <button class="btn-action btn-orange" @click="cambiarEstado(p.id, 'preparado')">
                      Listo
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Columna 2: Listos -->
        <div class="kitchen-col">
          <h2 class="col-title text-green">Listos para Servir</h2>
          <div class="table-card">
            <table class="kitchen-table">
              <thead>
                <tr>
                  <th>Hora</th>
                  <th>Mesa</th>
                  <th>Detalle</th>
                  <th class="text-right">Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="preparados.length === 0">
                  <td colspan="4" class="empty-cell">Nada por servir</td>
                </tr>
                <tr v-for="p in preparados" :key="p.id">
                  <td class="time-cell">{{ p.fecha ? p.fecha.split(' ')[1].substring(0,5) : '' }}</td>
                  <td class="table-cell font-bold">Mesa {{ p.mesa }}</td>
                  <td class="detail-cell">{{ p.detalle }}</td>
                  <td class="action-cell text-right">
                     <div class="btn-group">
                        <span class="status-badge badge-ready">PREPARADO</span>
                        <button class="btn-sm btn-success" @click="cambiarEstado(p.id, 'entregado')">✓</button>
                     </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Columna 3: Historial -->
        <div class="kitchen-col">
          <h2 class="col-title text-gray">Historial Reciente</h2>
          <div class="table-card">
            <table class="kitchen-table">
              <thead>
                <tr>
                  <th>Hora</th>
                  <th>Mesa</th>
                  <th>Detalle</th>
                  <th class="text-right">Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in entregados.slice().reverse().slice(0, 15)" :key="p.id">
                  <td class="time-cell">{{ p.fecha ? p.fecha.split(' ')[1].substring(0,5) : '' }}</td>
                  <td class="table-cell font-medium">Mesa {{ p.mesa }}</td>
                  <td class="detail-cell text-muted">{{ p.detalle }}</td>
                  <td class="action-cell text-right">
                    <span class="status-label">ENTREGADO</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>

    <!-- Notificaciones flotantes -->
    <div class="toast-container">
      <transition-group name="toast">
        <div v-for="(aviso, idx) in avisos" :key="idx" class="toast">
          🔔 {{ aviso }}
        </div>
      </transition-group>
    </div>
  </div>
</template>

<style scoped>
/* Layout */
.cocina-page {
  font-family: 'Segoe UI', Arial, sans-serif;
  background: #f8f9fa;
  min-height: 100vh;
}

/* Header */
.topbar {
  background-color: #ff9d5c !important;
  padding: 0 25px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  height: 70px;
}
.logo { display: flex; align-items: center; gap: 15px; }
.logo img { height: 65px; /* Maximized for 70px header */ }
.logo h1 { font-size: 24px; color: white; font-weight: 700; margin: 0; }
.user-actions { display: flex; align-items: center; gap: 14px; }
.user-profile { display: flex; align-items: center; gap: 8px; background: white; padding: 6px 14px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.user-avatar { width: 28px; height: 28px; border-radius: 50%; background: #f1af32; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
.logout-btn { background: #e53935; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; }

/* Main Content */
.content-area {
  padding: 30px;
  max-width: 1600px;
  margin: 0 auto;
}

.kitchen-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 25px;
  align-items: start;
}

.kitchen-col {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.col-title {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0 0 5px 0;
  padding-left: 5px;
}
.text-orange { color: #ef6c00; }
.text-green { color: #2e7d32; }
.text-gray { color: #424242; }

/* Cards & Tables */
.table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 15px rgba(0,0,0,0.03);
  overflow: hidden; /* For rounded corners */
  border: 1px solid #f0f0f0;
}

.kitchen-table {
  width: 100%;
  border-collapse: collapse;
}

.kitchen-table th {
  text-align: left;
  padding: 18px 20px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #90a4ae;
  background: #fff; /* White header as in image */
  border-bottom: 2px solid #f5f5f5;
}

.kitchen-table td {
  padding: 18px 20px;
  border-bottom: 1px solid #f9f9f9;
  vertical-align: middle;
}

.kitchen-table tr:last-child td { border-bottom: none; }

/* Cell Content */
.time-cell { color: #90a4ae; font-size: 0.9rem; width: 60px; }
.table-cell { color: #37474f; white-space: nowrap; }
.detail-cell { color: #546e7a; font-size: 0.95rem; line-height: 1.4; font-style: italic; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 600; }
.text-muted { color: #b0bec5; font-style: italic; }
.text-right { text-align: right; }
.empty-cell { text-align: center; color: #cfd8dc; font-style: italic; padding: 40px; }

/* Actions */
.btn-action {
  padding: 6px 14px;
  border-radius: 6px;
  border: none;
  font-weight: 600;
  cursor: pointer;
  font-size: 0.8rem;
  transition: all 0.2s;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.btn-orange { background: #fff3e0; color: #ef6c00; }
.btn-orange:hover { background: #ef6c00; color: white; }

.btn-group {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}
.btn-sm { padding: 4px 8px; border-radius: 4px; border: none; cursor: pointer; font-weight: bold; }
.btn-success { background: #e8f5e9; color: #2e7d32; }
.btn-success:hover { background: #2e7d32; color: white; }

.status-badge { font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; }
.badge-ready { background: #e8f5e9; color: #2e7d32; }

.status-label {
  background: #f5f5f5;
  color: #bdbdbd;
  padding: 6px 12px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 700;
}

/* Toast */
.toast-container {
  position: fixed; bottom: 20px; right: 20px; display: flex; flex-direction: column; gap: 10px; z-index: 100;
}
.toast {
  background: #37474f; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

@media (max-width: 1200px) {
  .kitchen-grid { grid-template-columns: 1fr; }
}
</style>
