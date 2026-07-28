<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import api from '../../api';
import './ReportesSection.css';

const props = defineProps({
  meseros: {
    type: Array,
    default: () => []
  }
});

// ── Estado interno ──────────────────────────────────────────
const reportType    = ref('pedidos');
const reportData    = ref([]);
const loading       = ref(false);
const searched      = ref(false);   // si ya se hizo al menos una búsqueda

const filters = ref({
  from:       '',
  to:         '',
  mesa:       '',
  mesero_id:  '',
  costo_min:  '',
  costo_max:  '',
});

// ── Tipos disponibles ───────────────────────────────────────
const tiposReporte = [
  { value: 'pedidos',           label: '📋 Pedidos'          },
  { value: 'pedidos_mesero',    label: '🧑‍🍳 Por mesero'       },
  { value: 'recibos_entregados', label: '🧾 Recibos'          },
];

const tipoLabel = computed(
  () => tiposReporte.find(t => t.value === reportType.value)?.label ?? 'Reporte'
);

// Resetear filtros específicos al cambiar de tipo
watch(reportType, () => {
  filters.value.mesa      = '';
  filters.value.mesero_id = '';
  filters.value.costo_min = '';
  filters.value.costo_max = '';
  reportData.value = [];
  searched.value   = false;
});

// ── Carga de datos ──────────────────────────────────────────
const loadReport = async () => {
  loading.value = true;
  searched.value = true;
  reportData.value = [];

  try {
    const params = {};
    if (filters.value.from) params.from = filters.value.from;
    if (filters.value.to)   params.to   = filters.value.to;

    if (reportType.value === 'pedidos') {
      if (filters.value.mesa) params.mesa = filters.value.mesa;
      const r = await api.get('/admin/reportes/pedidos', { params });
      reportData.value = r.data ?? [];

    } else if (reportType.value === 'pedidos_mesero') {
      if (filters.value.mesero_id) params.mesero_id = filters.value.mesero_id;
      const r = await api.get('/admin/reportes/pedidos-mesero', { params });
      reportData.value = r.data ?? [];

    } else if (reportType.value === 'recibos_entregados') {
      if (filters.value.costo_min) params.costo_min = filters.value.costo_min;
      if (filters.value.costo_max) params.costo_max = filters.value.costo_max;
      if (filters.value.mesa)      params.mesa      = filters.value.mesa;
      const r = await api.get('/admin/reportes/recibos-entregados', { params });
      reportData.value = r.data ?? [];
    }

  } catch (err) {
    console.error('Error cargando reporte:', err);
    reportData.value = [];
  } finally {
    loading.value = false;
  }
};

// Carga automática al montar con datos vacíos/sin filtro
onMounted(() => loadReport());

// ── Exportar PDF ────────────────────────────────────────────
const exportPDF = () => {
  const w = window.open('', '_blank');
  const titleMap = {
    pedidos:            'Reporte de Pedidos',
    pedidos_mesero:     'Pedidos por Mesero',
    recibos_entregados: 'Reporte de Recibos',
  };
  const title = titleMap[reportType.value] ?? 'Reporte';
  let html = `<html><head><title>${title}</title><style>
    body{font-family:Arial,sans-serif;padding:16px;}
    h1{font-size:18px;margin:0 0 12px;}
    table{width:100%;border-collapse:collapse;}
    th,td{border:1px solid #ccc;padding:7px 10px;font-size:11px;}
    th{background:#f4f4f4;font-weight:700;}
    tr:nth-child(even){background:#fafafa;}
  </style></head><body><h1>${title}</h1><table>`;

  if (reportType.value !== 'recibos_entregados') {
    html += '<thead><tr><th>ID</th><th>Mesa</th><th>Mesero</th><th>Fecha</th><th>Estado</th><th>Costo</th><th>Detalle</th></tr></thead><tbody>';
    reportData.value.forEach(p => {
      html += `<tr><td>${p.id}</td><td>${p.mesa}</td><td>${p.mesero || '-'}</td><td>${p.fecha}</td><td>${p.estado}</td><td>S/. ${Number(p.costo || 0).toFixed(2)}</td><td>${p.detalle || ''}</td></tr>`;
    });
  } else {
    html += '<thead><tr><th>ID</th><th>Número</th><th>Tipo</th><th>Subtotal</th><th>IGV</th><th>Total</th><th>Fecha</th><th>Pago</th></tr></thead><tbody>';
    reportData.value.forEach(r => {
      html += `<tr><td>${r.id}</td><td>${r.numero}</td><td>${r.tipo}</td><td>S/. ${Number(r.subtotal||0).toFixed(2)}</td><td>S/. ${Number(r.igv||0).toFixed(2)}</td><td>S/. ${Number(r.total||0).toFixed(2)}</td><td>${r.fecha}</td><td>${r.metodo_pago||'-'}</td></tr>`;
    });
  }
  html += '</tbody></table></body></html>';
  w.document.write(html);
  w.document.close();
  w.focus();
  w.print();
};

