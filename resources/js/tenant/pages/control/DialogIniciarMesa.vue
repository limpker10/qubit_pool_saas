<template>
    <v-dialog
        :model-value="visible"
        @update:model-value="$emit('close', $event)"
        max-width="70vw"
        class="rounded-lg"
        persistent
        transition="dialog-bottom-transition"
    >
        <v-card class="rounded-lg">
            <!-- Toolbar -->
            <v-toolbar flat color="primary" class="rounded-t-lg">
                <v-toolbar-title class="d-flex align-center ga-3">
                    <v-avatar color="white" class="text-primary font-weight-bold">{{ tableNumber }}</v-avatar>
                </v-toolbar-title>

                <div class="d-flex align-center justify-space-between">
                    <span class="text-h6 font-weight-bold text-white">{{ mesaTitle }}</span>
                </div>

                <v-spacer/>

                <!-- Estado -->
                <v-chip :color="statusChip.color" size="small" variant="flat" class="text-white mr-2">
                    <v-icon start size="16">{{ statusChip.icon }}</v-icon>
                    {{ statusChip.label }}
                </v-chip>

                <!-- Tarifa -->
                <v-chip color="white" size="small" variant="tonal" class="mr-2">
                    <v-icon start size="16">mdi-cash</v-icon>
                    {{ formattedPrice }}
                </v-chip>

                <v-btn icon @click="close" aria-label="Cerrar">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-toolbar>

            <v-card-text class="pt-6">
                <v-row>
                    <!-- Columna principal -->
                    <v-col cols="12" md="8">
                        <v-form ref="formRef" v-model="isValid" @submit.prevent="onSubmit">
                            <!-- Cliente -->
                            <v-row>
                                <v-col cols="12" sm="6">
                                    <v-select
                                        label="Cliente"
                                        :items="clientes"
                                        v-model="cliente"
                                        :rules="[rules.required]"
                                        variant="outlined"
                                        density="compact"
                                        prepend-inner-icon="mdi-account"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        label="Otro cliente (opcional)"
                                        v-model="otroCliente"
                                        variant="outlined"
                                        density="compact"
                                        prepend-inner-icon="mdi-account-plus"
                                        hint="Si no está en la lista, escribe un alias"
                                        persistent-hint
                                    />
                                </v-col>
                            </v-row>

                            <!-- Tiempo -->
                            <v-expansion-panels flat class="mb-4 rounded-lg overflow-hidden border">
                                <v-expansion-panel>
                                    <v-expansion-panel-title class="font-medium">
                                        <v-icon start class="mr-2">mdi-clock-outline</v-icon>
                                        Detalle de tiempo
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text class="pt-4">
                                        <v-row>
                                            <v-col cols="12" sm="6">
                                                <v-text-field
                                                    label="Fecha y hora de inicio"
                                                    type="datetime-local"
                                                    v-model="inicio"
                                                    :rules="[rules.required]"
                                                    variant="outlined"
                                                    density="compact"
                                                    prepend-inner-icon="mdi-calendar-clock"
                                                />
                                            </v-col>
                                            <v-col cols="12" sm="6">
                                                <v-select
                                                    label="Tiempo a utilizar"
                                                    :items="tiempos"
                                                    v-model="tiempoSeleccionado"
                                                    :rules="[rules.required]"
                                                    variant="outlined"
                                                    density="compact"
                                                    prepend-inner-icon="mdi-timer-sand"
                                                />
                                                <!-- Atajos -->
                                                <div class="d-flex flex-wrap ga-2 mt-2">
                                                    <v-chip size="x-small" @click="setTiempo('30 minutos')">30m</v-chip>
                                                    <v-chip size="x-small" @click="setTiempo('1 hora')">1h</v-chip>
                                                    <v-chip size="x-small" @click="setTiempo('1.5 horas')">1.5h</v-chip>
                                                    <v-chip size="x-small" @click="setTiempo('2 horas')">2h</v-chip>
                                                    <v-chip size="x-small" color="info" variant="tonal"
                                                            @click="setTiempo('Libre : Indefinido')">
                                                        Libre
                                                    </v-chip>
                                                </div>
                                            </v-col>
                                        </v-row>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>

                            <!-- Consumo -->
                            <v-expansion-panels flat class="rounded-lg overflow-hidden border">
                                <v-expansion-panel>
                                    <v-expansion-panel-title class="font-medium">
                                        <v-icon start class="mr-2">mdi-cart-outline</v-icon>
                                        Consumo
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text class="pt-4">
                                        <v-autocomplete
                                            v-model="filters.productId"
                                            v-model:search="productSearch"
                                            :items="productItems"
                                            :loading="loadingProducts"
                                            item-title="name"
                                            item-value="id"
                                            placeholder="Producto"
                                            density="compact"
                                            hide-details
                                            :no-filter="true"
                                            clearable
                                            @update:model-value="onSelectProduct"
                                        >
                                            <template #no-data>
                                                <div class="px-4 py-2 text-caption">
                                                    {{ productSearch.length < MIN_CHARS ? `Escribe al menos ${MIN_CHARS} caracteres…` : 'Sin resultados' }}
                                                </div>
                                            </template>
                                        </v-autocomplete>
                                        <v-table density="compact" class="w-full border rounded-md overflow-hidden">
                                            <thead>
                                            <tr>
                                                <th class="text-left text-sm font-semibold p-2">Del</th>
                                                <th class="text-left text-sm font-semibold p-2">Cantidad</th>
                                                <th class="text-left text-sm font-semibold p-2">Descripción</th>
                                                <th class="text-left text-sm font-semibold p-2">Precio</th>
                                                <th class="text-left text-sm font-semibold p-2">Subtotal</th>
                                                <th class="text-left text-sm font-semibold p-2">Dsto</th>
                                                <th class="text-left text-sm font-semibold p-2">Total</th>
                                                <th class="text-left text-sm font-semibold p-2">Pagado</th>
                                                <th class="text-left text-sm font-semibold p-2">Saldo</th>
                                            </tr>
                                            </thead>
                                            <tbody v-if="tableRows.length">
                                            <tr v-for="row in tableRows" :key="row.id">
                                                <td class="p-2">{{ row.from || 'Barra' }}</td>
                                                <td class="p-2" style="width:110px">
                                                    <v-text-field
                                                        v-model.number="row.qty"
                                                        type="number"
                                                        min="1"
                                                        density="compact"
                                                        variant="outlined"
                                                        hide-details
                                                        @change="recalcRow(row)"
                                                    />
                                                </td>
                                                <td class="p-2">{{ row.name }}</td>
                                                <td class="p-2">{{ nf.format(row.price) }}</td>
                                                <td class="p-2">{{ nf.format(row.subtotal) }}</td>
                                                <td class="p-2" style="width:120px">
                                                    <v-text-field
                                                        v-model.number="row.discount"
                                                        type="number"
                                                        min="0"
                                                        density="compact"
                                                        variant="outlined"
                                                        hide-details
                                                        @change="recalcRow(row)"
                                                    />
                                                </td>
                                                <td class="p-2">{{ nf.format(row.total) }}</td>
                                                <td class="p-2" style="width:120px">
                                                    <v-text-field
                                                        v-model.number="row.paid"
                                                        type="number"
                                                        min="0"
                                                        density="compact"
                                                        variant="outlined"
                                                        hide-details
                                                        @change="recalcRow(row)"
                                                    />
                                                </td>
                                                <td class="p-2">{{ nf.format(row.balance) }}</td>
                                            </tr>
                                            </tbody>
                                            <tbody v-else>
                                            <tr>
                                                <td colspan="9" class="text-center text-medium-emphasis p-4">
                                                    No hay datos disponibles
                                                </td>
                                            </tr>
                                            </tbody>
                                        </v-table>

                                        <v-btn color="primary" class="mt-4" prepend-icon="mdi-plus" variant="tonal">
                                            Agregar items
                                        </v-btn>

                                        <v-row class="mt-6">
                                            <v-col cols="12" sm="6">
                                                <v-select
                                                    label="Método de pago"
                                                    :items="metodosPago"
                                                    v-model="metodoPago"
                                                    variant="outlined"
                                                    density="compact"
                                                    prepend-inner-icon="mdi-credit-card-outline"
                                                />
                                            </v-col>
                                            <v-col cols="12" sm="6" class="text-right d-flex flex-column align-end">
                                                <div class="text-body-1">Total: <strong
                                                    class="text-success">{{ nf.format(0) }}</strong></div>
                                                <div class="text-body-2">Pagado: <strong
                                                    class="text-info">{{ nf.format(0) }}</strong></div>
                                                <div class="text-body-2">Saldo: <strong
                                                    class="text-error">{{ nf.format(0) }}</strong></div>
                                            </v-col>
                                        </v-row>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </v-form>
                    </v-col>

                    <!-- Sidebar resumen -->
                    <v-col cols="12" md="4">
                        <v-card variant="tonal" class="rounded-lg">
                            <v-card-title class="text-subtitle-1 font-weight-semibold d-flex align-center ga-2">
                                <v-icon color="primary">mdi-information-outline</v-icon>
                                Resumen de la mesa
                            </v-card-title>
                            <v-divider/>
                            <v-card-text>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Mesa</span>
                                    <span class="font-weight-medium">#{{ tableNumber }}</span>
                                </div>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Estado</span>
                                    <span class="font-weight-medium">{{ statusChip.label }}</span>
                                </div>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Tarifa</span>
                                    <span class="font-weight-medium">{{ formattedPrice }}</span>
                                </div>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Importe (API)</span>
                                    <span class="font-weight-medium">{{ formattedAmount }}</span>
                                </div>
                                <div class="d-flex justify-space-between py-1">
                                    <span class="text-medium-emphasis">Consumo (API)</span>
                                    <span class="font-weight-medium">{{ formattedConsumption }}</span>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-card-text>

            <v-divider/>

            <v-card-actions class="p-4 rounded-b-lg d-flex justify-end ga-2">
                <v-btn  color="error" @click="close" prepend-icon="mdi-close-circle">
                    Cancelar
                </v-btn>
                <v-btn color="accent" :disabled="!isValid" variant="tonal" @click="onSubmit" prepend-icon="mdi-content-save"
                       class="rounded-md">
                    Guardar
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import API from "@/tenant/services/index.js";

