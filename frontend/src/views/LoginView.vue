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
      if (rol === 'admin') router.push('/admin'); 
      else if (rol === 'caja') router.push('/caja'); 
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

/* HEADER: Efecto Cristal (Glassmorphism) */
.header {
    background: rgba(17, 24, 39, 0.15); /* Transparencia mínima */
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    padding: 0 40px; 
    display: flex;
    align-items: center;
    gap: 20px;
    height: 75px; 
    position: relative;
    z-index: 100;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo-img {
    height: 85px; 
    width: auto;
    filter: drop-shadow(0px 4px 8px rgba(0,0,0,0.3));
    position: relative;
    top: 5px; /* Sobresale un poquito por abajo */
}

.slogan {
    font-family: 'Inter', sans-serif;
    color: #f1af32;
    font-size: 16px;
    font-style: italic;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    letter-spacing: 1px;
}

/* CONTENEDOR CENTRAL */
.main {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

/* LOGIN BOX: Rediseño Profesional y Delgado */
.login-box.professional-style {
    width: 350px; /* Más delgado para un look más moderno */
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(15px); /* Más desenfoque para calidad premium */
    -webkit-backdrop-filter: blur(15px);
    padding: 40px 30px;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.4);
}

.login-header {
    text-align: center;
    margin-bottom: 30px;
}

.login-header h3 {
    font-size: 26px;
    font-weight: 800;
    color: #111827;
    margin: 0;
    font-family: 'Inter', sans-serif;
}

.login-header p {
    font-size: 14px;
    color: #6b7280;
    margin-top: 5px;
}

.field {
    margin-bottom: 20px;
}

.field label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.input-container {
    position: relative;
    display: flex;
    align-items: center;
}

.input-container .field-icon {
    position: absolute;
    left: 12px;
    width: 16px;
    height: 16px;
    filter: grayscale(1) brightness(0); /* Los hace negros sólidos */
    opacity: 0.6;
}

.field input {
    width: 100%;
    padding: 12px 12px 12px 40px;
    border-radius: 12px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    font-size: 14px;
    transition: all 0.2s ease;
}

.field input:focus {
    outline: none;
    border-color: #f1af32;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(241, 175, 50, 0.1);
}

/* BOTÓN PROFESIONAL */
.btn-login {
    width: 100%;
    padding: 14px;
    background: #ff6f2c;
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
}

.btn-login:hover {
    background: #e55f22;
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(255, 111, 44, 0.3);
}

.btn-login:active {
    transform: translateY(0);
}

.btn-login:disabled {
    background: #d1d5db;
    cursor: not-allowed;
    box-shadow: none;
}

.error-message {
    background: #fef2f2;
    color: #991b1b;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    text-align: center;
    font-weight: 600;
    border: 1px solid #fee2e2;
}
</style>
