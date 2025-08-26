import { createRouter, createWebHistory } from 'vue-router'
import AuthLayout from '@/tenant/layouts/AuthLayout.vue'

import DashboardHome from '@/tenant/pages/DashboardHome.vue'
import LoginPage from '@/tenant/pages/LoginPage.vue'
import ControlPage from "@/tenant/pages/control/ControlPage.vue";
import ProductsPage from "@/tenant/pages/items/ProductsPage.vue";
import KardexPage from "@/tenant/pages/logistic/KardexPage.vue";
import CashSessionPanel from "@/tenant/pages/cash/CashSessionPanel.vue";
import CashSessionList from "@/tenant/pages/cash/CashSessionList.vue";
import DashboardLayout from "@/tenant/layouts/DashboardLayout.vue";

const routes = [
    {
        path: '/',
        component: DashboardLayout,
        children: [
            {
                path: '',
                name: 'Home',
                component: DashboardHome
            },
            {
                path: 'control',
                name: 'Control Mesas',
                component: ControlPage
            },
            {
                path: 'products',
                name: 'Control productos',
                component: ProductsPage
            },
            {
                path: 'Kardex',
                name: 'Kardex',
                component: KardexPage
            },
            {
                path: 'Cash',
                name: 'Cash',
                component: CashSessionPanel
            },
            {
                path: 'Cash_list',
                name: 'Cash_list',
                component: CashSessionList
            }
        ]
    },
    {
        path: '/auth',
        component: AuthLayout,
        children: [
            {
                path: 'login',
                name: 'Login',
                component: LoginPage
            }
        ]
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

router.beforeEach((to, from, next) => {
    const isAuthenticated = !!localStorage.getItem('auth_token'); // o tu método real

    // Si intenta acceder a cualquier ruta que no sea login y no está autenticado
    if (!isAuthenticated && to.name !== 'Login') {
        return next({ name: 'Login' });
    }

    // Si ya está autenticado y va al login, redirigir al home
    if (isAuthenticated && to.name === 'Login') {
        return next({ name: 'Home' });
    }

    return next(); // acceso permitido
});
export default router
