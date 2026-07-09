<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();
const pedidos = ref([]);
const pedidosAnteriores = ref([]); // Track previous state for new ready orders
const avisos = ref([]);
const mesas = Array.from({ length: 10 }, (_, i) => i + 1);
const mesaSeleccionada = ref('');
const menu = ref([]);
const platoSeleccionado = ref('');
const cantidad = ref(1);
const carrito = ref([]);
const ajustePrecio = ref(null);
const ajusteDescripcion = ref('');
const usuarioNombre = ref('');
const bebidaSeleccionada = ref('');
const cantidadBebida = ref(1);
const tipoServicio = ref('local');
const apiOrigin = new URL(api.defaults.baseURL).origin;

const platosMenu = computed(() => menu.value.filter(p => (p.categoria || 'comida') === 'comida'));
const bebidasMenu = computed(() => menu.value.filter(p => (p.categoria || 'comida') === 'bebidas'));

const mesasOcupadas = computed(() => {
  return pedidos.value.filter(p => p.estado !== 'pagado' && p.estado !== 'cancelado').map(p => parseInt(p.mesa));
});

// Create a simple beep sound using Web Audio API (no external files needed)
const reproducirSonido = () => {
  try {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.type = 'sine';
    oscillator.frequency.value = 800;
    gainNode.gain.value = 0.5;
    
    oscillator.start();
    gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.5);
    oscillator.stop(audioContext.currentTime + 0.5);
  } catch (e) {
    console.error('Error playing sound:', e);
  }
};

const mostrarAviso = (mensaje) => {
  avisos.value.push(mensaje);
  setTimeout(() => {
    avisos.value.shift();
  }, 5000);
};

