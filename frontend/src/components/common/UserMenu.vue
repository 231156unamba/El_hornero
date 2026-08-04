<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { getStoredUser, logoutSession, normalizeRole } from '../../utils/auth';
import api from '../../api';

const props = defineProps({
  dropdownPlacement: {
    type: String,
    default: 'bottom',
    validator: (value) => ['bottom', 'top'].includes(value),
  },
});

const router = useRouter();
const user = ref(getStoredUser());
const open = ref(false);

const fullName = computed(() => {
  const names = [user.value.nombres, user.value.apellidos].filter(Boolean).join(' ').trim();
  return names || user.value.usuario || 'Usuario';
});

const userName = computed(() => {
  return user.value.usuario || 'Usuario';
});

const displayRole = computed(() => {
  const role = (user.value.rol || '').toLowerCase();
  const roleMap = {
    'admin': 'administrador',
    'cocina': 'cocinero',
    'pedido': 'mesero',
    'caja': 'cajero'
  };
  return roleMap[role] || user.value.rol || '';
});

const handleLogout = async () => {
  open.value = false;
  await logoutSession(router);
};

// Fetch real user data from backend /api/user endpoint
const fetchCurrentUser = async () => {
  try {
    const response = await api.get('/user');
    if (response.data) {
      user.value = {
        id: response.data.id,
        usuario: response.data.usuario,
        nombres: response.data.nombres,
        apellidos: response.data.apellidos,
        rol: normalizeRole(response.data.tipo),
      };
    }
  } catch (error) {
    console.error('Error fetching current user:', error);
  }
};

onMounted(() => {
  fetchCurrentUser();
});
</script>

<template>
  <div class="user-menu" @click="open = !open">
    <div class="user-icon">👤</div>
    <span class="user-name">{{ userName }}</span>

    <div
      v-if="open"
      class="user-dropdown"
      :class="`user-dropdown--${props.dropdownPlacement}`"
      @click.stop
    >
      <div class="dropdown-header">
        <div class="dropdown-icon">👤</div>
        <div class="dropdown-info">
          <div class="dropdown-name">{{ fullName }}</div>
          <div class="dropdown-username">{{ user.usuario }}</div>
          <div class="dropdown-role">{{ displayRole }}</div>
        </div>
      </div>
      <button class="logout-btn" @click="handleLogout">Cerrar sesión</button>
    </div>
  </div>
</template>

<style scoped>
.user-menu {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  background: rgb(255, 201, 66);
  cursor: pointer;
  color: rgb(0, 28, 65);
  transition: background 0.2s;
}

.user-menu:hover {
  background: rgb(255, 131, 49);
}

.user-icon {
  font-size: 20px;
  line-height: 1;
}

.user-name {
  font-weight: 600;
  font-size: 14px;
  white-space: nowrap;
}

.user-dropdown {
  position: absolute;
  right: 0;
  min-width: 200px;
  background: white;
  color: #111827;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  padding: 12px;
  z-index: 100;
}

.user-dropdown--bottom {
  top: calc(100% + 8px);
}

.user-dropdown--top {
  bottom: calc(100% + 8px);
}

.dropdown-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid #e5e7eb;
}

.dropdown-icon {
  font-size: 32px;
  line-height: 1;
}

.dropdown-info {
  flex: 1;
}

.dropdown-name {
  font-weight: 700;
  font-size: 14px;
  color: #111827;
}

.dropdown-username {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.dropdown-role {
  font-size: 12px;
  color: #6b7280;
  text-transform: capitalize;
  margin-top: 2px;
}

.logout-btn {
  width: 100%;
  border: none;
  border-radius: 6px;
  padding: 8px 12px;
  background: #ef4444;
  color: white;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s;
}

.logout-btn:hover {
  background: #dc2626;
}
</style>
