<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();
const apiOrigin = new URL(api.defaults.baseURL).origin;
const usuarioNombre = ref('Cajero Principal');
const estadoCaja = ref('Cerrada'); // Abierta, Cerrada
const cajaId = ref(null);
const activeTab = ref('mesas'); // mesas, historial
const selectedMesa = ref(null);
const cajaConfig = ref({ nombre_comercial: '', ruc: '', direccion: '', telefono: '', yape_numero: '' });
const tipoComprobante = ref('BOLETA'); // BOLETA, FACTURA

// Configuración de Mesas (Físicas)
const TOTAL_MESAS = 10; // Definimos 9 mesas por defecto
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

// Boleta Preview
const showBoletaPreview = ref(false);
const boletaData = computed(() => {
  if (!selectedMesa.value) return null;
  const items = selectedMesa.value.items || [];
  const total = items.reduce((acc, i) => acc + (i.precio || 0), 0);
  const igvVal = total * 0.18;
  const subVal = total - igvVal;
  return {
    mesa: selectedMesa.value.numero,
    items,
    subtotal: subVal,
    igv: igvVal,
    total,
    tipo: tipoComprobante.value
  };
});
const openBoleta = () => { if (selectedMesa.value) showBoletaPreview.value = true; };
const closeBoleta = () => { showBoletaPreview.value = false; };

// Yape Modal
const showYapeModal = ref(false);
const openYape = () => { showYapeModal.value = true; };
const closeYape = () => { showYapeModal.value = false; };
const confirmarPagoYape = async () => {
  await procesarPagoSI('Yape');
  closeYape();
};

// Cierre por rango
const cierreFrom = ref('');
const cierreTo = ref('');
const cierreList = ref([]);
const loadCierre = async () => {
  const params = {};
  if (cierreFrom.value) params.from = cierreFrom.value;
  if (cierreTo.value) params.to = cierreTo.value;
  try {
    const r = await api.get('/admin/reportes/recibos-entregados', { params });
    cierreList.value = r.data;
  } catch (e) { console.error(e); }
};

