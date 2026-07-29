<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import SessionGuard from '../components/common/SessionGuard.vue';
import UserMenu from '../components/common/UserMenu.vue';

const router = useRouter();
const pedidos           = ref([]);
const pedidosAnteriores = ref([]);
const avisos            = ref([]);
const mesasOcupadas     = ref([]);
const mesas             = Array.from({ length: 10 }, (_, i) => i + 1);
const mesaSeleccionada  = ref('');
const menu              = ref([]);
const platoSeleccionado      = ref('');
const cantidad               = ref(1);
const bebidaSeleccionada     = ref('');
const cantidadBebida         = ref(1);
const promocionSeleccionada  = ref('');
const cantidadPromocion      = ref(1);
const carrito                = ref([]);
const ajustePrecio           = ref(null);
const ajusteDescripcion      = ref('');
const tipoServicio           = ref('local');
const apiOrigin = new URL(api.defaults.baseURL).origin;

// ── Filtros de categoría (valores exactos de la BD) ──────────
const platosMenu     = computed(() => menu.value.filter(p => (p.categoria || '').toLowerCase() === 'comida'));
const bebidasMenu    = computed(() => menu.value.filter(p => (p.categoria || '').toLowerCase() === 'bebida'));
const promocionesMenu= computed(() => menu.value.filter(p => (p.categoria || '').toLowerCase() === 'promocion'));

// ── Sonido de aviso ──────────────────────────────────────────
const reproducirSonido = () => {
  try {
    const ctx  = new (window.AudioContext || window.webkitAudioContext)();
    const osc  = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'sine';
    osc.frequency.value = 800;
    gain.gain.value = 0.5;
    osc.start();
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
    osc.stop(ctx.currentTime + 0.5);
  } catch (e) { console.error(e); }
};

const mostrarAviso = (msg) => {
  avisos.value.push(msg);
  setTimeout(() => avisos.value.shift(), 5000);
};

const menuChannel = new BroadcastChannel('menu-updates');

onMounted(async () => {
  if (localStorage.getItem('rol') !== 'pedido') { router.push('/login'); return; }
  await fetchMenu();
  await fetchPedidos();
  await fetchMesasOcupadas();
  pedidosAnteriores.value = [...pedidos.value];
  setInterval(() => {
    fetchPedidos();
    fetchMesasOcupadas();
  }, 5000);
  menuChannel.addEventListener('message', (e) => {
    if (e.data.type === 'menu-changed') fetchMenu();
  });
});

onUnmounted(() => menuChannel.close());

const fetchMenu = async () => {
  try {
    const r = await api.get('/menu');
    menu.value = r.data;
  } catch (e) { console.error(e); }
};

const fetchPedidos = async () => {
  try {
    const r = await api.get('/pedidos');
    const nuevos = r.data;
    for (const p of nuevos) {
      const ant = pedidosAnteriores.value.find(x => x.id === p.id);
      if (ant && ant.estado !== 'preparado' && p.estado === 'preparado') {
        reproducirSonido();
        mostrarAviso(`¡Pedido listo! Mesa ${p.mesa}`);
      }
    }
    pedidos.value = nuevos;
    pedidosAnteriores.value = [...nuevos];
  } catch (e) { console.error(e); }
};

const fetchMesasOcupadas = async () => {
  try {
    const r = await api.get('/pedidos/mesas-ocupadas');
    mesasOcupadas.value = r.data;
  } catch (e) { console.error(e); }
};

// ── Helpers de carrito ───────────────────────────────────────
const agregarAlCarrito = (idRef, cantRef) => {
  const item = menu.value.find(p => p.id === idRef.value);
  if (!item) return;
  carrito.value.push({ ...item, cantidad: cantRef.value });
  idRef.value   = '';
  cantRef.value = 1;
};

