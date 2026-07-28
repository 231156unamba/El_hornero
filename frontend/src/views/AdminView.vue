<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import '../styles/admin.css';

// ── Componentes del dashboard y secciones ──
import DashboardPanel       from '../components/admin/DashboardPanel.vue';
import MenuManagement       from '../components/admin/MenuManagement.vue';
import UsuariosManagement   from '../components/admin/UsuariosManagement.vue';
import PedidosTable         from '../components/admin/PedidosTable.vue';
import ReportesSection      from '../components/admin/ReportesSection.vue';
import CajaControl          from '../components/admin/CajaControl.vue';
import DiscountModal        from '../components/admin/DiscountModal.vue';
import AddButton            from '../components/Crud/AddButton.vue';
import CreateUser           from '../components/Users/CreateUser.vue';
import UpdateUser           from '../components/Users/UpdateUser.vue';
import CreateMenu           from '../components/Menu/CreateMenu.vue';
import UpdateMenu           from '../components/Menu/UpdateMenu.vue';

const router = useRouter();

// ── BroadcastChannel para notificar cambios de menú ──────────
const menuChannel = new BroadcastChannel('menu-updates');

const notifyMenuChanged = () => {
  menuChannel.postMessage({ type: 'menu-changed' });
};

// ── Navegación ──────────────────────────────────────────────
const activeTab = ref('dashboard');

const setTab = async (t) => {
  activeTab.value = t;
  if (t === 'menu')         loadMenu();
  if (t === 'usuarios')     loadUsuarios();
  if (t === 'pedidos')      loadPedidos();
  if (t === 'reportes') {
    if (!usuarios.value.length) await loadUsuarios();
    meseros.value = usuarios.value.filter(u => (u.tipo || '').toLowerCase() === 'pedido');
  }
  if (t === 'caja_control') {
    await loadCajaConfig();
    await loadCierreAdmin();
  }
};

// ── Auth ────────────────────────────────────────────────────
onMounted(() => {
  const rol = localStorage.getItem('rol');
  if (rol !== 'admin') {
    router.push('/login');
    return;
  }
  // Precarga mínima: menú y usuarios (el dashboard se carga solo)
  loadMenu();
  loadUsuarios();
});

const logout = () => {
  localStorage.clear();
  router.push('/login');
};

// ── Menú ────────────────────────────────────────────────────
const menu          = ref([]);
const selectedItem  = ref(null);
const showCreateMenuModal  = ref(false);
const showUpdateMenuModal  = ref(false);

const loadMenu = async () => {
  const r = await api.get('/menu', { params: { admin: '1' } });
  menu.value = r.data;
};

const editItem = (item) => {
  selectedItem.value = item;
  showUpdateMenuModal.value = true;
};

const deleteItem = async (item) => {
  const nombre = item?.nombre || String(item?.id || '');
  if (!confirm(`¿Está seguro de eliminar "${nombre}"?`)) return;
  await api.delete(`/menu/${item.id}`);
  loadMenu();
  notifyMenuChanged();
};

const toggleItem = async (item) => {
  await api.patch(`/menu/${item.id}/toggle`);
  loadMenu();
  notifyMenuChanged();
};

// ── Ofertas/Descuentos ──────────────────────────────────────
const showDiscountModal = ref(false);
const discountItem      = ref(null);
const discountForm      = ref({ discount_percentage: '', discount_expires_at: '' });

const openDiscountModal = (item) => {
  discountItem.value = item;
  discountForm.value.discount_percentage = item.discount_percentage || '';
  discountForm.value.discount_expires_at = item.discount_expires_at
    ? item.discount_expires_at.split(' ')[0] : '';
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
    if (discountForm.value.discount_expires_at)
      fd.append('discount_expires_at', discountForm.value.discount_expires_at);
    fd.append('_method', 'PUT');
    const r = await api.post(`/menu/${discountItem.value.id}`, fd);
    if (!r.data?.success) { alert(r.data?.error || 'Error al guardar la oferta'); return; }
    await loadMenu();
    notifyMenuChanged();
    closeDiscountModal();
    alert('Oferta guardada correctamente');
  } catch (err) {
    console.error(err);
    alert('Error al guardar la oferta');
  }
};

