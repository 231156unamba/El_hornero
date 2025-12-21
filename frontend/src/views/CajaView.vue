<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';

const estadoCaja = ref('Estado: --');
const monto = ref(100.00);
const resultado = ref('');

const mostrar = (data) => {
  try {
    resultado.value = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
  } catch {
    resultado.value = String(data);
  }
};

const actualizarEstado = async () => {
  try {
    const response = await api.get('/caja/estado');
    const r = response.data;
    if (r && r.estado) {
      estadoCaja.value = `Estado: ${r.estado} (id:${r.id || '-'})`;
    } else {
      estadoCaja.value = 'Estado: --';
    }
  } catch (error) {
    console.error('Error actualizando estado:', error);
    estadoCaja.value = 'Estado: -- (Error de conexión)';
  }
};

const abrirCaja = async () => {
  try {
    const response = await api.post('/caja/abrir');
    mostrar(response.data);
    actualizarEstado();
  } catch (error) {
    mostrar(error.response?.data || error.message || 'Error al abrir caja');
  }
};

const cerrarCaja = async () => {
  try {
    const response = await api.post('/caja/cerrar');
    mostrar(response.data);
    actualizarEstado();
  } catch (error) {
    mostrar(error.response?.data || error.message || 'Error al cerrar caja');
  }
};

const registrarVenta = async () => {
  try {
    const val = parseFloat(monto.value) || 0;
    const response = await api.post('/caja/venta', { monto: val });
    mostrar(response.data);
    actualizarEstado();
  } catch (error) {
    mostrar(error.response?.data || error.message || 'Error al registrar venta');
  }
};

const generarRecibo = async () => {
  try {
    const response = await api.post('/caja/recibo');
    mostrar(response.data);
  } catch (error) {
    mostrar(error.response?.data || error.message || 'Error generando recibo');
  }
};

const enviarSunat = async () => {
  try {
    const response = await api.post('/caja/sunat');
    mostrar(response.data);
  } catch (error) {
    mostrar(error.response?.data || error.message || 'Error enviando a SUNAT');
  }
};

onMounted(() => {
  actualizarEstado();
});
</script>

<template>
  <div class="caja-view">
    <h2>Módulo de Caja</h2>

    <div class="container">
      <div class="row mb-2">
        <div class="col-md-6">
          <button @click="abrirCaja" class="btn btn-success mb-2 me-2">Abrir Caja</button>
          <button @click="cerrarCaja" class="btn btn-danger mb-2">Cerrar Caja</button>
        </div>
        <div class="col-md-6 text-end">
          <small id="estadoCaja">{{ estadoCaja }}</small>
        </div>
      </div>

      <hr />

      <h4>Registrar Venta</h4>
      <div class="row g-2 align-items-center">
        <div class="col-auto">
          <input v-model="monto" type="number" step="0.01" class="form-control" placeholder="Monto S/">
        </div>
        <div class="col-auto">
          <button @click="registrarVenta" class="btn btn-primary">Registrar Venta</button>
        </div>
        <div class="col-auto">
          <button @click="generarRecibo" class="btn btn-secondary">Generar Recibo (última venta)</button>
        </div>
        <div class="col-auto">
          <button @click="enviarSunat" class="btn btn-warning">Enviar a SUNAT (último recibo)</button>
        </div>
      </div>

      <hr />

      <h4>Últimos registros</h4>
      <pre class="resultado">{{ resultado }}</pre>
    </div>
  </div>
</template>

<style scoped>
/* Simulating Bootstrap 5 styles for exact appearance */
.caja-view {
  font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  padding: 20px;
}

h2, h4 {
  margin-top: 0;
  margin-bottom: 0.5rem;
  font-weight: 500;
  line-height: 1.2;
}

h2 { font-size: calc(1.325rem + .9vw); }
h4 { font-size: calc(1.275rem + .3vw); }

.container {
  width: 100%;
  padding-right: var(--bs-gutter-x, .75rem);
  padding-left: var(--bs-gutter-x, .75rem);
  margin-right: auto;
  margin-left: auto;
}
@media (min-width: 768px) { .container { max-width: 720px; } }
@media (min-width: 992px) { .container { max-width: 960px; } }
@media (min-width: 1200px) { .container { max-width: 1140px; } }

.row {
  --bs-gutter-x: 1.5rem;
  --bs-gutter-y: 0;
  display: flex;
  flex-wrap: wrap;
  margin-top: calc(-1 * var(--bs-gutter-y));
  margin-right: calc(-.5 * var(--bs-gutter-x));
  margin-left: calc(-.5 * var(--bs-gutter-x));
}
.row > * {
  flex-shrink: 0;
  width: 100%;
  max-width: 100%;
  padding-right: calc(var(--bs-gutter-x) * .5);
  padding-left: calc(var(--bs-gutter-x) * .5);
  margin-top: var(--bs-gutter-y);
}

.g-2 {
  --bs-gutter-x: 0.5rem;
  --bs-gutter-y: 0.5rem;
}

.col-auto {
  flex: 0 0 auto;
  width: auto;
}

@media (min-width: 768px) {
  .col-md-6 {
    flex: 0 0 auto;
    width: 50%;
  }
  .text-end {
    text-align: right !important;
  }
}

.mb-2 { margin-bottom: 0.5rem !important; }
.me-2 { margin-right: 0.5rem !important; }

.btn {
  display: inline-block;
  font-weight: 400;
  line-height: 1.5;
  color: #212529;
  text-align: center;
  text-decoration: none;
  vertical-align: middle;
  cursor: pointer;
  user-select: none;
  background-color: transparent;
  border: 1px solid transparent;
  padding: .375rem .75rem;
  font-size: 1rem;
  border-radius: .25rem;
  transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
}

.btn-primary { color: #fff; background-color: #0d6efd; border-color: #0d6efd; }
.btn-primary:hover { background-color: #0b5ed7; border-color: #0a58ca; }

.btn-secondary { color: #fff; background-color: #6c757d; border-color: #6c757d; }
.btn-secondary:hover { background-color: #5c636a; border-color: #565e64; }

.btn-success { color: #fff; background-color: #198754; border-color: #198754; }
.btn-success:hover { background-color: #157347; border-color: #146c43; }

.btn-danger { color: #fff; background-color: #dc3545; border-color: #dc3545; }
.btn-danger:hover { background-color: #bb2d3b; border-color: #b02a37; }

.btn-warning { color: #000; background-color: #ffc107; border-color: #ffc107; }
.btn-warning:hover { background-color: #ffca2c; border-color: #ffc720; }

.form-control {
  display: block;
  width: 100%;
  padding: .375rem .75rem;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.5;
  color: #212529;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #ced4da;
  appearance: none;
  border-radius: .25rem;
  transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
}

.resultado {
  height: 250px; 
  overflow: auto; 
  background: #f8f9fa; 
  padding: 10px; 
  border: 1px solid #ddd;
  display: block;
  font-family: var(--bs-font-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace);
  white-space: pre-wrap;
}

hr {
  margin: 1rem 0;
  color: inherit;
  background-color: currentColor;
  border: 0;
  opacity: .25;
  height: 1px;
}

.align-items-center {
  align-items: center !important;
}
</style>
