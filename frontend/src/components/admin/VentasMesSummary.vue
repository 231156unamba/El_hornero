<script setup>
const props = defineProps({
  ventasMes: {
    type: Number,
    required: true,
    default: 0
  }
});

const formatCurrency = (value) => `S/. ${Number(value || 0).toFixed(2)}`;
</script>

<template>
  <div class="dashboard-panel">
    <div class="panel-header">
      <div>
        <p class="panel-kicker">Resumen</p>
        <h3>Ventas del mes</h3>
      </div>
    </div>
    <div class="summary-box">
      <div class="summary-value">{{ formatCurrency(ventasMes) }}</div>
      <p>Acumulado del mes actual</p>
    </div>
  </div>
</template>
