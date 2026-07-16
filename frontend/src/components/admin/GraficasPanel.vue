<script setup>
import { Line, Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend);

const props = defineProps({
  pedidosDiarios: {
    type: Object,
    required: true,
    default: () => ({ labels: [], datasets: [] })
  },
  pedidosMensuales: {
    type: Object,
    required: true,
    default: () => ({ labels: [], datasets: [] })
  },
  pedidosAnuales: {
    type: Object,
    required: true,
    default: () => ({ labels: [], datasets: [] })
  },
  pagosMetodo: {
    type: Object,
    required: true,
    default: () => ({ labels: [], datasets: [] })
  }
});

const chartOptions = {
  responsive: true,
  plugins: { legend: { position: 'top' }, title: { display: false } }
};
</script>

<template>
  <div class="dashboard-grid">
    <div class="dashboard-panel panel-large">
      <div class="panel-header">
        <div>
          <p class="panel-kicker">Tendencia</p>
          <h3>Pedidos y ventas</h3>
        </div>
        <span class="panel-pill">Gráficas</span>
      </div>
      <div class="charts-grid">
        <div class="chart-card">
          <Line v-if="pedidosDiarios.labels.length" :data="pedidosDiarios" :options="chartOptions" />
          <div v-else class="no-data">Sin datos</div>
        </div>
        <div class="chart-card">
          <Bar v-if="pedidosMensuales.labels.length" :data="pedidosMensuales" :options="chartOptions" />
          <div v-else class="no-data">Sin datos</div>
        </div>
        <div class="chart-card">
          <Bar v-if="pedidosAnuales.labels.length" :data="pedidosAnuales" :options="chartOptions" />
          <div v-else class="no-data">Sin datos</div>
        </div>
      </div>
    </div>

    <div class="dashboard-panel">
      <div class="panel-header">
        <div>
          <p class="panel-kicker">Pagos</p>
          <h3>Métodos de pago</h3>
        </div>
      </div>
      <div class="chart-card compact">
        <Bar v-if="pagosMetodo.labels.length" :data="pagosMetodo" :options="chartOptions" />
        <div v-else class="no-data compact">Sin datos de pagos</div>
      </div>
    </div>
  </div>
</template>
