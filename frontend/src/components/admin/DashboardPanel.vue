<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  Chart as ChartJS,
  CategoryScale, LinearScale,
  PointElement, LineElement,
  BarElement, ArcElement,
  Title, Tooltip, Legend, Filler
} from 'chart.js';
import { Line, Bar, Doughnut } from 'vue-chartjs';
import api from '../../api';
import './DashboardPanel.css';

ChartJS.register(
  CategoryScale, LinearScale,
  PointElement, LineElement,
  BarElement, ArcElement,
  Title, Tooltip, Legend, Filler
);

// ── Estado ──────────────────────────────────────────────────
const loading   = ref(true);
const kpis      = ref(null);
const recientes = ref([]);
const descuentos= ref([]);
const meseros   = ref([]);

// Datos para gráficas
const gVentasDiarias   = ref(null);
const gVentasSemanales = ref(null);
const gVentasMensuales = ref(null);
const gMasVendidos     = ref(null);
const gMenosVendidos   = ref(null);
const gCategoria       = ref(null);
const gPagos           = ref(null);
const gPorHora         = ref(null);
const gEstados         = ref(null);

// ── Subtítulos dinámicos con intervalo de tiempo ─────────────
const fmtFecha = (d) => new Date(d + 'T00:00:00').toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
const hoy      = new Date();
const hace7    = new Date(hoy); hace7.setDate(hoy.getDate() - 6);
const hace6sem = new Date(hoy); hace6sem.setDate(hoy.getDate() - 41);
const hace6mes = new Date(hoy); hace6mes.setMonth(hoy.getMonth() - 5); hace6mes.setDate(1);

const subDiarias   = `${fmtFecha(hace7.toISOString().slice(0,10))} – ${fmtFecha(hoy.toISOString().slice(0,10))} (7 días)`;
const subSemanales = `Últimas 6 semanas`;
const subMensuales = `${hace6mes.toLocaleDateString('es-PE',{month:'short',year:'numeric'})} – ${hoy.toLocaleDateString('es-PE',{month:'short',year:'numeric'})}`;
const subHoy       = `Hoy ${hoy.toLocaleDateString('es-PE',{weekday:'long',day:'2-digit',month:'long'})}`;
const subMesAct    = `${hoy.toLocaleDateString('es-PE',{month:'long',year:'numeric'})}`;
const subHistorico = 'Histórico completo';

// ── Opciones base de charts ──────────────────────────────────

// Tooltip para gráficas de ventas (línea/barra): "2 ventas — S/. 102.00"
const ventasTooltip = {
  callbacks: {
    label: (ctx) => {
      const raw   = ctx.raw ?? 0;
      const count = ctx.chart.data.datasets[ctx.datasetIndex]?.counts?.[ctx.dataIndex] ?? null;
      const monto = `S/. ${Number(raw).toFixed(2)}`;
      return count !== null ? ` ${count} venta${count !== 1 ? 's' : ''} — ${monto}` : ` ${monto}`;
    }
  }
};

// Tooltip para barras horizontales (productos): "X unidades"
const unidadesTooltip = {
  callbacks: {
    label: (ctx) => ` ${ctx.raw} unidad${ctx.raw !== 1 ? 'es' : ''}`
  }
};

const baseOpts = () => ({
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend:  { display: false },
    tooltip: ventasTooltip,
  },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
    y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 }, callback: (v) => `S/.${v}` } }
  }
});

// Opciones para gráficas de cantidades (enteros, sin S/.)
const cantidadOpts = () => ({
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend:  { display: false },
    tooltip: { callbacks: { label: (ctx) => ` ${Math.round(ctx.raw)} pedido${ctx.raw !== 1 ? 's' : ''}` } }
  },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
    y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 }, stepSize: 1, callback: (v) => Number.isInteger(v) ? v : '' } }
  }
});

const donutOpts = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10, padding: 10 } },
    tooltip: {
      callbacks: {
        label: (ctx) => {
          const cnt = ctx.dataset.counts?.[ctx.dataIndex];
          const val = `S/. ${Number(ctx.raw).toFixed(2)}`;
          return cnt != null ? ` ${ctx.label}: ${val} (${cnt} venta${cnt !== 1 ? 's' : ''})` : ` ${ctx.label}: ${val}`;
        }
      }
    }
  },
  cutout: '60%'
};

// Dona para cantidades (estados de pedido): muestra enteros sin S/.
const donutCantidadOpts = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10, padding: 10 } },
    tooltip: {
      callbacks: {
        label: (ctx) => ` ${ctx.label}: ${Math.round(ctx.raw)} pedido${ctx.raw !== 1 ? 's' : ''}`
      }
    }
  },
  cutout: '60%'
};

