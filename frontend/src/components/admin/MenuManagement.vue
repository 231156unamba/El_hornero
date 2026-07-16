<script setup>
import { ref } from 'vue';
import api from '../../api';

const props = defineProps({
  menu: {
    type: Array,
    required: true,
    default: () => []
  }
});

const emit = defineEmits(['edit', 'delete', 'discount']);

const menuImageUrl = (item) => {
  if (!item) return '';
  if (item.imagen_url) return item.imagen_url;
  const img = item.imagen;
  if (!img) return '';
  if (/^https?:\/\//i.test(img)) return img;
  const apiOrigin = new URL(api.defaults.baseURL).origin;
  if (img.startsWith('/')) return apiOrigin + img;
  return `${apiOrigin}/images/menu/${img}`;
};

const imgFallback = (e) => {
  e.target.src = '/logo.png';
};

const editItem = (item) => emit('edit', item);
const deleteItem = (item) => emit('delete', item);
const openDiscountModal = (item) => emit('discount', item);
</script>

<template>
  <div>
    <h3 style="margin: 25px 0 15px; color: #f1af32; font-size: 1.3rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Comidas</h3>
    <div class="menu-list">
      <div class="menu-item" v-for="item in menu.filter(i => (i.categoria || '').toLowerCase() === 'comida')" :key="item.id">
        <img :src="menuImageUrl(item)" alt="" @error="imgFallback">
        <div class="menu-content">
          <div class="menu-header">
            <div class="nombre">{{ item.nombre }}</div>
            <div class="precio">
              <span v-if="item.discount_percentage" style="text-decoration: line-through; color: #777; font-size: 0.8em; margin-right: 5px;">
                S/. {{ item.precio }}
              </span>
              S/. {{ item.discount_percentage ? (item.precio * (1 - item.discount_percentage / 100)).toFixed(2) : item.precio }}
              <span v-if="item.discount_percentage" style="background: #ef5350; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; margin-left: 5px;">
                -{{ item.discount_percentage }}%
              </span>
            </div>
          </div>
          <div class="descripcion">{{ item.descripcion }}</div>
          <div class="menu-actions">
            <button class="btn btn-success" @click="editItem(item)">Editar</button>
            <button class="btn" style="background: #ffc107; color: #333;" @click="openDiscountModal(item)">Oferta</button>
            <button class="btn btn-danger" @click="deleteItem(item)">Eliminar</button>
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
            <div class="precio">
              <span v-if="item.discount_percentage" style="text-decoration: line-through; color: #777; font-size: 0.8em; margin-right: 5px;">
                S/. {{ item.precio }}
              </span>
              S/. {{ item.discount_percentage ? (item.precio * (1 - item.discount_percentage / 100)).toFixed(2) : item.precio }}
              <span v-if="item.discount_percentage" style="background: #ef5350; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; margin-left: 5px;">
                -{{ item.discount_percentage }}%
              </span>
            </div>
          </div>
          <div class="descripcion">{{ item.descripcion }}</div>
          <div class="menu-actions">
            <button class="btn btn-success" @click="editItem(item)">Editar</button>
            <button class="btn" style="background: #ffc107; color: #333;" @click="openDiscountModal(item)">Oferta</button>
            <button class="btn btn-danger" @click="deleteItem(item)">Eliminar</button>
          </div>
        </div>
      </div>
    </div>

    <h3 style="margin: 40px 0 15px; color: #f1af32; font-size: 1.3rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Promociones</h3>
    <div class="menu-list">
      <div class="menu-item" v-for="item in menu.filter(i => (i.categoria || '').toLowerCase() === 'promociones')" :key="item.id">
        <img :src="menuImageUrl(item)" alt="" @error="imgFallback">
        <div class="menu-content">
          <div class="menu-header">
            <div class="nombre">{{ item.nombre }}</div>
            <div class="precio">
              <span v-if="item.discount_percentage" style="text-decoration: line-through; color: #777; font-size: 0.8em; margin-right: 5px;">
                S/. {{ item.precio }}
              </span>
              S/. {{ item.discount_percentage ? (item.precio * (1 - item.discount_percentage / 100)).toFixed(2) : item.precio }}
              <span v-if="item.discount_percentage" style="background: #ef5350; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; margin-left: 5px;">
                -{{ item.discount_percentage }}%
              </span>
            </div>
          </div>
          <div class="descripcion">{{ item.descripcion }}</div>
          <div class="menu-actions">
            <button class="btn btn-success" @click="editItem(item)">Editar</button>
            <button class="btn" style="background: #ffc107; color: #333;" @click="openDiscountModal(item)">Oferta</button>
            <button class="btn btn-danger" @click="deleteItem(item)">Eliminar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
