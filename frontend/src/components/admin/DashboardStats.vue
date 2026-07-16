<script setup>
import { computed } from 'vue';

const props = defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({ pedidosHoy: 0, ventasHoy: 0, totalClientes: 0, ventasMes: 0, recibosHoy: 0 })
  }
});

const formatCurrency = (value) => `S/. ${Number(value || 0).toFixed(2)}`;
</script>

<template>
  <div class="dashboard-stats">
    <div class="stat-card">
      <div class="stat-icon orange">🧾</div>
      <div>
        <div class="stat-value">{{ stats.pedidosHoy }}</div>
        <div class="stat-label">Pedidos Hoy</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green">💰</div>
      <div>
        <div class="stat-value">{{ formatCurrency(stats.ventasHoy) }}</div>
        <div class="stat-label">Ventas Hoy</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue">👤</div>
      <div>
        <div class="stat-value">{{ stats.totalClientes }}</div>
        <div class="stat-label">Usuarios</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon red">🧾</div>
      <div>
        <div class="stat-value">{{ stats.recibosHoy }}</div>
        <div class="stat-label">Recibos Hoy</div>
      </div>
    </div>
  </div>
</template>