// ── Usuarios ────────────────────────────────────────────────
const usuarios          = ref([]);
const meseros           = ref([]);
const selectedUser      = ref(null);
const showCreateUserModal = ref(false);
const showUpdateUserModal = ref(false);
const loadedUsuarios    = ref(false);

const loadUsuarios = async () => {
  const r = await api.get('/admin/usuarios');
  usuarios.value  = r.data;
  loadedUsuarios.value = true;
};

const editUsuario = (u) => {
  selectedUser.value = u;
  showUpdateUserModal.value = true;
};

const deleteUsuario = async (u) => {
  const nombre = ((u.nombres || '') + ' ' + (u.apellidos || '')).trim() || u.usuario || String(u.id);
  if (!confirm(`¿Está seguro que desea eliminar al usuario ${nombre}?`)) return;
  await api.delete(`/admin/usuarios/${u.id}`);
  loadUsuarios();
};

// ── Pedidos ─────────────────────────────────────────────────
const pedidos       = ref([]);
const loadedPedidos = ref(false);

const loadPedidos = async () => {
  const r = await api.get('/pedidos');
  pedidos.value     = r.data;
  loadedPedidos.value = true;
};

// ── Control de Caja ─────────────────────────────────────────
const cajaForm    = ref({ nombre_comercial: '', ruc: '', direccion: '', telefono: '', yape_numero: '' });
const cierreAdmin = ref({ from: '', to: '', data: [] });

const loadCajaConfig = async () => {
  const r = await api.get('/admin/caja/config');
  const d = r.data || {};
  cajaForm.value = {
    nombre_comercial: d.nombre_comercial || '',
    ruc:              d.ruc              || '',
    direccion:        d.direccion        || '',
    telefono:         d.telefono         || '',
    yape_numero:      d.yape_numero      || '',
  };
};

const submitCajaConfig = async () => {
  const fd = new FormData();
  Object.entries(cajaForm.value).forEach(([k, v]) => fd.append(k, v));
  const r = await api.post('/admin/caja/config', fd);
  if (r.data?.success) {
    alert('Configuración guardada.');
    await loadCajaConfig();
  } else {
    alert('No se pudo guardar la configuración.');
  }
};

const loadCierreAdmin = async () => {
  const params = {};
  if (cierreAdmin.value.from) params.from = cierreAdmin.value.from;
  if (cierreAdmin.value.to)   params.to   = cierreAdmin.value.to;
  const r = await api.get('/admin/reportes/recibos-entregados', { params });
  cierreAdmin.value.data = r.data || [];
};

const exportCierreAdminPDF = () => {
  const w = window.open('', '_blank');
  let html = '<html><head><title>Cierre de Caja</title><style>body{font-family:Arial,sans-serif;padding:16px;}h1{font-size:20px;margin:0 0 12px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:8px;font-size:12px;}th{background:#f4f4f4;}</style></head><body>';
  html += '<h1>Cierre de Caja</h1><table><thead><tr><th>ID</th><th>Número</th><th>Tipo</th><th>Subtotal</th><th>IGV</th><th>Total</th><th>Fecha</th><th>Método</th></tr></thead><tbody>';
  cierreAdmin.value.data.forEach(r => {
    html += `<tr><td>${r.id}</td><td>${r.numero}</td><td>${r.tipo}</td><td>S/. ${Number(r.subtotal||0).toFixed(2)}</td><td>S/. ${Number(r.igv||0).toFixed(2)}</td><td>S/. ${Number(r.total||0).toFixed(2)}</td><td>${r.fecha}</td><td>${r.metodo_pago||'-'}</td></tr>`;
  });
  html += '</tbody></table></body></html>';
  w.document.write(html); w.document.close(); w.focus(); w.print();
};
</script>

