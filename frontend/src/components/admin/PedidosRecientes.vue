<script setup>
const props = defineProps({
  recientes: {
    type: Array,
    required: true,
    default: () => []
  }
});

const getStatusClass = (status) => {
  const s = (status || '').toLowerCase();
  if (s.includes('pendiente')) return 'status-pendiente';
  if (s.includes('preparando')) return 'status-preparando';
  if (s.includes('listo') || s.includes('entregado') || s.includes('completado')) return 'status-completado';
  if (s.includes('cancelado')) return 'status-cancelado';
  return 'status-default';
};

const formatCurrency = (value) => `S/. ${Number(value || 0).toFixed(2)}`;
</script>

<template>
  <div class="dashboard-panel">
    <div class="panel-header">
      <div>
        <p class="panel-kicker">Actividad</p>
        <h3>Pedidos recientes</h3>
      </div>
    </div>
    <div v-if="recientes.length" class="activity-list">
      <div v-for="r in recientes" :key="r.id" class="activity-item">
        <div class="activity-main">
          <div class="activity-title">Mesa {{ r.mesa }}</div>
          <div class="activity-detail">{{ r.detalle || 'Sin detalle' }}</div>
        </div>
        <div class="activity-meta">
          <span :class="['status-badge', getStatusClass(r.estado)]">{{ r.estado }}</span>
          <strong>{{ formatCurrency(r.costo) }}</strong>
        </div>
      </div>
    </div>
    <div v-else class="no-data compact">No hay pedidos recientes</div>
  </div>
</template>
