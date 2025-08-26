<template>
    <v-container class="pa-4" fluid>
        <v-card elevation="6" class="rounded-xl">
            <v-card-title>
                <span class="text-h6 font-weight-bold">Lista de Clientes</span>
                <v-spacer />
                <v-btn color="primary" prepend-icon="mdi-plus" @click="openForm()">
                    Nuevo Cliente
                </v-btn>
            </v-card-title>

            <v-data-table
                :headers="headers"
                :items="clientes"
                :loading="loading"
                class="elevation-1"
                :items-per-page="10"
            >
                <template v-slot:item.actions="{ item }">
                    <v-btn icon size="small" color="primary" @click="openForm(item)">
                        <v-icon>mdi-pencil</v-icon>
                    </v-btn>
                    <v-btn icon size="small" color="error" @click="deleteCliente(item)">
                        <v-icon>mdi-delete</v-icon>
                    </v-btn>
                </template>
            </v-data-table>
        </v-card>

        <!-- Modal con el formulario -->
        <v-dialog v-model="showForm" max-width="800px">
            <cliente-form :cliente="selectedCliente" @saved="loadClientes" @close="showForm = false" />
        </v-dialog>
    </v-container>
</template>

<script>
import API from "@/services/index.js";
import ClienteForm from "./ClienteForm.vue";

export default {
    name: "ClientesTable",
    components: { ClienteForm },

    data() {
        return {
            loading: false,
            showForm: false,
            selectedCliente: null,
            clientes: [],
            headers: [
                { title: "Empresa", key: "company" },
                { title: "Subdominio", key: "subdomain" },
                { title: "Correo", key: "email" },
                { title: "Plan", key: "plan" },
                { title: "Acciones", key: "actions", sortable: false },
            ],
        };
    },

    mounted() {
        this.loadClientes();
    },

    methods: {
        async loadClientes() {
            this.loading = true;
            try {
                const res = await API.tenants.list();
                this.clientes = res.data;
            } catch (e) {
                console.error("Error cargando clientes", e);
            } finally {
                this.loading = false;
            }
        },

        openForm(cliente = null) {
            this.selectedCliente = cliente;
            this.showForm = true;
        },

        async deleteCliente(cliente) {
            if (!confirm(`¿Seguro de eliminar al cliente ${cliente.company}?`)) return;
            try {
                await API.tenants.delete(cliente.id);
                this.loadClientes();
            } catch (e) {
                console.error("Error eliminando cliente", e);
            }
        },
    },
};
</script>
