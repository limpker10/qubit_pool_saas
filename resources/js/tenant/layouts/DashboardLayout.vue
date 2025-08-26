<template>
    <v-app>
        <!-- Sidebar -->
        <v-navigation-drawer
            v-model="drawer"
            app
            :temporary="isMobile"
            :rail="!isMobile && rail"
            :expand-on-hover="!isMobile && rail"
            width="280"
            floating
            color="primary"
        >
            <v-list density="compact" nav>
                <v-list-item class="mb-4">
                    <v-list-item-title class="font-bold text-xl px-2">
                        <span v-if="!rail || isMobile">Panel</span>
                    </v-list-item-title>
                </v-list-item>

                <v-divider class="mb-2" />

                <v-list-item
                    v-for="item in menuItems"
                    :key="item.to"
                    :to="item.to"
                    link
                    exact
                    active-class="bg-indigo-100 text-indigo-700"
                >
                    <template #prepend>
                        <v-icon color="surface">{{ item.icon }}</v-icon>
                    </template>

                    <v-list-item-title class="font-medium" v-show="!rail || isMobile">
                        {{ item.text }}
                    </v-list-item-title>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

        <!-- Topbar -->
        <v-app-bar app flat>
            <!-- En móvil: abre/cierra el drawer. En desktop: compacta/expande (rail) -->
            <v-app-bar-nav-icon
                :title="isMobile ? 'Menú' : (rail ? 'Expandir menú' : 'Compactar menú')"
                @click="onNavClick"
            />

            <v-toolbar-title class="font-semibold">Panel</v-toolbar-title>
            <v-spacer />

            <template v-if="!isMobile">
                <v-btn icon>
                    <v-icon class="text-gray-600">mdi-bell</v-icon>
                </v-btn>
                <!-- Botón de usuario con menú -->
                <v-menu
                    offset-y
                    transition="scale-transition"
                >
                    <template #activator="{ props }">
                        <v-btn icon v-bind="props">
                            <v-icon class="text-gray-600">mdi-account</v-icon>
                        </v-btn>
                    </template>

                    <v-list>
                        <v-list-item>
                            <v-list-item-title class="font-medium">Mi perfil</v-list-item-title>
                        </v-list-item>

                        <v-divider />

                        <v-list-item @click="logout">
                            <v-icon start>mdi-logout</v-icon>
                            <v-list-item-title class="text-red-600 font-medium">Cerrar sesión</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>

            </template>

            <v-btn
                icon
                @click="toggleTheme"
                :title="themeIcon === 'mdi-white-balance-sunny' ? 'Modo claro' : 'Modo oscuro'"
            >
                <v-icon>{{ themeIcon }}</v-icon>
            </v-btn>
        </v-app-bar>

        <!-- Contenido -->
        <v-main class="p-2">
            <div class="pa-5">
                <router-view />
            </div>
        </v-main>
    </v-app>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useTheme, useDisplay } from 'vuetify'

/**
 * Display / breakpoints
 */
const display = useDisplay()
const isMobile = computed(() => display.smAndDown.value)

/**
 * Drawer & Rail (sidebar compacto)
 */
const drawer = ref(true)
const rail = ref(false)

/**
 * Persistencia en localStorage
 */
const LS_KEYS = {
    drawer: 'layout.drawer',
    rail: 'layout.rail',
    theme: 'layout.theme',
}

function loadPersistedLayout() {
    const storedDrawer = localStorage.getItem(LS_KEYS.drawer)
    const storedRail = localStorage.getItem(LS_KEYS.rail)

    if (isMobile.value) {
        drawer.value = false // por defecto cerrado en móvil
        rail.value = false
    } else {
        drawer.value = storedDrawer !== null ? storedDrawer === '1' : true
        rail.value = storedRail !== null ? storedRail === '1' : false
    }
}

watch(drawer, v => localStorage.setItem(LS_KEYS.drawer, v ? '1' : '0'))
watch(rail, v => localStorage.setItem(LS_KEYS.rail, v ? '1' : '0'))

/**
 * Cambiar comportamiento del botón menú según tamaño:
 * - móvil: abre/cierra drawer
 * - desktop: alterna rail (compacto)
 */
function onNavClick() {
    if (isMobile.value) {
        drawer.value = !drawer.value
    } else {
        rail.value = !rail.value
    }
}

/**
 * Tema claro/oscuro
 */
const theme = useTheme()
const themeIcon = computed(() =>
    theme.global.current.value.dark
        ? 'mdi-white-balance-sunny'
        : 'mdi-moon-waning-crescent'
)

function toggleTheme() {
    const isDark = theme.global.current.value.dark
    theme.global.name.value = isDark ? 'light' : 'dark'
    localStorage.setItem(LS_KEYS.theme, theme.global.name.value)
}

/**
 * Inicialización
 */
onMounted(() => {
    // Restaurar tema
    const savedTheme = localStorage.getItem(LS_KEYS.theme)
    if (savedTheme) theme.global.name.value = savedTheme

    // Ajustar layout según breakpoint + persistencia
    loadPersistedLayout()
})

// Si cambia el breakpoint en caliente, reajusta valores por UX consistente
watch(isMobile, (mobile) => {
    if (mobile) {
        drawer.value = false
        rail.value = false
    } else {
        // al pasar a desktop, si no había persistencia deja abierto el drawer
        const storedDrawer = localStorage.getItem(LS_KEYS.drawer)
        drawer.value = storedDrawer !== null ? storedDrawer === '1' : true

        const storedRail = localStorage.getItem(LS_KEYS.rail)
        rail.value = storedRail !== null ? storedRail === '1' : false
    }
})
function logout() {
    // Aquí colocas tu lógica de logout
    // Ejemplo con localStorage + redirección:
    localStorage.removeItem('auth_token') // o el storage de sesión que uses
    window.location.href = '/login'
}
/**
 * Menú
 */
const menuItems = [
    { text: 'Inicio', to: '/', icon: 'mdi-view-dashboard' },
    { text: 'Control', to: '/control', icon: 'mdi-file-chart' },
    { text: 'Ajustes', to: '/settings', icon: 'mdi-cog' },
    { text: 'Productos', to: '/products', icon: 'mdi-package-variant' },
    { text: 'Kardex', to: '/kardex', icon: 'mdi-clipboard-list-outline' },
    { text: 'Cash', to: '/cash', icon: 'mdi-cash' },
    { text: 'Cash List', to: '/cash_list', icon: 'mdi-playlist-check' },
]
</script>

<style scoped>
/* Ajustes suaves para que el contenido respire en móvil */
@media (max-width: 600px) {
    .pa-5 {
        padding: 12px !important;
    }
}
</style>
