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
  // Validation logic (commented for flexibility during dev)
  // if (rol !== 'pedido') { router.push('/login'); return; }
  
  usuarioNombre.value = localStorage.getItem('usuario') || 'Mesero';
  
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

  // Construir detalle string
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
      alert('Pedido enviado a cocina correctamente');
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
                  :class="{ selected: mesaSeleccionada === m }"
                  @click="mesaSeleccionada = m"
                >
                  {{ m }}
                </button>
              </div>
            </div>

            <!-- Item Selection -->
            <div class="form-group">
              <label>Agregar Productos</label>
              <div class="add-item-grid">
                <div class="select-wrapper item-select">
                  <select v-model="platoSeleccionado">
                    <option value="">-- Seleccionar Plato --</option>
                    <option v-for="p in menu" :key="p.id" :value="p.id">
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
                            <span class="qty">{{ item.cantidad }}x</span>
                            <span class="name">{{ item.nombre }}</span>
                        </div>
                        <div class="cart-item-price">
                            S/ {{ (item.precio * item.cantidad).toFixed(2) }}
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
         <h3>📋 Pedidos en Curso</h3>
         <div class="orders-list">
             <div v-for="p in pedidos" 
                  :key="p.id" 
                  class="order-card"
                  :class="p.estado === 'listo' ? 'status-ready' : 'status-pending'">
                <div class="order-header">
                    <span class="table-badge">Mesa {{ p.mesa }}</span>
                    <span class="time">{{ new Date(p.created_at || Date.now()).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</span>
                </div>
                <div class="order-body">
                    <p>{{ p.detalle }}</p>
                </div>
                <div class="order-footer">
                    <span class="status-pill">
                        {{ p.estado.toUpperCase() }}
                    </span>
                </div>
             </div>
         </div>
      </section>

    </div>
  </div>
</template>

<style scoped>
/* Modern Layout Variables */
.pedido-layout {
  --primary: #f59e0b; /* Amber 500 */
  --primary-dark: #d97706;
  --bg-color: #f3f4f6;
  --card-bg: #ffffff;
  --text-main: #1f2937;
  --text-muted: #6b7280;
  
  font-family: 'Inter', system-ui, sans-serif;
  background-color: var(--bg-color);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* Header */
.navbar {
  background: #111827;
  color: white;
  padding: 15px 30px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.brand h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: #f1af32;
  display: inline-block;
}
.brand .subtitle {
  color: #9ca3af;
  font-size: 13px;
  letter-spacing: 1px;
  text-transform: uppercase;
  border-left: 1px solid #374151;
  padding-left: 10px;
}

.user-control { display: flex; align-items: center; gap: 14px; }
.user-profile { display: flex; align-items: center; gap: 8px; background: white; padding: 6px 14px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: #1f2937; }
.user-avatar { width: 28px; height: 28px; border-radius: 50%; background: #f1af32; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
.logout-btn { background: #e53935; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background 0.2s; }
.logout-btn:hover { background: #d32f2f; }

/* Main Content Grid */
.main-content {
    flex: 1;
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 30px;
    padding: 30px;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
}

/* Cards */
.card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
}
.card-header {
    padding: 20px 25px;
    border-bottom: 1px solid #f3f4f6;
}
.card-header h2 {
    margin: 0;
    color: var(--text-main);
    font-size: 18px;
    font-weight: 700;
}
.card-body { padding: 25px; }

/* Forms */
.form-group { margin-bottom: 25px; }
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 14px;
    color: var(--text-main);
}

/* New Table Grid Selector */
.table-grid-selector {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
    gap: 10px;
}
.table-btn {
    background: #f3f4f6;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 0;
    font-weight: 700;
    font-size: 16px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s;
}
.table-btn:hover {
    background: #e5e7eb;
    border-color: #d1d5db;
}
.table-btn.selected {
    background: #111827;
    color: var(--primary);
    border-color: #111827;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

select, input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: white;
}
select:focus, input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.1);
}


/* Add Item Grid */
.add-item-grid {
    display: flex;
    gap: 10px;
}
.item-select { flex: 2; }
.qty-actions {
    display: flex;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    overflow: hidden;
}
.qty-actions button {
    padding: 0 12px;
    border: none;
    background: #f9fafb;
    cursor: pointer;
    font-weight: bold;
}
.qty-actions button:hover { background: #e5e7eb; }
.qty-input {
    width: 50px;
    text-align: center;
    border: none;
    border-left: 1px solid #d1d5db;
    border-right: 1px solid #d1d5db;
    border-radius: 0;
    padding: 0;
}
.btn-add {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0 20px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-add:hover:not(:disabled) { background: var(--primary-dark); }
.btn-add:disabled { opacity: 0.5; cursor: not-allowed; }

/* Extra Options */
.extra-options { margin-bottom: 25px; font-size: 13px; color: var(--text-muted); }
.ajuste-grid {
    display: grid;
    grid-template-columns: 100px 1fr;
    gap: 10px;
    margin-top: 10px;
}

/* Cart */
.cart-preview {
    background: #f9fafb;
    padding: 20px;
    border-radius: 8px;
    border: 1px dashed #d1d5db;
}
.cart-preview h3 {
    margin: 0 0 15px;
    font-size: 14px;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.5px;
}
.cart-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.cart-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
}
.cart-list li:last-child { border-bottom: none; }
.cart-item-info { display: flex; gap: 10px; align-items: center; }
.qty { font-weight: 700; color: var(--primary-dark); }
.name { font-weight: 500; }
.cart-item-price { display: flex; align-items: center; gap: 15px; font-weight: 600; }
.btn-remove {
    background: none;
    border: none;
    color: #ef4444;
    font-weight: bold;
    font-size: 18px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.cart-total {
    margin-top: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 16px;
    font-weight: 700;
}
.amount { font-size: 20px; color: var(--text-main); }

.btn-submit {
    width: 100%;
    margin-top: 20px;
    padding: 15px;
    background: #111827;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    letter-spacing: 0.5px;
    transition: transform 0.1s;
}
.btn-submit:hover:not(:disabled) { background: black; transform: translateY(-1px); }
.btn-submit:disabled { background: #9ca3af; cursor: not-allowed; }

/* Right Panel */
.active-orders-panel h3 {
    font-size: 16px;
    color: var(--text-muted);
    margin: 0 0 20px;
    text-transform: uppercase;
}
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.order-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    border-left: 4px solid #d1d5db;
}
.order-card.status-pending { border-left-color: #f59e0b; }
.order-card.status-ready { border-left-color: #10b981; }

.order-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}
.table-badge {
    background: #111827;
    color: white;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 12px;
}
.time { font-size: 12px; color: var(--text-muted); }
.order-body p { margin: 0; font-size: 14px; line-height: 1.5; color: #374151; }
.order-footer { margin-top: 12px; text-align: right; }
.status-pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    background: #f3f4f6;
    color: #6b7280;
}
.status-ready .status-pill { background: #d1fae5; color: #047857; }

@media (max-width: 900px) {
    .main-content { grid-template-columns: 1fr; }
}
</style>
