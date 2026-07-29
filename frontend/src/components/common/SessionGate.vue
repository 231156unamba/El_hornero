<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api';
import { clearAuthState, getStoredUser, roleToPath } from '../../utils/auth';

const router = useRouter();
const route = useRoute();
const user = ref(getStoredUser());
const showModal = ref(false);
const active = ref(true);
const hasAuthenticatedSession = ref(false);
let intervalId = null;

const requiresAuth = computed(() => Boolean(route.meta?.requiresAuth));
const expectedPath = computed(() => roleToPath(user.value.rol));

const verifySession = async () => {
  if (!requiresAuth.value) {
    active.value = true;
    showModal.value = false;
    return;
  }

  const storedUser = getStoredUser();
  if (!storedUser?.id && !storedUser?.usuario && !storedUser?.rol) {
    hasAuthenticatedSession.value = false;
    active.value = true;
    showModal.value = false;
    if (route.path !== '/login') {
      router.replace('/login');
    }
    return;
  }

  try {
    const response = await api.get('/user');
    const currentUser = response?.data;
    if (!currentUser || !currentUser.id) {
      throw new Error('No auth');
    }

    const sameUser = String(currentUser.id) === String(storedUser.id);
    const sameRole = String(currentUser.tipo || currentUser.rol || '').toLowerCase() === String(storedUser.rol || '').toLowerCase();
    if (!sameUser || !sameRole) {
      throw new Error('session mismatch');
    }

    user.value = {
      id: currentUser.id,
      usuario: currentUser.usuario || currentUser.name || storedUser.usuario,
      nombres: currentUser.nombres || storedUser.nombres,
      apellidos: currentUser.apellidos || storedUser.apellidos,
      rol: currentUser.tipo || storedUser.rol,
    };
    localStorage.setItem('userId', currentUser.id);
    localStorage.setItem('usuario', currentUser.usuario || currentUser.name || storedUser.usuario);
    localStorage.setItem('nombres', currentUser.nombres || storedUser.nombres || '');
    localStorage.setItem('apellidos', currentUser.apellidos || storedUser.apellidos || '');
    localStorage.setItem('rol', currentUser.tipo || storedUser.rol || '');
    hasAuthenticatedSession.value = true;
    active.value = true;
    showModal.value = false;
  } catch (error) {
    if (hasAuthenticatedSession.value) {
      active.value = false;
      showModal.value = true;
      clearAuthState();
      hasAuthenticatedSession.value = false;
    } else {
      active.value = true;
      showModal.value = false;
    }
  }
};

const goToCurrentPanel = () => {
  if (route.path === '/login') return;
  router.replace(expectedPath.value || '/login');
};

watch(() => route.fullPath, () => {
  verifySession();
});

onMounted(() => {
  verifySession();
  intervalId = window.setInterval(verifySession, 5000);
  window.addEventListener('focus', verifySession);
});

onBeforeUnmount(() => {
  if (intervalId) window.clearInterval(intervalId);
  window.removeEventListener('focus', verifySession);
});
</script>

<template>
  <div class="session-gate">
    <slot v-if="active" />
    <div v-else class="session-lock">
      <div class="session-modal">
        <h3>Sesión cambiada o inválida</h3>
        <p>La sesión actual ya no coincide con esta vista. Por seguridad, la interfaz quedó bloqueada.</p>
        <button @click="goToCurrentPanel">Ir al panel correspondiente</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.session-gate {
  min-height: 100vh;
}
.session-lock {
  position: fixed;
  inset: 0;
  background: rgba(17, 24, 39, 0.82);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}
.session-modal {
  width: min(440px, 100%);
  background: white;
  border-radius: 18px;
  padding: 28px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.25);
  text-align: center;
}
.session-modal h3 {
  margin: 0 0 10px;
  color: #111827;
}
.session-modal p {
  margin: 0 0 18px;
  color: #4b5563;
  line-height: 1.5;
}
.session-modal button {
  border: none;
  border-radius: 10px;
  padding: 10px 16px;
  background: #f59e0b;
  color: white;
  font-weight: 700;
  cursor: pointer;
}
</style>
