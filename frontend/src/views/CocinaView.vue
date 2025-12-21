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
    
    // Si cambia a preparado, mostrar aviso (como en el original)
    if (nuevoEstado === 'preparado') {
      const pedido = pedidos.value.find(p => p.id === id);
      mostrarAviso(`Pedido listo para la mesa ${pedido ? pedido.mesa : '?'}`);
    }

    fetchPedidos(); // Refrescar inmediatamente
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

const pendientes = computed(() => pedidos.value.filter(p => p.estado === 'pedido'));
const preparados = computed(() => pedidos.value.filter(p => p.estado === 'preparado')); // En el html dice 'preparado'
const entregados = computed(() => pedidos.value.filter(p => p.estado === 'entregado'));
</script>

<template>
  <div class="cocina-page">
    <div class="container">
      <h1>Vista Cocina</h1>
      
      <div class="estados">
        <!-- Columna Pendientes (Pedidos) -->
        <div class="estado" id="estado-pedido">
          <h2>Pedidos</h2>
          <div v-for="p in pendientes" :key="p.id" class="tarjeta">
            <div class="hora">{{ p.fecha ? p.fecha.split(' ')[1] : '' }}</div>
            <div class="mesa">Mesa {{ p.mesa }}</div>
            <div class="detalle">{{ p.detalle }}</div>
            <button class="boton" @click="cambiarEstado(p.id, 'preparado')">Marcar como PREPARADO</button>
          </div>
        </div>

        <!-- Columna Preparados -->
        <div class="estado" id="estado-preparado">
          <h2>Preparados</h2>
          <div v-for="p in preparados" :key="p.id" class="tarjeta">
             <div class="hora">{{ p.fecha ? p.fecha.split(' ')[1] : '' }}</div>
             <div class="mesa">Mesa {{ p.mesa }}</div>
             <div class="detalle">{{ p.detalle }}</div>
             <button class="boton" @click="cambiarEstado(p.id, 'entregado')">Marcar como ENTREGADO</button>
          </div>
        </div>

        <!-- Columna Entregados -->
        <div class="estado" id="estado-entregado">
          <h2>Entregados</h2>
           <div v-for="p in entregados" :key="p.id" class="tarjeta">
             <div class="hora">{{ p.fecha ? p.fecha.split(' ')[1] : '' }}</div>
             <div class="mesa">Mesa {{ p.mesa }}</div>
             <div class="detalle">{{ p.detalle }}</div>
             <!-- No button here -->
          </div>
        </div>
      </div>

      <div id="avisos">
        <div v-for="(aviso, idx) in avisos" :key="idx" class="aviso">{{ aviso }}</div>
      </div>
      
    </div>
  </div>
</template>

<style scoped>
/* Estilos migrados de cocina.html */
.cocina-page {
  font-family: 'Segoe UI', Arial, sans-serif;
  background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
  min-height: 100vh;
  margin: 0;
  padding-top: 20px;
}
.container {
  max-width: 1300px;
  margin: 40px auto;
  padding: 30px 20px;
  background: #fff8e1;
  border-radius: 18px;
  box-shadow: 0 6px 32px rgba(255, 152, 0, 0.13);
}
h1 {
  text-align: center;
  margin-bottom: 35px;
  color: #ff6f00;
  letter-spacing: 2px;
  font-size: 2.5em;
}
.estados {
  display: flex;
  gap: 30px;
  justify-content: center;
  flex-wrap: wrap; /* Added for responsiveness */
}
.estado {
  flex: 1;
  min-width: 300px; /* Added min-width */
  background: #fffde7;
  border-radius: 14px;
  padding: 18px 10px 30px 10px;
  min-height: 350px;
  box-shadow: 0 2px 12px rgba(255, 193, 7, 0.08);
}
.estado h2 {
  text-align: center;
  color: #ff9800;
  font-size: 1.5em;
  margin-bottom: 18px;
}
.tarjeta {
  background: linear-gradient(90deg, #ffe0b2 0%, #fffde7 100%);
  border: 2px solid #ffb300;
  border-radius: 12px;
  margin: 18px 0;
  padding: 22px 18px 18px 18px;
  box-shadow: 0 4px 16px rgba(255, 167, 38, 0.13);
  position: relative;
  transition: transform 0.1s;
}
.tarjeta:hover {
  transform: scale(1.03);
  box-shadow: 0 8px 32px rgba(255, 152, 0, 0.18);
}
.tarjeta .mesa {
  font-weight: bold;
  color: #e65100;
  font-size: 1.2em;
  margin-bottom: 8px;
}
.tarjeta .detalle {
  margin: 8px 0 12px 0;
  color: #6d4c41;
  font-size: 1.1em;
}
.tarjeta .hora {
  position: absolute;
  top: 12px;
  right: 18px;
  color: #ff9800;
  font-size: 0.95em;
}
.boton {
  background: linear-gradient(90deg, #ff9800 0%, #ffd54f 100%);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px 22px;
  cursor: pointer;
  font-size: 1.1em;
  font-weight: bold;
  margin-top: 10px;
  box-shadow: 0 2px 8px rgba(255, 152, 0, 0.13);
  transition: background 0.2s;
  width: 100%; /* Make full width for better touch */
}
.boton:disabled {
  background: #ffe0b2;
  color: #bdbdbd;
}
.aviso {
  background: #fff8e1;
  color: #ff6f00;
  border-radius: 8px;
  padding: 12px;
  margin: 18px 0;
  font-size: 1.1em;
  text-align: center;
  border: 2px solid #ffd54f;
  box-shadow: 0 2px 8px rgba(255, 193, 7, 0.08);
}
@media (max-width: 900px) {
  .estados { flex-direction: column; gap: 18px; }
}
</style>
