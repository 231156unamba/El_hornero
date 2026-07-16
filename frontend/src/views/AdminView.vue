<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import '../styles/admin.css';
import DashboardStats from '../components/admin/DashboardStats.vue';
import GraficasPanel from '../components/admin/GraficasPanel.vue';
import PedidosRecientes from '../components/admin/PedidosRecientes.vue';
import VentasMesSummary from '../components/admin/VentasMesSummary.vue';
import MenuManagement from '../components/admin/MenuManagement.vue';
import MenuForm from '../components/admin/MenuForm.vue';
import UsuariosManagement from '../components/admin/UsuariosManagement.vue';
import UsuarioForm from '../components/admin/UsuarioForm.vue';
import PedidosTable from '../components/admin/PedidosTable.vue';
import ReportesSection from '../components/admin/ReportesSection.vue';
import CajaControl from '../components/admin/CajaControl.vue';
import DiscountModal from '../components/admin/DiscountModal.vue'

const router = useRouter();
const apiOrigin = new URL(api.defaults.baseURL).origin;
const stats = ref({ pedidosHoy: 0, ventasHoy: 0, totalClientes: 0, ventasMes: 0, recibosHoy: 0 });
const recientes = ref([]);
const form = ref({ id: null, nombre: '', precio: '', categoria: 'comida', descripcion: '' });

// Discount modal state
const showDiscountModal = ref(false);
const discountItem = ref(null);
const discountForm = ref({ discount_percentage: '', discount_expires_at: '' });
const menu = ref([]);
const editing = ref(false);
const activeTab = ref('dashboard');
const usuarios = ref([]);
const pedidos = ref([]);
const pedidosDiarios = ref({ labels: [], datasets: [] });
const pedidosMensuales = ref({ labels: [], datasets: [] });
const pedidosAnuales = ref({ labels: [], datasets: [] });
const pagosMetodo = ref({ labels: [], datasets: [] });
const reportType = ref('pedidos');
const reportFilters = ref({ from: '', to: '', mesa: '', mesero_id: '', tipo: '', costo_min: '', costo_max: '' });
const reportData = ref([]);
const meseros = ref([]);
const todayLabel = computed(() => new Date().toLocaleDateString('es-PE', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric'
}));
const loaded = ref({ stats: false, recientes: false, usuarios: false, pedidos: false });
const userForm = ref({ id: null, usuario: '', nombres: '', apellidos: '', clave: '', tipo: 'pedido' });
const userEditing = ref(false);
const imagenFile = ref(null);
const currentImageUrl = ref(null);
const imagenName = ref('');

const onImageChange = (e) => {
  const files = e?.target?.files;
  const f = files && files[0] ? files[0] : null;
  imagenFile.value = f;
  imagenName.value = f ? f.name : '';
};

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
    loadPagosMetodo();
  }
});

