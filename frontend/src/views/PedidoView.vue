<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();
const pedidos = ref([]);
const mesas = Array.from({ length: 20 }, (_, i) => i + 1); // 20 mesas simuladas
const mesaSeleccionada = ref('');
const menu = ref([]);
const platoSeleccionado = ref('');
const cantidad = ref(1);
const carrito = ref([]);
const ajustePrecio = ref(null);
const ajusteDescripcion = ref('');
const usuarioNombre = ref('');

// Verificar sesión
onMounted(async () => {
  const rol = localStorage.getItem('rol');
  if (rol !== 'pedido') {
    router.push('/login');
    return;
  }
  
  usuarioNombre.value = localStorage.getItem('usuario') || 'Usuario';
  
  await fetchMenu();
  fetchPedidos();
});

const fetchMenu = async () => {
  try {
    const response = await api.get('/menu');
    menu.value = response.data;
  } catch (error) {
    console.error('Error cargando menú:', error);
  }
};

const fetchPedidos = async () => {
  try {
    const response = await api.get('/pedidos');
    pedidos.value = response.data;
  } catch (error) {
    console.error('Error obteniendo pedidos:', error);
  }
};

const agregarTarjeta = () => {
  if (!platoSeleccionado.value) return;
  const plato = menu.value.find(p => p.id === platoSeleccionado.value);
  if (plato) {
    carrito.value.push({
      ...plato,
      cantidad: cantidad.value
    });
    // Reset inputs
    platoSeleccionado.value = '';
    cantidad.value = 1;
  }
};

const eliminarDelCarrito = (index) => {
  carrito.value.splice(index, 1);
};

const total = computed(() => {
  let sum = carrito.value.reduce((acc, item) => acc + (parseFloat(item.precio) * item.cantidad), 0);
  if (ajustePrecio.value) {
    sum += parseFloat(ajustePrecio.value);
  }
  return sum.toFixed(2);
});

const crearPedido = async () => {
  if (!mesaSeleccionada.value || carrito.value.length === 0) return;

  // Construir detalle string como lo hacía el original
  let detalleStr = carrito.value.map(item => `${item.cantidad}x ${item.nombre}`).join(', ');
  if (ajustePrecio.value) {
    detalleStr += ` (Ajuste: ${ajusteDescripcion.value} S/.${ajustePrecio.value})`;
  }

  try {
    const response = await api.post('/pedidos', {
      mesa: mesaSeleccionada.value,
      detalle: detalleStr
    });

    if (response.data.success) {
      mesaSeleccionada.value = '';
      carrito.value = [];
      ajustePrecio.value = null;
      ajusteDescripcion.value = '';
      fetchPedidos(); // Recargar lista
    } else {
      alert('Error al crear pedido');
    }
  } catch (error) {
    console.error(error);
    alert('Error de conexión');
  }
};

const logout = () => {
  localStorage.clear();
  router.push('/login');
};
</script>

<template>
  <div class="pedido-page">
    <header>
      <div class="header-title">Panel de Pedidos - El Hornero</div>
      <div class="header-user">
        <span>👤 {{ usuarioNombre }}</span>
        <button class="btn-logout" @click="logout">Salir</button>
      </div>
    </header>

    <div class="container">
      <h2>Crear Pedido</h2>

      <label>Mesa</label>
      <select v-model="mesaSeleccionada">
        <option value="">Seleccione una mesa</option>
        <option v-for="m in mesas" :key="m" :value="m">Mesa {{ m }}</option>
      </select>

      <label>Agregar Plato</label>
      <div class="row add-item-row">
        <div class="col-select">
          <select v-model="platoSeleccionado">
            <option value="">Seleccione un plato</option>
            <option v-for="p in menu" :key="p.id" :value="p.id">{{ p.nombre }} - S/. {{ p.precio }}</option>
          </select>
        </div>
        <div class="col-qty">
          <div class="qty-control">
            <button type="button" class="qty-btn" @click="cantidad = Math.max(1, cantidad - 1)">−</button>
            <input v-model.number="cantidad" type="number" min="1" step="1" placeholder="Cant." />
            <button type="button" class="qty-btn" @click="cantidad = cantidad + 1">+</button>
          </div>
          <button class="btn btn-add" @click="agregarTarjeta">Agregar</button>
        </div>
      </div>

      <label>Ajuste de precio externo</label>
      <div class="row ajuste-row">
        <input v-model.number="ajustePrecio" type="number" placeholder="Monto extra" step="0.10" />
        <textarea v-model="ajusteDescripcion" placeholder="Motivo del ajuste"></textarea>
      </div>

      <h3>Detalle del Pedido</h3>
      <div id="listaPedidos">
        <div v-for="(item, index) in carrito" :key="index" class="tarjeta-pedido">
          <div class="item-header">
            <span>{{ item.nombre }}</span>
            <button class="btn-small" @click="eliminarDelCarrito(index)">X</button>
          </div>
          <div class="item-line">
            <span>Cantidad: {{ item.cantidad }}</span>
            <span>Subtotal: S/. {{ (item.precio * item.cantidad).toFixed(2) }}</span>
          </div>
        </div>
      </div>

      <div class="total-box">Total: S/ <span>{{ total }}</span></div>

      <button class="btn" :disabled="carrito.length === 0 || !mesaSeleccionada" @click="crearPedido" style="width:100%; margin-top:20px; font-size:18px;">
        Crear Pedido
      </button>

      <h3 style="margin-top:28px;">Pedidos guardados</h3>
      <div id="pedidosList">
         <div v-for="p in pedidos" :key="p.id" class="tarjeta-pedido" style="background: #fff3e0;">
            <div style="font-weight:bold; color:#e65100;">Mesa {{ p.mesa }} <span style="float:right; font-size:12px; color:#555;">{{ p.estado }}</span></div>
            <div style="margin-top:5px;">{{ p.detalle }}</div>
            <small style="color:#888;">{{ p.fecha }}</small>
         </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Estilos migrados de pedido.html */