<template>
  <div class="admin-page">

    <!-- Topbar -->
    <header class="topbar">
      <div class="logo">
        <h1>EL HORNERO</h1>
      </div>
      <div class="user-actions">
        <div class="user-profile">
          <div class="user-avatar">A</div>
          <span>Administrador</span>
        </div>
        <button class="logout-btn" @click="logout">Cerrar Sesión</button>
      </div>
    </header>

    <main class="content-area">

      <!-- Navbar -->
      <nav class="admin-navbar">
        <button :class="['nav-btn', activeTab==='dashboard'    ? 'active' : '']" @click="setTab('dashboard')">Dashboard</button>
        <button :class="['nav-btn', activeTab==='pedidos'      ? 'active' : '']" @click="setTab('pedidos')">Pedidos de hoy</button>
        <button :class="['nav-btn', activeTab==='menu'         ? 'active' : '']" @click="setTab('menu')">Menú</button>
        <button :class="['nav-btn', activeTab==='usuarios'     ? 'active' : '']" @click="setTab('usuarios')">Usuarios</button>
        <button :class="['nav-btn', activeTab==='caja_control' ? 'active' : '']" @click="setTab('caja_control')">Control de Caja</button>
        <button :class="['nav-btn', activeTab==='reportes'     ? 'active' : '']" @click="setTab('reportes')">Reportes</button>
      </nav>

      <!-- ── Dashboard ── -->
      <section v-if="activeTab==='dashboard'" class="content-section" style="padding: 24px 28px;">
        <DashboardPanel />
      </section>

      <!-- ── Menú ── -->
      <section v-if="activeTab==='menu'" class="content-section" style="padding: 20px 32px;">
        <h2 class="section-title">Gestión del Menú</h2>
        <AddButton label="Agregar nuevo artículo" @click="showCreateMenuModal = true" />
        <MenuManagement
          :menu="menu"
          @edit="editItem"
          @delete="deleteItem"
          @toggle="toggleItem"
          @discount="openDiscountModal"
        />
      </section>

      <!-- ── Pedidos ── -->
      <section v-if="activeTab==='pedidos' && loadedPedidos" class="content-section" style="padding: 20px 32px;">
        <h2 class="section-title">Pedidos de hoy</h2>
        <PedidosTable :pedidos="pedidos" />
      </section>
      <div v-if="activeTab==='pedidos' && !loadedPedidos" class="loading">Cargando...</div>

      <!-- ── Usuarios ── -->
      <section v-if="activeTab==='usuarios' && loadedUsuarios" class="content-section" style="padding: 20px 32px;">
        <h2 class="section-title">Usuarios</h2>
        <AddButton label="Agregar nuevo usuario" @click="showCreateUserModal = true" />
        <UsuariosManagement
          :usuarios="usuarios"
          @edit="editUsuario"
          @delete="deleteUsuario"
        />
      </section>
      <div v-if="activeTab==='usuarios' && !loadedUsuarios" class="loading">Cargando...</div>

      <!-- ── Reportes ── -->
      <section v-if="activeTab==='reportes'" class="content-section" style="padding: 20px 32px;">
        <h2 class="section-title">Reportes</h2>
        <ReportesSection :meseros="meseros" />
      </section>

      <!-- ── Control de Caja ── -->
      <section v-if="activeTab==='caja_control'" class="content-section" style="padding: 20px 32px;">
        <h2 class="section-title">Control de Caja</h2>
        <CajaControl
          :caja-form="cajaForm"
          :cierre-admin="cierreAdmin"
          @submit="submitCajaConfig"
          @update:caja-form="cajaForm = $event"
          @update:cierre-admin="cierreAdmin = $event"
          @export="exportCierreAdminPDF"
        />
      </section>

    </main>
  </div>

  <!-- ── Modales globales ── -->

  <DiscountModal
    :show="showDiscountModal"
    :discount-item="discountItem"
    :discount-form="discountForm"
    @close="closeDiscountModal"
    @save="submitDiscount"
    @update:discount-form="discountForm = $event"
  />

  <CreateUser
    :show="showCreateUserModal"
    @success="loadUsuarios"
    @close="showCreateUserModal = false"
  />
  <UpdateUser
    :show="showUpdateUserModal"
    :user="selectedUser || {}"
    @success="loadUsuarios"
    @close="showUpdateUserModal = false"
  />

  <CreateMenu
    :show="showCreateMenuModal"
    @success="() => { loadMenu(); notifyMenuChanged(); }"
    @close="showCreateMenuModal = false"
  />
  <UpdateMenu
    :show="showUpdateMenuModal"
    :item="selectedItem || {}"
    @success="() => { loadMenu(); notifyMenuChanged(); }"
    @close="showUpdateMenuModal = false"
  />
</template>
