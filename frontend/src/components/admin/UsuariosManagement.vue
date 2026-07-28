<script setup>
const props = defineProps({
  usuarios: {
    type: Array,
    required: true,
    default: () => []
  }
});

const emit = defineEmits(['edit', 'delete']);

const tipoLabel = {
  admin:   'Administrador',
  cocina:  'Cocinero',
  pedido:  'Mesero',
  caja:    'Cajero',
};

const getTipoLabel = (tipo) => tipoLabel[(tipo || '').toLowerCase()] ?? tipo;
</script>

<template>
  <table class="custom-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>USUARIO</th>
        <th>NOMBRES</th>
        <th>APELLIDOS</th>
        <th>TIPO</th>
        <th style="text-align: center;">ACCIONES</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="c in usuarios" :key="c.id">
        <td class="table-cell-black">{{ c.id }}</td>
        <td class="table-cell-bold">{{ c.usuario }}</td>
        <td class="table-cell-black">{{ c.nombres }}</td>
        <td class="table-cell-black">{{ c.apellidos }}</td>
        <td><span :class="['role-badge', c.tipo]">{{ getTipoLabel(c.tipo) }}</span></td>
        <td>
          <div class="menu-actions">
            <button class="btn btn-success" @click="emit('edit', c)">Editar</button>
            <button class="btn btn-danger" v-if="c.id !== 1" @click="emit('delete', c)">Eliminar</button>
            <button class="btn btn-danger btn-disabled" v-else disabled>Eliminar</button>
          </div>
        </td>
      </tr>
    </tbody>
  </table>
</template>
