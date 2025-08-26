<template>
    <v-container>
        <!-- Title & Controls -->
        <div class="d-flex align-center">
            <h1 class="text-h5 font-weight-bold">Mesas de Pool</h1>
            <v-spacer/>
            <v-btn class="mr-2" color="primary" prepend-icon="mdi-plus" @click="openCreate">
                Nueva mesa
            </v-btn>
            <v-btn icon variant="text" :loading="loading" @click="fetchTables">
                <v-icon>mdi-refresh</v-icon>
            </v-btn>
        </div>

        <!-- Grid -->
        <v-row v-if="tables.length" dense>
            <v-col v-for="t in tables" :key="t.id" cols="12" sm="6" md="4" lg="3">
                <PoolTableCard
                    :table="t"
                    :image-field="'image'" :max-size-m-b="4" :cache-bust="true"

                    @start="startTable"
                    @finish="openFinish"
                    @cancel="cancelTable"
                    @edit="openEdit"

                    @cover-updated="coverUpdated"
                    @upload-error="onUploadError"
                    @update-consumption="onUpdateConsumption"
                />
            </v-col>
        </v-row>

        <v-empty-state
            v-else
            headline="Sin mesas"
            title="No hay mesas para mostrar"
            text="Crea mesas desde el módulo de administración o ajusta los filtros."
        />

        <!-- Pagination -->
        <div class="d-flex justify-end mt-3">
            <v-pagination
                v-model="page"
                :length="pages"
                :total-visible="7"
                @update:model-value="fetchTables"
            />
        </div>

        <!-- Finish Dialog -->
        <v-dialog v-model="dialogs.finish" max-width="560">
            <v-card>
                <v-card-title class="text-h6">
                    Finalizar y cobrar — Mesa #{{ current && current.number }}
                </v-card-title>
                <v-card-text>
                    <v-form ref="finishFormRef" v-model="finishValid">
                        <v-row>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model.number="finishForm.consumption"
                                    type="number" min="0" step="0.1"
                                    label="Consumo (PEN)"
                                />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="finishForm.payment_method"
                                    :items="paymentMethods"
                                    label="Método de pago"
                                    :rules="[req]"
                                />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model.number="finishForm.rate_per_hour"
                                    type="number" min="0" step="0.1"
                                    label="Tarifa cierre (opcional)"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model.number="finishForm.discount"
                                    type="number" min="0" step="0.1"
                                    label="Descuento (opcional)"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model.number="finishForm.surcharge"
                                    type="number" min="0" step="0.1"
                                    label="Recargo (opcional)"
                                />
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer/>
                    <v-btn variant="text" @click="dialogs.finish = false">Cancelar</v-btn>
                    <v-btn color="success" :loading="loadingAction" @click="submitFinish">Cobrar</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Crear/Editar -->
        <PoolTableFormDialog
            v-model="showForm"
            :table="editingTable"
            @saved="onSavedTable"
        />

        <!-- Snackbar -->
        <v-snackbar v-model="snack.show" :color="snack.color" timeout="3500">
            {{ snack.text }}
        </v-snackbar>
    </v-container>
</template>

<script>
import PoolTableCard from "@/tenant/pages/control/PoolTableCard.vue";
import PoolTableFormDialog from "@/tenant/pages/control/MesaForm.vue";
import API from "@/tenant/services/index.js";