// Verificar sesión
onMounted(async () => {
  const rol = localStorage.getItem('rol');
  if (rol !== 'pedido') { router.push('/login'); return; }
  
  usuarioNombre.value = localStorage.getItem('usuario') || 'Mesero';
  
  await fetchMenu();
  await fetchPedidos();
  pedidosAnteriores.value = [...pedidos.value]; // Initialize previous state
  // Polling para mantener estado de mesas actualizado
  setInterval(fetchPedidos, 5000);
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
    const nuevosPedidos = response.data;
    
    // Check for new orders that are now "preparado"
    for (const pedido of nuevosPedidos) {
      const pedidoAnterior = pedidosAnteriores.value.find(p => p.id === pedido.id);
      if (pedidoAnterior && pedidoAnterior.estado !== 'preparado' && pedido.estado === 'preparado') {
        reproducirSonido();
        mostrarAviso(`¡Pedido listo! Mesa ${pedido.mesa}`);
      }
    }
    
    pedidos.value = nuevosPedidos;
    pedidosAnteriores.value = [...nuevosPedidos];
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

const agregarBebida = () => {
  if (!bebidaSeleccionada.value) return;
  const bebida = menu.value.find(p => p.id === bebidaSeleccionada.value);
  if (bebida) {
    carrito.value.push({
      ...bebida,
      cantidad: cantidadBebida.value
    });
    bebidaSeleccionada.value = '';
    cantidadBebida.value = 1;
  }
};

// Removido: porciones/extras

const eliminarDelCarrito = (index) => {
  carrito.value.splice(index, 1);
};

const menuImageUrl = (obj) => {
  if (!obj) return '';
  if (obj.imagen_url) return obj.imagen_url;
  const img = obj.imagen;
  if (!img) return '';
  if (/^https?:\/\//i.test(img)) return img;
  if (img.startsWith('/')) return apiOrigin + img;
  return `${apiOrigin}/images/menu/${img}`;
};

const buscarMenuPorNombre = (nombre) => {
  const n = (nombre || '').trim().toLowerCase();
  return menu.value.find(m => (m.nombre || '').trim().toLowerCase() === n) || null;
};

const itemsDePedido = (p) => {
  const d = String(p.detalle || '');
  return d.split(',').map(s => s.trim()).map(s => {
    const m = s.match(/^\s*(\d+)\s*x\s*(.+)$/i);
    const nombre = m ? String(m[2]).replace(/\(.*$/, '').trim() : String(s).replace(/\(.*$/, '').trim();
    return buscarMenuPorNombre(nombre);
  }).filter(Boolean);
};

const getDiscountedPrice = (item) => {
  if (!item.discount_percentage) return parseFloat(item.precio);
  return parseFloat(item.precio) * (1 - item.discount_percentage / 100);
};

const total = computed(() => {
  let sum = carrito.value.reduce((acc, item) => acc + (getDiscountedPrice(item) * item.cantidad), 0);
  if (ajustePrecio.value) {
    sum += parseFloat(ajustePrecio.value);
  }
  return sum.toFixed(2);
});

const crearPedido = async () => {
  if (!mesaSeleccionada.value || carrito.value.length === 0) return;

  // Construir detalle string
  let detalleStr = carrito.value.map(item => `${item.cantidad}x ${item.nombre}`).join(', ');
  if (ajustePrecio.value) {
    detalleStr += ` (Ajuste: ${ajusteDescripcion.value} S/.${ajustePrecio.value})`;
  }

  try {
    const response = await api.post('/pedidos', {
      mesa: mesaSeleccionada.value,
      detalle: detalleStr,
      usuario_id: Number(localStorage.getItem('userId')) || undefined,
      tipo_servicio: tipoServicio.value
    });

    if (response.data.success) {
      mesaSeleccionada.value = '';
      carrito.value = [];
      ajustePrecio.value = null;
      ajusteDescripcion.value = '';
      fetchPedidos(); // Recargar lista
      alert('Pedido enviado a cocina correctamente');
    } else {
      alert('Error al crear pedido');
    }
  } catch (error) {
    console.error(error);
    alert('Error de conexión');
  }
};

const cancelarPedido = async (pedido) => {
  try {
    if (String(pedido.estado).toLowerCase() !== 'pedido') {
      alert('Solo se puede cancelar pedidos en estado pedido.');
      return;
    }
    const ok = confirm(`¿Cancelar pedido de la mesa ${pedido.mesa}?`);
    if (!ok) return;
    const r = await api.delete(`/pedidos/${pedido.id}`);
    if (r.data?.success) {
      fetchPedidos();
      alert('Pedido cancelado.');
    } else {
      alert(r.data?.error || 'No se pudo cancelar el pedido.');
    }
  } catch (e) {
    console.error(e);
    alert('Error al cancelar el pedido.');
  }
};

const formatHora = (fechaStr) => {
  if (!fechaStr) return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  
  const isoStr = fechaStr.includes(' ') ? fechaStr.replace(' ', 'T') + 'Z' : fechaStr;
  const date = new Date(isoStr);
  
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const logout = () => {
  localStorage.clear();
  router.push('/login');
};
</script>

<template>
  <div class="pedido-layout">
    <!-- Navbar / Header -->
    <header class="navbar">
      <div class="brand">
        <h1>EL HORNERO</h1>
      </div>
      
      <div class="user-control">
        <div class="user-profile">
          <div class="user-avatar">M</div>
          <span>Mesero</span>
        </div>
        <button class="logout-btn" @click="logout">
          Cerrar Sesión
        </button>
      </div>
    </header>

    <div class="main-content">
      
      <!-- Left Panel: Order Creation -->
      <section class="order-creation-panel">
        <div class="card">
          <div class="card-header">
            <h2>Nuevo Pedido</h2>
          </div>
          <div class="card-body">
            
            <!-- Mesa Selection (Visual Grid) -->
            <div class="form-group">
              <label>Seleccionar Mesa</label>
              <div class="table-grid-selector">
                <button 
                  v-for="m in mesas" 
                  :key="m" 
                  class="table-btn"
                  :class="{ 
                      selected: mesaSeleccionada === m,
                      occupied: mesasOcupadas.includes(m)
                  }"
                  @click="mesaSeleccionada = m"
                >
                  {{ m }}
                </button>
              </div>
            </div>
            
            <div class="form-group">
              <label>Tipo de servicio</label>
              <select v-model="tipoServicio">
                <option value="local">Local</option>
                <option value="llevar">Para llevar</option>
              </select>
            </div>

            <!-- Comida Section -->
            <div class="product-box">
              <label>Comida / Platos</label>
              <div class="add-item-grid">
                <div class="select-wrapper item-select">
                  <select v-model.number="platoSeleccionado">
                    <option value="">-- Seleccionar Plato --</option>
                    <option v-for="p in platosMenu" :key="p.id" :value="p.id">
                        {{ p.nombre }} - S/ {{ parseFloat(p.precio).toFixed(2) }}
                    </option>
                  </select>
                </div>
                
                <div class="qty-actions">
                  <button type="button" @click="cantidad = Math.max(1, cantidad - 1)">-</button>
                  <input type="number" v-model.number="cantidad" min="1" class="qty-input">
                  <button type="button" @click="cantidad++">+</button>
                </div>
                
                <button class="btn-add" @click="agregarTarjeta" :disabled="!platoSeleccionado">
                  Añadir
                </button>
              </div>
            </div>

            <!-- Parallel Sections: Porciones & Bebidas -->
            <!-- Bebidas Section -->
            <div class="product-box drinks-box">
                <label>Bebidas / Refrescos</label>
                <div class="add-item-grid">
                  <div class="select-wrapper item-select">
                    <select v-model.number="bebidaSeleccionada">
                      <option value="">-- Seleccionar --</option>
                      <option v-for="b in bebidasMenu" :key="b.id" :value="b.id">
                          {{ b.nombre }}
                      </option>
                    </select>
                  </div>
                  
                  <div class="qty-actions">
                    <button type="button" @click="cantidadBebida = Math.max(1, cantidadBebida - 1)">-</button>
                    <input type="number" v-model.number="cantidadBebida" min="1" class="qty-input">
                    <button type="button" @click="cantidadBebida++">+</button>
                  </div>
                  
                  <button class="btn-add btn-drink" @click="agregarBebida" :disabled="!bebidaSeleccionada">
                    Añadir
                  </button>
                </div>
            </div>

            <!-- Extras -->
            <div class="extra-options">
               <details>
                   <summary>Opciones Avanzadas (Ajustes de Precio)</summary>
                   <div class="ajuste-grid">
                       <input v-model.number="ajustePrecio" type="number" placeholder="S/ Extra" step="0.10" />
                       <input v-model="ajusteDescripcion" type="text" placeholder="Motivo (ej. Extra queso)">
                   </div>
               </details>
            </div>

            <!-- Cart Preview -->
            <div class="cart-preview">
                <h3>Resumen del Pedido</h3>
                <div v-if="carrito.length === 0" class="empty-cart">
                    No hay items en el pedido actual.
                </div>
                <ul v-else class="cart-list">
                    <li v-for="(item, index) in carrito" :key="index">
                        <div class="cart-item-info">
                            <img :src="menuImageUrl(item)" alt="" class="thumb">
                            <span class="qty">{{ item.cantidad }}x</span>
                            <span class="name">{{ item.nombre }}</span>
                        </div>
                        <div class="cart-item-price">
                            <span v-if="item.discount_percentage" style="text-decoration: line-through; color: #777; font-size: 0.9em; margin-right: 5px;">
                                S/ {{ (item.precio * item.cantidad).toFixed(2) }}
                            </span>
                            S/ {{ (getDiscountedPrice(item) * item.cantidad).toFixed(2) }}
                            <span v-if="item.discount_percentage" style="background: #ef5350; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; margin-left: 5px;">
                                -{{ item.discount_percentage }}%
                            </span>
                            <button @click="eliminarDelCarrito(index)" class="btn-remove">×</button>
                        </div>
                    </li>
                </ul>
                
                <div class="cart-total">
                    <span>Total Estimado:</span>
                    <span class="amount">S/ {{ total }}</span>
                </div>
            </div>
            
            <button class="btn-submit" :disabled="carrito.length === 0 || !mesaSeleccionada" @click="crearPedido">
                ENVIAR A COCINA
            </button>

          </div>
        </div>
      </section>

      <!-- Right Panel: Active Orders List -->
      <section class="active-orders-panel">
         <h3>Pedidos en Curso</h3>
         <div class="orders-list">
             <div v-for="p in pedidos" 
                  :key="p.id" 
                  class="order-card"
                  :class="{ 
                      'status-ready': p.estado === 'preparado', 
                      'status-pending': p.estado === 'pedido',
                      'status-delivered': p.estado === 'entregado'
                  }">
                <div class="order-header">
                    <span class="table-badge">Mesa {{ p.mesa }}</span>
                    <span class="time">{{ formatHora(p.fecha) }}</span>
                </div>
                <div class="order-body">
                    <p>{{ p.detalle }}</p>
                    <p style="margin-top:6px; color:#6b7280;">Tipo: {{ p.tipo_servicio === 'llevar' ? 'Para llevar' : 'Local' }}</p>
                    <div class="thumbs">
                      <img v-for="it in itemsDePedido(p)" :key="it.id" :src="menuImageUrl(it)" alt="" class="thumb">
                    </div>
                </div>
                <div class="order-footer">
                    <span style="float:left; font-weight:700; color:#b45309;">Costo: S/ {{ Number(p.costo || 0).toFixed(2) }}</span>
                    <span class="status-pill">
                        {{ p.estado === 'pedido' ? 'En Cocina' : (p.estado === 'preparado' ? '¡LISTO!' : p.estado.toUpperCase()) }}
                    </span>
                    <button class="btn-cancel" @click="cancelarPedido(p)" :disabled="String(p.estado).toLowerCase() !== 'pedido'">Cancelar</button>
                </div>
             </div>
         </div>
      </section>

    </div>
    
    <!-- Toast notifications -->
    <div class="toast-container">
      <transition-group name="toast">
        <div v-for="(aviso, index) in avisos" :key="index" class="toast">
          🔔 {{ aviso }}
        </div>
      </transition-group>
    </div>
  </div>
</template>

<style src="../styles/pedido.css" scoped>
</style>

<style scoped>
.toast-container {
  position: fixed;
  top: 100px;
  right: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  z-index: 1000;
}

.toast {
  background: #10b981;
  color: white;
  padding: 15px 25px;
  border-radius: 8px;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.toast-enter-active {
  animation: slideIn 0.3s ease-out;
}

.toast-leave-active {
  animation: slideIn 0.3s ease-out reverse;
}
</style>