// Dona para categorías: muestra monto y unidades
const donutCategoriaOpts = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10, padding: 10 } },
    tooltip: {
      callbacks: {
        label: (ctx) => {
          const unidades = ctx.dataset.unidades?.[ctx.dataIndex] ?? 0;
          const monto    = `S/. ${Number(ctx.raw).toFixed(2)}`;
          return ` ${ctx.label}: ${monto} — ${Math.round(unidades)} unidad${unidades !== 1 ? 'es' : ''}`;
        }
      }
    }
  },
  cutout: '60%'
};

const barOptsH = {
  responsive: true,
  maintainAspectRatio: true,
  indexAxis: 'y',
  plugins: {
    legend:  { display: false },
    tooltip: unidadesTooltip,
  },
  scales: {
    x: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 9 }, stepSize: 1, callback: (v) => Number.isInteger(v) ? v : '' } },
    y: { grid: { display: false  }, ticks: { font: { size: 9 } } }
  }
};

// ── Colores ──────────────────────────────────────────────────
const ORANGE = 'rgba(239,108,0,0.85)';
const ORANGE_L = 'rgba(239,108,0,0.15)';
const PALETA_DONA = ['#ef6c00','#1565c0','#2e7d32','#7b1fa2','#c62828','#00695c'];
const PALETA_ESTADOS = {
  pedido: '#ff9800', cocinando: '#1976d2', preparado: '#7986cb',
  entregado: '#66bb6a', pagado: '#26a69a', cancelado: '#ef5350'
};

// ── Helpers ──────────────────────────────────────────────────
const fmt = (v) => `S/. ${Number(v || 0).toFixed(2)}`;

const delta = (pct) => {
  if (pct === 0) return { cls: 'flat', icon: '→', label: 'Sin cambio' };
  return pct > 0
    ? { cls: 'up',   icon: '↑', label: `+${pct}% vs ayer` }
    : { cls: 'down', icon: '↓', label: `${pct}% vs ayer` };
};

const estadoBadge = (e) => {
  const map = {
    pedido: 'dp-badge-pedido', cocinando: 'dp-badge-cocinando',
    preparado: 'dp-badge-preparado', entregado: 'dp-badge-entregado',
    pagado: 'dp-badge-pagado', cancelado: 'dp-badge-cancelado'
  };
  return map[(e||'').toLowerCase()] ?? 'dp-badge-default';
};

const fmtDate = (f) => {
  if (!f) return '—';
  const d = new Date(f.includes('T') ? f : f.replace(' ', 'T'));
  return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' });
};

