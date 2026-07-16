<script setup>
const props = defineProps({
  usuarios: {
    type: Array,
    required: true,
    default: () => []
  }
});

const emit = defineEmits(['edit', 'delete']);
</script>

<template>
  <table class="custom-table" style="margin-top: 20px;">
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
        <td style="color: #000">{{ c.id }}</td>
        <td class="fw-bold" style="color: #000">{{ c.usuario }}</td>
        <td style="color: #000">{{ c.nombres }}</td>
        <td style="color: #000">{{ c.apellidos }}</td>
        <td><span :class="['role-badge', c.tipo]">{{ c.tipo }}</span></td>
        <td>
          <div class="menu-actions" style="justify-content: center;">
            <button class="btn btn-success" @click="emit('edit', c)">Editar</button>
            <button class="btn btn-danger" v-if="c.id !== 1" @click="emit('delete', c)">Eliminar</button>
            <button class="btn btn-danger" v-else disabled style="opacity: 0.5; cursor: not-allowed;">Eliminar</button>
          </div>
        </td>
      </tr>
    </tbody>
  </table>
</template>