export default {
    name: 'DialogIniciarMesa',
    props: {
        visible: {type: Boolean, required: true},
        // JSON de la mesa seleccionada tal como viene del backend
        mesa: {type: Object, required: true},
        currency: {type: String, default: 'PEN'},
        locale: {type: String, default: 'es-PE'},
    },
    emits: ['close', 'guardar'],
    data() {
        return {
            // formulario
            cliente: 'VARIOS',
            otroCliente: '',
            inicio: '', // datetime-local
            tiempoSeleccionado: 'Libre : Indefinido',
            metodoPago: 'A Cuenta',
            isValid: false,

            // catálogos
            clientes: ['VARIOS', 'CLIENTE 1', 'CLIENTE 2', 'CLIENTE 3'],
            tiempos: ['Libre : Indefinido', '30 minutos', '1 hora', '1.5 horas', '2 horas', '3 horas'],
            metodosPago: ['A Cuenta', 'Efectivo', 'Tarjeta'],
            // búsqueda de producto
            productItems: [],
            productSearch: '',
            loadingProducts: false,
            filters: {
                productId: null,
            },
            MIN_CHARS: 3,
            _searchTimer: null,
            tableRows: [], // ← filas de la tabla (consumo)
            // para evitar duplicados por id
            _rowIndexByProductId: new Map(),
        };
    },
    computed: {
        mesaSafe() {
            return this.mesa || {};
        },
        mesaTitle() {
            return this.mesaSafe.name || `Mesa #${this.mesaSafe.number || ''}`;
        },
        tableNumber() {
            return this.mesaSafe.number ?? '—';
        },
        statusName() {
            return this.mesaSafe?.status?.name || 'available';
        },
        statusChip() {
            const map = {
                available: {color: 'success', icon: 'mdi-check-circle', label: 'Disponible'},
                in_progress: {color: 'warning', icon: 'mdi-timer-outline', label: 'En progreso'},
                occupied: {color: 'error', icon: 'mdi-account', label: 'Ocupada'},
                maintenance: {color: 'secondary', icon: 'mdi-wrench', label: 'Mantenimiento'},
                paused: {color: 'secondary', icon: 'mdi-pause-circle', label: 'Pausada'},
                completed: {color: 'success', icon: 'mdi-check-decagram', label: 'Completada'},
                cancelled: {color: 'error', icon: 'mdi-close-circle', label: 'Cancelada'},
            };
            return map[this.statusName] || {
                color: 'secondary',
                icon: 'mdi-information-outline',
                label: this.statusName
            };
        },
        nf() {
            try {
                return new Intl.NumberFormat(this.locale, {
                    style: 'currency',
                    currency: this.currency,
                    minimumFractionDigits: 2
                });
            } catch (e) {
                return new Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN', minimumFractionDigits: 2});
            }
        },
        formattedPrice() {
            const v = Number(String(this.mesaSafe.price ?? 0).replace(/[^\d.-]/g, ''));
            return Number.isFinite(v) ? this.nf.format(v) : '—';
        },
        formattedAmount() {
            const v = Number(String(this.mesaSafe.amount ?? 0).replace(/[^\d.-]/g, ''));
            return Number.isFinite(v) ? this.nf.format(v) : this.nf.format(0);
        },
        formattedConsumption() {
            const v = Number(String(this.mesaSafe.consumption ?? 0).replace(/[^\d.-]/g, ''));
            return Number.isFinite(v) ? this.nf.format(v) : this.nf.format(0);
        },
        rules() {
            return {
                required: v => (v !== null && v !== undefined && String(v).length > 0) || 'Campo requerido',
            };
        },
        totales() {
            return this.tableRows.reduce((acc, r) => {
                acc.subtotal += r.subtotal;
                acc.discount += r.discount;
                acc.total += r.total;
                acc.paid += r.paid;
                acc.balance += r.balance;
                return acc;
            }, { subtotal: 0, discount: 0, total: 0, paid: 0, balance: 0 });
        },
    },
    watch: {
        // Cuando abre el diálogo o cambia la mesa, setea defaults
        visible(newVal) {
            if (newVal) this.seedFromMesa();
        },
        mesa: {
            handler() {
                if (this.visible) this.seedFromMesa();
            },
            deep: true,
        },
        productSearch(q) { this.debouncedRemoteSearch(q); },

    },
    mounted() {
        if (this.visible) this.seedFromMesa();
    },
    methods: {
        debouncedRemoteSearch(q) {
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this.remoteSearch(q), 350); // 300–400ms
        },
        async remoteSearch(q) {
            if (!q || q.length < this.MIN_CHARS) {
                this.productItems = [];
                return;
            }
            this.loadingProducts = true;
            try {
                // asegúrate que tu wrapper GET use { params } en Axios
                const res = await API.products.search({ search: q, limit: 10 });
                this.productItems = Array.isArray(res) ? res : (res.data ?? []);
            } catch (e) {
                console.error(e);
            } finally {
                this.loadingProducts = false;
            }
        },
        seedFromMesa() {
            // Hora de inicio: si mesa.start_time viene, úsala; si no, ahora (local)
            const start = this.mesaSafe.start_time ? new Date(this.mesaSafe.start_time) : new Date();
            this.inicio = this.toLocalDatetimeInputValue(start);

            // Defaults de selects
            this.cliente = this.cliente || 'VARIOS';
            this.tiempoSeleccionado = this.tiempoSeleccionado || 'Libre : Indefinido';
            this.metodoPago = this.metodoPago || 'A Cuenta';
        },
        toLocalDatetimeInputValue(date) {
            // Convierte Date a 'YYYY-MM-DDTHH:mm' en zona local (requerido por input datetime-local)
            const pad = n => String(n).padStart(2, '0');
            const y = date.getFullYear();
            const m = pad(date.getMonth() + 1);
            const d = pad(date.getDate());
            const hh = pad(date.getHours());
            const mm = pad(date.getMinutes());
            return `${y}-${m}-${d}T${hh}:${mm}`;
        },
        setTiempo(label) {
            this.tiempoSeleccionado = label;
        },
        async onSubmit() {
            const ok = await this.$refs.formRef?.validate();
            if (!ok) return;

            this.$emit('guardar', {
                mesa_id: this.mesaSafe.id,
                mesa_number: this.tableNumber,
                cliente: this.cliente,
                otro_cliente: this.otroCliente || null,
                inicio: this.inicio,
                tiempo: this.tiempoSeleccionado,
                status: this.statusName, // por si ocupas en backend
            });
            this.close();
        },
        close() {
            this.$emit('close', false);
        },
        onSelectProduct(productId) {
            if (!productId) return; // limpieza del autocomplete
            const prod = this.productItems.find(p => p.id === productId);
            if (!prod) return;

            // Convierte "4.0000" -> 4
            const parseMoney = v => {
                if (v == null) return 0;
                const n = Number(String(v).replace(/[^\d.-]/g, ''));
                return Number.isFinite(n) ? n : 0;
            };

            // Toma el precio de venta por defecto del JSON
            const priceNum = parseMoney(prod.default_sale_price);

            if (this._rowIndexByProductId.has(prod.id)) {
                const idx = this._rowIndexByProductId.get(prod.id);
                this.tableRows[idx].qty += 1;
                this.recalcRow(this.tableRows[idx]);
            } else {
                const row = {
                    id: prod.id,
                    name: prod.name,                  // "Empanada"
                    sku: prod.sku || null,            // "12" (opcional, para mostrar)
                    price: priceNum,                  // 4.0000 -> 4
                    qty: 1,
                    discount: 0,
                    paid: 0,
                    from: 'Barra',
                    subtotal: 0,
                    total: 0,
                    balance: 0,
                };
                this.recalcRow(row);
                this.tableRows.push(row);
                this._rowIndexByProductId.set(prod.id, this.tableRows.length - 1);
            }

            // limpia el autocomplete para la siguiente búsqueda
            this.filters.productId = null;
            this.productSearch = '';
        },
        recalcRow(row) {
            // Asegura números válidos
            row.qty = Math.max(1, Number(row.qty) || 1);
            row.price = Number(row.price) || 0;
            row.discount = Math.max(0, Number(row.discount) || 0);
            row.paid = Math.max(0, Number(row.paid) || 0);

            row.subtotal = row.qty * row.price;
            row.total = Math.max(0, row.subtotal - row.discount);
            row.balance = Math.max(0, row.total - row.paid);
        },
    },
};
</script>

<style scoped>
.border {
    border: 1px solid rgba(0, 0, 0, .08);
}
</style>