// Actions
const selectMesa = (mesa) => {
    selectedMesa.value = mesa;
    // Resetear tipo a Boleta por defecto al seleccionar nueva mesa
    tipoComprobante.value = 'BOLETA';
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

    if (!confirm(`¿Confirmar pago de S/ ${totalMesaSeleccionada.value.toFixed(2)} con ${metodo} (${tipoComprobante.value})?`)) return;

    try {
        const montoTotal = totalMesaSeleccionada.value;
        const itemsAActualizar = [...selectedMesa.value.items]; // Copia

        // 1. Registrar Venta en Caja
        const resVenta = await api.post('/caja/venta', { monto: montoTotal, metodo_pago: metodo });
        const ventaId = resVenta.data.ventaId;

        // 2. Generar Recibo y marcar ENVIADO (para reflejar en cierre desde BD)
        try {
          await api.post('/caja/recibo', { venta_id: ventaId, tipo: tipoComprobante.value });
        } catch (eRec) {
          console.error('Recibo error:', eRec);
        }

        // 3. Actualizar estado de los pedidos a 'pagado' y vincular venta
        const updates = itemsAActualizar.map(item => 
            api.post('/pedidos/actualizar', { id: item.id, estado: 'pagado', venta_id: ventaId })
        );
        await Promise.all(updates);

        // 4. UI Updates
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
        await loadCierre(); // Refrescar cierre con datos de BD
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
    const rol = localStorage.getItem('rol');
    if (rol !== 'caja') { router.push('/login'); return; }
    
    usuarioNombre.value = localStorage.getItem('usuario') || 'Cajero';
    initMesas();
    actualizarEstado();
    cargarPedidos(); // Cargar estado inicial de mesas
    api.get('/admin/caja/config').then(r => { cajaConfig.value = r.data || {}; }).catch(()=>{});

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
                        <div style="margin-bottom:15px; display:flex; gap:10px; justify-content:center;">
                            <label style="display:flex; align-items:center; gap:5px; cursor:pointer;">
                                <input type="radio" value="BOLETA" v-model="tipoComprobante"> Boleta
                            </label>
                            <label style="display:flex; align-items:center; gap:5px; cursor:pointer;">
                                <input type="radio" value="FACTURA" v-model="tipoComprobante"> Factura
                            </label>
                        </div>
                        <h4>Método de Pago</h4>
                        <div class="payment-grid">
                            <button @click="procesarPagoSI('Efectivo')" class="pay-btn cash">
                                 Efectivo
                            </button>
                            <button @click="procesarPagoSI('Tarjeta')" class="pay-btn card">
                                Tarjeta
                            </button>
                            <button @click="openYape" class="pay-btn digital">
                                 Yape
                            </button>
                        </div>
                        
                        <div class="secondary-actions">
                            <button class="btn-sec" @click="openBoleta">🧾 Ver Recibo</button>
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
                    <h2>Historial / Cierre por Rango</h2>
                    <div class="filters">
                      <label>Desde</label>
                      <input type="date" v-model="cierreFrom" @change="loadCierre">
                      <label>Hasta</label>
                      <input type="date" v-model="cierreTo" @change="loadCierre">
                      <button class="btn-export" @click="loadCierre">Cargar</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Número</th>
                                <th>Recibo</th>
                                <th>Mesa</th>
                                <th>Detalle</th>
                                <th>Subtotal</th>
                                <th>IGV</th>
                                <th>Total</th>
                                <th>Fecha</th>
                                <th>Tipo Pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in cierreList" :key="r.id">
                                <td>{{ r.id }}</td>
                                <td class="fw-bold">{{ r.numero }}</td>
                                <td>{{ r.tipo }}</td>
                                <td>{{ r.mesa }}</td>
                                <td style="font-size: 0.85rem;">{{ r.detalle }}</td>
                                <td>S/ {{ Number(r.subtotal || 0).toFixed(2) }}</td>
                                <td>S/ {{ Number(r.igv || 0).toFixed(2) }}</td>
                                <td class="text-right font-bold">S/ {{ Number(r.total || 0).toFixed(2) }}</td>
                                <td>{{ r.fecha }}</td>
                                <td>{{ r.metodo_pago || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="resume" v-if="cierreList.length">
                      <span>Total Operaciones: {{ cierreList.length }}</span>
                      <span style="margin-left:20px;">Total Ventas: S/ {{ cierreList.reduce((a,b)=>a + Number(b.total||0),0).toFixed(2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Boleta Preview -->
        <div v-if="showBoletaPreview" class="modal-overlay" @click.self="closeBoleta">
          <div class="modal-card">
            <div class="modal-header">
              <h3>{{ boletaData?.tipo || 'Recibo' }} - {{ boletaData?.mesa }}</h3>
              <button class="close-btn" @click="closeBoleta">✕</button>
            </div>
            <div class="modal-body">
              <div class="boleta" id="boleta-content" style="background:white; padding:15px;">
                <div class="boleta-header">
                  <h4>{{ cajaConfig.nombre_comercial || 'EL HORNERO' }}</h4>
                  <p>RUC: {{ cajaConfig.ruc || '' }}</p>
                  <p>Dirección: {{ cajaConfig.direccion || '' }}</p>
                  <p>{{ new Date().toLocaleString('es-PE') }}</p>
                  <p style="font-weight:bold; margin-top:5px;">{{ boletaData?.tipo }} DE VENTA</p>
                </div>
                <table class="boleta-table">
                  <thead>
                    <tr><th>Detalle</th><th class="text-right">Precio</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="i in boletaData?.items || []" :key="i.id">
                      <td>{{ i.cantidad }}x {{ i.nombre }}</td>
                      <td class="text-right">S/ {{ Number(i.precio || 0).toFixed(2) }}</td>
                    </tr>
                  </tbody>
                </table>
                <div class="boleta-totals">
                  <div><span>Subtotal</span><span>S/ {{ Number(boletaData?.subtotal||0).toFixed(2) }}</span></div>
                  <div><span>IGV (18%)</span><span>S/ {{ Number(boletaData?.igv||0).toFixed(2) }}</span></div>
                  <div class="total"><span>Total</span><span>S/ {{ Number(boletaData?.total||0).toFixed(2) }}</span></div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn-sec" @click="closeBoleta">Cerrar</button>
            </div>
          </div>
        </div>

        <!-- Modal: Yape QR -->
        <div v-if="showYapeModal" class="modal-overlay" @click.self="closeYape">
          <div class="modal-card">
            <div class="modal-header">
              <h3>Pagar con Yape</h3>
              <button class="close-btn" @click="closeYape">✕</button>
            </div>
            <div class="modal-body" style="text-align:center;">
              <img :src="apiOrigin + '/images/qr/yape.png'" alt="QR Yape" style="max-width:280px; border:1px solid #eee; border-radius:8px;" @error="$event.target.style.display='none'">
              <p style="color:#6b7280; margin-top:10px;">Escanee el código QR para realizar el pago.</p>
              <p v-if="cajaConfig.yape_numero" style="font-weight:bold; font-size:1.2rem; margin-top: 5px; color:#111827;">
                  {{ cajaConfig.yape_numero }}
              </p>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:10px;">
              <button class="btn-sec" @click="closeYape">Cancelar</button>
              <button class="pay-btn digital" @click="confirmarPagoYape">Confirmar pago Yape</button>
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
.filters {
  display: flex;
  align-items: center;
  gap: 10px;
}
.resume {
  margin-top: 10px;
  font-weight: 600;
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

/* Modals */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}
.modal-card {
  width: 640px;
  max-width: 95vw;
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  background: #111827;
  color: white;
}
.modal-body {
  padding: 18px;
}
.modal-footer {
  padding: 12px 18px;
  border-top: 1px solid #eee;
}
.close-btn {
  background: transparent;
  border: none;
  color: white;
  font-size: 18px;
  cursor: pointer;
}
.boleta {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}
.boleta-header {
  padding: 12px 14px;
  background: #f9fafb;
  border-bottom: 1px solid #eee;
  text-align: center;
}
.boleta-table {
  width: 100%;
  border-collapse: collapse;
}
.boleta-table th, .boleta-table td {
  border-bottom: 1px solid #f1f5f9;
  padding: 8px 10px;
}
.boleta-table th {
  background: #f8fafc;
  text-align: left;
}
.boleta-totals {
  padding: 10px 14px;
}
.boleta-totals > div {
  display: flex;
  justify-content: space-between;
  margin-top: 6px;
}
.boleta-totals .total {
  font-weight: 800;
}
</style>
