<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../api';

const usuarioNombre = ref('Cajero Principal');
const estadoCaja = ref('Cerrada'); // Abierta, Cerrada
const cajaId = ref(null);
const activeTab = ref('mesas'); // mesas, historial
const selectedMesa = ref(null);

// Configuración de Mesas (Físicas)
const TOTAL_MESAS = 9; // Definimos 9 mesas por defecto
const mesas = ref([]);

// Historial
const historialVentas = ref([]);

// Cargar estado inicial
const initMesas = () => {
    const arr = [];
    for (let i = 1; i <= TOTAL_MESAS; i++) {
        arr.push({
            id: i,
            numero: `M-0${i}`,
            estado: 'libre', // libre, ocupada, por_pagar
            total: 0,
            items: [] // Aquí irán los pedidos
        });
    }
    mesas.value = arr;
};

// Helper Computeds
const totalMesaSeleccionada = computed(() => {
  if (!selectedMesa.value) return 0.00;
  if (selectedMesa.value.items && selectedMesa.value.items.length > 0) {
      // Sumar precio de items (asumiendo que el detalle del pedido pueda tener precio, 
      // pero el backend actual solo guarda 'detalle' string. 
      return selectedMesa.value.items.reduce((acc, item) => acc + (item.precio || 0), 0);
  }
  return 0;
});

const igv = computed(() => totalMesaSeleccionada.value * 0.18);
const subtotal = computed(() => totalMesaSeleccionada.value - igv.value);

// Actions
const selectMesa = (mesa) => {
    selectedMesa.value = mesa;
};

// Fetch Data Real
const cargarPedidos = async () => {
    try {
        const response = await api.get('/pedidos');
        const pedidos = response.data;
        
        // Reiniciar estado de mesas
        initMesas(); 

        // Mapear pedidos a mesas usando costo real y derivando estado 'por_pagar'
        pedidos.forEach(p => {
            if (p.estado === 'pagado') return; // ignorar pagados en el mapa de ocupación
            const mesaIndex = mesas.value.findIndex(m => m.id === parseInt(p.mesa));
            if (mesaIndex !== -1) {
                // Derivar estado de la mesa:
                // - Si hay entregados sin pagar -> 'por_pagar'
                // - Si hay en cocina o preparados -> 'ocupada'
                const current = mesas.value[mesaIndex].estado;
                const derived = p.estado === 'entregado' ? 'por_pagar' : 'ocupada';
                mesas.value[mesaIndex].estado = current === 'por_pagar' ? 'por_pagar' : derived;
                
                mesas.value[mesaIndex].items.push({
                    id: p.id,
                    nombre: p.detalle,
                    cantidad: 1,
                    precio: Number(p.costo || 0),
                    estado: p.estado
                });
            }
        });

        // Recalcular totales por mesa
        mesas.value.forEach(m => {
            if (m.items.length > 0) {
                m.total = m.items.reduce((acc, i) => acc + i.precio, 0);
            } else {
                m.total = 0;
            }
        });

    } catch (e) {
        console.error("Error cargando pedidos:", e);
    }
};

const procesarPagoSI = async (metodo) => {
    if(!selectedMesa.value) return;
    
    if (selectedMesa.value.items.length === 0) {
        alert("Esta mesa no tiene pedidos pendientes.");
        return;
    }

    if (!confirm(`¿Confirmar pago de S/ ${totalMesaSeleccionada.value.toFixed(2)} con ${metodo}?`)) return;

    try {
        const montoTotal = totalMesaSeleccionada.value;
        const itemsAActualizar = [...selectedMesa.value.items]; // Copia

        // 1. Registrar Venta en Caja
        await api.post('/caja/venta', { monto: montoTotal });

        // 2. Actualizar estado de los pedidos a 'pagado'
        const updates = itemsAActualizar.map(item => 
            api.post('/pedidos/actualizar', { id: item.id, estado: 'pagado' })
        );
        await Promise.all(updates);

        // 3. UI Updates
        historialVentas.value.unshift({
            id: Date.now(), // Temp ID
            hora: new Date().toLocaleTimeString('es-PE', {hour: '2-digit', minute:'2-digit'}),
            detalle: `Mesa ${selectedMesa.value.numero}`,
            total: montoTotal,
            metodo: metodo,
            estado: 'Completado'
        });

        // Refetch de pedidos para limpiar la mesa visualmente
        await cargarPedidos();
        selectedMesa.value = null; // Deseleccionar
        
        alert("Pago procesado correctamente.");

    } catch (e) {
        console.error(e);
        alert("Error al procesar el pago.");
    }
};

