<template>
    <!-- Contenedor general centrado -->
    <div class="max-w-md w-full mx-auto p-6  rounded-2xl shadow-md space-y-4">

        <!-- Campo de correo -->
        <v-text-field
            v-model="email"
            label="Correo electrónico"
            variant="outlined"
            density="comfortable"
            type="email"
            class="mb-2 text-sm"
            :rules="[rules.required, rules.email]"
        />

        <!-- Campo de contraseña -->
        <v-text-field
            v-model="password"
            label="Contraseña"
            :type="showPassword ? 'text' : 'password'"
            variant="outlined"
            density="comfortable"
            class="mb-2 text-sm"
            :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
            @click:append-inner="togglePassword"
            :rules="[rules.required]"
        />

        <!-- Botón de entrada -->
        <v-btn
            color="primary"
            block
            class="font-bold py-3 rounded-xl text-base"
            :loading="loading"
            @click="login"
        >
            Entrar
        </v-btn>
    </div>
</template>

<script>
import API from "@/tenant/services/index.js";
export default {
    name: 'LoginCard',

    data() {
        return {
            email: '',
            password: '',
            showPassword: false,
            loading: false,
            rules: {
                required: v => !!v || 'Este campo es obligatorio',
                email: v => /.+@.+\..+/.test(v) || 'Debe ser un correo válido',
            }
        }
    },

    mounted() {
        // Puedes ejecutar lógica al montar, como verificar sesión existente
        console.log('Componente montado')
    },

    methods: {
        togglePassword() {
            this.showPassword = !this.showPassword
        },

        async login() {
            if (!this.email || !this.password) return;

            this.loading = true;
            try {
                const credentials = {
                    email: this.email,
                    password: this.password,
                };

                const response = await API.auth.login(credentials);

                localStorage.setItem('auth_token', response.data.token);
                localStorage.setItem('user', JSON.stringify(response.data.user));
                this.$router.push({ name: 'Home' });

            } catch (error) {
                const msg = error?.response?.data?.message || error.message || 'Error desconocido';
                alert(`Error al iniciar sesión: ${msg}`);
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
