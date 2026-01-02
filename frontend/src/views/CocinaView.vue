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
      <div class="main-panel">
        <div class="kitchen-grid">
          
          <!-- Columna 1: Pendientes -->
          <div class="kitchen-col">
            <h2 class="col-title text-orange-main">Pendientes de Preparación</h2>
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
                  <tr v-for="p in pendientes" :key="p.id" class="row-pending">
                    <td class="time-cell">{{ formatHora(p.fecha) }}</td>
                    <td class="table-cell-highlight">Mesa {{ p.mesa }}</td>
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
            <h2 class="col-title text-green-main">Listos para Servir</h2>
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
                  <tr v-for="p in preparados" :key="p.id" class="row-ready">
                    <td class="time-cell">{{ formatHora(p.fecha) }}</td>
                    <td class="table-cell-highlight">Mesa {{ p.mesa }}</td>
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
            <h2 class="col-title text-gray-main">Historial Reciente</h2>
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
                  <tr v-for="p in entregados.slice().reverse().slice(0, 15)" :key="p.id" class="row-delivered">
                    <td class="time-cell">{{ formatHora(p.fecha) }}</td>
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
  background-color: #f1f5f9;
  background-image: radial-gradient(#cbd5e1 0.5px, transparent 0.5px);
  background-size: 20px 20px; /* Subtle grid pattern background */
  min-height: 100vh;
}

/* Header */
.topbar {
  background-color: #111827 !important;
  padding: 15px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.logo { display: flex; align-items: center; gap: 15px; }
.logo h1 { font-size: 24px; color: #f1af32; font-weight: 700; margin: 0; }
.user-actions { display: flex; align-items: center; gap: 14px; }
.user-profile { display: flex; align-items: center; gap: 8px; background: white; padding: 6px 14px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.user-avatar { width: 28px; height: 28px; border-radius: 50%; background: #f1af32; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
.logout-btn { background: #e53935; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; }

/* Main Content */
.content-area {
  padding: 40px 20px;
  max-width: 1650px;
  margin: 0 auto;
}

.main-panel {
  background: rgba(241, 175, 50, 0.05); /* Soft transparent orange */
  border: 1px solid rgba(241, 175, 50, 0.15); /* Tinted border */
  box-shadow: 0 4px 25px rgba(0, 0, 0, 0.03);
  padding: 25px;
  border-radius: 0;
}

.kitchen-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  align-items: start;
}

.kitchen-col {
  display: flex;
  flex-direction: column;
  gap: 5px; /* Reduced gap to bring table closer to title */
}

.col-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin: 0 0 5px 0; /* Reduced margin */
  text-transform: uppercase;
  letter-spacing: 1px;
  text-align: center;
}

.text-orange-main { color: #ef6c00; }
.text-green-main { color: #2e7d32; }
.text-gray-main { color: #546e7a; }

/* Cards & Tables */
.table-card {
  background: white;
  border-radius: 0; /* Rectangular as requested */
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  overflow: hidden;
  border: 1px solid #e0e0e0;
}

.kitchen-table {
  width: 100%;
  border-collapse: collapse;
}

.kitchen-table th {
  text-align: left;
  padding: 10px 12px;
  font-size: 0.7rem;
  font-weight: 600; /* Reduced from 800 */
  color: white;
  background: #37474f; /* Modern dark header */
  border: 1px solid rgba(255,255,255,0.1);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Specific Header Colors for Intuition (Updated for clean layout) */
.kitchen-col:nth-child(1) .table-card th { background: #ef6c00; border-color: #e65100; }
.kitchen-col:nth-child(2) .table-card th { background: #2e7d32; border-color: #1b5e20; }
.kitchen-col:nth-child(3) .table-card th { background: #546e7a; border-color: #455a64; }

.kitchen-table td {
  padding: 14px 15px;
  border: 1px solid #eeeeee;
  vertical-align: middle;
  transition: background 0.2s;
}

.kitchen-table tr:nth-child(even) { background: #fcfcfc; }
.kitchen-table tr:hover { background: #f5f5f5 !important; }

/* Cell Content */
.time-cell { 
  color: #90a4ae; 
  font-size: 0.7rem;
  width: 40px; /* Even thinner */
  min-width: 40px;
  font-weight: 400;
  text-align: center;
  padding-left: 5px !important;
  padding-right: 5px !important;
  white-space: nowrap;
}
.table-cell-highlight { 
  color: #111827; 
  font-weight: 500;
  font-size: 0.95rem;
  white-space: nowrap;
  width: 70px; /* Fixed width for Mesa column to not jump */
}
.detail-cell { 
  color: #263238; 
  font-size: 0.9rem; 
  line-height: 1.4; 
  font-weight: 400;
  width: 100%; /* Spans all remaining space */
}
.font-bold { font-weight: 500; }
.font-medium { font-weight: 400; }
.text-muted { color: #b0bec5; font-style: italic; }
.text-right { text-align: right; }
.empty-cell { text-align: center; color: #cfd8dc; font-style: italic; padding: 40px; }

/* Actions */
.btn-action {
  padding: 6px 14px;
  border-radius: 6px; /* Rounded button shape */
  border: 1px solid transparent;
  font-weight: 700;
  cursor: pointer;
  font-size: 0.8rem;
  text-transform: uppercase;
  transition: all 0.2s;
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

.status-badge { font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 0; }
.badge-ready { background: #e8f5e9; color: #2e7d32; }

.status-label {
  background: #f5f5f5;
  color: #bdbdbd;
  padding: 6px 12px;
  border-radius: 0;
  font-size: 0.75rem;
  font-weight: 700;
}

/* Toast */
.toast-container {
  position: fixed; bottom: 20px; right: 20px; display: flex; flex-direction: column; gap: 10px; z-index: 100;
}
.toast {
  background: #37474f; color: white; padding: 12px 24px; border-radius: 0; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

@media (max-width: 1200px) {
  .kitchen-grid { grid-template-columns: 1fr; }
}
</style>