// Existing Functionality Integration
const actualizarEstado = async () => {
    try {
        const response = await api.get('/caja/estado');
        if (response.data && response.data.estado) {
            estadoCaja.value = response.data.estado;
            cajaId.value = response.data.id;
        } else {
             estadoCaja.value = 'Cerrada';
        }
    } catch(e) { console.error(e); }
};

const abrirCaja = async () => {
    try {
        await api.post('/caja/abrir');
        estadoCaja.value = 'Abierta';
        actualizarEstado(); 
    } catch(e) { console.error(e); alert('Error abriendo caja'); }
};

const cerrarCaja = async () => {
   try {
        await api.post('/caja/cerrar');
        estadoCaja.value = 'Cerrada';
        actualizarEstado(); 
    } catch(e) { console.error(e); alert('Error cerrando caja'); }
};

onMounted(() => {
    // const rol = localStorage.getItem('rol');
    // Validation Logic (Commented for dev ease if needed)
    // if (rol !== 'caja') router.push('/login');
    
    usuarioNombre.value = localStorage.getItem('usuario') || 'Cajero';
    initMesas();
    actualizarEstado();
    cargarPedidos(); // Cargar estado inicial de mesas

    // Polling opcional para actualizar mesas en tiempo real
    setInterval(cargarPedidos, 5000); 
});
</script>

