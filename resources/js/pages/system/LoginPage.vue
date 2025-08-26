<template>
    <v-container class="pa-4 auth-bg" fluid>
        <v-row align="center" justify="center" style="min-height: 90vh">
            <v-col cols="12" sm="8" md="5" lg="4" xl="3">
                <v-card elevation="10" class="pa-6 rounded-xl auth-card" :loading="loading">
                    <!-- Loader superior -->
                    <v-progress-linear v-if="loading" indeterminate absolute top />

                    <!-- Encabezado -->
                    <div class="text-center mb-4">
                        <v-avatar size="56" class="mb-2" color="primary">
                            <v-icon size="32">mdi-account</v-icon>
                        </v-avatar>
                        <h1 class="text-h5 font-weight-bold mb-1">Bienvenido</h1>
                        <p class="text-body-2 text-medium-emphasis">Inicia sesión para continuar.</p>
                    </div>

                    <!-- Error global -->
                    <v-alert
                        v-if="error"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-4"
                    >
                        {{ error }}
                    </v-alert>

                    <!-- Formulario -->
                    <v-form
                        ref="loginForm"
                        v-model="valid"
                        validate-on="submit"
                        fast-fail
                        @submit.prevent="onSubmit"
                    >
                        <!-- Email -->
                        <v-text-field
                            v-model="email"
                            label="Correo electrónico"
                            prepend-inner-icon="mdi-email"
                            variant="outlined"
                            density="comfortable"
                            class="mb-3"
                            :rules="[rules.required, rules.email]"
                            autocomplete="email"
                        />

                        <!-- Password -->
                        <v-text-field
                            v-model="password"
                            :type="showPassword ? 'text' : 'password'"
                            label="Contraseña"
                            prepend-inner-icon="mdi-lock"
                            :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                            @click:append-inner="togglePassword"
                            variant="outlined"
                            density="comfortable"
                            class="mb-4"
                            :rules="[rules.required]"
                            autocomplete="current-password"
                        />

                        <!-- Botón -->
                        <v-btn
                            type="submit"
                            color="primary"
                            size="large"
                            block
                            :loading="loading"
                        >
                            Ingresar
                        </v-btn>
                    </v-form>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import API from "@/services/index.js";

export default {
    name: "LoginCard",

    data() {
        return {
            email: "",
            password: "",
            showPassword: false,
            loading: false,
            valid: false,
            error: null,
            rules: {
                required: (v) => !!v || "Este campo es obligatorio",
                email: (v) => /.+@.+\..+/.test(v) || "Debe ser un correo válido",
            },
        };
    },

    methods: {
        togglePassword() {
            this.showPassword = !this.showPassword;
        },

        async onSubmit() {
            if (!this.valid) return;

            this.loading = true;
            this.error = null;

            try {
                const credentials = {
                    email: this.email,
                    password: this.password,
                };

                const response = await API.auth.login(credentials);
                console.log(response)
                localStorage.setItem("auth_token", response.data.token);
                localStorage.setItem("user", JSON.stringify(response.data.user));
                this.$router.push({ name: "Home" });
            } catch (error) {
                this.error =
                    error?.response?.data?.message ||
                    error.message ||
                    "Error desconocido al iniciar sesión";
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