const agregarTarjeta   = () => agregarAlCarrito(platoSeleccionado,     cantidad);
const agregarBebida    = () => agregarAlCarrito(bebidaSeleccionada,    cantidadBebida);
const agregarPromocion = () => agregarAlCarrito(promocionSeleccionada, cantidadPromocion);

const eliminarDelCarrito = (idx) => carrito.value.splice(idx, 1);

const getDiscountedPrice = (item) =>
  item.discount_percentage
    ? parseFloat(item.precio) * (1 - item.discount_percentage / 100)
    : parseFloat(item.precio);

const total = computed(() => {
  let s = carrito.value.reduce((a, i) => a + getDiscountedPrice(i) * i.cantidad, 0);
  if (ajustePrecio.value) s += parseFloat(ajustePrecio.value);
  return s.toFixed(2);
});

// ── Imagen ───────────────────────────────────────────────────
const menuImageUrl = (obj) => {
  if (!obj) return '';
  if (obj.imagen_url) return obj.imagen_url;
  const img = obj.imagen;
  if (!img) return '';
  if (/^https?:\/\//i.test(img)) return img;
  if (img.startsWith('/')) return apiOrigin + img;
  return `${apiOrigin}/images/menu/${img}`;
};

const buscarMenuPorNombre = (nombre) => {
  const n = (nombre || '').trim().toLowerCase();
  return menu.value.find(m => (m.nombre || '').trim().toLowerCase() === n) || null;
};

const itemsDePedido = (p) =>
  String(p.detalle || '').split(',').map(s => {
    const m = s.trim().match(/^\s*(\d+)\s*x\s*(.+)$/i);
    const nombre = m ? String(m[2]).replace(/\(.*$/, '').trim() : String(s).replace(/\(.*$/, '').trim();
    return buscarMenuPorNombre(nombre);
  }).filter(Boolean);

// ── Crear pedido ─────────────────────────────────────────────
const crearPedido = async () => {
  if (!mesaSeleccionada.value || carrito.value.length === 0) return;
  let detalle = carrito.value.map(i => `${i.cantidad}x ${i.nombre}`).join(', ');
  if (ajustePrecio.value) detalle += ` (Ajuste: ${ajusteDescripcion.value} S/.${ajustePrecio.value})`;

  try {
    const r = await api.post('/pedidos', {
      mesa:          mesaSeleccionada.value,
      detalle,
      tipo_servicio: tipoServicio.value,
    });
    if (r.data.success) {
      mesaSeleccionada.value     = '';
      carrito.value              = [];
      ajustePrecio.value         = null;
      ajusteDescripcion.value    = '';
      fetchPedidos();
      fetchMesasOcupadas();
      alert('Pedido enviado a cocina correctamente');
    } else { alert('Error al crear pedido'); }
  } catch (e) { console.error(e); alert('Error de conexión'); }
};

const cancelarPedido = async (p) => {
  if (String(p.estado).toLowerCase() !== 'pedido') {
    alert('Solo se puede cancelar pedidos en estado pedido.'); return;
  }
  if (!confirm(`¿Cancelar pedido de la mesa ${p.mesa}?`)) return;
  try {
    const r = await api.delete(`/pedidos/${p.id}`);
    if (r.data?.success) { fetchPedidos(); alert('Pedido cancelado.'); }
    else alert(r.data?.error || 'No se pudo cancelar el pedido.');
  } catch (e) {
    console.error(e);
    alert(e.response?.data?.error || 'Error al cancelar el pedido.');
  }
};

const marcarEntregado = async (p) => {
  try {
    await api.post('/pedidos/actualizar', { id: p.id, estado: 'entregado' });
    const idx = pedidos.value.findIndex(x => x.id === p.id);
    if (idx !== -1) {
      pedidos.value[idx] = { ...pedidos.value[idx], estado: 'entregado' };
      pedidosAnteriores.value = [...pedidos.value];
    }
  } catch (e) {
    console.error(e);
    alert(e.response?.data?.error || 'No se pudo marcar el pedido como entregado.');
  }
};

// ── Clase de color del order-card ────────────────────────────
const orderCardClass = (estado) => {
  const e = (estado || '').toLowerCase();
  if (e === 'pedido')    return 'oc-pedido';
  if (e === 'cocinando') return 'oc-cocinando';
  if (e === 'preparado') return 'oc-preparado';
  if (e === 'entregado') return 'oc-entregado';
  if (e === 'pagado')    return 'oc-pagado';
  return '';
};

const fechaPedidoKey = (valor) => {
  if (!valor) return '';
  const parsed = new Date(valor);
  if (Number.isNaN(parsed.getTime())) return '';
  return `${parsed.getFullYear()}-${String(parsed.getMonth() + 1).padStart(2, '0')}-${String(parsed.getDate()).padStart(2, '0')}`;
};

const pedidosDelDia = computed(() => {
  const hoy = new Date();
  const hoyKey = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
  return pedidos.value.filter(p => fechaPedidoKey(p.fecha || p.created_at || p.createdAt) === hoyKey);
});

const estadoLabel = (estado) => {
  const map = { pedido: 'En cocina', cocinando: 'Cocinando', preparado: '¡LISTO!', entregado: 'Entregado', pagado: 'Pagado' };
  return map[(estado || '').toLowerCase()] ?? (estado || '').toUpperCase();
};

const formatHora = (f) => {
  if (!f) return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  const iso = f.includes(' ') ? f.replace(' ', 'T') + 'Z' : f;
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
  <div class="pedido-layout">

    <!-- Header -->
    <header class="navbar">
      <div class="brand"><h1>EL HORNERO</h1></div>
      <div class="user-control">
        <UserMenu />
      </div>
    </header>

    <div class="main-content">

      <!-- ── Panel izquierdo: Nuevo pedido ── -->
      <section class="order-creation-panel">
        <div class="card">
          <div class="card-header">
            <h2>🧾 Nuevo Pedido</h2>
          </div>
          <div class="card-body">

            <!-- Selección de mesa -->
            <div class="form-group">
              <label>Seleccionar Mesa</label>
              <div class="table-grid-selector">
                <button
                  v-for="m in mesas" :key="m"
                  class="table-btn"
                  :class="{ selected: mesaSeleccionada === m, occupied: mesasOcupadas.includes(m) }"
                  @click="mesaSeleccionada = m"
                >{{ m }}</button>
              </div>
            </div>

            <!-- Tipo de servicio -->
            <div class="form-group">
              <label>Tipo de servicio</label>
              <select v-model="tipoServicio">
                <option value="local">🍽️ Local</option>
                <option value="llevar">🛍️ Para llevar</option>
              </select>
            </div>

            <div class="product-selector-grid">
              <!-- Comida -->
              <div class="product-box">
                <div class="product-box-header">
                  <span class="pbox-icon">🍽️</span>
                  <span class="pbox-title">Comida / Platos</span>
                </div>
                <div class="add-item-grid">
                  <select v-model.number="platoSeleccionado" class="item-select">
                    <option value="">— Seleccionar plato —</option>
                    <option v-for="p in platosMenu" :key="p.id" :value="p.id">
                      {{ p.nombre }} · S/ {{ parseFloat(p.precio).toFixed(2) }}
                    </option>
                  </select>
                  <div class="qty-block">
                    <span class="qty-label">Cantidad</span>
                    <div class="qty-actions">
                      <button type="button" @click="cantidad = Math.max(1, cantidad - 1)">−</button>
                      <input type="number" v-model.number="cantidad" min="1" class="qty-input" />
                      <button type="button" @click="cantidad++">+</button>
                    </div>
                  </div>
                  <button class="btn-add" @click="agregarTarjeta" :disabled="!platoSeleccionado">Añadir</button>
                </div>
              </div>

              <!-- Bebidas -->
              <div class="product-box product-box--bebida">
                <div class="product-box-header">
                  <span class="pbox-icon">🥤</span>
                  <span class="pbox-title">Bebidas</span>
                </div>
                <div class="add-item-grid">
                  <select v-model.number="bebidaSeleccionada" class="item-select">
                    <option value="">— Seleccionar bebida —</option>
                    <option v-for="b in bebidasMenu" :key="b.id" :value="b.id">
                      {{ b.nombre }} · S/ {{ parseFloat(b.precio).toFixed(2) }}
                    </option>
                  </select>
                  <div class="qty-block">
                    <span class="qty-label">Cantidad</span>
                    <div class="qty-actions">
                      <button type="button" @click="cantidadBebida = Math.max(1, cantidadBebida - 1)">−</button>
                      <input type="number" v-model.number="cantidadBebida" min="1" class="qty-input" />
                      <button type="button" @click="cantidadBebida++">+</button>
                    </div>
                  </div>
                  <button class="btn-add btn-add--bebida" @click="agregarBebida" :disabled="!bebidaSeleccionada">Añadir</button>
                </div>
              </div>

              <!-- Promociones -->
              <div class="product-box product-box--promo">
                <div class="product-box-header">
                  <span class="pbox-icon">🎉</span>
                  <span class="pbox-title">Promociones</span>
                </div>
                <div class="add-item-grid">
                  <select v-model.number="promocionSeleccionada" class="item-select">
                    <option value="">— Seleccionar promoción —</option>
                    <option v-for="p in promocionesMenu" :key="p.id" :value="p.id">
                      {{ p.nombre }} · S/ {{ parseFloat(p.precio).toFixed(2) }}
                    </option>
                  </select>
                  <div class="qty-block">
                    <span class="qty-label">Cantidad</span>
                    <div class="qty-actions">
                      <button type="button" @click="cantidadPromocion = Math.max(1, cantidadPromocion - 1)">−</button>
                      <input type="number" v-model.number="cantidadPromocion" min="1" class="qty-input" />
                      <button type="button" @click="cantidadPromocion++">+</button>
                    </div>
                  </div>
                  <button class="btn-add btn-add--promo" @click="agregarPromocion" :disabled="!promocionSeleccionada">Añadir</button>
                </div>
              </div>
            </div>

            <!-- Ajuste de precio — siempre visible -->
            <div class="ajuste-panel">
              <div class="ajuste-panel-header">
                <span>⚙️ Ajuste de precio</span>
                <span class="ajuste-hint">Opcional — usa valores negativos para descuentos</span>
              </div>
              <div class="ajuste-grid">
                <input v-model.number="ajustePrecio" type="number" placeholder="S/ Extra / Descuento" step="0.10" />
                <input v-model="ajusteDescripcion" type="text" placeholder="Motivo (ej. Extra queso)" />
              </div>
            </div>

            <!-- Resumen del carrito -->
            <div class="cart-preview">
              <h3>Resumen del pedido</h3>
              <div v-if="carrito.length === 0" class="empty-cart">No hay ítems en el pedido.</div>
              <ul v-else class="cart-list">
                <li v-for="(item, idx) in carrito" :key="idx">
                  <div class="cart-item-info">
                    <img :src="menuImageUrl(item)" alt="" class="thumb" />
                    <span class="qty">{{ item.cantidad }}×</span>
                    <span class="name">{{ item.nombre }}</span>
                  </div>
                  <div class="cart-item-price">
                    <span v-if="item.discount_percentage" class="price-original">
                      S/ {{ (parseFloat(item.precio) * item.cantidad).toFixed(2) }}
                    </span>
                    S/ {{ (getDiscountedPrice(item) * item.cantidad).toFixed(2) }}
                    <span v-if="item.discount_percentage" class="discount-tag">
                      −{{ item.discount_percentage }}%
                    </span>
                    <button class="btn-remove" @click="eliminarDelCarrito(idx)">×</button>
                  </div>
                </li>
              </ul>
              <div class="cart-total">
                <span>Total estimado</span>
                <span class="amount">S/ {{ total }}</span>
              </div>
            </div>

            <button
              class="btn-submit"
              :disabled="carrito.length === 0 || !mesaSeleccionada"
              @click="crearPedido"
            >🍳 ENVIAR A COCINA</button>

          </div>
        </div>
      </section>

      <!-- ── Panel derecho: Pedidos en curso ── -->
      <section class="active-orders-panel">

        <div class="orders-panel-header">
          <h3>Pedidos en curso</h3>
        </div>

        <!-- Guía de colores -->
        <div class="color-legend">
          <div class="legend-item"><span class="legend-dot dot-pedido"></span>En cocina</div>
          <div class="legend-item"><span class="legend-dot dot-cocinando"></span>Cocinando</div>
          <div class="legend-item"><span class="legend-dot dot-preparado"></span>Listo</div>
          <div class="legend-item"><span class="legend-dot dot-entregado"></span>Entregado</div>
          <div class="legend-item"><span class="legend-dot dot-pagado"></span>Pagado</div>
        </div>

        <div class="orders-list">
          <div
            v-for="p in pedidosDelDia" :key="p.id"
            class="order-card"
            :class="orderCardClass(p.estado)"
          >
            <!-- Advertencia pedido de día anterior -->
            <!-- (oculto: limpieza diaria restaurada) -->

            <div class="order-header">
              <span class="table-badge">Mesa {{ p.mesa }}</span>
              <span class="status-pill">{{ estadoLabel(p.estado) }}</span>
              <span class="time">{{ formatHora(p.fecha) }}</span>
            </div>
            <div class="order-body">
              <p>{{ p.detalle }}</p>
              <p class="order-service">{{ p.tipo_servicio === 'llevar' ? '🛍️ Para llevar' : '🍽️ Local' }}</p>
              <div class="thumbs">
                <img v-for="it in itemsDePedido(p)" :key="it.id" :src="menuImageUrl(it)" alt="" class="thumb" />
              </div>
            </div>
            <div class="order-footer">
              <span class="order-costo">S/ {{ Number(p.costo || 0).toFixed(2) }}</span>
              <div class="order-footer-actions">
                <button
                  v-if="String(p.estado).toLowerCase() === 'preparado'"
                  class="btn-entregado"
                  @click="marcarEntregado(p)"
                >✔ Entregado</button>
                <button class="btn-cancel" @click="cancelarPedido(p)" :disabled="String(p.estado).toLowerCase() !== 'pedido'">
                  Cancelar
                </button>
              </div>
            </div>
          </div>

          <div v-if="pedidosDelDia.length === 0" class="empty-orders">
            Sin pedidos activos hoy
          </div>
        </div>
      </section>

    </div>

    <!-- Toast notifications -->
    <div class="toast-container">
      <transition-group name="toast">
        <div v-for="(aviso, i) in avisos" :key="i" class="toast">🔔 {{ aviso }}</div>
      </transition-group>
    </div>
  </div>

  <SessionGuard />
</template>

<style src="../styles/pedido.css" scoped></style>

<style scoped>
.toast-container {
  position: fixed; top: 100px; right: 20px;
  display: flex; flex-direction: column; gap: 10px; z-index: 1000;
}
.toast {
  background: #10b981; color: white;
  padding: 15px 25px; border-radius: 8px; font-weight: 700;
  box-shadow: 0 4px 12px rgba(16,185,129,0.4);
}
.toast-enter-active { animation: slideIn 0.3s ease-out; }
.toast-leave-active { animation: slideIn 0.3s ease-out reverse; }
@keyframes slideIn {
  from { transform: translateX(100%); opacity: 0; }
  to   { transform: translateX(0);    opacity: 1; }
}
</style>