<template>
  <div class="pos-container">
    
    <!-- Sidebar / Navigation -->
    <aside class="pos-sidebar">
      <div class="logo-area">
        <h1>EL HORNERO</h1>
        <span>POS SYSTEM</span>
      </div>
      
      <nav class="nav-menu">
        <button 
            @click="activeTab = 'mesas'" 
            :class="{ active: activeTab === 'mesas' }">
            <span class="icon"></span> Panel de Mesas
        </button>
        <button 
            @click="activeTab = 'historial'" 
            :class="{ active: activeTab === 'historial' }">
            <span class="icon"></span> Historial/Cierre
        </button>
        <button class="disabled"><span class="icon"></span> Configuración</button>
      </nav>

      <div class="user-profile">
        <div class="avatar-circle">
            {{ usuarioNombre.charAt(0).toUpperCase() }}
        </div>
        <div class="user-info">
            <p class="name">{{ usuarioNombre }}</p>
            <p class="role">Cajero</p>
        </div>
        <button @click="cerrarCaja" class="logout-btn" title="Cerrar Caja">🛑</button>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="pos-content">
        
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="status-indicator">
                <span class="label">Estado de Caja:</span>
                <span class="badge" :class="estadoCaja.toLowerCase() === 'abierta' ? 'green' : 'red'">
                    {{ estadoCaja.toUpperCase() }}
                </span>
                <button v-if="estadoCaja.toLowerCase() !== 'abierta'" @click="abrirCaja" class="btn-mini-action">
                    Abrir Turno
                </button>
            </div>
            <div class="date-display">
                {{ new Date().toLocaleDateString('es-PE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
            </div>
        </header>

        <!-- VIEW: MESAS (Grid + Detail) -->
        <div v-if="activeTab === 'mesas'" class="view-mesas">
            
            <!-- Left: Table Map -->
            <div class="table-map-section">
                <div class="section-header">
                    <h2>Sala Principal</h2>
                    <div class="legend">
                        <span class="dot free"></span> Libre
                        <span class="dot busy"></span> Ocupada
                        <span class="dot pay"></span> Por Pagar
                    </div>
                </div>
                
                <div class="tables-grid">
                    <div 
                        v-for="mesa in mesas" 
                        :key="mesa.id"
                        class="table-card"
                        :class="[mesa.estado, { selected: selectedMesa?.id === mesa.id }]"
                        @click="selectMesa(mesa)"
                    >
                        <div class="table-number">{{ mesa.numero }}</div>
                        <div class="table-status">
                            <span v-if="mesa.estado === 'libre'">Disponible</span>
                            <span v-else-if="mesa.estado === 'ocupada'">Ocupada</span>
                            <span v-else>Cobrar</span>
                        </div>
                        <div v-if="mesa.total > 0" class="table-total">
                            S/ {{ mesa.total.toFixed(2) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Order Detail / Payment -->
            <div class="order-detail-section">
                <!-- Empty State -->
                <div v-if="!selectedMesa" class="empty-state">
                    <div class="icon"></div>
                    <h3>Selecciona una mesa</h3>
                    <p>Para ver el detalle o procesar el pago</p>
                </div>

                <!-- Active Order State -->
                <div v-else class="receipt-panel">
                    <div class="receipt-header">
                        <h3>Detalle de Consumo</h3>
                        <span class="table-tag">{{ selectedMesa.numero }}</span>
                    </div>

                    <div class="order-items">
                        <div v-if="selectedMesa.items.length === 0" class="no-items">
                            Mesa sin pedidos registrados.
                        </div>
                        <div 
                            v-for="item in selectedMesa.items" 
                            :key="item.id" 
                            class="order-item"
                        >
                            <div class="item-qty">{{ item.cantidad }}x</div>
                            <div class="item-desc">
                                <span class="name">{{ item.nombre }}</span>
                                <span class="unit-price">S/ {{ item.precio.toFixed(2) }}</span>
                            </div>
                            <div class="item-total">S/ {{ (item.cantidad * item.precio).toFixed(2) }}</div>
                        </div>
                    </div>

                    <div class="receipt-summary">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>S/ {{ subtotal.toFixed(2) }}</span>
                        </div>
                        <div class="summary-row">
                            <span>IGV (18%)</span>
                            <span>S/ {{ igv.toFixed(2) }}</span>
                        </div>
                        <div class="summary-row total">
                            <span>TOTAL A PAGAR</span>
                            <span>S/ {{ totalMesaSeleccionada.toFixed(2) }}</span>
                        </div>
                    </div>

                    <div class="payment-actions">
                        <h4>Método de Pago</h4>
                        <div class="payment-grid">
                            <button @click="procesarPagoSI('Efectivo')" class="pay-btn cash">
                                 Efectivo
                            </button>
                            <button @click="procesarPagoSI('Tarjeta')" class="pay-btn card">
                                Tarjeta
                            </button>
                            <button @click="procesarPagoSI('Yape')" class="pay-btn digital">
                                 Yape/Plin
                            </button>
                        </div>
                        
                        <div class="secondary-actions">
                            <button class="btn-sec">🖨️ Pre-Cuenta</button>
                            <button class="btn-sec" @click="selectedMesa = null">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VIEW: HISTORIAL -->
        <div v-if="activeTab === 'historial'" class="view-historial">
            <div class="card history-card">
                <div class="card-header">
                    <h2>Historial de Movimientos</h2>
                    <button class="btn-export">Exportar Reporte</button>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th># Op</th>
                                <th>Hora</th>
                                <th>Detalle</th>
                                <th>Método</th>
                                <th>Estado</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="venta in historialVentas" :key="venta.id">
                                <td>{{ venta.id }}</td>
                                <td>{{ venta.hora }}</td>
                                <td>{{ venta.detalle }}</td>
                                <td><span class="badge-method">{{ venta.metodo }}</span></td>
                                <td>
                                    <span class="status-pill completed">✔ {{ venta.estado }}</span>
                                </td>
                                <td class="text-right font-bold">S/ {{ venta.total.toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
  </div>
</template>

<style scoped>
/* Modern Reset & Base */
.pos-container {
    display: flex;
    height: 100vh;
    background-color: #f3f4f6;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1f2937;
    overflow: hidden;
}

/* Sidebar */
.pos-sidebar {
    width: 260px;
    background: #111827;
    color: white;
    display: flex;
    flex-direction: column;
    padding: 20px;
    box-shadow: 4px 0 15px rgba(0,0,0,0.1);
    z-index: 10;
}

.logo-area {
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 1px solid #374151;
}
.logo-area h1 {
    font-size: 24px;
    font-weight: 800;
    color: #f59e0b; /* Amber/Orange */
    margin: 0;
    letter-spacing: -1px;
}
.logo-area span {
    font-size: 12px;
    color: #9ca3af;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.nav-menu {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.nav-menu button {
    background: transparent;
    border: none;
    color: #d1d5db;
    padding: 12px 15px;
    text-align: left;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s;
}
.nav-menu button:hover {
    background: #374151;
    color: white;
}
.nav-menu button.active {
    background: #f59e0b;
    color: #111827;
    font-weight: 600;
}
.nav-menu button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.user-profile {
    margin-top: auto;
    background: #1f2937;
    padding: 12px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.avatar-circle {
    width: 36px;
    height: 36px;
    background: #4b5563;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
}
.user-info {
    flex: 1;
    overflow: hidden;
}
.user-info .name {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.user-info .role {
    margin: 0;
    font-size: 11px;
    color: #9ca3af;
}
.logout-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    padding: 5px;
    border-radius: 4px;
}
.logout-btn:hover {
    background: rgba(255,255,255,0.1);
}

/* Main Content */
.pos-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

/* Top Bar */
.top-bar {
    height: 70px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.status-indicator {
    display: flex;
    align-items: center;
    gap: 15px;
}
.label { font-size: 14px; color: #6b7280; }
.badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.badge.green { background: #d1fae5; color: #047857; }
.badge.red { background: #fee2e2; color: #b91c1c; }
.btn-mini-action {
    padding: 6px 12px;
    border: 1px solid #e5e7eb;
    background: white;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
}
.btn-mini-action:hover { background: #f9fafb; }

.date-display {
    color: #6b7280;
    font-weight: 500;
    text-transform: capitalize;
}

/* View: Mesas */
.view-mesas {
    flex: 1;
    display: flex;
    overflow: hidden;
    padding: 20px;
    gap: 20px;
}

/* Table Map Section */
.table-map-section {
    flex: 1; /* Takes remaining space */
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}
.section-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}
.legend {
    display: flex;
    gap: 15px;
    font-size: 13px;
    color: #6b7280;
}
.legend .dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 5px;
}
.dot.free { background: #10b981; }
.dot.busy { background: #ef4444; }
.dot.pay { background: #f59e0b; }

.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 20px;
}

.table-card {
    aspect-ratio: 1;
    border-radius: 24px;
    background: white;
    border: 2px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.table-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
.table-card.selected { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }

.table-card.libre .table-number { color: #10b981; background: #ecfdf5; }
.table-card.ocupada .table-number { color: #ef4444; background: #fef2f2; }
.table-card.por_pagar .table-number { color: #f59e0b; background: #fffbeb; }

.table-number {
    font-size: 24px;
    font-weight: 800;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-bottom: 10px;
}
.table-status { font-size: 13px; font-weight: 500; }
.table-total {
    margin-top: 8px;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
}

/* Order Detail Section */
.order-detail-section {
    width: 380px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    text-align: center;
    padding: 40px;
}
.empty-state .icon { font-size: 48px; margin-bottom: 20px; }
.empty-state h3 { color: #374151; margin: 0 0 10px; }

.receipt-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.receipt-header {
    padding: 20px;
    border-bottom: 1px dashed #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f9fafb;
}
.receipt-header h3 { margin: 0; font-size: 16px; color: #374151; }
.table-tag {
    background: #111827;
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 12px;
}

.order-items {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}
.order-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 15px;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f3f4f6;
}
.order-item:last-child { border-bottom: none; }
.item-qty { font-weight: 700; color: #f59e0b; min-width: 25px; }
.item-desc { flex: 1; display: flex; flex-direction: column; }
.item-desc .name { font-weight: 500; font-size: 14px; margin-bottom: 2px; }
.item-desc .unit-price { font-size: 12px; color: #9ca3af; }
.item-total { font-weight: 600; font-size: 14px; }

.receipt-summary {
    padding: 20px;
    background: #f9fafb;
    border-top: 1px dashed #e5e7eb;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
    color: #6b7280;
}
.summary-row.total {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px solid #e5e7eb;
    font-weight: 800;
    font-size: 18px;
    color: #111827;
}

.payment-actions {
    padding: 20px;
    background: white;
    border-top: 1px solid #e5e7eb;
}
.payment-actions h4 {
    margin: 0 0 15px;
    font-size: 12px;
    text-transform: uppercase;
    color: #9ca3af;
    letter-spacing: 1px;
}
.payment-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 15px;
}
.pay-btn {
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
    transition: transform 0.1s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.pay-btn:active { transform: scale(0.98); }
.pay-btn.cash { grid-column: 1 / -1; background: #10b981; color: white; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3); }
.pay-btn.card { background: #3b82f6; color: white; }
.pay-btn.digital { background: #8b5cf6; color: white; }

.secondary-actions {
    display: flex;
    gap: 10px;
}
.btn-sec {
    flex: 1;
    padding: 8px;
    border: 1px solid #e5e7eb;
    background: white;
    border-radius: 6px;
    font-size: 12px;
    color: #4b5563;
    cursor: pointer;
}
.btn-sec:hover { background: #f3f4f6; }

/* View: Historial */
.view-historial {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}
.history-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}
.btn-export {
    background: #111827;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
}
.table-responsive {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th {
    text-align: left;
    padding: 15px;
    color: #6b7280;
    font-size: 13px;
    font-weight: 500;
    border-bottom: 1px solid #e5e7eb;
}
.data-table td {
    padding: 15px;
    color: #111827;
    font-size: 14px;
    border-bottom: 1px solid #f3f4f6;
}
.badge-method {
    padding: 4px 10px;
    background: #eef2ff;
    color: #4f46e5;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.status-pill.completed {
    color: #059669;
    font-weight: 600;
    font-size: 13px;
}
.text-right { text-align: right; }
.font-bold { font-weight: 700; }

</style>