const fmtDateFull = (f) => {
  if (!f) return '—';
  const d = new Date(f.includes('T') ? f : f.replace(' ', 'T'));
  return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

// ── Carga de datos — 1 sola llamada ─────────────────────────
const load = async () => {
  loading.value = true;
  try {
    const { data: d } = await api.get('/admin/dashboard-data');

    kpis.value       = d.kpis       ?? null;
    recientes.value  = Array.isArray(d.recientes)  ? d.recientes  : [];
    descuentos.value = Array.isArray(d.descuentos) ? d.descuentos : [];
    meseros.value    = Array.isArray(d.meseros)    ? d.meseros    : [];

    // Helper: línea desde [{label, value, count}]
    const lineDS = (rows, color, colorBg) => {
      if (!Array.isArray(rows) || !rows.length) return null;
      return {
        labels: rows.map(r => r.label),
        datasets: [{
          label: 'Ventas',
          data:   rows.map(r => Number(r.value) || 0),
          counts: rows.map(r => r.count ?? 0),   // accesible desde el tooltip
          borderColor: color, backgroundColor: colorBg,
          tension: 0.4, fill: true, pointRadius: 3, pointHoverRadius: 5
        }]
      };
    };

    gVentasDiarias.value   = lineDS(d.ventasDiarias,   ORANGE,    ORANGE_L);
    gVentasSemanales.value = lineDS(d.ventasSemanales, '#1565c0', 'rgba(21,101,192,0.12)');
    gVentasMensuales.value = lineDS(d.ventasMensuales, '#2e7d32', 'rgba(46,125,50,0.12)');

    // Más vendidos
    if (Array.isArray(d.masVendidos) && d.masVendidos.length) {
      gMasVendidos.value = {
        labels: d.masVendidos.map(r => r.nombre),
        datasets: [{ label: 'Uds.', data: d.masVendidos.map(r => Number(r.total)||0), backgroundColor: ORANGE }]
      };
    }

    // Menos vendidos
    if (Array.isArray(d.menosVendidos) && d.menosVendidos.length) {
      gMenosVendidos.value = {
        labels: d.menosVendidos.map(r => r.nombre),
        datasets: [{ label: 'Uds.', data: d.menosVendidos.map(r => Number(r.total)||0), backgroundColor: '#90a4ae' }]
      };
    }

    // Categorías — monto en el dato, unidades como propiedad extra del dataset
    if (Array.isArray(d.categorias) && d.categorias.length) {
      gCategoria.value = {
        labels: d.categorias.map(r => r.categoria),
        datasets: [{
          data:     d.categorias.map(r => Number(r.total)    || 0),
          unidades: d.categorias.map(r => Number(r.unidades) || 0),
          backgroundColor: PALETA_DONA
        }]
      };
    }

    // Métodos de pago
    if (Array.isArray(d.pagos) && d.pagos.length) {
      gPagos.value = {
        labels: d.pagos.map(r => r.label),
        datasets: [{
          data:   d.pagos.map(r => Number(r.value) || 0),
          counts: d.pagos.map(r => r.count ?? 0),
          backgroundColor: PALETA_DONA
        }]
      };
    }

    // Pedidos por hora (cantidad, no monto)
    if (Array.isArray(d.ventasPorHora?.data) && d.ventasPorHora.data.some(v => v > 0)) {
      gPorHora.value = {
        labels: d.ventasPorHora.labels,
        datasets: [{
          label: 'Pedidos',
          data:   d.ventasPorHora.data,
          backgroundColor: ORANGE
        }]
      };
    }

    // Estado pedidos
    if (Array.isArray(d.estados) && d.estados.length) {
      gEstados.value = {
        labels: d.estados.map(r => r.estado),
        datasets: [{
          data: d.estados.map(r => Number(r.total)||0),
          backgroundColor: d.estados.map(r => PALETA_ESTADOS[r.estado] ?? '#90a4ae')
        }]
      };
    }

  } catch (e) {
    console.error('Error cargando dashboard:', e);
  } finally {
    loading.value = false;
  }
};

onMounted(load);
</script>

<template>
  <div class="dp-layout">

    <!-- Cargando -->
    <div v-if="loading" class="dp-loading">
      <div class="dp-spinner"></div>
      <span>Cargando dashboard...</span>
    </div>

    <template v-else>

      <!-- Encabezado con botón refresh -->
      <div class="dp-refresh-bar">
        <span class="dp-refresh-label">Datos al: {{ new Date().toLocaleString('es-PE') }}</span>
        <button class="dp-refresh-btn" @click="load" title="Actualizar datos">
          ↺ Actualizar
        </button>
      </div>

      <!-- ── KPIs ── -->
      <div class="dp-kpi-grid">

        <div class="dp-kpi-card">
          <div class="dp-kpi-accent dp-kpi-accent-orange"></div>
          <div class="dp-kpi-icon">💰</div>
          <div class="dp-kpi-label">Ventas hoy</div>
          <div class="dp-kpi-value">{{ fmt(kpis?.ventasHoy) }}</div>
          <div :class="['dp-kpi-delta', delta(kpis?.ventasHoyPct).cls]">
            {{ delta(kpis?.ventasHoyPct).icon }}
            {{ delta(kpis?.ventasHoyPct).label }}
          </div>
        </div>

        <div class="dp-kpi-card">
          <div class="dp-kpi-accent dp-kpi-accent-blue"></div>
          <div class="dp-kpi-icon">📅</div>
          <div class="dp-kpi-label">Ventas semana</div>
          <div class="dp-kpi-value">{{ fmt(kpis?.ventasSemana) }}</div>
          <div :class="['dp-kpi-delta', delta(kpis?.ventasSemanaPct).cls]">
            {{ delta(kpis?.ventasSemanaPct).icon }}
            {{ delta(kpis?.ventasSemanaPct).label.replace('vs ayer', 'vs sem. ant.') }}
          </div>
        </div>

        <div class="dp-kpi-card">
          <div class="dp-kpi-accent dp-kpi-accent-green"></div>
          <div class="dp-kpi-icon">📆</div>
          <div class="dp-kpi-label">Ventas mes</div>
          <div class="dp-kpi-value">{{ fmt(kpis?.ventasMes) }}</div>
          <div :class="['dp-kpi-delta', delta(kpis?.ventasMesPct).cls]">
            {{ delta(kpis?.ventasMesPct).icon }}
            {{ delta(kpis?.ventasMesPct).label.replace('vs ayer', 'vs mes ant.') }}
          </div>
        </div>

        <div class="dp-kpi-card">
          <div class="dp-kpi-accent dp-kpi-accent-indigo"></div>
          <div class="dp-kpi-icon">🧾</div>
          <div class="dp-kpi-label">Pedidos hoy</div>
          <div class="dp-kpi-value">{{ kpis?.pedidosHoy }}</div>
          <div :class="['dp-kpi-delta', delta(kpis?.pedidosHoyPct).cls]">
            {{ delta(kpis?.pedidosHoyPct).icon }}
            {{ delta(kpis?.pedidosHoyPct).label }}
          </div>
        </div>

        <div class="dp-kpi-card">
          <div class="dp-kpi-accent dp-kpi-accent-teal"></div>
          <div class="dp-kpi-icon">🎯</div>
          <div class="dp-kpi-label">Ticket promedio</div>
          <div class="dp-kpi-value">{{ fmt(kpis?.ticketPromedio) }}</div>
          <div :class="['dp-kpi-delta', delta(kpis?.ticketPct).cls]">
            {{ delta(kpis?.ticketPct).icon }}
            {{ delta(kpis?.ticketPct).label }}
          </div>
        </div>

      </div>

      <!-- ── Fila 1: Ventas diarias / semanales / mensuales ── -->
      <div class="dp-charts-row dp-charts-row-3">

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">📈 Ventas por día</span>
            <span class="dp-chart-sub">{{ subDiarias }}</span>
          </div>
          <div class="dp-chart-body">
            <Line v-if="gVentasDiarias" :data="gVentasDiarias" :options="baseOpts()" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">📊</span><span>Sin ventas registradas</span></div>
          </div>
        </div>

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">📅 Ventas por semana</span>
            <span class="dp-chart-sub">{{ subSemanales }}</span>
          </div>
          <div class="dp-chart-body">
            <Line v-if="gVentasSemanales" :data="gVentasSemanales" :options="baseOpts()" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">📊</span><span>Sin ventas registradas</span></div>
          </div>
        </div>

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">📆 Ventas por mes</span>
            <span class="dp-chart-sub">{{ subMensuales }}</span>
          </div>
          <div class="dp-chart-body">
            <Line v-if="gVentasMensuales" :data="gVentasMensuales" :options="baseOpts()" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">📊</span><span>Sin ventas registradas</span></div>
          </div>
        </div>

      </div>

      <!-- ── Fila 2: Top productos + Ventas por hora ── -->
      <div class="dp-charts-row dp-charts-row-3">

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">🏆 Más vendidos</span>
            <span class="dp-chart-sub">Últimos 30 días — unidades</span>
          </div>
          <div class="dp-chart-body">
            <Bar v-if="gMasVendidos" :data="gMasVendidos" :options="barOptsH" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">📊</span><span>Sin datos</span></div>
          </div>
        </div>

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">📉 Menos vendidos</span>
            <span class="dp-chart-sub">Últimos 30 días — unidades</span>
          </div>
          <div class="dp-chart-body">
            <Bar v-if="gMenosVendidos" :data="gMenosVendidos" :options="barOptsH" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">📊</span><span>Sin datos</span></div>
          </div>
        </div>

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">🕐 Pedidos por hora</span>
            <span class="dp-chart-sub">{{ subHoy }}</span>
          </div>
          <div class="dp-chart-body">
            <Bar v-if="gPorHora" :data="gPorHora" :options="cantidadOpts()" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">📊</span><span>Sin pedidos hoy</span></div>
          </div>
        </div>

      </div>

      <!-- ── Fila 3: Donas ── -->
      <div class="dp-charts-row dp-charts-row-3">

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">🍽️ Ventas por categoría</span>
            <span class="dp-chart-sub">{{ subMesAct }} — monto + unidades</span>
          </div>
          <div class="dp-chart-body">
            <Doughnut v-if="gCategoria" :data="gCategoria" :options="donutCategoriaOpts" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">🍩</span><span>Sin datos</span></div>
          </div>
        </div>

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">💳 Métodos de pago</span>
            <span class="dp-chart-sub">{{ subHistorico }}</span>
          </div>
          <div class="dp-chart-body">
            <Doughnut v-if="gPagos" :data="gPagos" :options="donutOpts" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">🍩</span><span>Sin datos</span></div>
          </div>
        </div>

        <div class="dp-chart-card">
          <div class="dp-chart-header">
            <span class="dp-chart-title">📋 Estado de pedidos</span>
            <span class="dp-chart-sub">{{ subHoy }}</span>
          </div>
          <div class="dp-chart-body">
            <Doughnut v-if="gEstados" :data="gEstados" :options="donutCantidadOpts" />
            <div v-else class="dp-no-data"><span class="dp-no-data-icon">🍩</span><span>Sin datos</span></div>
          </div>
        </div>

      </div>

      <!-- ── Fila 4: Pedidos recientes + Descuentos activos ── -->
      <div class="dp-bottom-row">

        <!-- Últimos pedidos -->
        <div class="dp-panel">
          <div class="dp-panel-header">
            <span class="dp-panel-icon">🧾</span>
            <h3 class="dp-panel-title">Últimos pedidos</h3>
            <span class="dp-panel-count">{{ recientes.length }}</span>
          </div>
          <div class="dp-panel-body">
            <table class="dp-table">
              <thead>
                <tr>
                  <th>Mesa</th>
                  <th>Detalle</th>
                  <th>Fecha</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in recientes" :key="p.id">
                  <td class="dp-td-bold">{{ p.cliente }}</td>
                  <td class="dp-td-muted" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ p.detalle || '—' }}
                  </td>
                  <td class="dp-td-muted">{{ fmtDate(p.fecha) }}</td>
                  <td>
                    <span :class="['dp-badge', estadoBadge(p.estado)]">{{ p.estado }}</span>
                  </td>
                </tr>
                <tr v-if="!recientes.length">
                  <td colspan="4" class="dp-td-muted" style="text-align:center;padding:24px;">Sin pedidos recientes</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Productos con descuento -->
        <div class="dp-panel">
          <div class="dp-panel-header">
            <span class="dp-panel-icon">🏷️</span>
            <h3 class="dp-panel-title">Descuentos activos</h3>
            <span class="dp-panel-count">{{ descuentos.length }}</span>
          </div>
          <div class="dp-panel-body">
            <div class="dp-discount-list">
              <div v-for="d in descuentos" :key="d.id" class="dp-discount-item">
                <div class="dp-discount-badge">-{{ d.descuento }}%</div>
                <div class="dp-discount-info">
                  <div class="dp-discount-name">{{ d.nombre }}</div>
                  <div class="dp-discount-meta">
                    {{ d.categoria }} · Vence: {{ d.vence ? fmtDateFull(d.vence) : 'Sin fecha' }}
                  </div>
                </div>
                <div class="dp-discount-prices">
                  <div class="dp-discount-original">{{ fmt(d.precio) }}</div>
                  <div class="dp-discount-final">{{ fmt(d.precio_final) }}</div>
                </div>
              </div>
              <div v-if="!descuentos.length" style="padding:24px;text-align:center;color:#9ca3af;font-size:0.85rem;">
                Sin descuentos activos
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ── Fila 5: Ranking de meseros ── -->
      <div class="dp-panel">
        <div class="dp-panel-header">
          <span class="dp-panel-icon">🏅</span>
          <h3 class="dp-panel-title">Ranking de meseros</h3>
          <span class="dp-panel-count">{{ meseros.length }}</span>
        </div>
        <div class="dp-panel-body" style="max-height: none;">
          <table class="dp-table">
            <thead>
              <tr>
                <th style="width:48px;">#</th>
                <th>Mesero</th>
                <th>Usuario</th>
                <th style="text-align:right;">Total pedidos</th>
                <th style="text-align:right;">Pagados</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(m, idx) in meseros" :key="m.id">
                <td>
                  <span :class="['dp-rank-badge', idx === 0 ? 'dp-rank-1' : idx === 1 ? 'dp-rank-2' : idx === 2 ? 'dp-rank-3' : 'dp-rank-n']">
                    {{ idx === 0 ? '🥇' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : idx + 1 }}
                  </span>
                </td>
                <td class="dp-td-bold">{{ m.nombre }}</td>
                <td class="dp-td-muted">@{{ m.usuario }}</td>
                <td style="text-align:right;">
                  <span class="dp-rank-total">{{ m.total_pedidos }}</span>
                </td>
                <td style="text-align:right;" class="dp-td-muted">{{ m.pedidos_pagados }}</td>
              </tr>
              <tr v-if="!meseros.length">
                <td colspan="5" class="dp-td-muted" style="text-align:center;padding:24px;">Sin datos de meseros</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </template>
  </div>
</template>
