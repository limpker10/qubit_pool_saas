import { createRouter, createWebHistory } from 'vue-router'
import DashboardLayout from "@/layouts/DashboardLayout.vue";
import AuthLayout from "@/layouts/AuthLayout.vue";
import LoginPage from "@/pages/system/LoginPage.vue";
import DashboardPage from "@/pages/system/dashboard/DashboardPage.vue";


const routes = [
    {
        path: '/',
        component: DashboardLayout,
        children: [
            {
                path: '',
                name: 'Home',
                component: DashboardPage
            },

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
