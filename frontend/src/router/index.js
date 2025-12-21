import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import MenuView from '../views/MenuView.vue'
import PedidoView from '../views/PedidoView.vue'
import CocinaView from '../views/CocinaView.vue'
import CajaView from '../views/CajaView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/login'
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView
    },
    {
      path: '/menu',
      name: 'menu',
      component: MenuView
    },
    {
      path: '/pedidos',
      name: 'pedidos',
      component: PedidoView
    },
    {
      path: '/cocina',
      name: 'cocina',
      component: CocinaView
    },
    {
      path: '/caja',
      name: 'caja',
      component: CajaView
    }
  ]
})

export default router