export default {
    name: "PoolTablesGrid",
    components: {PoolTableCard, PoolTableFormDialog},
    data() {
        return {
            loading: false,
            loadingAction: false,

            tables: [],
            total: 0,
            page: 1,
            perPage: 12,
            pages: 1,

            filters: {status: null, number: null},
            statusItems: [
                {title: "Disponible", value: "available"},
                {title: "En juego", value: "in_progress"},
                {title: "Pausada", value: "paused"},
                {title: "Cancelada", value: "cancelled"},
            ],

            // finish dialog
            dialogs: {finish: false},
            current: null,
            finishForm: {consumption: 0, payment_method: "cash", rate_per_hour: null, discount: 0, surcharge: 0},
            finishValid: false,

            // crear/editar
            showForm: false,
            editingTable: null,

            snack: {show: false, text: "", color: "success"},

            paymentMethods: [
                {title: "Efectivo", value: "cash"},
                {title: "Tarjeta", value: "card"},
                {title: "Transferencia", value: "transfer"},
                {title: "Otro", value: "other"},
            ],
        };
    },
    mounted() {
        this.fetchTables();
    },
    methods: {
        req(v) {
            return (v !== null && v !== undefined && String(v).trim() !== "") || "Requerido";
        },

        async fetchTables() {
            this.loading = true;
            try {
                const params = {per_page: this.perPage, page: this.page};
                if (this.filters.status) params.status = this.filters.status;
                if (this.filters.number) params.number = String(this.filters.number);

                const pg = await API.pool_tables.list(params);
                console.log(pg)
                this.tables = pg.data || [];
                this.total = pg.total || 0;
                this.pages = pg.last_page || Math.ceil((this.total || 0) / this.perPage) || 1;
                this.page = pg.current_page || this.page;
            } catch (e) {
                this.error(e);
            } finally {
                this.loading = false;
            }
        },

        applyFilters() {
            this.page = 1;
            this.fetchTables();
        },

        // ---- Acciones ----
        async startTable(table) {
            this.loadingAction = true;
            try {
                const res = await API.pool_tables.start(table.id);
                const data = res?.data ?? res;
                const updated = data.table || data;
                this.updateRow(updated);
                this.ok(`Mesa #${updated.number} iniciada`);
            } catch (e) {
                this.error(e);
            } finally {
                this.loadingAction = false;
            }
        },

        openFinish(table) {
            this.current = table;
            this.finishForm = {
                consumption: Number(table.consumption || 0),
                payment_method: "cash",
                rate_per_hour: null,
                discount: 0,
                surcharge: 0,
            };
            this.dialogs.finish = true;
        },

        async submitFinish() {
            const formOk = await this.$refs.finishFormRef?.validate();
            if (!formOk?.valid || !this.current) return;

            this.loadingAction = true;
            try {
                const payload = {
                    consumption: Number(this.finishForm.consumption || 0),
                    payment_method: this.finishForm.payment_method,
                };
                if (this.finishForm.rate_per_hour != null) payload.rate_per_hour = Number(this.finishForm.rate_per_hour);
                if (this.finishForm.discount) payload.discount = Number(this.finishForm.discount);
                if (this.finishForm.surcharge) payload.surcharge = Number(this.finishForm.surcharge);

                const res = await API.pool_tables.finish(this.current.id, payload);
                const data = res?.data ?? res;

                this.updateRow(data.table);
                this.ok(`Cobrado NV ${data.document.series}-${String(data.document.number).padStart(4, "0")} · Total S/ ${Number(data.document.total).toFixed(2)}`);
                this.dialogs.finish = false;
            } catch (e) {
                this.error(e);
            } finally {
                this.loadingAction = false;
            }
        },

        async cancelTable(table) {
            this.loadingAction = true;
            try {
                const res = await API.pool_tables.cancel(table.id);
                const updated = res?.data ?? res;
                this.updateRow(updated);
                this.ok("Mesa cancelada");
            } catch (e) {
                this.error(e);
            } finally {
                this.loadingAction = false;
            }
        },

        openCreate() {
            this.editingTable = null;
            this.showForm = true;
        },
        openEdit(table) {
            this.editingTable = table;
            this.showForm = true;
        },
        onSavedTable() {
            // refresca la lista tras crear/editar
            this.fetchTables();
        },

        // ---- Integraciones con PoolTableCard ----
        coverUpdated({tableId, url}) {
            // reflejar la nueva portada en el registro local
            const i = this.tables.findIndex(r => r.id === tableId);
            if (i >= 0) {
                const updated = {...this.tables[i], cover_url: url};
                this.tables.splice(i, 1, updated);
            }
            this.ok("Portada actualizada");
        },

        onUploadError({tableId, message}) {
            console.warn("upload-error", tableId, message);
            this.snack = {show: true, text: message || "No se pudo subir la imagen.", color: "error"};
        },

        onUpdateConsumption({tableId, total /*, items*/}) {
            // actualiza el consumo de la mesa, útil para cuando el usuario abre el diálogo de cobro luego del POS
            const i = this.tables.findIndex(r => r.id === tableId);
            if (i >= 0) {
                const updated = {...this.tables[i], consumption: Number(total || 0)};
                this.tables.splice(i, 1, updated);
            }
        },

        // ---- Util ----
        updateRow(updated) {
            const i = this.tables.findIndex(r => r.id === updated.id);
            if (i >= 0) this.tables.splice(i, 1, {...this.tables[i], ...updated});
            else this.tables.unshift(updated);
        },

        ok(text) {
            this.snack = {show: true, text, color: "success"};
        },
        error(err) {
            const msg = err?.response?.data?.message || err?.message || "Ocurrió un error";
            this.snack = {show: true, text: msg, color: "error"};
        },
    },
};
</script>

<style scoped>
</style>
