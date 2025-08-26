// Vuetify
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import '@mdi/font/css/materialdesignicons.css'

const vuetify = createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                colors: {
                    background: '#f4f7fc', // Fondo principal
                    surface: '#dfe7f2',    // Superficies (cards, etc.)
                    primary: '#283347',    // Color principal
                    secondary: '#586c91',  // Color secundario
                    accent: '#015366',     // Color de acento
                    error: '#B00020',
                    info: '#2196F3',
                    success: '#4CAF50',
                    warning: '#FB8C00',
                }
            }
        }
    },
    icons: {
        defaultSet: 'mdi',
    },
})

export default vuetify
