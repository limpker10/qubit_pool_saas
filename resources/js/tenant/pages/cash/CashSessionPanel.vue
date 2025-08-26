<!-- CashSessionPanel.vue -->
<template>
    <v-card class="rounded-lg">
        <v-toolbar flat color="primary" class="rounded-t-lg">
            <v-toolbar-title class="text-white d-flex align-center ga-2">
                <v-icon>mdi-cash-register</v-icon>
                Caja
            </v-toolbar-title>
            <v-spacer/>
            <v-chip v-if="session" variant="elevated" :color="session.status === 'open' ? 'success' : 'secondary'"
                    class="mr-2">
                <v-icon start>mdi-circle</v-icon>
                {{ session.status === 'open' ? 'Abierta' : 'Cerrada' }}
            </v-chip>
            <v-btn icon @click="reload" :disabled="loading" aria-label="Recargar">
                <v-icon>mdi-refresh</v-icon>
            </v-btn>
        </v-toolbar>

        <v-card-text>
            <v-row>
                <!-- Panel apertura / estado -->
                <v-col cols="12" md="4">
                    <v-card variant="tonal" class="mb-4">
                        <v-card-title class="text-subtitle-1 font-weight-semibold d-flex align-center ga-2">
                            <v-icon color="primary">mdi-door-open</v-icon>
                            Estado de la caja
                        </v-card-title>
                        <v-divider/>
                        <v-card-text>
                            <div v-if="!session || session.status === 'closed'">
                                <v-form ref="openFormRef" v-model="openFormValid" @submit.prevent="openSession">
                                    <v-text-field
                                        v-model.number="openingCash"
                                        label="Efectivo inicial"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        density="compact"
                                        variant="outlined"
                                        prepend-inner-icon="mdi-cash"
                                        :rules="[rules.required, rules.nonNegative]"
                                    />
                                    <v-btn color="primary" class="mt-2" :loading="loading" @click="openSession"
                                           prepend-icon="mdi-lock-open-variant">
                                        Abrir caja
                                    </v-btn>
                                </v-form>
                            </div>

                            <div v-else>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Apertura</span>
                                    <span class="font-weight-medium">{{ dt(session.opened_at) }}</span>
                                </div>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Efectivo inicial</span>
                                    <span class="font-weight-medium">{{ nf(session.opening_cash) }}</span>
                                </div>
                                <v-divider class="my-2"/>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Esperado</span>
                                    <span class="font-weight-medium">{{ nf(expectedCash) }}</span>
                                </div>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Ingresos</span>
                                    <span class="font-weight-medium">{{ nf(inflows) }}</span>
                                </div>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Egresos</span>
                                    <span class="font-weight-medium">{{ nf(outflows) }}</span>
                                </div>
                                <v-divider class="my-2"/>
                                <v-form ref="closeFormRef" v-model="closeFormValid" @submit.prevent="closeSession">
                                    <v-text-field
                                        v-model.number="countedCash"
                                        label="Efectivo contado"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        density="compact"
                                        variant="outlined"
                                        prepend-inner-icon="mdi-counter"
                                        :rules="[rules.required, rules.nonNegative]"
                                    />
                                    <div class="d-flex justify-space-between py-1">
                                        <span class="text-medium-emphasis">Diferencia</span>
                                        <span :class="difference >= 0 ? 'text-success' : 'text-error'"
                                              class="font-weight-bold">{{ nf(difference) }}</span>
                                    </div>
                                    <v-btn color="secondary" class="mt-2" :loading="loading" @click="closeSession"
                                           prepend-icon="mdi-lock">
                                        Cerrar caja
                                    </v-btn>
                                </v-form>
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- Agregar movimiento manual -->
                    <v-card variant="tonal">
                        <v-card-title class="text-subtitle-1 font-weight-semibold d-flex align-center ga-2">
                            <v-icon color="primary">mdi-swap-vertical</v-icon>
                            Movimiento manual
                        </v-card-title>
                        <v-divider/>
                        <v-card-text>
                            <v-form ref="movFormRef" v-model="movFormValid" @submit.prevent="addMovement">
                                <v-select
                                    v-model="movement.type"
                                    :items="movementTypes"
                                    label="Tipo"
                                    density="compact"
                                    variant="outlined"
                                    :disabled="!session || session.status !== 'open'"
                                    :rules="[rules.required]"
                                    prepend-inner-icon="mdi-shape"
                                />
                                <v-text-field
                                    v-model.number="movement.amount"
                                    label="Monto"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    density="compact"
                                    variant="outlined"
                                    :disabled="!session || session.status !== 'open'"
                                    :rules="[rules.required, rules.nonNegative]"
                                    prepend-inner-icon="mdi-currency-usd"
                                />
                                <v-text-field
                                    v-model="movement.description"
                                    label="Descripción"
                                    density="compact"
                                    variant="outlined"
                                    :disabled="!session || session.status !== 'open'"
                                    prepend-inner-icon="mdi-text"
                                />
                                <v-btn color="primary" class="mt-2" :loading="loading"
                                       :disabled="!session || session.status !== 'open'" @click="addMovement"
                                       prepend-icon="mdi-plus-circle">
                                    Agregar
                                </v-btn>
                            </v-form>
                        </v-card-text>
                    </v-card>
                </v-col>

                <!-- Lista de movimientos -->
                <v-col cols="12" md="8">
                    <v-card>
                        <v-card-title class="text-subtitle-1 font-weight-semibold d-flex align-center ga-2">
                            <v-icon color="primary">mdi-format-list-bulleted</v-icon>
                            Movimientos del turno
                            <v-spacer/>
                            <v-chip size="small" variant="tonal">{{ movements.length }} items</v-chip>
                        </v-card-title>
                        <v-divider/>
                        <v-card-text>
                            <v-table density="compact" class="border rounded">
                                <thead>
                                <tr>
                                    <th class="text-left p-2">Fecha</th>
                                    <th class="text-left p-2">Tipo</th>
                                    <th class="text-right p-2">Monto</th>
                                    <th class="text-left p-2">Descripción</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="m in movements" :key="m.id">
                                    <td class="p-2">{{ dt(m.created_at) }}</td>
                                    <td class="p-2">{{ typeLabel(m.type) }}</td>
                                    <td class="p-2 text-right" :class="m.amount >= 0 ? 'text-success' : 'text-error'">
                                        {{ nf(m.amount) }}
                                    </td>
                                    <td class="p-2">{{ m.description || '—' }}</td>
                                </tr>
                                <tr v-if="!movements.length">
                                    <td colspan="4" class="text-center text-medium-emphasis p-6">Sin movimientos</td>
                                </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="2500">{{ snackbar.text }}</v-snackbar>
        </v-card-text>
    </v-card>
