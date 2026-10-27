import { createRouter, createWebHistory } from 'vue-router'

import HomeView from '../views/HomeView.vue'
import ProductDetailView from '../views/ProductDetailView.vue'
import AdminView from '../views/AdminView.vue'
import AdminOrdersView from '../views/AdminOrdersView.vue'
import AdminUsersView from '../views/AdminUsersView.vue'
import CartView from '../views/CartView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import ProfileView from '../views/ProfileView.vue'
import CheckoutView from '../views/CheckoutView.vue'
import OrdersView from '../views/OrdersView.vue'

import { estaAutenticado, ehAdministrador } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(),

  routes: [
    {
      path: '/',
      name: 'loja',
      component: HomeView,
    },
    {
      path: '/produto/:id',
      name: 'produto',
      component: ProductDetailView,
      props: true,
    },
    {
      path: '/carrinho',
      name: 'carrinho',
      component: CartView,
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/registrar',
      name: 'registrar',
      component: RegisterView,
    },
    {
      path: '/perfil',
      name: 'perfil',
      component: ProfileView,
      meta: { requerAutenticacao: true },
    },
    {
      path: '/finalizar-compra',
      name: 'checkout',
      component: CheckoutView,
    },
    {
      path: '/meus-pedidos',
      name: 'meus-pedidos',
      component: OrdersView,
    },

    {
      path: '/admin',
      name: 'admin',
      component: AdminView,
      meta: { requerAdmin: true },
    },

    {
      path: '/admin/pedidos',
      name: 'admin-pedidos',
      component: AdminOrdersView,
      meta: { requerAdmin: true },
    },

    {
      path: '/admin/usuarios',
      name: 'admin-usuarios',
      component: AdminUsersView,
      meta: { requerAdmin: true },
    },
  ],

  scrollBehavior() {
    return { top: 0, behavior: 'smooth' }
  },
})

router.beforeEach((to) => {
  if (to.meta.requerAutenticacao && !estaAutenticado()) {
    return '/login'
  }

  if (to.meta.requerAdmin) {
    if (!estaAutenticado()) {
      return '/login'
    }

    if (!ehAdministrador()) {
      return '/'
    }
  }

  return true
})

export default router