const loadStats = async () => {
  const r = await api.get('/admin/stats');
  stats.value = r.data;
  loaded.value.stats = true;
};
const loadRecientes = async () => {
  const r = await api.get('/admin/recientes');
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
const deleteUsuario = async (u) => {
  const nombre = ((u.nombres || '') + ' ' + (u.apellidos || '')).trim() || u.usuario || String(u.id);
  const ok = confirm(`¿Está seguro que desea eliminar al usuario ${nombre}?`);
  if (!ok) return;
  await api.delete(`/admin/usuarios/${u.id}`);
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
const loadPagosMetodo = async () => {
  const r = await api.get('/admin/pagos-por-metodo');
  const labels = r.data.map(i => i.label || 'Sin dato');
  const values = r.data.map(i => Number(i.value) || 0);
  if (!labels.length || values.every(v => v === 0)) {
    pagosMetodo.value = { labels: [], datasets: [] };
  } else {
    pagosMetodo.value = {
      labels,
      datasets: [{
        label: 'Ventas por método de pago',
        data: values,
        backgroundColor: ['#ef6c00', '#2e7d32', '#1565c0', '#8e24aa', '#c62828']
      }]
    };
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
    loadPagosMetodo();
  }
  if (t === 'usuarios') loadUsuarios();
  if (t === 'pedidos') loadPedidos();
  if (t === 'reportes') loadReport();
  if (t === 'reportes') {
    if (!usuarios.value.length) await loadUsuarios();
    meseros.value = usuarios.value.filter(u => (u.tipo || '').toLowerCase() === 'pedido');
  }
  if (t === 'caja_control') {
    await loadCajaConfig();
    await loadCierreAdmin();
  }
};
const submitMenu = async () => {
  try {
    if (!editing.value && !imagenFile.value) {
      alert('Selecciona una imagen para el plato.');
      return;
    }
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
        const msg = r.data?.error || 'No se pudo actualizar el plato.';
        alert(msg);
        return;
      }
    } else {
      const r = await api.post('/menu', fd);
      if (!r.data?.success) {
        const msg = r.data?.error || 'No se pudo crear el plato.';
        alert(msg);
        return;
      }
    }
    clearForm();
    await loadMenu();
    alert('Plato guardado correctamente.');
  } catch (err) {
    const errors = err?.response?.data?.errors;
    if (errors) {
      const first = Object.values(errors)[0];
      const msg = Array.isArray(first) ? first[0] : String(first || '');
      alert(msg || 'Error de validación.');
      return;
    }
    const msg = err?.response?.data?.error || err?.response?.data?.message || 'Ocurrió un error al guardar el plato.';
    alert(msg);
  }
};
const editItem = (item) => {
  editing.value = true;
  form.value = {
    id: item.id,
    nombre: item.nombre,
    precio: item.precio,
    categoria: item.categoria || 'comida',
    descripcion: item.descripcion,
  };
  imagenFile.value = null;
  currentImageUrl.value = menuImageUrl(item) || null;
};
const deleteItem = async (item) => {
  const nombre = item?.nombre || String(item?.id || '');
  const ok = confirm(`¿Está seguro de eliminar "${nombre}"?`);
  if (!ok) return;
  await api.delete(`/menu/${item.id}`);
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

// Discount management
const openDiscountModal = (item) => {
  discountItem.value = item;
  discountForm.value.discount_percentage = item.discount_percentage || '';
  discountForm.value.discount_expires_at = item.discount_expires_at ? item.discount_expires_at.split(' ')[0] : '';
  showDiscountModal.value = true;
};

const closeDiscountModal = () => {
  showDiscountModal.value = false;
  discountItem.value = null;
  discountForm.value = { discount_percentage: '', discount_expires_at: '' };
};

const submitDiscount = async () => {
  try {
    const fd = new FormData();
    fd.append('discount_percentage', discountForm.value.discount_percentage);
    if (discountForm.value.discount_expires_at) {
      fd.append('discount_expires_at', discountForm.value.discount_expires_at);
    }
    fd.append('_method', 'PUT');
    const r = await api.post(`/menu/${discountItem.value.id}`, fd);
    if (!r.data?.success) {
      alert(r.data?.error || 'Error al guardar la oferta');
      return;
    }
    await loadMenu();
    closeDiscountModal();
    alert('Oferta guardada correctamente');
  } catch (err) {
    console.error(err);
    alert('Error al guardar la oferta');
  }
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
    if (reportFilters.value.mesa) params.mesa = reportFilters.value.mesa;
    if (reportFilters.value.costo_min) params.costo_min = reportFilters.value.costo_min;
    if (reportFilters.value.costo_max) params.costo_max = reportFilters.value.costo_max;
    const r = await api.get('/admin/reportes/pedidos', { params });
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

const formatCurrency = (value) => `S/. ${Number(value || 0).toFixed(2)}`;

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
    'recibos_entregados': 'Reporte por Costo'
  };
  const title = titleMap[reportType.value] || 'Reporte';
  let html = '<html><head><title>'+title+'</title><style>';
  html += 'body{font-family:Arial,sans-serif;padding:16px;} h1{font-size:20px;margin:0 0 12px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:8px;font-size:12px;} th{background:#f4f4f4;}';
  html += '</style></head><body>';
  html += '<h1>'+title+'</h1>';
  if (reportType.value === 'pedidos' || reportType.value === 'pedidos_mesero' || reportType.value === 'recibos_entregados') {
    html += '<table><thead><tr><th>ID</th><th>Mesa</th><th>Mesero</th><th>Fecha</th><th>Estado</th><th>Costo</th><th>Detalle</th></tr></thead><tbody>';
    reportData.value.forEach(p => {
      html += '<tr><td>'+p.id+'</td><td>'+p.mesa+'</td><td>'+(p.mesero||'')+'</td><td>'+p.fecha+'</td><td>'+p.estado+'</td><td>S/. '+Number(p.costo||0).toFixed(2)+'</td><td>'+p.detalle+'</td></tr>';
    });
    html += '</tbody></table>';
  }
  html += '</body></html>';
  w.document.write(html);
  w.document.close();
  w.focus();
  w.print();
};

