<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import SessionGuard from '../components/common/SessionGuard.vue';
import UserMenu from '../components/common/UserMenu.vue';
import AssistantChat from '../components/common/AssistantChat.vue';

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
const ventaIdActual = ref(null);
const boletaDetallesHistoricos = ref([]);

const boletaData = computed(() => {
  if (!selectedMesa.value) return null;
  
  // Usar detalles históricos si están disponibles, sino usar items actuales
  const items = boletaDetallesHistoricos.value.length > 0 
    ? boletaDetallesHistoricos.value.map(d => ({
        cantidad: d.cantidad,
        nombre: d.nombre_producto,
        precio: d.precio_unitario
      }))
    : selectedMesa.value.items || [];
    
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

const openBoleta = async () => { 
  if (selectedMesa.value) {
    // Si tenemos ventaId, cargar detalles históricos
    if (ventaIdActual.value) {
      try {
        const response = await api.get(`/caja/venta/${ventaIdActual.value}/detalle-historico`);
        if (response.data && response.data.detalles) {
          boletaDetallesHistoricos.value = response.data.detalles;
        }
      } catch (e) {
        console.error('Error cargando detalles históricos:', e);
      }
    }
    showBoletaPreview.value = true; 
  }
};
const closeBoleta = () => { 
  showBoletaPreview.value = false;
  boletaDetallesHistoricos.value = [];
};

// Yape Modal
const showYapeModal = ref(false);
const openYape = () => { showYapeModal.value = true; };
const closeYape = () => { showYapeModal.value = false; };
const confirmarPagoYape = async () => {
  await procesarPagoSI('Yape');
  closeYape();
};

// Cierre por rango
const cierreFrom   = ref('');
const cierreTo     = ref('');
const cierreNumero = ref('');
const cierreMesa   = ref('');
const cierreTipo   = ref('');
const cierreList   = ref([]);

// ── Autocomplete de N° Comprobante ──────────────────────────
const sugerencias      = ref([]);
const showSuggestions  = ref(false);
const suggestionIndex  = ref(-1);
let   suggestTimer     = null;

const onNumeroInput = () => {
  clearTimeout(suggestTimer);
  suggestionIndex.value = -1;
  const q = (cierreNumero.value || '').trim();
  if (q.length < 2) { sugerencias.value = []; showSuggestions.value = false; loadCierre(); return; }
  suggestTimer = setTimeout(async () => {
    try {
      const r = await api.get('/admin/reportes/recibos-entregados', { params: { numero: q } });
      sugerencias.value = (r.data || []).slice(0, 8);
      showSuggestions.value = sugerencias.value.length > 0;
    } catch (e) { sugerencias.value = []; }
  }, 250);
  loadCierre();
};

const hideSuggestions = () => setTimeout(() => { showSuggestions.value = false; }, 150);

const moveSuggestion = (dir) => {
  if (!showSuggestions.value) return;
  suggestionIndex.value = Math.max(-1, Math.min(sugerencias.value.length - 1, suggestionIndex.value + dir));
};

const pickSuggestion = () => {
  if (suggestionIndex.value >= 0) selectSuggestion(sugerencias.value[suggestionIndex.value]);
};

const selectSuggestion = (s) => {
  cierreNumero.value    = s.numero;
  showSuggestions.value = false;
  sugerencias.value     = [];
  loadCierre();
};

const loadCierre = async () => {
  const params = {};
  if (cierreFrom.value)   params.from   = cierreFrom.value;
  if (cierreTo.value)     params.to     = cierreTo.value;
  if (cierreNumero.value) params.numero = cierreNumero.value;
  if (cierreMesa.value)   params.mesa   = cierreMesa.value;
  if (cierreTipo.value)   params.tipo   = cierreTipo.value;
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

    // Abrir ventana inmediatamente para evitar bloqueador de popups
    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><body><h3>Procesando pago y generando recibo...</h3></body></html>');

    try {
        const montoTotal = totalMesaSeleccionada.value;
        const itemsAActualizar = [...selectedMesa.value.items]; // Copia

        // 1. Registrar Venta en Caja
        const pedidoIds = itemsAActualizar.map(item => item.id);
        const resVenta = await api.post('/caja/venta', { 
            monto: montoTotal, 
            metodo_pago: metodo,
            pedido_ids: pedidoIds 
        });
        const ventaId = resVenta.data.ventaId;

        // 2. Generar Recibo y obtener detalle histórico
        let reciboDetalles = [];
        try {
          const reciboResponse = await api.post('/caja/recibo', { venta_id: ventaId, tipo: tipoComprobante.value });
          reciboDetalles = reciboResponse.data.detalles || [];
          
          // También obtener detalle histórico completo
          const historicoResponse = await api.get(`/caja/venta/${ventaId}/detalle-historico`);
          if (historicoResponse.data && historicoResponse.data.detalles) {
            reciboDetalles = historicoResponse.data.detalles;
            ventaIdActual.value = ventaId;
            boletaDetallesHistoricos.value = historicoResponse.data.detalles;
          }
        } catch (eRec) {
          console.error('Recibo error:', eRec);
        }

        // 3. Actualizar Pedidos
        const updates = itemsAActualizar.map(item => 
            api.post('/pedidos/actualizar', { id: item.id, estado: 'pagado', venta_id: ventaId })
        );
        await Promise.all(updates);

        // 4. UI Updates
        historialVentas.value.unshift({
            id: Date.now(),
            hora: new Date().toLocaleTimeString('es-PE', {hour: '2-digit', minute:'2-digit'}),
            detalle: `Mesa ${selectedMesa.value.numero}`,
            total: montoTotal,
            metodo: metodo,
            estado: 'Completado'
        });

        // 5. Imprimir en la ventana ya abierta
        imprimirRecibo(printWindow);

        // Refetch de pedidos
        await cargarPedidos();
        await loadCierre(); 

        // Limpiar selección
        setTimeout(() => {
            selectedMesa.value = null; 
        }, 1000);

    } catch (e) {
        console.error(e);
        if (printWindow) printWindow.close(); // Cerrar si hubo error
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

    // Exportar Historial
    const exportHistorialPDF = () => {
        if (!cierreList.value.length) {
            alert('No hay datos para exportar.');
            return;
        }
        const w = window.open('', '_blank');
        let html = '<html><head><title>Historial Cierre de Caja</title><style>';
        html += 'body{font-family:Arial,sans-serif;padding:16px;} h1{font-size:20px;margin:0 0 12px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:8px;font-size:12px;} th{background:#f4f4f4;} .text-right{text-align:right;}';
        html += '</style></head><body>';
        html += '<h1>Historial de Ventas</h1>';
        html += `<p><strong>Desde:</strong> ${cierreFrom.value || '-'} <strong>Hasta:</strong> ${cierreTo.value || '-'}</p>`;
        html += '<table><thead><tr><th>ID</th><th>Número</th><th>Recibo</th><th>Mesa</th><th>Detalle</th><th>Subtotal</th><th>IGV</th><th>Total</th><th>Fecha</th><th>Tipo Pago</th></tr></thead><tbody>';
        
        cierreList.value.forEach(r => {
            html += `<tr>
                <td>${r.id}</td>
                <td>${r.numero}</td>
                <td>${r.tipo}</td>
                <td>${r.mesa}</td>
                <td>${r.detalle}</td>
                <td class="text-right">S/ ${Number(r.subtotal || 0).toFixed(2)}</td>
                <td class="text-right">S/ ${Number(r.igv || 0).toFixed(2)}</td>
                <td class="text-right">S/ ${Number(r.total || 0).toFixed(2)}</td>
                <td>${r.fecha}</td>
                <td>${r.metodo_pago || '-'}</td>
            </tr>`;
        });
        
        html += '</tbody></table>';
        
        // Totales
        const totalVentas = cierreList.value.reduce((a,b) => a + Number(b.total||0), 0);
        html += `<div style="margin-top:20px; font-weight:bold; text-align:right;">
            Total Ventas: S/ ${totalVentas.toFixed(2)}
        </div>`;

        html += '</body></html>';
        w.document.write(html);
        w.document.close();
        w.focus();
        w.print();
    };

    // Imprimir Recibo Individual (Boleta Modal)
    const imprimirRecibo = (existingWindow = null) => {
        if (!boletaData.value) {
            if (existingWindow) existingWindow.close();
            return;
        }
        
        const d = boletaData.value;
        const conf = cajaConfig.value;
        
        let html = '<html><head><title>Imprimir Recibo</title><style>';
        html += 'body{font-family:Courier,monospace;padding:10px;width:300px;margin:0 auto;}';
        html += '.header{text-align:center;margin-bottom:10px;}';
        html += '.header h2{margin:0;font-size:16px;}';
        html += '.header p{margin:2px 0;font-size:12px;}';
        html += 'table{width:100%;font-size:12px;}';
        html += 'th,td{text-align:left;padding:2px 0;}';
        html += '.text-right{text-align:right;}';
        html += '.totals{margin-top:10px;border-top:1px dashed #000;padding-top:5px;font-size:12px;}';
        html += '.totals div{display:flex;justify-content:space-between;}';
        html += '</style></head><body>';
        
        html += `<div class="header">
            <h2>${conf.nombre_comercial || 'EL HORNERO'}</h2>
            <p>RUC: ${conf.ruc || ''}</p>
            <p>${conf.direccion || ''}</p>
            <p>Tel: ${conf.telefono || ''}</p>
            <hr style="border:0;border-top:1px dashed #000;margin:10px 0;">
            <p><strong>${d.tipo} DE VENTA</strong></p>
            <p>Mesa: ${d.mesa}</p>
            <p>Fecha: ${new Date().toLocaleString('es-PE')}</p>
        </div>`;
        
        html += '<table><thead><tr><th>Cant. Desc.</th><th class="text-right">Imp.</th></tr></thead><tbody>';
        d.items.forEach(i => {
             html += `<tr>
                <td>${i.cantidad} x ${i.nombre}</td>
                <td class="text-right">${Number(i.precio||0).toFixed(2)}</td>
             </tr>`;
        });
        html += '</tbody></table>';
        
        html += `<div class="totals">
            <div><span>Subtotal:</span><span>S/ ${Number(d.subtotal||0).toFixed(2)}</span></div>
            <div><span>IGV (18%):</span><span>S/ ${Number(d.igv||0).toFixed(2)}</span></div>
            <div style="font-weight:bold;margin-top:5px;"><span>TOTAL:</span><span>S/ ${Number(d.total||0).toFixed(2)}</span></div>
        </div>`;
        
        html += '<div style="text-align:center;margin-top:20px;font-size:11px;">¡Gracias por su preferencia!</div>';
        
        html += '</body></html>';
        
        // Usar iframe para impresión (más confiable que ventana nueva)
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
        
        const iframeDoc = iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(html);
        iframeDoc.close();
        
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        
        // Limpiar iframe después de impresión
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000);
    };

    onMounted(() => {
    const rol = localStorage.getItem('rol');
    if (rol !== 'caja') { router.push('/login'); return; }
    
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
            <span class="icon"></span> Historial
        </button>
      </nav>

      <div class="user-profile">
        <UserMenu dropdown-placement="top" />
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="pos-content">
        
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="top-bar-title">
                <h2>Panel de Caja</h2>
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
                        <div class="comprobante-options">
                            <label class="comprobante-label">
                                <input type="radio" value="BOLETA" v-model="tipoComprobante"> Boleta
                            </label>
                            <label class="comprobante-label">
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
                    <h2>Historial de Boletas / Facturas</h2>
                    <div class="filters">
                      <div class="filter-group">
                        <label>Desde</label>
                        <input type="date" v-model="cierreFrom" @change="loadCierre">
                      </div>
                      <div class="filter-group">
                        <label>Hasta</label>
                        <input type="date" v-model="cierreTo" @change="loadCierre">
                      </div>

                      <!-- Búsqueda inteligente de comprobante -->
                      <div class="filter-group filter-group--autocomplete">
                        <label>N° Comprobante</label>
                        <div class="autocomplete-wrap">
                          <input
                            type="text"
                            v-model="cierreNumero"
                            @input="onNumeroInput"
                            @focus="showSuggestions = sugerencias.length > 0"
                            @blur="hideSuggestions"
                            @keydown.down.prevent="moveSuggestion(1)"
                            @keydown.up.prevent="moveSuggestion(-1)"
                            @keydown.enter.prevent="pickSuggestion()"
                            @keydown.escape="showSuggestions = false"
                            placeholder="Escribe para buscar..."
                            autocomplete="off"
                            class="autocomplete-input"
                          />
                          <ul
                            v-if="showSuggestions && sugerencias.length"
                            class="autocomplete-list"
                          >
                            <li
                              v-for="(s, i) in sugerencias"
                              :key="s.numero"
                              :class="['autocomplete-item', { 'autocomplete-item--active': i === suggestionIndex }]"
                              @mousedown.prevent="selectSuggestion(s)"
                            >
                              <span class="sug-numero">{{ s.numero }}</span>
                              <span class="sug-meta">{{ s.tipo }} · S/ {{ Number(s.total||0).toFixed(2) }} · {{ s.fecha ? s.fecha.slice(0,10) : '' }}</span>
                            </li>
                          </ul>
                        </div>
                      </div>

                      <div class="filter-group">
                        <label>Mesa</label>
                        <input type="number" v-model="cierreMesa" @input="loadCierre" placeholder="Ej: 1" min="1">
                      </div>
                      <div class="filter-group">
                        <label>Tipo</label>
                        <select v-model="cierreTipo" @change="loadCierre">
                          <option value="">Todos</option>
                          <option value="BOLETA">Boleta</option>
                          <option value="FACTURA">Factura</option>
                        </select>
                      </div>
                      <div class="filter-group filter-group--actions">
                        <label>&nbsp;</label>
                        <div class="filter-btns">
                          <button class="btn-export" @click="loadCierre">Buscar</button>
                          <button class="btn-export btn-export--secondary" @click="exportHistorialPDF">🖨 PDF</button>
                        </div>
                      </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Número</th>
                                <th>Tipo</th>
                                <th>Mesa</th>
                                <th>Detalle</th>
                                <th>Subtotal</th>
                                <th>IGV</th>
                                <th>Total</th>
                                <th>Fecha</th>
                                <th>Método</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in cierreList" :key="r.id">
                                <td class="td-id">{{ r.id }}</td>
                                <td class="fw-bold td-numero">{{ r.numero }}</td>
                                <td>
                                  <span :class="['badge-tipo', r.tipo === 'BOLETA' ? 'badge-boleta' : 'badge-factura']">
                                    {{ r.tipo }}
                                  </span>
                                </td>
                                <td class="td-center">{{ r.mesa || '—' }}</td>
                                <td class="cell-detalle">{{ r.detalle }}</td>
                                <td class="td-money">S/ {{ Number(r.subtotal || 0).toFixed(2) }}</td>
                                <td class="td-money">S/ {{ Number(r.igv || 0).toFixed(2) }}</td>
                                <td class="td-money td-total">S/ {{ Number(r.total || 0).toFixed(2) }}</td>
                                <td class="td-fecha">{{ new Date(r.fecha).toLocaleString('es-PE') }}</td>
                                <td>
                                  <span :class="['badge-method', 'badge-method--' + (r.metodo_pago||'otro').toLowerCase()]">
                                    {{ r.metodo_pago || '—' }}
                                  </span>
                                </td>
                            </tr>
                            <tr v-if="!cierreList.length">
                              <td colspan="10" class="td-empty">Sin registros. Ajusta los filtros y presiona Buscar.</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="resume" v-if="cierreList.length">
                      <span>{{ cierreList.length }} operación{{ cierreList.length !== 1 ? 'es' : '' }}</span>
                      <span class="resume-total">Total: S/ {{ cierreList.reduce((a,b)=>a + Number(b.total||0),0).toFixed(2) }}</span>
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
              <div class="boleta boleta-content" id="boleta-content">
                <div class="boleta-header">
                  <h4>{{ cajaConfig.nombre_comercial || 'EL HORNERO' }}</h4>
                  <p>RUC: {{ cajaConfig.ruc || '' }}</p>
                  <p>Dirección: {{ cajaConfig.direccion || '' }}</p>
                  <p>{{ new Date().toLocaleString('es-PE') }}</p>
                  <p class="boleta-tipo">{{ boletaData?.tipo }} DE VENTA</p>
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
                  <div><span>IGV (18%):</span><span>S/ {{ Number(boletaData?.igv||0).toFixed(2) }}</span></div>
                  <div class="total"><span>Total</span><span>S/ {{ Number(boletaData?.total||0).toFixed(2) }}</span></div>
                </div>
              </div>
            </div>
            <div class="modal-footer modal-footer--between">
              <button class="btn-sec" @click="imprimirRecibo">🖨 Imprimir</button>
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
            <div class="modal-body modal-body--center">
              <img :src="apiOrigin + '/images/qr/yape.png'" alt="QR Yape" class="yape-qr" @error="$event.target.style.display='none'">
              <p class="yape-hint">Escanee el código QR para realizar el pago.</p>
              <p v-if="cajaConfig.yape_numero" class="yape-number">
                  {{ cajaConfig.yape_numero }}
              </p>
            </div>
            <div class="modal-footer modal-footer--end">
              <button class="btn-sec" @click="closeYape">Cancelar</button>
              <button class="pay-btn digital" @click="confirmarPagoYape">Confirmar pago Yape</button>
            </div>
          </div>
        </div>

    </main>
  </div>

  <SessionGuard />
  <AssistantChat module="caja" />
</template>

<style src="../styles/caja.css" scoped></style>
