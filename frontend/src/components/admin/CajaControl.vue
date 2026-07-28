<script setup>
import { ref } from 'vue';
import './CajaControl.css';

const props = defineProps({
  cajaForm: {
    type: Object,
    required: true,
    default: () => ({ nombre_comercial: '', ruc: '', direccion: '', telefono: '', yape_numero: '' })
  },
  cierreAdmin: {
    type: Object,
    required: true,
    default: () => ({ from: '', to: '', data: [] })
  }
});

const emit = defineEmits(['submit', 'update:cajaForm', 'update:cierreAdmin', 'export']);

const yapeQrFile = ref(null);
const selectedRecibo = ref(null);

const onYapeQrChange = (e) => {
  const files = e?.target?.files;
  yapeQrFile.value = files && files[0] ? files[0] : null;
};

const openDetail = (recibo) => {
  selectedRecibo.value = recibo;
};

const closeDetail = () => {
  selectedRecibo.value = null;
};

const fmt = (val) => `S/. ${Number(val || 0).toFixed(2)}`;

const formatFecha = (f) => {
  if (!f) return '—';
  return new Date(f.includes('T') ? f : f.replace(' ', 'T')).toLocaleString('es-PE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
};

const badgeTipo = (tipo) => {
  if (!tipo) return 'caja-badge-otro';
  const t = tipo.toLowerCase();
  if (t === 'boleta')  return 'caja-badge-boleta';
  if (t === 'factura') return 'caja-badge-factura';
  return 'caja-badge-otro';
};

const badgePago = (metodo) => {
  if (!metodo) return 'caja-badge-otro';
  const m = metodo.toLowerCase();
  if (m.includes('efectivo')) return 'caja-badge-efectivo';
  if (m.includes('tarjeta'))  return 'caja-badge-tarjeta';
  if (m.includes('yape'))     return 'caja-badge-yape';
  return 'caja-badge-otro';
};
</script>

<template>
  <div class="caja-layout">

    <!-- ── Panel: Información del negocio ── -->
    <div class="caja-panel">
      <div class="caja-panel-header">
        <span class="caja-panel-icon">🏪</span>
        <h3 class="caja-panel-title">Información del negocio</h3>
      </div>

      <div class="caja-panel-body">
        <form @submit.prevent="emit('submit')">
          <div class="caja-form-grid">

            <div class="caja-form-group">
              <label class="caja-form-label">Nombre Comercial</label>
              <input
                :value="cajaForm.nombre_comercial"
                @input="emit('update:cajaForm', { ...cajaForm, nombre_comercial: $event.target.value })"
                class="caja-form-input"
                placeholder="Ej. El Hornero"
                required
              />
            </div>

            <div class="caja-form-group">
              <label class="caja-form-label">RUC</label>
              <input
                :value="cajaForm.ruc"
                @input="emit('update:cajaForm', { ...cajaForm, ruc: $event.target.value })"
                class="caja-form-input"
                placeholder="Ej. 20123456789"
                required
              />
            </div>

            <div class="caja-form-group full">
              <label class="caja-form-label">Dirección</label>
              <input
                :value="cajaForm.direccion"
                @input="emit('update:cajaForm', { ...cajaForm, direccion: $event.target.value })"
                class="caja-form-input"
                placeholder="Av. Tamburco N° 224..."
                required
              />
            </div>

            <div class="caja-form-group">
              <label class="caja-form-label">Teléfono</label>
              <input
                :value="cajaForm.telefono"
                @input="emit('update:cajaForm', { ...cajaForm, telefono: $event.target.value })"
                class="caja-form-input"
                placeholder="Ej. 972322520"
                required
              />
            </div>

            <div class="caja-form-group">
              <label class="caja-form-label">Número Yape</label>
              <input
                :value="cajaForm.yape_numero"
                @input="emit('update:cajaForm', { ...cajaForm, yape_numero: $event.target.value })"
                class="caja-form-input"
                placeholder="Ej. 972322520"
                required
              />
            </div>

            <div class="caja-form-group full">
              <label class="caja-form-label">QR Yape</label>
              <input
                type="file"
                class="caja-form-input"
                accept="image/*"
                @change="onYapeQrChange"
              />
            </div>

          </div>

          <div class="caja-form-footer">
            <button type="submit" class="caja-btn-save">Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Panel: Registro de ventas ── -->
    <div class="caja-ventas-panel">

      <div class="caja-ventas-header">
        <div class="caja-ventas-title-row">
          <span class="caja-ventas-icon">🧾</span>
          <h3 class="caja-ventas-title">Registro de ventas</h3>
        </div>

        <div class="caja-filters">
          <div class="caja-filter-group">
            <label class="caja-filter-label">Desde</label>
            <input
              :value="cierreAdmin.from"
              @input="emit('update:cierreAdmin', { ...cierreAdmin, from: $event.target.value })"
              type="date"
              class="caja-filter-input"
            />
          </div>

          <div class="caja-filter-group">
            <label class="caja-filter-label">Hasta</label>
            <input
              :value="cierreAdmin.to"
              @input="emit('update:cierreAdmin', { ...cierreAdmin, to: $event.target.value })"
              type="date"
              class="caja-filter-input"
            />
          </div>

          <button class="caja-btn-export" @click="emit('export')">
            ⬇ Exportar cierre
          </button>
        </div>
      </div>

      <!-- Tabla -->
      <div class="caja-table-wrapper">
        <table class="caja-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Número</th>
              <th>Tipo</th>
              <th>Mesa</th>
              <th>Subtotal</th>
              <th>IGV</th>
              <th>Total</th>
              <th>Fecha</th>
              <th>Pago</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in cierreAdmin.data"
              :key="r.id"
              @click="openDetail(r)"
            >
              <td>{{ r.id }}</td>
              <td>{{ r.numero }}</td>
              <td>
                <span :class="['caja-badge', badgeTipo(r.tipo)]">{{ r.tipo }}</span>
              </td>
              <td>{{ r.mesa || '—' }}</td>
              <td>{{ fmt(r.subtotal) }}</td>
              <td>{{ fmt(r.igv) }}</td>
              <td class="caja-amount">{{ fmt(r.total) }}</td>
              <td>{{ formatFecha(r.fecha) }}</td>
              <td>
                <span :class="['caja-badge', badgePago(r.metodo_pago)]">
                  {{ r.metodo_pago || '—' }}
                </span>
              </td>
            </tr>

            <tr v-if="!cierreAdmin.data || cierreAdmin.data.length === 0">
              <td colspan="9" class="caja-table-empty">
                Sin registros para el período seleccionado
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="cierreAdmin.data && cierreAdmin.data.length > 0" class="caja-table-hint">
        Haz clic en una fila para ver el detalle completo
      </div>
    </div>

  </div>

  <!-- ── Modal: Detalle de compra ── -->
  <Teleport to="body">
    <div v-if="selectedRecibo" class="caja-detail-overlay" @click.self="closeDetail">
      <div class="caja-detail-modal">

        <!-- Header -->
        <div class="caja-detail-header">
          <div class="caja-detail-header-left">
            <span class="caja-detail-numero">Recibo {{ selectedRecibo.numero }}</span>
            <span class="caja-detail-fecha">{{ formatFecha(selectedRecibo.fecha) }}</span>
          </div>
          <button class="caja-detail-close" type="button" @click="closeDetail">&times;</button>
        </div>

        <!-- Body -->
        <div class="caja-detail-body">

          <!-- Identificación -->
          <div class="caja-detail-group">
            <div class="caja-detail-group-title">Identificación</div>
            <div class="caja-detail-row">
              <span class="caja-detail-key">ID interno</span>
              <span class="caja-detail-val"># {{ selectedRecibo.id }}</span>
            </div>
            <div class="caja-detail-row">
              <span class="caja-detail-key">Número de recibo</span>
              <span class="caja-detail-val">{{ selectedRecibo.numero }}</span>
            </div>
            <div class="caja-detail-row">
              <span class="caja-detail-key">Tipo de documento</span>
              <span class="caja-detail-val">
                <span :class="['caja-badge', badgeTipo(selectedRecibo.tipo)]">
                  {{ selectedRecibo.tipo }}
                </span>
              </span>
            </div>
            <div class="caja-detail-row" v-if="selectedRecibo.mesa">
              <span class="caja-detail-key">Mesa</span>
              <span class="caja-detail-val">{{ selectedRecibo.mesa }}</span>
            </div>
          </div>

          <!-- Montos -->
          <div class="caja-detail-group">
            <div class="caja-detail-group-title">Desglose de montos</div>
            <div class="caja-detail-row">
              <span class="caja-detail-key">Subtotal</span>
              <span class="caja-detail-val">{{ fmt(selectedRecibo.subtotal) }}</span>
            </div>
            <div class="caja-detail-row">
              <span class="caja-detail-key">IGV (18%)</span>
              <span class="caja-detail-val">{{ fmt(selectedRecibo.igv) }}</span>
            </div>
            <div class="caja-detail-row">
              <span class="caja-detail-key">Total</span>
              <span class="caja-detail-val highlight">{{ fmt(selectedRecibo.total) }}</span>
            </div>
          </div>

          <!-- Pago -->
          <div class="caja-detail-group">
            <div class="caja-detail-group-title">Pago</div>
            <div class="caja-detail-row">
              <span class="caja-detail-key">Método de pago</span>
              <span class="caja-detail-val">
                <span :class="['caja-badge', badgePago(selectedRecibo.metodo_pago)]">
                  {{ selectedRecibo.metodo_pago || '—' }}
                </span>
              </span>
            </div>
            <div class="caja-detail-row" v-if="selectedRecibo.venta_id">
              <span class="caja-detail-key">ID de venta</span>
              <span class="caja-detail-val"># {{ selectedRecibo.venta_id }}</span>
            </div>
          </div>

          <!-- Detalle del pedido -->
          <div class="caja-detail-group" v-if="selectedRecibo.detalle">
            <div class="caja-detail-group-title">Detalle del pedido</div>
            <div class="caja-detail-detalle">{{ selectedRecibo.detalle }}</div>
          </div>

        </div>
      </div>
    </div>
  </Teleport>
</template>