.pedido-page {
  font-family: Arial, sans-serif;
  background: #fff6f0; 
  min-height: 100vh;
}
header {
  background: #ff6f00; /* Naranja fuerte */
  color: white;
  padding: 18px;
  text-align: center;
  font-size: 26px;
  letter-spacing: 1px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-title {
  flex: 1;
  text-align: center;
  font-size: clamp(18px, 4.5vw, 26px);
}
.header-user {
  display: flex;
  align-items: center;
  gap: 15px;
  font-size: 14px;
}
.header-user span {
  background: #ffffff;
  color: #e65100;
  padding: 6px 12px;
  border-radius: 20px;
  border: 1px solid #ffd1a6;
  box-shadow: 0 1px 2px rgba(0,0,0,0.06);
}
.btn-logout {
  background: #e64a19;
  color: white;
  border: none;
  padding: 8px 15px;
  border-radius: 5px;
  cursor: pointer;
  font-weight: bold;
  transition: 0.2s;
}
.btn-logout:hover {
  background: #d84315;
}
.container {
  max-width: 900px;
  margin: 25px auto;
  background: #fff;
  padding: 25px;
  border-radius: 14px;
  box-shadow: 0 3px 14px rgba(0,0,0,0.18);
}
h2 { margin-top: 0; color: #e65100; }
h3 { color: #d84315; margin-top: 30px; }
label { font-weight: bold; margin-top: 15px; display: block; color: #bf360c; }
select, input, textarea {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #d9a082;
  font-size: 16px;
  margin-top: 6px;
  background: #fff3e0;
  box-sizing: border-box; /* Fix for width 100% */
}
.ajuste-row {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 10px;
  margin-top: 8px;
}
.ajuste-row input,
.ajuste-row textarea {
  padding: 8px;
  font-size: 14px;
  background: #fff9f2;
  border-color: #e7b793;
}
.ajuste-row input {
  width: 170px;
  max-width: 200px;
}
.ajuste-row textarea {
  min-height: 60px;
  max-height: 100px;
  resize: vertical;
}
.tarjeta-pedido {
  border: 1px solid #ffab80;
  border-radius: 10px;
  padding: 18px;
  background: #fff8f0; /* Más separación visual */
  margin-top: 20px; /* Más espacio entre tarjetas */
  box-shadow: 0 2px 6px rgba(255,138,101,0.3);
}
.grid-2 { display: grid; grid-template-columns: 1fr 80px; gap: 15px; }
.btn {
  background: #e64a19;
  color: white;
  border: none;
  padding: 12px 18px;
  font-size: 16px;
  border-radius: 8px;
  cursor: pointer;
  transition: 0.2s;
  box-shadow: 0 1px 2px rgba(0,0,0,0.08);
  font-weight: 600;
  border: 1px solid #bf360c;
}
.btn:hover { background: #bf360c; }
.btn:active { transform: translateY(1px); }
.btn-small {
  padding: 7px 12px;
  font-size: 14px;
  background: #e64a19;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.btn-small:hover {
  background: #ff7043;
}
.row { display: flex; gap: 15px; margin-top: 10px; }
.row div { flex: 1; }
.add-item-row { align-items: flex-start; }
.col-select { flex: 1; }
.col-qty { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.qty-control { display: flex; align-items: center; gap: 8px; }
.qty-control input { flex: 1; }
.qty-btn {
  background: #ffcc80;
  color: #5d4037;
  border: 1px solid #d9a082;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
}
.qty-btn:hover { background: #ffe0b2; }
.btn-add { width: 100%; }
.item-header {
  font-weight: bold;
  margin-bottom: 10px;
  display: flex;
  justify-content: space-between;
  color: #d84315;
  font-size: 17px;
}
.item-line {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #ffe0cc;
  color: #5d4037;
  font-size: 15px;
}
.total-box {
  font-size: 24px;
  font-weight: bold;
  text-align: right;
  margin-top: 30px;
  color: #bf360c;
}
@media (max-width: 600px) {
  header { flex-direction: column; gap: 10px; }
  .header-user { font-size: 12px; }
  .row { flex-direction: column; }
  .ajuste-row { grid-template-columns: 1fr; }
  .ajuste-row input { width: 100%; max-width: 100%; }
  .container { margin: 10px auto; padding: 16px; border-radius: 10px; }
  h2 { font-size: clamp(18px, 5vw, 22px); }
  h3 { font-size: clamp(16px, 4.5vw, 20px); }
  select, input, textarea { font-size: 16px; padding: 12px; }
  .qty-btn { width: 40px; height: 40px; font-size: 22px; }
}
button:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}
</style>
