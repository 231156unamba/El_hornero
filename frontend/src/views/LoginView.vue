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
      // Guardar sesión
      localStorage.setItem('token', 'dummy-token'); 
      localStorage.setItem('usuario', response.data.usuario);
      localStorage.setItem('rol', response.data.tipo);
      localStorage.setItem('userId', response.data.id);

      // Redirigir según rol
      const rol = response.data.tipo;
      if (rol === 'admin') router.push('/caja'); 
      else if (rol === 'cocina') router.push('/cocina');
      else if (rol === 'pedido') router.push('/pedidos'); 
      else router.push('/menu'); 
    } else {
      error.value = 'Credenciales incorrectas';
    }
  } catch (e) {
    error.value = 'Error de conexión con el servidor';
    console.error(e);
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="login-page">
    <div class="overlay">
      <div class="header">
          <img src="/logo.png" alt="Polleria El Hornero" class="logo-img">
          <span class="slogan">"Donde el buen sabor comienza"</span>
      </div>

      <div class="main">
          <div class="login-box">
              <h3>Acceso</h3>

              <div v-if="error" class="error-message" style="display: block;">{{ error }}</div>

              <form @submit.prevent="login">
                  <div class="field">
                      <label>
                          <img src="https://cdn-icons-png.flaticon.com/512/1077/1077114.png">
                          Usuario
                      </label>
                      <input v-model="usuario" type="text" placeholder="Tu usuario" required>
                  </div>

                  <div class="field">
                      <label>
                          <img src="https://cdn-icons-png.flaticon.com/512/3064/3064155.png">
                          Contraseña:
                      </label>
                      <input v-model="clave" type="password" placeholder="••••••••" required>
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

<style scoped>
/* Estilos migrados de login.html */
* {
    box-sizing: border-box;
}

.login-page {
    font-family: Arial, sans-serif;
    min-height: 100vh;
    background: url("https://inverzm.com/wp-content/uploads/2024/08/iStock-1470700554.jpg") center/cover no-repeat;
}

/* CAPA OSCURA */
.overlay {
    min-height: 100vh;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
}

/* HEADER CORREGIDO: Más delgado */
.header {
    background: #f3e1b8;
    padding: 0 25px; 
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    height: 75px; 
}

/* LOGO CORREGIDO: Más grande y sobresale un poco */
.logo-img {
    height: 100px; 
    width: auto;
    position: relative;
    top: 5px; 
    filter: drop-shadow(2px 2px 5px rgba(0,0,0,0.2));
}

.slogan {
    font-family: 'Georgia', serif;
    font-size: 15px;
    font-weight: bold;
    color: #4b3d02; 
    font-style: italic;
}

/* CONTENEDOR CENTRAL */
.main {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* LOGIN BOX */
.login-box {
    width: 360px; 
    background: rgba(255, 255, 255, 0.88);
    padding: 35px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.login-box h3 {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 25px;
    color: #333;
}

.field {
    margin-bottom: 18px;
}

.field label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: bold;
    color: #000;
    margin-bottom: 8px;
}

.field label img {
    width: 16px;
    height: 16px;
}

.field input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #777;
    font-size: 14px;
}

/* BOTON */
.btn-login {
    margin-top: 10px;
    width: 100%;
    padding: 14px;
    background: #ff6f2c;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.btn-login:hover {
    background: #e55f22;
    transform: scale(1.02);
}

.btn-login:disabled {
    background: #ccc;
    cursor: not-allowed;
}

/* MENSAJES */
.error-message {
    background: #ffebee;
    color: #c62828;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-size: 13px;
    text-align: center;
    font-weight: bold;
    border: 1px solid #c62828;
}
</style>
