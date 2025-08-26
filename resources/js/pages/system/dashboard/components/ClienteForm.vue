<template>
    <v-card>
        <v-card-title>
            <span class="text-h6">{{ cliente ? "Editar Cliente" : "Nuevo Cliente" }}</span>
            <v-spacer />
            <v-btn icon @click="$emit('close')"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-divider />

        <v-card-text>
            <v-form v-model="valid" @submit.prevent="onSubmit" fast-fail>
                <v-row dense>
                    <v-col cols="12" sm="6">
                        <v-text-field v-model="form.company" label="Nombre de la Empresa" :rules="[rules.required]" />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-text-field v-model="form.subdomain" label="Subdominio" suffix=".innovaservicios.pe" :rules="[rules.required]" />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-text-field v-model="form.email" label="Correo de Acceso" :rules="[rules.required, rules.email]" />
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-select v-model="form.plan" :items="plans" label="Plan" />
                    </v-col>
                </v-row>

                <!-- Contraseña solo en nuevo -->
                <v-row v-if="!cliente">
                    <v-col cols="12" sm="6">
                        <v-text-field v-model="form.password" label="Contraseña" type="password" :rules="[rules.required]" />
                    </v-col>
                </v-row>

                <v-divider class="my-3" />
                <v-checkbox v-model="form.limitDocuments" label="Limitar emisión de documentos" />

                <!-- Módulos -->
                <h3 class="text-subtitle-1 font-weight-bold mt-4 mb-2">Módulos</h3>
                <v-row>
                    <v-col cols="6">
                        <v-checkbox v-for="mod in modules" :key="mod" v-model="form.modules" :label="mod" :value="mod" />
                    </v-col>
                    <v-col cols="6">
                        <v-checkbox v-for="app in apps" :key="app" v-model="form.apps" :label="app" :value="app" />
                    </v-col>
                </v-row>
            </v-form>
        </v-card-text>

        <v-card-actions>
            <v-btn color="secondary" @click="$emit('close')">Cancelar</v-btn>
            <v-btn color="primary" :loading="loading" @click="onSubmit">
                {{ cliente ? "Actualizar" : "Crear" }}
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<script>
import API from "@/services/index.js";

export default {
    name: "ClienteForm",
    props: {
        cliente: Object,
    },

    data() {
        return {
            valid: false,
            loading: false,
            plans: ["Básico", "Pro", "Premium"],
            modules: ["Dashboard", "Ventas", "Compras", "Clientes", "Inventario"],
            apps: ["Trámite documentario", "Producción", "Cuenta", "Configuración"],
            form: {
                company: "",
                subdomain: "",
                email: "",
                password: "",
                plan: null,
                limitDocuments: false,
                modules: [],
                apps: [],
            },
            rules: {
                required: (v) => !!v || "Campo obligatorio",
                email: (v) => /.+@.+\..+/.test(v) || "Correo inválido",
            },
        };
    },

    mounted() {
        if (this.cliente) {
            this.form = { ...this.cliente };
        }
    },

    methods: {
        async onSubmit() {
            this.loading = true;
            try {
                if (this.cliente) {
                    await API.tenants.update(this.cliente.id, this.form);
                } else {
                    await API.tenants.create(this.form);
                }
                this.$emit("saved");
                this.$emit("close");
            } catch (e) {
                console.error("Error guardando cliente", e);
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
