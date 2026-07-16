<script setup>
const props = defineProps({
  reportType: {
    type: String,
    required: true,
    default: 'pedidos'
  },
  reportFilters: {
    type: Object,
    required: true,
    default: () => ({ from: '', to: '', mesa: '', mesero_id: '', costo_min: '', costo_max: '' })
  },
  reportData: {
    type: Array,
    required: true,
    default: () => []
  },
  meseros: {
    type: Array,
    required: true,
    default: () => []
  }
});

const emit = defineEmits(['update:reportType', 'update:reportFilters', 'export']);

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
  <div>
    <div class="report-controls">
      <div class="control">
        <label>Tipo</label>
        <select :value="reportType" @change="emit('update:reportType', $event.target.value)" class="form-control">
          <option value="pedidos">Pedidos</option>
          <option value="pedidos_mesero">Pedidos por mesero</option>
          <option value="recibos_entregados">Por costo</option>
        </select>
      </div>
      <div class="control">
        <label>Desde</label>
        <input :value="reportFilters.from" @input="emit('update:reportFilters', { ...reportFilters, from: $event.target.value })" type="date" class="form-control">
      </div>
      <div class="control">
        <label>Hasta</label>
        <input :value="reportFilters.to" @input="emit('update:reportFilters', { ...reportFilters, to: $event.target.value })" type="date" class="form-control">
      </div>
      <div class="control" v-if="reportType==='pedidos'">
        <label>Mesa</label>
        <input :value="reportFilters.mesa" @input="emit('update:reportFilters', { ...reportFilters, mesa: $event.target.value })" type="number" min="1" class="form-control">
      </div>
      <div class="control" v-if="reportType==='pedidos_mesero'">
        <label>Mesero</label>
        <select :value="reportFilters.mesero_id" @input="emit('update:reportFilters', { ...reportFilters, mesero_id: $event.target.value })" class="form-control">
          <option value="">Todos</option>
          <option v-for="m in meseros" :key="m.id" :value="m.id">{{ (m.nombres || '') + ' ' + (m.apellidos || '') }}</option>
        </select>
      </div>
      <div class="control" v-if="reportType==='recibos_entregados'">
        <label>Costo mínimo</label>
        <input :value="reportFilters.costo_min" @input="emit('update:reportFilters', { ...reportFilters, costo_min: $event.target.value })" type="number" step="0.01" min="0" class="form-control">
      </div>
      <div class="control" v-if="reportType==='recibos_entregados'">
        <label>Costo máximo</label>
        <input :value="reportFilters.costo_max" @input="emit('update:reportFilters', { ...reportFilters, costo_max: $event.target.value })" type="number" step="0.01" min="0" class="form-control">
      </div>
      <div class="control" v-if="reportType==='recibos_entregados'">
        <label>Mesa</label>
        <input :value="reportFilters.mesa" @input="emit('update:reportFilters', { ...reportFilters, mesa: $event.target.value })" type="number" min="1" class="form-control">
      </div>
      <div class="control">
        <label>&nbsp;</label>
        <button class="btn btn-solid-orange" @click="emit('export')">Exportar PDF</button>
      </div>
    </div>
    <div class="report-preview">
      <table class="custom-table" v-if="reportType==='pedidos'||reportType==='pedidos_mesero'||reportType==='recibos_entregados'">
        <thead>
          <tr>
            <th>ID</th>
            <th>MESA</th>
            <th>MESERO</th>
            <th>TIPO</th>
            <th>DETALLE</th>
            <th>COSTO</th>
            <th>FECHA / HORA</th>
            <th style="text-align: right;">ESTADO</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in reportData" :key="p.id">
            <td style="color: #000">{{ p.id }}</td>
            <td class="fw-bold" style="color: #000">Mesa {{ p.mesa }}</td>
            <td style="color: #000">{{ p.mesero || '-' }}</td>
            <td style="color: #000; text-transform: capitalize;">{{ p.tipo_servicio || 'local' }}</td>
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
    </div>
  </div>
</template>