// ── Helpers de presentación ─────────────────────────────────
const fmt = (v) => `S/. ${Number(v || 0).toFixed(2)}`;

const formatFecha = (f) => {
  if (!f) return '—';
  const d = new Date(f.includes('T') ? f : f.replace(' ', 'T'));
  return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatHora = (f) => {
  if (!f) return '';
  const d = new Date(f.includes('T') ? f : f.replace(' ', 'T'));
  return d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true });
};

const estadoBadge = (estado) => {
  const e = (estado || '').toLowerCase();
  if (e === 'pedido')    return 'rep-badge-pedido';
  if (e === 'cocinando') return 'rep-badge-cocinando';
  if (e === 'preparado') return 'rep-badge-preparado';
  if (e === 'entregado') return 'rep-badge-entregado';
  if (e === 'pagado')    return 'rep-badge-pagado';
  if (e === 'cancelado') return 'rep-badge-cancelado';
  return 'rep-badge-default';
};
</script>

<template>
  <div class="rep-layout">

    <!-- ── Panel de controles ── -->
    <div class="rep-controls-panel">
      <div class="rep-controls-header">
        <span class="rep-controls-icon">🔍</span>
        <h3 class="rep-controls-title">Filtros de búsqueda</h3>
      </div>

      <div class="rep-controls-body">

        <!-- Tipo de reporte -->
        <div class="rep-type-group">
          <span class="rep-filter-label">Tipo de reporte</span>
          <div class="rep-type-pills">
            <button
              v-for="t in tiposReporte"
              :key="t.value"
              :class="['rep-type-pill', reportType === t.value ? 'active' : '']"
              type="button"
              @click="reportType = t.value"
            >{{ t.label }}</button>
          </div>
        </div>

        <!-- Filtros comunes: fechas -->
        <div class="rep-filter-group">
          <label class="rep-filter-label">Desde</label>
          <input v-model="filters.from" type="date" class="rep-filter-input" />
        </div>

        <div class="rep-filter-group">
          <label class="rep-filter-label">Hasta</label>
          <input v-model="filters.to" type="date" class="rep-filter-input" />
        </div>

        <!-- Filtro mesa (pedidos) -->
        <div v-if="reportType === 'pedidos'" class="rep-filter-group">
          <label class="rep-filter-label">Mesa</label>
          <input v-model="filters.mesa" type="number" min="1" class="rep-filter-input" placeholder="Nº" />
        </div>

        <!-- Filtro mesero -->
        <div v-if="reportType === 'pedidos_mesero'" class="rep-filter-group">
          <label class="rep-filter-label">Mesero</label>
          <select v-model="filters.mesero_id" class="rep-filter-input">
            <option value="">Todos</option>
            <option v-for="m in meseros" :key="m.id" :value="m.id">
              {{ (m.nombres || '') + ' ' + (m.apellidos || '') }}
            </option>
          </select>
        </div>

        <!-- Filtros de costo (recibos) -->
        <div v-if="reportType === 'recibos_entregados'" class="rep-filter-group">
          <label class="rep-filter-label">Total mínimo</label>
          <input v-model="filters.costo_min" type="number" step="0.01" min="0" class="rep-filter-input" placeholder="0.00" />
        </div>

        <div v-if="reportType === 'recibos_entregados'" class="rep-filter-group">
          <label class="rep-filter-label">Total máximo</label>
          <input v-model="filters.costo_max" type="number" step="0.01" min="0" class="rep-filter-input" placeholder="0.00" />
        </div>

        <div v-if="reportType === 'recibos_entregados'" class="rep-filter-group">
          <label class="rep-filter-label">Mesa</label>
          <input v-model="filters.mesa" type="number" min="1" class="rep-filter-input" placeholder="Nº" />
        </div>

        <!-- Acciones -->
        <div class="rep-actions">
          <button class="rep-btn rep-btn-search" type="button" @click="loadReport">
            Buscar
          </button>
          <button class="rep-btn rep-btn-export" type="button" @click="exportPDF" :disabled="!reportData.length">
            ⬇ PDF
          </button>
        </div>

      </div>
    </div>

    <!-- ── Panel de resultados ── -->
    <div class="rep-results-panel">
      <div class="rep-results-header">
        <span class="rep-results-title">{{ tipoLabel }}</span>
        <span class="rep-results-count">{{ reportData.length }} resultado{{ reportData.length !== 1 ? 's' : '' }}</span>
      </div>

      <!-- Cargando -->
      <div v-if="loading" class="rep-loading">
        <div class="rep-spinner"></div>
        <span>Cargando...</span>
      </div>

      <!-- Sin búsqueda aún -->
      <div v-else-if="!searched" class="rep-empty">
        <span class="rep-empty-icon">🔍</span>
        <span class="rep-empty-text">Aplica los filtros y presiona Buscar</span>
      </div>

      <!-- Sin resultados -->
      <div v-else-if="reportData.length === 0" class="rep-empty">
        <span class="rep-empty-icon">📭</span>
        <span class="rep-empty-text">Sin resultados para los filtros seleccionados</span>
      </div>

      <!-- ── Tabla: Pedidos / Pedidos por mesero ── -->
      <div v-else-if="reportType === 'pedidos' || reportType === 'pedidos_mesero'" class="rep-table-wrapper">
        <table class="rep-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Mesa</th>
              <th>Mesero</th>
              <th>Servicio</th>
              <th>Costo</th>
              <th>Fecha / Hora</th>
              <th>Estado</th>
              <th>Detalle</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in reportData" :key="p.id">
              <td class="rep-td-id">#{{ p.id }}</td>
              <td class="rep-td-bold">Mesa {{ p.mesa }}</td>
              <td>{{ p.mesero || '—' }}</td>
              <td style="text-transform: capitalize;">{{ p.tipo_servicio || 'local' }}</td>
              <td class="rep-td-money">{{ fmt(p.costo) }}</td>
              <td class="rep-td-date">
                {{ formatFecha(p.fecha) }}<br/>
                <small>{{ formatHora(p.fecha) }}</small>
              </td>
              <td>
                <span :class="['rep-badge', estadoBadge(p.estado)]">{{ p.estado }}</span>
              </td>
              <td class="rep-td-small">{{ p.detalle || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Tabla: Recibos ── -->
      <div v-else-if="reportType === 'recibos_entregados'" class="rep-table-wrapper">
        <table class="rep-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Número</th>
              <th>Tipo</th>
              <th>Mesa</th>
              <th>Subtotal</th>
              <th>IGV</th>
              <th>Total</th>
              <th>Fecha</th>
              <th>Método pago</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reportData" :key="r.id">
              <td class="rep-td-id">#{{ r.id }}</td>
              <td class="rep-td-bold">{{ r.numero }}</td>
              <td>{{ r.tipo }}</td>
              <td>{{ r.mesa || '—' }}</td>
              <td>{{ fmt(r.subtotal) }}</td>
              <td>{{ fmt(r.igv) }}</td>
              <td class="rep-td-money">{{ fmt(r.total) }}</td>
              <td class="rep-td-date">{{ formatFecha(r.fecha) }}</td>
              <td>{{ r.metodo_pago || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</template>
