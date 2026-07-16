<script setup>
const props = defineProps({
  pedidos: {
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

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString.includes('Z') ? dateString : dateString.replace(' ', 'T') + 'Z');
  return date.toLocaleDateString('es-PE', { day: 'numeric', month: 'numeric', year: 'numeric' });
};

const formatTime = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString.includes('Z') ? dateString : dateString.replace(' ', 'T') + 'Z');
  return date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true });
};
</script>

<template>
  <table class="custom-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>MESA</th>
        <th>DETALLE PEDIDO</th>
        <th>COSTO</th>
        <th>FECHA / HORA</th>
        <th style="text-align: right;">ESTADO</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="p in pedidos" :key="p.id">
        <td style="color: #000">{{ p.id }}</td>
        <td class="fw-bold" style="color: #000">Mesa {{ p.mesa }}</td>
        <td style="font-size: 0.85rem; color: #000">{{ p.detalle }}</td>
        <td style="color: #000">S/. {{ Number(p.costo || 0).toFixed(2) }}</td>
        <td style="color: #000">{{ formatDate(p.fecha) }} {{ formatTime(p.fecha) }}</td>
        <td style="text-align: right;">
          <span :class="['status-badge', getStatusClass(p.estado)]">
            {{ p.estado }}
          </span>
        </td>
      </tr>
    </tbody>
  </table>
</template>
