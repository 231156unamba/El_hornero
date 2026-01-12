<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { Line, Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend);

const router = useRouter();
const apiOrigin = new URL(api.defaults.baseURL).origin;
const stats = ref({ pedidosHoy: 0, ventasHoy: 0, totalClientes: 0, pedidosPendientes: 0 });
const recientes = ref([]);
const form = ref({ id: null, nombre: '', precio: '', categoria: 'comida', descripcion: '' });
const menu = ref([]);
const editing = ref(false);
const activeTab = ref('dashboard');
const usuarios = ref([]);
const pedidos = ref([]);
const pedidosDiarios = ref({ labels: [], datasets: [] });
const pedidosMensuales = ref({ labels: [], datasets: [] });
const pedidosAnuales = ref({ labels: [], datasets: [] });
const reportType = ref('pedidos');
const reportFilters = ref({ from: '', to: '', mesa: '', mesero_id: '', tipo: '' });
const reportData = ref([]);
const meseros = ref([]);
const chartOptions = {
  responsive: true,
  plugins: { legend: { position: 'top' }, title: { display: false } }
};
const loaded = ref({ stats: false, recientes: false, usuarios: false, pedidos: false });
const userForm = ref({ id: null, usuario: '', nombres: '', apellidos: '', clave: '', tipo: 'pedido' });
const userEditing = ref(false);
const imagenFile = ref(null);
const currentImageUrl = ref(null);
const imagenName = ref('');

