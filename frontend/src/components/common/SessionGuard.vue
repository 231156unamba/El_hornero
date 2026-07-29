<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { getStoredUser } from '../../utils/auth';

const router = useRouter();
const currentUserId = ref(null);
const currentSessionId = ref(null);
let sessionChannel = null;

// BroadcastChannel para comunicar entre pestañas
const initSessionChannel = () => {
  sessionChannel = new BroadcastChannel('session-sync');
  
  sessionChannel.onmessage = (event) => {
    const { type, userId, sessionId } = event.data;
    
    if (type === 'new-login') {
      // Ignorar si es el mensaje de esta misma pestaña
      if (currentSessionId.value && sessionId === currentSessionId.value) {
        return;
      }
      
      // Si hay un nuevo login de otra pestaña, redirigir a login
      // El backend ya invalidó el token anterior
      router.push('/login');
    }
  };
};

onMounted(() => {
  // Store the current user ID and session ID when component mounts
  const storedUser = getStoredUser();
  currentUserId.value = storedUser.id ? parseInt(storedUser.id) : null;
  currentSessionId.value = localStorage.getItem('sessionId');
  
  // Initialize BroadcastChannel (solo escuchar, no notificar)
  initSessionChannel();
  
  // Cleanup
  onUnmounted(() => {
    if (sessionChannel) {
      sessionChannel.close();
    }
  });
});
</script>

<template>
  <!-- SessionGuard: redirección automática cuando hay nuevo login en otra pestaña -->
</template>
