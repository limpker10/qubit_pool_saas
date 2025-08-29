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
                    :image-field="'image'"
                    :max-size-m-b="4"
                    :cache-bust="true"

                    @start="startTable"
                    @finish="openFinish"
                    @cancel="cancelTable"
                    @edit="openEdit"

                    @cover-updated="coverUpdated"
                    @upload-error="onUploadError"

                    @open-pos="openPosForTable"
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
        <v-dialog v-model="dialogs.finish" max-width="840">
            <v-card class="rounded-xl">
                <!-- Header -->
                <v-card-title class="d-flex align-center text-h6 py-3">
                    <v-icon class="mr-2">mdi-cash-register</v-icon>
                    Finalizar y cobrar — Mesa #{{ current?.number ?? '—' }}
                    <v-spacer />
                    <v-chip size="small" v-if="current?.active_rental?.is_open" color="green" variant="flat">
                        En juego · {{ current?.active_rental?.duration_human || '—' }}
                    </v-chip>
                </v-card-title>

                <v-divider />

                <!-- Body -->
                <v-card-text class="pt-4">
                    <v-form ref="finishFormRef" v-model="finishValid">
                        <v-row>
                            <!-- LEFT: Form -->
                            <v-col cols="12" md="7">
                                <v-row dense>
                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model.number="finishForm.rate_per_hour"
                                            type="number" min="0" step="0.1"
                                            label="Tarifa cierre (S/ por hora)"
                                            prepend-inner-icon="mdi-timer-sand"
                                            variant="outlined" density="comfortable"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model.number="finishForm.consumption"
                                            type="number" min="0" step="0.1"
                                            label="Consumo (S/)"
                                            prepend-inner-icon="mdi-cart"
                                            variant="outlined" density="comfortable"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model.number="finishForm.discount"
                                            type="number" min="0" step="0.1"
                                            label="Descuento (S/)"
                                            prepend-inner-icon="mdi-ticket-percent"
                                            variant="outlined" density="comfortable"
                                        />
                                        <div class="mt-2 d-flex gap-2">
                                            <span class="text-caption text-medium-emphasis mr-2">Rápidos:</span>
                                            <v-chip size="small" @click="applyDiscountPct(0)">0%</v-chip>
                                            <v-chip size="small" @click="applyDiscountPct(5)">5%</v-chip>
                                            <v-chip size="small" @click="applyDiscountPct(10)">10%</v-chip>
                                        </div>
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model.number="finishForm.surcharge"
                                            type="number" min="0" step="0.1"
                                            label="Recargo (S/)"
                                            prepend-inner-icon="mdi-cash-plus"
                                            variant="outlined" density="comfortable"
                                        />
                                    </v-col>

                                    <v-col cols="12">
                                        <div class="text-subtitle-2 mb-2">Método de pago</div>
                                        <v-btn-toggle v-model="finishForm.payment_method" mandatory>
                                            <v-btn value="cash" prepend-icon="mdi-cash">Efectivo</v-btn>
                                            <v-btn value="card" prepend-icon="mdi-credit-card-outline">Tarjeta</v-btn>
                                            <v-btn value="transfer" prepend-icon="mdi-bank-transfer">Transferencia</v-btn>
                                            <v-btn value="other" prepend-icon="mdi-dots-horizontal">Otro</v-btn>
                                        </v-btn-toggle>
                                    </v-col>

                                    <v-col cols="12" md="6" v-if="finishForm.payment_method === 'cash'">
                                        <v-text-field
                                            v-model.number="finishForm.tendered"
                                            type="number" min="0" step="0.1"
                                            label="Recibido (S/)"
                                            prepend-inner-icon="mdi-hand-coin"
                                            variant="outlined" density="comfortable"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="6" v-if="finishForm.payment_method === 'cash'">
                                        <v-text-field
                                            :model-value="money(change)"
                                            label="Vuelto"
                                            prepend-inner-icon="mdi-cash-refund"
                                            variant="outlined" density="comfortable"
                                            readonly
                                        />
                                    </v-col>

                                    <v-col cols="12">
                                        <v-alert
                                            type="warning" density="compact" variant="tonal"
                                            v-if="finishForm.payment_method === 'cash' && finishForm.tendered > 0 && change < 0"
                                        >
                                            El monto recibido es menor al total.
                                        </v-alert>
                                    </v-col>
                                </v-row>
                            </v-col>

                            <!-- RIGHT: Resumen -->
                            <v-col cols="12" md="5">
                                <v-card class="rounded-lg elevation-1">
                                    <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center">
                                        Resumen
                                        <v-spacer />
                                        <v-chip size="small" label>
                                            {{ current?.active_rental?.started_at ? new Date(current.active_rental.started_at).toLocaleTimeString() : '—' }}
                                        </v-chip>
                                    </v-card-title>
                                    <v-divider />
                                    <v-card-text class="py-2">
                                        <div class="d-flex justify-space-between my-1">
                                            <span class="text-body-2 text-medium-emphasis">Tiempo ({{ durationHuman || '—' }})</span>
                                            <span class="text-body-2">{{ money(amountTime) }}</span>
                                        </div>
                                        <div class="d-flex justify-space-between my-1">
                                            <span class="text-body-2 text-medium-emphasis">Consumo</span>
                                            <span class="text-body-2">{{ money(Number(finishForm.consumption || 0)) }}</span>
                                        </div>
                                        <div class="d-flex justify-space-between my-1">
                                            <span class="text-body-2 text-medium-emphasis">Descuento</span>
                                            <span class="text-body-2">- {{ money(Number(finishForm.discount || 0)) }}</span>
                                        </div>
                                        <div class="d-flex justify-space-between my-1">
                                            <span class="text-body-2 text-medium-emphasis">Recargo</span>
                                            <span class="text-body-2">+ {{ money(Number(finishForm.surcharge || 0)) }}</span>
                                        </div>

                                        <v-divider class="my-3" />

                                        <div class="d-flex justify-space-between">
                                            <span class="text-subtitle-1 font-weight-bold">Total a cobrar</span>
                                            <span class="text-subtitle-1 font-weight-bold">{{ money(totalToPay) }}</span>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-divider />

                <!-- Footer -->
                <v-card-actions class="py-3 px-4">
                    <v-spacer />
                    <v-btn variant="text" @click="dialogs.finish = false">Cancelar</v-btn>
                    <v-btn
                        color="success"
                        :loading="loadingAction"
                        :disabled="!canSubmit"
                        @click="submitFinish"
                    >
                        Cobrar {{ money(totalToPay) }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>


        <!-- Crear/Editar -->
        <PoolTableFormDialog
            v-model="showForm"
            :table="editingTable"
            @saved="onSavedTable"
        />

        <!-- POS Consumo -->
        <ConsumptionPOSDialog
            v-model="dialogs.pos"
            :table-number="current?.number"
            :rental-id="pos.rentalId"
            :existing-items="pos.existingItems"
            @confirm="onPosConfirm"
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
import ConsumptionPOSDialog from "@/tenant/pages/control/ConsumptionPOSDialog.vue";

export default {
    name: "PoolTablesGrid",
    components: {ConsumptionPOSDialog, PoolTableCard, PoolTableFormDialog},
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
            dialogs: {finish: false, pos: false},
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

            pos: {
                rentalId: null,
                existingItems: []
            },
        };
    },
    computed: {
        // Duración legible del rental activo (si viene del backend)
        durationHuman() {
            return this.current?.active_rental?.duration_human || null
        },
        // Segundos para estimar monto por tiempo
        elapsedSeconds() {
            return Number(this.current?.active_rental?.duration_seconds ?? 0)
        },
        // Tarifa efectiva a usar en el cálculo de tiempo (fallback a rate/h de la mesa)
        rateToUse() {
            const formRate = Number(this.finishForm.rate_per_hour ?? 0)
            if (formRate > 0) return formRate
            const rentalRate = Number(this.current?.active_rental?.rate_per_hour ?? 0)
            const tableRate  = Number(this.current?.rate_per_hour ?? 0)
            return rentalRate || tableRate || 0
        },
        // Monto por tiempo (estimado en UI; el backend recalculará al cerrar)
        amountTime() {
            if (!this.elapsedSeconds || !this.rateToUse) return 0
            return Math.round(((this.elapsedSeconds / 3600) * this.rateToUse) * 100) / 100
        },
        // Total según backend (amount_time + consumption - discount + surcharge)
        totalToPay() {
            const c = Number(this.finishForm.consumption || 0)
            const d = Number(this.finishForm.discount || 0)
            const s = Number(this.finishForm.surcharge || 0)
            return Math.max(0, Math.round((this.amountTime + c - d + s) * 100) / 100)
        },
        change() {
            if (this.finishForm.payment_method !== 'cash') return 0
            return Math.round((Number(this.finishForm.tendered || 0) - this.totalToPay) * 100) / 100
        },
        canSubmit() {
            const methodOk = !!this.finishForm.payment_method
            const totalOk  = this.totalToPay >= 0
            const cashOk   = this.finishForm.payment_method !== 'cash' || Number(this.finishForm.tendered || 0) >= this.totalToPay
            return this.finishValid && methodOk && totalOk && cashOk
        },
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
                console.log(e)
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

        money(n) {
            return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(Number(n || 0))
        },
        // Chips rápidos para descuento porcentual
        applyDiscountPct(pct) {
            const base = Number(this.finishForm.consumption || 0) + this.amountTime
            this.finishForm.discount = Math.round((base * (pct / 100)) * 100) / 100
        },

        // Al abrir el diálogo, precarga valores amigables
        openFinish(table) {
            this.current = table
            // Preferir consumo del rental si viene fresco
            const rentalConsumption = Number(table?.active_rental?.consumption ?? table?.consumption ?? 0)
            const rentalRate        = Number(table?.active_rental?.rate_per_hour ?? table?.rate_per_hour ?? 0)

            this.finishForm = {
                consumption: rentalConsumption,
                payment_method: "cash",
                rate_per_hour: rentalRate || null,
                discount: 0,
                surcharge: 0,
                tendered: 0,
            }
            this.dialogs.finish = true
            this.$nextTick(() => this.$refs.finishFormRef?.resetValidation?.())
        },

        async submitFinish() {
            const formOk = await this.$refs.finishFormRef?.validate()
            if (!formOk?.valid || !this.current) return

            this.loadingAction = true
            try {
                const payload = {
                    rental_id: this.current?.active_rental?.id,
                    consumption: this.finishForm.consumption,
                    payment_method: this.finishForm.payment_method,
                    // El backend recalcula amount_time con rate/ended_at; enviamos overrides opcionales:
                    rate_per_hour: this.finishForm.rate_per_hour ?? undefined,
                    discount: this.finishForm.discount || 0,
                    surcharge: this.finishForm.surcharge || 0,
                    tendered: this.finishForm.payment_method === 'cash' ? (this.finishForm.tendered || 0) : undefined,
                }

                const res = await API.pool_tables.finish(this.current.id, payload)
                const data = res?.data ?? res
                this.updateRow(data.table)
                this.ok(`Cobrado NV ${data.document.series}-${String(data.document.number).padStart(4, "0")} · Total ${this.money(data.document.total)}`)
                this.dialogs.finish = false
            } catch (e) {
                this.error(e)
            } finally {
                this.loadingAction = false
            }
        },

        // async submitFinish() {
        //     const formOk = await this.$refs.finishFormRef?.validate();
        //     if (!formOk?.valid || !this.current) return;
        //
        //     this.loadingAction = true;
        //     try {
        //         const payload = {
        //             consumption: Number(this.finishForm.consumption || 0),
        //             payment_method: this.finishForm.payment_method,
        //         };
        //         if (this.finishForm.rate_per_hour != null) payload.rate_per_hour = Number(this.finishForm.rate_per_hour);
        //         if (this.finishForm.discount) payload.discount = Number(this.finishForm.discount);
        //         if (this.finishForm.surcharge) payload.surcharge = Number(this.finishForm.surcharge);
        //
        //         const res = await API.pool_tables.finish(this.current.id, payload);
        //         const data = res?.data ?? res;
        //
        //         this.updateRow(data.table);
        //         this.ok(`Cobrado NV ${data.document.series}-${String(data.document.number).padStart(4, "0")} · Total S/ ${Number(data.document.total).toFixed(2)}`);
        //         this.dialogs.finish = false;
        //     } catch (e) {
        //         this.error(e);
        //     } finally {
        //         this.loadingAction = false;
        //     }
        // },

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
        async openPosForTable(t) {
            try {
                this.current = t

                // 1) obtener/crear rental abierto
                let rental = t.active_rental
                if (!rental) {
                    const res  = await API.pool_tables.start(t.id)
                    const data = res?.data ?? res
                    rental = data?.rental ?? data?.data?.rental ?? null

                    // sincroniza la mesa en la grilla
                    const updatedTable = data?.table ?? data?.data?.table ?? null
                    if (updatedTable) this.updateRow(updatedTable)

                    // fallback: si el backend ya devuelve active_rental en table
                    if (!rental && updatedTable?.active_rental) rental = updatedTable.active_rental
                }
                if (!rental?.id) {
                    this.error('No se pudo obtener el alquiler abierto de la mesa.')
                    return
                }

                // 2) cargar items guardados
                const resItems = await API.rental_items.list(rental.id)
                console.log(resItems)
                const itemsRaw = resItems.items;
                const existingItems = (itemsRaw || []).map(it => ({
                    id: it.id,
                    product_id: it.product_id,
                    name: it.product_name,
                    unit_id: it.unit_id,
                    unit_name: it.unit_name,
                    qty: Number(it.qty),
                    price: Number(it.unit_price),
                    discount: Number(it.discount || 0),
                    subtotal: Number(it.total),
                    created_at: it.created_at,
                    _status: it.status,
                }))

                // 3) abrir POS
                this.pos.rentalId = rental.id
                this.pos.existingItems = existingItems
                this.dialogs.pos = true
            } catch (e) {
                this.error(e)
            }
        },

        async onPosConfirm({ items, total }) {
            try {
                if (!this.pos.rentalId) return

                // mapea al payload del backend
                const payload = {
                    items: items.map((r, idx) => ({
                        product_id: r.id ?? r.product_id ?? null,
                        qty: Number(r.qty || 0),
                        unit_price: Number(r.price || 0),
                        discount: Number(r.discount || 0),
                        client_op_id: r.client_op_id ?? `pos-${Date.now()}-${idx}`,
                    }))
                }

                const res = await API.rental_items.items_bulk(this.pos.rentalId, payload)
                const data = res?.data ?? res

                // refresca consumo y active_rental en la mesa actual
                const rentalUpdated = data?.rental
                const tableId = this.current?.id
                const i = this.tables.findIndex(r => r.id === tableId)
                if (i >= 0) {
                    const updated = {
                        ...this.tables[i],
                        consumption: Number(rentalUpdated?.consumption ?? this.tables[i].consumption ?? 0),
                        active_rental: rentalUpdated ? { ...rentalUpdated } : this.tables[i].active_rental
                    }
                    this.tables.splice(i, 1, updated)
                    this.current = updated
                }

                this.ok('Consumo aplicado')
                this.dialogs.pos = false
            } catch (e) {
                this.error(e)
            }
        },
    },
};
</script>

<style scoped>
</style>