</template>

<script>
import API from "@/services/index.js";

export default {
    name: 'CashSessionPanel',
    data() {
        return {
            loading: false,
            session: null,
            movements: [],

            // apertura
            openingCash: 0,
            openFormValid: false,

            // cierre
            countedCash: 0,
            closeFormValid: false,

            // movimiento manual
            movement: {type: 'income', amount: 0, description: ''},
            movFormValid: false,
            movementTypes: [
                {title: 'Ingreso', value: 'income'},
                {title: 'Egreso', value: 'expense'},
                {title: 'Retiro', value: 'withdrawal'},
                {title: 'Devolución', value: 'refund'},
                {title: 'Ajuste', value: 'adjust'},
            ],

            snackbar: {show: false, text: '', color: 'success'},

            rules: {
                required: v => (v !== null && v !== undefined && String(v) !== '') || 'Campo requerido',
                nonNegative: v => (Number(v) >= 0) || 'Debe ser ≥ 0',
            },
        };
    },
    computed: {
        expectedCash() {
            // esperado = suma de amounts (incluida apertura)
            return this.movements.reduce((acc, m) => acc + Number(m.amount || 0), 0);
        },
        inflows() {
            return this.movements.filter(m => m.amount > 0).reduce((a, m) => a + Number(m.amount || 0), 0);
        },
        outflows() {
            return Math.abs(this.movements.filter(m => m.amount < 0).reduce((a, m) => a + Number(m.amount || 0), 0));
        },
        difference() {
            return Number((Number(this.countedCash || 0) - Number(this.expectedCash || 0)).toFixed(2));
        },
    },
    mounted() {
        this.reload();
    },
    methods: {
        nf(n) {
            try {
                return new Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN'}).format(Number(n || 0));
            } catch {
                return Number(n || 0).toFixed(2);
            }
        },
        dt(s) {
            const d = new Date(s);
            return isNaN(d) ? '—' : d.toLocaleString();
        },
        typeLabel(t) {
            return ({
                open: 'Apertura',
                sale: 'Venta',
                income: 'Ingreso',
                expense: 'Egreso',
                withdrawal: 'Retiro',
                refund: 'Devolución',
                adjust: 'Ajuste'
            })[t] || t;
        },

        async reload() {
            try {
                this.loading = true;
                // const { data } = await axios.get('/api/cash-sessions/current');
                const response = await API.cash_sessions.current();
                console.log(response)
                this.session = response?.session || null;
                this.movements = response?.movements || [];
                if (this.session && this.session.status === 'open') {
                    this.openingCash = Number(this.session.opening_cash || 0);
                }
            } catch (e) {
                this.toast(e?.response?.data?.message || 'No se pudo cargar la caja', 'error');
            } finally {
                this.loading = false;
            }
        },

        async openSession() {
            const ok = await this.$refs.openFormRef?.validate();
            if (!ok) return;
            try {
                this.loading = true;
                // const { data } = await axios.post('/api/cash-sessions/open', { opening_cash: Number(this.openingCash || 0) });
                const response = await API.cash_sessions.open({opening_cash: Number(this.openingCash || 0)});
                console.log(response)
                this.session = response.session;
                this.movements = response.movements || [];
                this.toast('Caja abierta');
            } catch (e) {
                this.toast(e?.response?.message || 'No se pudo abrir la caja', 'error');
            } finally {
                this.loading = false;
            }
        },

        async addMovement() {
            const ok = await this.$refs.movFormRef?.validate();
            if (!ok) return;
            if (!this.session || this.session.status !== 'open') return this.toast('No hay caja abierta', 'error');
            try {
                this.loading = true;
                const payload = {
                    type: this.movement.type,
                    amount: Number(this.movement.amount || 0),
                    description: this.movement.description || null
                };
                // const { data } = await axios.post(`/api/cash-sessions/${this.session.id}/movements`, payload);
                const response = await API.cash_sessions.movements_create(this.session.id, payload);
                this.movements = response.movements;
                this.movement = {type: 'income', amount: 0, description: ''};
                this.toast('Movimiento agregado');
            } catch (e) {
                this.toast(e?.response?.data?.message || 'No se pudo agregar el movimiento', 'error');
            } finally {
                this.loading = false;
            }
        },

        async closeSession() {
            const ok = await this.$refs.closeFormRef?.validate();
            if (!ok) return;
            if (!this.session || this.session.status !== 'open') return this.toast('No hay caja abierta', 'error');
            try {
                this.loading = true;
                const payload = {counted_cash: Number(this.countedCash || 0), create_adjust: true};
                // const { data } = await axios.post(`/api/cash-sessions/${this.session.id}/close`, payload);
                const response = await API.cash_sessions.close(this.session.id, payload);
                this.session = response.session;
                this.movements = response.movements || this.movements;
                this.toast('Caja cerrada');
            } catch (e) {
                this.toast(e?.response?.data?.message || 'No se pudo cerrar la caja', 'error');
            } finally {
                this.loading = false;
            }
        },

        toast(text, color = 'success') {
            this.snackbar = {show: true, text, color};
        },
    },
};
</script>

<style scoped>
.border {
    border: 1px solid rgba(0, 0, 0, .08);
}
</style>
