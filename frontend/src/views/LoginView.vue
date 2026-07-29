<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();
const usuario = ref('');
const clave = ref('');
const error = ref('');
const loading = ref(false);

const login = async () => {
  loading.value = true;
  error.value = '';
  
  try {
    const response = await api.post('/login', {
      usuario: usuario.value,
      clave: clave.value
    });

    if (response.data.success) {
      // Generar ID único para esta sesión
      const sessionId = Date.now() + Math.random();
      localStorage.setItem('sessionId', sessionId.toString());

      // Guardar sesión
      localStorage.setItem('token', response.data.token); 
      localStorage.setItem('usuario', response.data.usuario);
      localStorage.setItem('rol', response.data.tipo);
      localStorage.setItem('userId', response.data.id);
      localStorage.setItem('nombres', response.data.nombres || '');
      localStorage.setItem('apellidos', response.data.apellidos || '');
      localStorage.setItem('auth_sync', Date.now().toString());

      // Notificar a otras pestañas que hubo un nuevo login
      const sessionChannel = new BroadcastChannel('session-sync');
      sessionChannel.postMessage({ 
        type: 'new-login', 
        userId: response.data.id,
        sessionId: sessionId
      });
      sessionChannel.close();

      // Redirigir según rol
      const rol = response.data.tipo;
      if (rol === 'admin') router.push('/admin'); 
      else if (rol === 'caja') router.push('/caja'); 
      else if (rol === 'cocina') router.push('/cocina');
      else if (rol === 'pedido') router.push('/pedidos'); 
      else router.push('/menu'); 
    } else {
      error.value = response.data.message || 'Error al iniciar sesión';
    }
  } catch (e) {
    if (e.response?.data?.message) {
      error.value = e.response.data.message;
    } else {
      error.value = 'Error de conexión con el servidor';
    }
    console.error(e);
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="login-page">
    <!-- Patrón de ondas de fuego, generado en CSS (no depende de ninguna imagen) -->
    <div class="flames" aria-hidden="true"></div>
    <div class="embers" aria-hidden="true"></div>

    <div class="overlay">
      <header class="header">
          <img src="/logo.png" alt="Polleria El Hornero" class="logo-img">
          <p class="slogan">"Donde el buen sabor comienza"</p>
      </header>

      <div class="main">
          <div class="login-box professional-style">
              <div class="login-header">
                <h3>Bienvenido</h3>
                <p>Ingresa tus datos para continuar</p>
              </div>

              <div v-if="error" class="error-message">{{ error }}</div>

              <form @submit.prevent="login">
                  <div class="field">
                      <label>Usuario</label>
                      <div class="input-container">
                        <img src="https://cdn-icons-png.flaticon.com/512/1077/1077114.png" class="field-icon">
                        <input v-model="usuario" type="text" placeholder="Tu usuario" required>
                      </div>
                  </div>

                  <div class="field">
                      <label>Contraseña</label>
                      <div class="input-container">
                        <img src="https://cdn-icons-png.flaticon.com/512/3064/3064155.png" class="field-icon">
                        <input v-model="clave" type="password" placeholder="••••••••" required>
                      </div>
                  </div>

                  <button type="submit" class="btn-login" :disabled="loading">
                    {{ loading ? 'Ingresando...' : 'Iniciar sesión' }}
                  </button>
              </form>
          </div>
      </div>
    </div>
  </div>
</template>

<style src="../styles/login.css" scoped></style>