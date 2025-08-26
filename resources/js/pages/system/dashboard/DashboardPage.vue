<template>
    <v-container fluid>
        <v-card>
            <v-card-title class="gap-3">
                <span class="text-h6 font-weight-bold">Clientes</span>
                <v-spacer />
                <v-text-field
                    v-model="filters.q"
                    prepend-inner-icon="mdi-magnify"
                    placeholder="Buscar empresa / documento / email"
                    hide-details
                    density="compact"
                    style="max-width: 360px"
                    @keydown.enter="loadClientes"
                />
                <v-btn color="primary" prepend-icon="mdi-plus" @click="openForm()">
                    Nuevo Cliente
                </v-btn>
            </v-card-title>

            <v-data-table
                :headers="headers"
                :items="clientes"
                :loading="loading"
                :items-per-page="10"
                class="elevation-1"
            >
                <template #item.document="{ item }">
                    {{ item.document_type }} {{ item.document_number }}
                </template>

                <template #item.domain="{ item }">
                    {{ item.primary_domain?.fqdn || item.primaryDomain?.fqdn || "—" }}
                </template>

                <template #item.email="{ item }">
                    {{ item.primary_contact?.email || item.primaryContact?.email || "—" }}
                </template>

                <template #item.plan="{ item }">
                    {{ item.plan?.name || "—" }}
                </template>

                <template #item.is_active="{ item }">
                    <v-chip :color="item.is_active ? 'success' : 'grey' " size="small" variant="flat">
                        {{ item.is_active ? 'Activo' : 'Inactivo' }}
                    </v-chip>
                </template>

                <template #item.actions="{ item }">
                    <v-btn icon size="small" color="primary" @click="openForm(item)">
                        <v-icon>mdi-pencil</v-icon>
                    </v-btn>
                    <v-btn icon size="small" color="error" @click="deleteCliente(item)">
                        <v-icon>mdi-delete</v-icon>
                    </v-btn>
                </template>
            </v-data-table>
        </v-card>

        <!-- Modal: Crear / Editar -->
        <v-dialog v-model="showForm" max-width="900px" persistent>
            <v-card>
                <v-card-title class="gap-3">
                    <span class="text-h6">{{ isEditing ? 'Editar Cliente' : 'Nuevo Cliente' }}</span>
                    <v-spacer />
                    <v-btn icon @click="closeForm"><v-icon>mdi-close</v-icon></v-btn>
                </v-card-title>
                <v-divider />

                <v-card-text>
                    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

                    <v-form v-model="valid" @submit.prevent="onSubmit" fast-fail>
                        <v-row dense>
                            <!-- Documento -->
                            <v-col cols="12" sm="3">
                                <v-select
                                    v-model="form.document_type"
                                    :items="docTypes"
                                    label="Tipo doc."
                                    :rules="[rules.required]"
                                    density="comfortable"
                                />
                            </v-col>
                            <v-col cols="12" sm="3">
                                <v-text-field
                                    v-model="form.document_number"
                                    label="N° documento"
                                    :maxlength="docMaxLength"
                                    :counter="docMaxLength"
                                    :rules="[rules.required, rules.onlyDigits, rules.docLen(form.document_type, docMaxLength)]"
                                    density="comfortable"
                                    @input="digitsOnly('document_number')"
                                />
                            </v-col>

                            <!-- Empresa -->
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.company"
                                    label="Razón social / Nombres"
                                    :rules="[rules.required]"
                                    density="comfortable"
                                />
                            </v-col>

                            <!-- Subdominio + Contacto -->
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="form.subdomain"
                                    label="Subdominio"
                                    :suffix="suffixDomain"
                                    :rules="[rules.required, rules.subdomain]"
                                    density="comfortable"
                                />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="form.email"
                                    label="Email admin"
                                    :rules="[rules.required, rules.email]"
                                    density="comfortable"
                                />
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="form.contact_name"
                                    label="Nombre admin/contacto"
                                    density="comfortable"
                                />
                            </v-col>

                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="form.phone"
                                    label="Teléfono"
                                    density="comfortable"
                                />
                            </v-col>

                            <!-- Plan -->
                            <v-col cols="12" sm="4">
                                <v-select
                                    v-model="form.plan_id"
                                    :items="plans"
                                    item-title="name"
                                    item-value="id"
                                    label="Plan"
                                    clearable
                                    density="comfortable"
                                />
                            </v-col>

                            <!-- Estado -->
                            <v-col cols="12" sm="4">
                                <v-switch
                                    v-model="form.is_active"
                                    inset
                                    color="success"
                                    hide-details
                                    label="Activo"
                                />
                            </v-col>

                            <!-- Password sólo al crear -->
                            <v-col v-if="!isEditing" cols="12" sm="6">
                                <v-text-field
                                    v-model="form.admin_password"
                                    label="Contraseña admin (opcional)"
                                    type="password"
                                    hint="Si la dejas vacía, el servidor generará una."
                                    persistent-hint
                                    density="comfortable"
                                />
                            </v-col>

                            <!-- Módulos -->
                            <v-col cols="12">
                                <div class="text-subtitle-2 font-weight-medium mb-2">Módulos</div>
                                <v-row>
                                    <v-col
                                        v-for="mod in modulesList"
                                        :key="mod.id"
                                        cols="12" sm="6" md="4"
                                    >
                                        <v-checkbox
                                            v-model="form.module_ids"
                                            :value="mod.id"
                                            :label="mod.name"
                                            density="comfortable"
                                            hide-details
                                        />
                                    </v-col>
                                </v-row>
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-6 pb-4">
                    <v-spacer />
                    <v-btn variant="text" @click="closeForm">Cancelar</v-btn>
                    <v-btn color="primary" :loading="loadingSave" @click="onSubmit">
                        {{ isEditing ? 'Actualizar' : 'Crear' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script>
import API from "@/services/index.js";

export default {
    name: "DashboardPage",

    data() {
        return {
            baseDomain: "saas-app.test", // para UI (el backend usa su config)
            loading: false,
            loadingSave: false,
            error: null,
            valid: false,
            showForm: false,
            isEditing: false,

            filters: { q: "" },

            clientes: [],
            headers: [
                { title: "Empresa", key: "company" },
                { title: "Documento", key: "document" },
                { title: "Dominio", key: "domain" },
                { title: "Email", key: "email" },
                { title: "Plan", key: "plan" },
                { title: "Estado", key: "is_active" },
                { title: "Acciones", key: "actions", sortable: false },
            ],

            // catálogos
            plans: [],
            modulesList: [],
            docTypes: ["DNI", "RUC"],

            // form
            form: this.emptyForm(),

            rules: {
                required: (v) => !!v || "Campo obligatorio",
                email: (v) => /.+@.+\..+/.test(v) || "Correo inválido",
                onlyDigits: (v) => (!v || /^\d+$/.test(v)) || "Sólo dígitos",
                subdomain: (v) => (!v || /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(v)) || "Subdominio inválido",
                docLen: (type, len) => (v) => {
                    if (!v) return true;
                    return v.length === len || `Debe tener ${len} dígitos`;
                },
            },
        };
    },

    computed: {
        suffixDomain() {
            return `.${this.baseDomain}`;
        },
        docMaxLength() {
            return this.form.document_type === "RUC" ? 11 : 8;
        },
    },

    mounted() {
        this.loadCatalogs();
        this.loadClientes();
    },

    methods: {
        emptyForm() {
            return {
                document_type: "RUC",    // default
                document_number: "",
                company: "",
                subdomain: "",
                email: "",
                contact_name: "",
                phone: "",
                plan_id: null,
                is_active: true,
                admin_password: "",      // sólo se envía en create si no está vacío
                module_ids: [],
            };
        },

        async loadCatalogs() {
            try {
                const [plansRes, modsRes] = await Promise.all([
                    API.plans.list(),      // GET /plans
                    API.modules.list(),    // GET /modules
                ]);
                this.plans = plansRes.data?.data || plansRes.data || [];      // soporta paginado o no
                this.modulesList = modsRes.data?.data || modsRes.data || [];
            } catch (e) {
                console.error("Error cargando catálogos", e);
            }
        },

        async loadClientes() {
            this.loading = true;
            try {
                const res = await API.clients.list();
                // Si tu API devuelve {data: {data: [],...}} por paginación de Laravel:
                console.log(res)
                const rows = res.data;
                this.clientes = rows.map((c) => ({
                    ...c,
                    // normaliza claves posibles
                    primary_domain: c.primaryDomain || c.primary_domain,
                    primary_contact: c.primaryContact || c.primary_contact,
                }));
            } catch (e) {
                console.error("Error cargando clientes", e);
            } finally {
                this.loading = false;
            }
        },

        // Helpers
        parseSubdomainFromFqdn(fqdn) {
            if (!fqdn) return "";
            const suffix = `.${this.baseDomain}`;
            return fqdn.endsWith(suffix) ? fqdn.slice(0, -suffix.length) : fqdn.split(".")[0];
        },
        digitsOnly(field) {
            this.form[field] = (this.form[field] || "").replace(/\D+/g, "").slice(0, this.docMaxLength);
        },

        openForm(item = null) {
            this.error = null;
            this.isEditing = !!item;

            if (item) {
                // Mapeo desde el listado (con relaciones)
                this.form = {
                    document_type: item.document_type,
                    document_number: item.document_number,
                    company: item.company,
                    subdomain: this.parseSubdomainFromFqdn(item.primary_domain?.fqdn || item.primaryDomain?.fqdn || ""),
                    email: item.primary_contact?.email || item.primaryContact?.email || "",
                    contact_name: item.primary_contact?.name || item.primaryContact?.name || "",
                    phone: item.primary_contact?.phone || item.primaryContact?.phone || "",
                    plan_id: item.plan?.id || null,
                    is_active: !!item.is_active,
                    admin_password: "", // no se usa en update
                    module_ids: (item.modules || []).map((m) => m.id),
                };
                this.selectedCliente = item;
            } else {
                this.form = this.emptyForm();
                this.selectedCliente = null;
            }

            this.showForm = true;
        },

        closeForm() {
            this.showForm = false;
            this.selectedCliente = null;
            this.form = this.emptyForm();
            this.error = null;
        },

        buildPayload() {
            const payload = {
                document_type: this.form.document_type,
                document_number: this.form.document_number,
                company: this.form.company,
                subdomain: this.form.subdomain,
                email: this.form.email,
                contact_name: this.form.contact_name || null,
                phone: this.form.phone || null,
                plan_id: this.form.plan_id || null,
                is_active: this.form.is_active,
                module_ids: this.form.module_ids || [],
            };
            if (!this.isEditing && this.form.admin_password) {
                payload.admin_password = this.form.admin_password;
            }
            return payload;
        },

        async onSubmit() {
            if (!this.valid) return;
            this.loadingSave = true;
            this.error = null;

            try {
                const payload = this.buildPayload();

                if (this.isEditing) {
                    await API.clients.update(this.selectedCliente.id, payload);
                } else {
                    await API.clients.create(payload);
                }

                await this.loadClientes();
                this.closeForm();
            } catch (e) {
                console.error("Error guardando cliente", e);
                this.error = e?.response?.data?.message || e.message || "Error desconocido";
            } finally {
                this.loadingSave = false;
            }
        },

        async deleteCliente(item) {
            if (!confirm(`¿Eliminar al cliente "${item.company}"?`)) return;
            try {
                await API.clients.delete(item.id);
                this.loadClientes();
            } catch (e) {
                console.error("Error eliminando cliente", e);
            }
        },
    },
};
</script>