const cajaForm = ref({ nombre_comercial: '', ruc: '', direccion: '', telefono: '', yape_numero: '' });
const yapeQrFile = ref(null);
const onYapeQrChange = (e) => {
  const files = e?.target?.files;
  yapeQrFile.value = files && files[0] ? files[0] : null;
};
const loadCajaConfig = async () => {
  const r = await api.get('/admin/caja/config');
  const d = r.data || {};
  cajaForm.value = {
    nombre_comercial: d.nombre_comercial || '',
    ruc: d.ruc || '',
    direccion: d.direccion || '',
    telefono: d.telefono || '',
    yape_numero: d.yape_numero || ''
  };
};
const submitCajaConfig = async () => {
  const fd = new FormData();
  fd.append('nombre_comercial', cajaForm.value.nombre_comercial);
  fd.append('ruc', cajaForm.value.ruc);
  fd.append('direccion', cajaForm.value.direccion);
  fd.append('telefono', cajaForm.value.telefono);
  fd.append('yape_numero', cajaForm.value.yape_numero);
  if (yapeQrFile.value) fd.append('qr', yapeQrFile.value);
  const r = await api.post('/admin/caja/config', fd);
  if (r.data?.success) {
    alert('Configuración de caja guardada.');
    await loadCajaConfig();
  } else {
    alert('No se pudo guardar la configuración.');
  }
};
const cierreAdmin = ref({ from: '', to: '', data: [] });
const loadCierreAdmin = async () => {
  const params = {};
  if (cierreAdmin.value.from) params.from = cierreAdmin.value.from;
  if (cierreAdmin.value.to) params.to = cierreAdmin.value.to;
  const r = await api.get('/admin/reportes/recibos-entregados', { params });
  cierreAdmin.value.data = r.data || [];
};
const exportCierreAdminPDF = () => {
  const w = window.open('', '_blank');
  let html = '<html><head><title>Cierre de Caja</title><style>';
  html += 'body{font-family:Arial,sans-serif;padding:16px;} h1{font-size:20px;margin:0 0 12px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:8px;font-size:12px;} th{background:#f4f4f4;}';
  html += '</style></head><body>';
  html += '<h1>Cierre de Caja</h1>';
  html += '<table><thead><tr><th>ID</th><th>Número</th><th>Tipo</th><th>Subtotal</th><th>IGV</th><th>Total</th><th>Fecha</th><th>Metodo Pago</th></tr></thead><tbody>';
  cierreAdmin.value.data.forEach(r => {
    html += '<tr><td>'+r.id+'</td><td>'+r.numero+'</td><td>'+r.tipo+'</td><td>S/. '+Number(r.subtotal||0).toFixed(2)+'</td><td>S/. '+Number(r.igv||0).toFixed(2)+'</td><td>S/. '+Number(r.total||0).toFixed(2)+'</td><td>'+r.fecha+'</td><td>'+(r.metodo_pago||'-')+'</td></tr>';
  });
  html += '</tbody></table>';
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
        <button :class="['tab-btn', activeTab==='pedidos'?'active':'']" @click="setTab('pedidos')">Pedidos de hoy</button>
        <button :class="['tab-btn', activeTab==='usuarios'?'active':'']" @click="setTab('usuarios')">Usuarios</button>
        <button :class="['tab-btn', activeTab==='reportes'?'active':'']" @click="setTab('reportes')">Reportes</button>
        <button :class="['tab-btn', activeTab==='caja_control'?'active':'']" @click="setTab('caja_control')">Control de Caja</button>
      </div>

      <section v-if="activeTab==='dashboard' && loaded.stats" class="dashboard-shell">
        <div class="dashboard-hero">
          <div>
            <p class="eyebrow">Panel administrativo</p>
            <h2>Resumen del día</h2>
            <p>Monitorea pedidos, ventas y actividad del restaurante desde una sola vista.</p>
          </div>
          <div class="hero-badge">
            <span>Hoy</span>
            <strong>{{ todayLabel }}</strong>
          </div>
        </div>

        <DashboardStats :stats="stats" />

        <GraficasPanel 
          :pedidos-diarios="pedidosDiarios"
          :pedidos-mensuales="pedidosMensuales"
          :pedidos-anuales="pedidosAnuales"
          :pagos-metodo="pagosMetodo"
        />

        <div class="dashboard-grid secondary-grid">
          <PedidosRecientes :recientes="recientes" />
          <VentasMesSummary :ventas-mes="stats.ventasMes" />
        </div>
      </section>
      <div v-if="activeTab==='dashboard' && !loaded.stats" class="loading">Cargando...</div>

      <section v-if="activeTab==='menu'" class="content-section" style="padding: 20px 50px;">
        <h2 class="section-title">Gestión del Menú</h2>
        <MenuForm 
          :form="form" 
          :editing="editing" 
          :current-image-url="currentImageUrl" 
          :imagen-name="imagenName"
          @submit="submitMenu"
          @clear="clearForm"
          @update:form="form = $event"
          @image-change="onImageChange"
        />
        <MenuManagement 
          :menu="menu" 
          @edit="editItem" 
          @delete="deleteItem" 
          @discount="openDiscountModal" 
        />
      </section>

      <section v-if="activeTab==='pedidos' && loaded.pedidos" class="content-section" style="padding: 20px 40px;">
        <h2 class="section-title">Pedidos</h2>
        <PedidosTable :pedidos="pedidos" />
      </section>
      <div v-if="activeTab==='pedidos' && !loaded.pedidos" class="loading">Cargando...</div>

      <section v-if="activeTab==='usuarios' && loaded.usuarios" class="content-section" style="padding: 20px 50px;">
        <h2 class="section-title">Usuarios</h2>
        <UsuarioForm 
          :user-form="userForm" 
          :user-editing="userEditing" 
          @submit="submitUsuario"
          @clear="clearUsuarioForm"
          @update:user-form="userForm = $event"
        />
        <UsuariosManagement 
          :usuarios="usuarios" 
          @edit="editUsuario" 
          @delete="deleteUsuario" 
        />
      </section>
      <div v-if="activeTab==='usuarios' && !loaded.usuarios" class="loading">Cargando...</div>

      <section v-if="activeTab==='reportes'" class="content-section" style="padding: 20px 40px;">
        <h2 class="section-title">Reportes</h2>
        <ReportesSection 
          :report-type="reportType"
          :report-filters="reportFilters"
          :report-data="reportData"
          :meseros="meseros"
          @update:report-type="reportType = $event"
          @update:report-filters="reportFilters = $event"
          @export="exportReportPDF"
        />
      </section>
      <section v-if="activeTab==='caja_control'" class="content-section" style="padding: 20px 40px;">
        <h2 class="section-title">Control de Caja</h2>
        <CajaControl 
          :caja-form="cajaForm"
          :cierre-admin="cierreAdmin"
          @submit="submitCajaConfig"
          @update:caja-form="cajaForm = $event"
          @update:cierre-admin="cierreAdmin = $event"
          @export="exportCierreAdminPDF"
        />
        <div class="report-preview">
          <table class="custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>NÚMERO</th>
                <th>RECIBO</th>
                <th>MESA</th>
                <th>DETALLE</th>
                <th>TIPO</th>
                <th>SUBTOTAL</th>
                <th>IGV</th>
                <th>TOTAL</th>
                <th>FECHA</th>
                <th>TIPO PAGO</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in cierreAdmin.data" :key="r.id">
                <td>{{ r.id }}</td>
                <td>{{ r.numero }}</td>
                <td>{{ r.tipo }}</td>
                <td>{{ r.mesa }}</td>
                <td style="font-size: 0.85rem;">{{ r.detalle }}</td>
                <td>{{ r.tipo }}</td>
                <td>S/. {{ Number(r.subtotal||0).toFixed(2) }}</td>
                <td>S/. {{ Number(r.igv||0).toFixed(2) }}</td>
                <td>S/. {{ Number(r.total||0).toFixed(2) }}</td>
                <td>{{ r.fecha }}</td>
                <td>{{ r.metodo_pago }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

    <!-- Discount Modal -->
    <DiscountModal 
      :show="showDiscountModal"
      :discount-item="discountItem"
      :discount-form="discountForm"
      @close="closeDiscountModal"
      @save="submitDiscount"
      @update:discount-form="discountForm = $event"
    />
</template>