const menuImageUrl = (item) => {
  if (!item) return '';
  if (item.imagen_url) return item.imagen_url;
  const img = item.imagen;
  if (!img) return '';
  if (/^https?:\/\//i.test(img)) return img;
  if (img.startsWith('/')) return apiOrigin + img;
  return `${apiOrigin}/images/menu/${img}`;
};

const imgFallback = (e) => {
  e.target.src = '/logo.png';
};

onMounted(() => {
  const rol = localStorage.getItem('rol');
  if (rol !== 'admin') {
    router.push('/login');
  } else {
    loadStats();
    loadRecientes();
    loadMenu();
    loadUsuarios();
    loadPedidos();
    loadPedidosDiarios();
    loadPedidosMensuales();
    loadPedidosAnuales();
  }
});

const loadStats = async () => {
  const r = await api.get('/admin/stats');
  stats.value = r.data;
  loaded.value.stats = true;
};
const loadRecientes = async () => {
  // Use /pedidos instead of /admin/recientes to get 'detalle' without touching backend code
  const r = await api.get('/pedidos');
  recientes.value = r.data.slice(0, 10);
  loaded.value.recientes = true;
};
const loadMenu = async () => {
  const r = await api.get('/menu');
  menu.value = r.data;
};
const loadUsuarios = async () => {
  const r = await api.get('/admin/usuarios');
  usuarios.value = r.data;
  loaded.value.usuarios = true;
};
const submitUsuario = async () => {
  const payload = {
    usuario: userForm.value.usuario,
    nombres: userForm.value.nombres,
    apellidos: userForm.value.apellidos,
    clave: userForm.value.clave,
    tipo: userForm.value.tipo,
  };
  if (userEditing.value && userForm.value.id) {
    await api.put(`/admin/usuarios/${userForm.value.id}`, payload);
  } else {
    await api.post('/admin/usuarios', payload);
  }
  clearUsuarioForm();
  loadUsuarios();
};
const editUsuario = (u) => {
  userEditing.value = true;
  userForm.value = { id: u.id, usuario: u.usuario, nombres: u.nombres || '', apellidos: u.apellidos || '', clave: '', tipo: u.tipo || 'pedido' };
};
const deleteUsuario = async (id) => {
  await api.delete(`/admin/usuarios/${id}`);
  loadUsuarios();
};
const clearUsuarioForm = () => {
  userEditing.value = false;
  userForm.value = { id: null, usuario: '', nombres: '', apellidos: '', clave: '', tipo: 'pedido' };
};
const loadPedidos = async () => {
  const r = await api.get('/pedidos');
  pedidos.value = r.data;
  loaded.value.pedidos = true;
};
const loadPedidosDiarios = async () => {
  const r = await api.get('/admin/pedidos-diarios');
  const labels = r.data.map(i => i.label);
  const values = r.data.map(i => parseInt(i.value) || 0);
  if (!labels.length || values.every(v => v === 0)) {
    pedidosDiarios.value = { labels: [], datasets: [] };
  } else {
    pedidosDiarios.value = { labels, datasets: [{ label: 'Pedidos diarios', data: values, borderColor: '#ff6d2f', backgroundColor: 'rgba(255,109,47,0.2)' }] };
  }
};
const loadPedidosMensuales = async () => {
  const r = await api.get('/admin/pedidos-mensuales');
  const labels = r.data.map(i => i.label);
  const values = r.data.map(i => parseInt(i.value) || 0);
  if (!labels.length || values.every(v => v === 0)) {
    pedidosMensuales.value = { labels: [], datasets: [] };
  } else {
    pedidosMensuales.value = { labels, datasets: [{ label: 'Pedidos mensuales', data: values, backgroundColor: '#66bb6a' }] };
  }
};
const loadPedidosAnuales = async () => {
  const r = await api.get('/admin/pedidos-anuales');
  const labels = r.data.map(i => i.label);
  const values = r.data.map(i => parseInt(i.value) || 0);
  if (!labels.length || values.every(v => v === 0)) {
    pedidosAnuales.value = { labels: [], datasets: [] };
  } else {
    pedidosAnuales.value = { labels, datasets: [{ label: 'Pedidos anuales', data: values, backgroundColor: '#42a5f5' }] };
  }
};
const setTab = async (t) => {
  activeTab.value = t;
  if (t === 'menu') loadMenu();
  if (t === 'dashboard') {
    loadStats();
    loadRecientes();
    loadPedidosDiarios();
    loadPedidosMensuales();
    loadPedidosAnuales();
  }
  if (t === 'usuarios') loadUsuarios();
  if (t === 'pedidos') loadPedidos();
  if (t === 'reportes') loadReport();
  if (t === 'reportes') {
    if (!usuarios.value.length) await loadUsuarios();
    meseros.value = usuarios.value.filter(u => (u.tipo || '').toLowerCase() === 'pedido');
  }
};
const submitMenu = async () => {
  try {
    const fd = new FormData();
    fd.append('nombre', form.value.nombre);
    fd.append('precio', String(form.value.precio));
    fd.append('categoria', form.value.categoria);
    fd.append('descripcion', form.value.descripcion);
    if (imagenFile.value) {
      fd.append('imagen', imagenFile.value);
    }
    if (editing.value && form.value.id) {
      fd.append('_method', 'PUT');
      const r = await api.post(`/menu/${form.value.id}`, fd);
      if (!r.data?.success) {
        alert('No se pudo actualizar el plato.');
        return;
      }
    } else {
      const r = await api.post('/menu', fd);
      if (!r.data?.success) {
        alert('No se pudo crear el plato.');
        return;
      }
    }
    clearForm();
    await loadMenu();
    alert('Plato guardado correctamente.');
  } catch (err) {
    console.error('Error guardando plato:', err);
    alert('Ocurrió un error al guardar el plato.');
  }
};
const editItem = (item) => {
  editing.value = true;
  form.value = { id: item.id, nombre: item.nombre, precio: item.precio, categoria: item.categoria || 'comida', descripcion: item.descripcion };
  imagenFile.value = null;
  currentImageUrl.value = menuImageUrl(item) || null;
};
const deleteItem = async (id) => {
  await api.delete(`/menu/${id}`);
  loadMenu();
};
const clearForm = () => {
  editing.value = false;
  form.value = { id: null, nombre: '', precio: '', categoria: 'comida', descripcion: '' };
  imagenFile.value = null;
  imagenName.value = '';
  currentImageUrl.value = null;
};
const logout = () => {
  localStorage.clear();
  router.push('/login');
};

const loadReport = async () => {
  if (reportType.value === 'pedidos') {
    const params = {};
    if (reportFilters.value.from) params.from = reportFilters.value.from;
    if (reportFilters.value.to) params.to = reportFilters.value.to;
    if (reportFilters.value.mesa) params.mesa = reportFilters.value.mesa;
    const r = await api.get('/admin/reportes/pedidos', { params });
    reportData.value = r.data;
  } else if (reportType.value === 'pedidos_mesero') {
    const params = {};
    if (reportFilters.value.from) params.from = reportFilters.value.from;
    if (reportFilters.value.to) params.to = reportFilters.value.to;
    if (reportFilters.value.mesero_id) params.mesero_id = reportFilters.value.mesero_id;
    const r = await api.get('/admin/reportes/pedidos-mesero', { params });
    reportData.value = r.data;
  } else if (reportType.value === 'recibos_entregados') {
    const params = {};
    if (reportFilters.value.from) params.from = reportFilters.value.from;
    if (reportFilters.value.to) params.to = reportFilters.value.to;
    if (reportFilters.value.tipo) params.tipo = reportFilters.value.tipo;
    const r = await api.get('/admin/reportes/recibos-entregados', { params });
    reportData.value = r.data;
  } else {
    reportData.value = [];
  }
};


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

const exportReportPDF = () => {
  const w = window.open('', '_blank');
  const titleMap = {
    'pedidos': 'Reporte de Pedidos',
    'pedidos_mesero': 'Reporte de Pedidos por Mesero',
    'recibos_entregados': 'Reporte de Recibos Entregados'
  };
  const title = titleMap[reportType.value] || 'Reporte';
  let html = '<html><head><title>'+title+'</title><style>';
  html += 'body{font-family:Arial,sans-serif;padding:16px;} h1{font-size:20px;margin:0 0 12px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:8px;font-size:12px;} th{background:#f4f4f4;}';
  html += '</style></head><body>';
  html += '<h1>'+title+'</h1>';
  if (reportType.value === 'pedidos' || reportType.value === 'pedidos_mesero') {
    html += '<table><thead><tr><th>ID</th><th>Mesa</th><th>Mesero</th><th>Fecha</th><th>Estado</th><th>Costo</th><th>Detalle</th></tr></thead><tbody>';
    reportData.value.forEach(p => {
      html += '<tr><td>'+p.id+'</td><td>'+p.mesa+'</td><td>'+(p.mesero||'')+'</td><td>'+p.fecha+'</td><td>'+p.estado+'</td><td>S/. '+Number(p.costo||0).toFixed(2)+'</td><td>'+p.detalle+'</td></tr>';
    });
    html += '</tbody></table>';
  } else if (reportType.value === 'recibos_entregados') {
    html += '<table><thead><tr><th>ID</th><th>Número</th><th>Fecha</th><th>Tipo</th><th>Total</th><th>Subtotal</th><th>IGV</th><th>Venta</th></tr></thead><tbody>';
    reportData.value.forEach(r => {
      html += '<tr><td>'+r.id+'</td><td>'+r.numero+'</td><td>'+r.fecha+'</td><td>'+r.tipo+'</td><td>S/. '+Number(r.total||0).toFixed(2)+'</td><td>S/. '+Number(r.subtotal||0).toFixed(2)+'</td><td>S/. '+Number(r.igv||0).toFixed(2)+'</td><td>'+(r.venta_id||'-')+'</td></tr>';
    });
    html += '</tbody></table>';
  }
  html += '</body></html>';
  w.document.write(html);
  w.document.close();
  w.focus();
  w.print();
};
</script>

<template>
  <div class="admin-page">
    <header class="topbar">
      <div class="logo">
        <h1>EL HORNERO</h1>
      </div>
      <div class="user-actions">
        <div class="user-profile">
          <div class="user-avatar">A</div>
          <span>Administrador</span>
        </div>
        <button class="logout-btn" @click="logout">
          Cerrar Sesión
        </button>
      </div>
    </header>

    <main class="content-area">
      <div class="tabs">
        <button :class="['tab-btn', activeTab==='dashboard'?'active':'']" @click="setTab('dashboard')">Dashboard</button>
        <button :class="['tab-btn', activeTab==='menu'?'active':'']" @click="setTab('menu')">Menú</button>
        <button :class="['tab-btn', activeTab==='pedidos'?'active':'']" @click="setTab('pedidos')">Pedidos</button>
        <button :class="['tab-btn', activeTab==='usuarios'?'active':'']" @click="setTab('usuarios')">Usuarios</button>
        <button :class="['tab-btn', activeTab==='reportes'?'active':'']" @click="setTab('reportes')">Reportes</button>
      </div>

      <section v-if="activeTab==='dashboard' && loaded.stats" class="dashboard-stats">
        <div class="stat-card">
          <div class="stat-value">{{ stats.pedidosHoy }}</div>
          <div class="stat-label">Pedidos Hoy</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">S/. {{ stats.ventasHoy }}</div>
          <div class="stat-label">Ventas Hoy</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.totalClientes }}</div>
          <div class="stat-label">Usuarios</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.pedidosPendientes }}</div>
          <div class="stat-label">Pedidos Pendientes</div>
        </div>
      </section>
      <div v-if="activeTab==='dashboard' && !loaded.stats" class="loading">Cargando...</div>

      <section v-if="activeTab==='menu'" class="content-section" style="padding: 20px 50px;">
        <h2 class="section-title">Gestión del Menú</h2>
        <form class="add-form" @submit.prevent="submitMenu" style="padding: 30px;">
          <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px;">
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Nombre del Plato</label>
              <input v-model="form.nombre" class="form-control" placeholder="Ej. Lomo Saltado" required>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Categoría</label>
              <select v-model="form.categoria" class="form-control" required>
                <option value="comida">Comida</option>
                <option value="bebidas">Bebidas</option>
              </select>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Precio (S/.)</label>
              <input v-model="form.precio" type="number" step="0.01" class="form-control" placeholder="0.00" required>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Imagen del Plato</label>
              <input type="file" class="form-control" accept="image/*" :required="!editing" @change="e => { imagenFile.value = e.target.files?.[0] || null; imagenName.value = imagenFile.value ? imagenFile.value.name : ''; }">
              <div v-if="editing && currentImageUrl" style="margin-top:8px; display:flex; align-items:center; gap:10px;">
                <img :src="currentImageUrl" alt="" style="width:64px; height:64px; object-fit:cover; border:1px solid #ddd;">
                <small style="color:#777;">Imagen actual. Si no subes una nueva, se mantiene.</small>
              </div>
              <div v-if="imagenName" style="margin-top:6px; color:#555; font-size:12px;">Seleccionado: {{ imagenName }}</div>
            </div>
            <div class="form-group" style="grid-column: span 2;">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Descripción</label>
              <textarea v-model="form.descripcion" class="form-control" rows="3" placeholder="Detalla los ingredientes y la presentación del plato..." required></textarea>
            </div>
          </div>
          <div class="form-buttons" style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
            <button type="button" class="btn" style="background: #ef5350; color: white; border: none; font-weight: 600;" @click="clearForm">Limpiar</button>
            <button type="button" class="btn btn-secondary" v-if="editing" @click="clearForm" style="background: #757575; color: white;">Cancelar</button>
            <button type="submit" class="btn btn-solid-orange">{{ editing ? 'Guardar Cambios' : 'Agregar Plato' }}</button>
          </div>
        </form>
        <h3 style="margin: 25px 0 15px; color: #f1af32; font-size: 1.3rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Comidas</h3>
        <div class="menu-list">
          <div class="menu-item" v-for="item in menu.filter(i => (i.categoria || '').toLowerCase() === 'comida')" :key="item.id">
            <img :src="menuImageUrl(item)" alt="" @error="imgFallback">
            <div class="menu-content">
              <div class="menu-header">
                <div class="nombre">{{ item.nombre }}</div>
                <div class="precio">S/. {{ item.precio }}</div>
              </div>
              <div class="descripcion">{{ item.descripcion }}</div>
              <div class="menu-actions">
                <button class="btn btn-success" @click="editItem(item)">Editar</button>
                <button class="btn btn-danger" @click="deleteItem(item.id)">Eliminar</button>
              </div>
            </div>
          </div>
        </div>

        <h3 style="margin: 40px 0 15px; color: #f1af32; font-size: 1.3rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Bebidas</h3>
        <div class="menu-list">
          <div class="menu-item" v-for="item in menu.filter(i => (i.categoria || '').toLowerCase() === 'bebidas')" :key="item.id">
            <img :src="menuImageUrl(item)" alt="" @error="imgFallback">
            <div class="menu-content">
              <div class="menu-header">
                <div class="nombre">{{ item.nombre }}</div>
                <div class="precio">S/. {{ item.precio }}</div>
              </div>
              <div class="descripcion">{{ item.descripcion }}</div>
              <div class="menu-actions">
                <button class="btn btn-success" @click="editItem(item)">Editar</button>
                <button class="btn btn-danger" @click="deleteItem(item.id)">Eliminar</button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section v-if="activeTab==='dashboard'" class="content-section" style="padding: 20px 40px;">
        <h2 class="section-title">Gráficas de Pedidos</h2>
        <div class="charts-grid">
          <div class="chart-card">
            <Line v-if="pedidosDiarios.labels.length" :data="pedidosDiarios" :options="chartOptions" />
            <div v-else class="no-data">Sin datos</div>
          </div>
          <div class="chart-card">
            <Bar v-if="pedidosMensuales.labels.length" :data="pedidosMensuales" :options="chartOptions" />
            <div v-else class="no-data">Sin datos</div>
          </div>
          <div class="chart-card">
            <Bar v-if="pedidosAnuales.labels.length" :data="pedidosAnuales" :options="chartOptions" />
            <div v-else class="no-data">Sin datos</div>
          </div>
        </div>
      </section>

      <section v-if="activeTab==='dashboard' && loaded.recientes" class="content-section" style="padding: 20px 40px;">
        <h2 class="section-title">Pedidos Recientes</h2>
        <table class="custom-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>MESA</th>
              <th>DETALLE PEDIDO</th>
              <th>COSTO</th>
              <th>FECHA / HORA</th>
              <th style="text-align: right;">ESTADO</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in recientes" :key="r.id">
              <td style="color: #000">{{ r.id }}</td>
              <td class="fw-bold" style="color: #000">Mesa {{ r.mesa }}</td>
              <td style="font-size: 0.85rem; color: #000">{{ r.detalle }}</td>
              <td style="color: #000">S/. {{ Number(r.costo || 0).toFixed(2) }}</td>
              <td style="color: #000">{{ formatDate(r.fecha) }} {{ formatTime(r.fecha) }}</td>
              <td style="text-align: right;">
                <span :class="['status-badge', getStatusClass(r.estado)]">
                  {{ r.estado }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
      <div v-if="activeTab==='dashboard' && !loaded.recientes" class="loading">Cargando...</div>

      <section v-if="activeTab==='pedidos' && loaded.pedidos" class="content-section" style="padding: 20px 40px;">
        <h2 class="section-title">Pedidos</h2>
        <table class="custom-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>MESA</th>
              <th>DETALLE PEDIDO</th>
              <th>COSTO</th>
              <th>FECHA / HORA</th>
              <th style="text-align: right;">ESTADO</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in pedidos" :key="p.id">
              <td style="color: #000">{{ p.id }}</td>
              <td class="fw-bold" style="color: #000">Mesa {{ p.mesa }}</td>
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
      </section>
      <div v-if="activeTab==='pedidos' && !loaded.pedidos" class="loading">Cargando...</div>

      <section v-if="activeTab==='usuarios' && loaded.usuarios" class="content-section" style="padding: 20px 50px;">
        <h2 class="section-title">Usuarios</h2>
        <form class="add-form" @submit.prevent="submitUsuario" style="padding: 30px;">
          <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px;">
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Usuario</label>
              <input v-model="userForm.usuario" class="form-control" placeholder="Ej. jperez" required>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Tipo</label>
              <select v-model="userForm.tipo" class="form-control" required>
                <option value="admin">Admin</option>
                <option value="cocina">Cocina</option>
                <option value="pedido">Pedido</option>
                <option value="caja">Caja</option>
              </select>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Nombres</label>
              <input v-model="userForm.nombres" class="form-control" placeholder="Ej. Juan" required>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Apellidos</label>
              <input v-model="userForm.apellidos" class="form-control" placeholder="Ej. Perez" required>
            </div>
            <div class="form-group">
              <label style="font-weight: 600; color: #444; margin-bottom: 4px; display: block;">Clave</label>
              <input v-model="userForm.clave" type="password" class="form-control" :required="!userEditing" placeholder="******">
              <small style="color:#777; margin-top: 4px; display: block;" v-if="userEditing">Dejar en blanco para mantener actual</small>
            </div>
          </div>
          <div class="form-buttons" style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
            <button type="button" class="btn" style="background: #ef5350; color: white; border: none; font-weight: 600;" @click="clearUsuarioForm">Limpiar</button>
            <button type="button" class="btn btn-secondary" v-if="userEditing" @click="clearUsuarioForm" style="background: #757575; color: white;">Cancelar</button>
            <button type="submit" class="btn btn-solid-orange">{{ userEditing ? 'Guardar Cambios' : 'Crear Usuario' }}</button>
          </div>
        </form>
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
                  <button class="btn btn-success" @click="editUsuario(c)">Editar</button>
                  <button class="btn btn-danger" v-if="c.id !== 1" @click="deleteUsuario(c.id)">Eliminar</button>
                  <button class="btn btn-danger" v-else disabled style="opacity: 0.5; cursor: not-allowed;">Eliminar</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
      <div v-if="activeTab==='usuarios' && !loaded.usuarios" class="loading">Cargando...</div>

      <section v-if="activeTab==='reportes'" class="content-section" style="padding: 20px 40px;">
        <h2 class="section-title">Reportes</h2>
        <div class="report-controls">
          <div class="control">
            <label>Tipo</label>
            <select v-model="reportType" class="form-control" @change="loadReport">
              <option value="pedidos">Pedidos</option>
              <option value="pedidos_mesero">Pedidos por mesero</option>
              <option value="recibos_entregados">Recibos entregados</option>
            </select>
          </div>
          <div class="control" v-if="reportType!=='recibos_entregados'">
            <label>Desde</label>
            <input v-model="reportFilters.from" type="date" class="form-control" @change="loadReport">
          </div>
          <div class="control" v-if="reportType!=='recibos_entregados'">
            <label>Hasta</label>
            <input v-model="reportFilters.to" type="date" class="form-control" @change="loadReport">
          </div>
          <div class="control" v-if="reportType==='pedidos'">
            <label>Mesa</label>
            <input v-model="reportFilters.mesa" type="number" min="1" class="form-control" @change="loadReport">
          </div>
          <div class="control" v-if="reportType==='pedidos_mesero'">
            <label>Mesero</label>
            <select v-model="reportFilters.mesero_id" class="form-control" @change="loadReport">
              <option value="">Todos</option>
              <option v-for="m in meseros" :key="m.id" :value="m.id">{{ (m.nombres || '') + ' ' + (m.apellidos || '') }}</option>
            </select>
          </div>
          <div class="control" v-if="reportType==='recibos_entregados'">
            <label>Tipo Doc.</label>
            <select v-model="reportFilters.tipo" class="form-control" @change="loadReport">
              <option value="">Todos</option>
              <option value="BOLETA">Boleta</option>
              <option value="FACTURA">Factura</option>
            </select>
          </div>
          <div class="control">
            <label>&nbsp;</label>
            <button class="btn btn-solid-orange" @click="exportReportPDF">Exportar PDF</button>
          </div>
        </div>
        <div class="report-preview">
          <table class="custom-table" v-if="reportType==='pedidos'||reportType==='pedidos_mesero'">
            <thead>
              <tr>
                <th>ID</th>
                <th>MESA</th>
                <th>MESERO</th>
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
          <table class="custom-table" v-if="reportType==='recibos_entregados'">
            <thead>
              <tr>
                <th>ID</th>
                <th>NÚMERO</th>
                <th>TIPO</th>
                <th>TOTAL</th>
                <th>SUBTOTAL</th>
                <th>IGV</th>
                <th>FECHA / HORA</th>
                <th>VENTA ID</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in reportData" :key="r.id">
                <td style="color: #000">{{ r.id }}</td>
                <td class="fw-bold" style="color: #000">{{ r.numero }}</td>
                <td style="color: #000">{{ r.tipo }}</td>
                <td style="color: #000">S/. {{ Number(r.total || 0).toFixed(2) }}</td>
                <td style="color: #000">S/. {{ Number(r.subtotal || 0).toFixed(2) }}</td>
                <td style="color: #000">S/. {{ Number(r.igv || 0).toFixed(2) }}</td>
                <td style="color: #000">{{ formatDate(r.fecha) }} {{ formatTime(r.fecha) }}</td>
                <td style="color: #000">{{ r.venta_id || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
  </template>

<style scoped>
.admin-page {
  font-family: 'Segoe UI', Arial, sans-serif;
  background: #f8f9fa;
  min-height: 100vh;
}
.topbar {
  background-color: #111827 !important;
  padding: 15px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
}
.logo img {
  height: 80px;
}
.logo h1 {
  font-size: 25px;
  color: #f1af32;
  font-weight: 700;
  margin: 0;
}
.user-actions {
  display: flex;
  align-items: center;
  gap: 14px;
}
.user-profile {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f8f9fa;
  padding: 8px 12px;
  border-radius: 20px;
}
.user-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #f1af32;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 14px;
}
.logout-btn {
  background: #e53935;
  color: white;
  border: none;
  padding: 8px 14px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}
.content-area {
  padding: 20px;
}
.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}
.tab-btn {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid #eee;
  background: #fff;
  cursor: pointer;
  font-weight: 600;
  color: #6e6e6e;
}
.tab-btn.active {
  border-color: #ffcc80;
  color: #f1af32;
  background: #fff9f0;
}
.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 18px;
  margin-bottom: 20px;
}
.stat-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.08);
  border-top: 4px solid #f1af32;
}
.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #f1af32;
}
.stat-label {
  color: #6e6e6e;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.content-section {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.08);
  margin-bottom: 18px;
}
.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}
.chart-card {
  background: #fff;
  border: 1px solid #eee;
  border-radius: 12px;
  padding: 12px;
}
.no-data {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 220px;
  color: #6e6e6e;
  font-weight: 600;
  background: #fafafa;
  border: 1px dashed #ddd;
  border-radius: 8px;
}
.section-title {
  font-size: 18px;
  color: #f1af32;
  margin-bottom: 12px;
  border-bottom: 2px solid #ffcc80;
  padding-bottom: 8px;
}
.add-form { background: #f8f9ff; border-radius: 12px; padding: 16px; margin-bottom: 16px; border: 1px solid #e0e0ff; }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 10px; }
.form-group { display: flex; flex-direction: column; }
.form-group.full-width { grid-column: 1 / -1; }
.form-control { padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; background: white; }
.form-buttons { display: flex; gap: 12px; margin-top: 8px; }
.btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-primary { background: #f1af32; color: white; border: none; }
.btn-primary:hover { background: #e6a42b; }
.btn-success { background: #4caf50; color: white; }
.btn-danger { background: #e53935; color: white; }
.btn-warning { background: #ff9800; color: white; }
.menu-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.menu-item { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 3px 10px rgba(0,0,0,0.08); border: 1px solid #eee; }
.menu-item img { width: 100%; height: 150px; object-fit: cover; display: block; background: #f5f5f5; }
.menu-content { padding: 12px; }
.menu-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.nombre { font-weight: 700; color: #f1af32; }
.precio { background: #ef6c00; color: white; padding: 5px 10px; border-radius: 20px; font-weight: 700; font-size: 0.95rem; white-space: nowrap; }
.descripcion { color: #6e6e6e; margin-bottom: 10px; font-size: 0.95rem; line-height: 1.45; min-height: 60px; }
.menu-actions { display: flex; gap: 10px; }
.menu-actions .btn-success { background: #e0f2f1; color: #00695c; transition: background 0.2s; }
.menu-actions .btn-success:hover { background: #b2dfdb; color: #004d40; }
.menu-actions .btn-danger { background: #ffebee; color: #c62828; transition: background 0.2s; }
.menu-actions .btn-danger:hover { background: #ffcdd2; color: #b71c1c; }
.role-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
.role-badge.admin { background: #e3f2fd; color: #1565c0; }
.role-badge.cocina { background: #fff3e0; color: #ef6c00; }
.role-badge.pedido { background: #e8f5e9; color: #2e7d32; }
.role-badge.caja { background: #e0f7fa; color: #006064; }
.orders-table { width: 100%; border-collapse: collapse; }
.orders-table th { background: #f8f9fa; color: #2b2b2b; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e0e0e0; }
.orders-table td { padding: 12px; border-bottom: 1px solid #eee; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
.report-controls {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 20px;
  align-items: flex-end;
  background: #f5f7fa; /* Professional Light Gray-Blue */
  padding: 20px;
  border: 1px solid #e0e0e0; /* Matches table border */
}

.report-controls .control {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 140px;
  flex: 1;
}

/* Make the button control not stretch infinitely */
.report-controls .control:last-child {
  flex: 0 0 auto;
}

.btn-solid-orange {
  background: #ef6c00; /* Solid Dark Orange */
  color: white;
  transition: background 0.2s;
}
.btn-solid-orange:hover {
  background: #e65100;
}

.report-preview { margin-top: 12px; }
@media (max-width: 900px) { .report-controls .control { min-width: 45%; } }
@media (max-width: 480px) { .report-controls .control { min-width: 100%; } }

/* Custom Table Design matching User Image & Cocina Style (Squared/Grid) */
.custom-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #e0e0e0; /* Outer border */
}
.custom-table th {
  background-color: #f1af32;
  color: white;
  text-transform: uppercase;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 8px 12px;
  text-align: left;
  border: 1px solid #e6a830; /* Header grid border */
}
.custom-table td {
  padding: 8px 12px;
  border: 1px solid #e0e0e0; /* Cell grid border */
  font-size: 0.9rem;
  vertical-align: middle;
}
.custom-table tr:hover {
  background-color: #fffdf5;
}
.fw-bold { font-weight: 700; color: #333; }
.text-muted { color: #666; }

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 0; /* Squared badges */
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  min-width: 80px;
  text-align: center;
}
/* Alternate Theme for Pedidos Tab */
.theme-teal th {
  background-color: #00897b;
  border-color: #00695c;
}
.status-pendiente { background-color: #fff3e0; color: #ff9800; }
.status-preparando { background-color: #e3f2fd; color: #1976d2; }
.status-completado { background-color: #e8f5e9; color: #388e3c; }
.status-cancelado { background-color: #ffebee; color: #d32f2f; }
.status-default { background-color: #f5f5f5; color: #616161; }
</style>
