<template>
    <v-dialog v-model="internalDialog" max-width="720" persistent>
        <v-card>
            <v-toolbar density="comfortable" color="primary" :title="title">
                <template #append>
                    <v-btn icon variant="text" @click="close"><v-icon>mdi-close</v-icon></v-btn>
                </template>
            </v-toolbar>

            <v-card-text>
                <v-form ref="formRef" v-model="isValid" lazy-validation>
                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Producto (autocomplete remoto con debounce) -->
                        <v-autocomplete
                            v-model="form.product_id"
                            v-model:search="productSearch"
                            :items="productItems"
                            :loading="loadingProducts"
                            :rules="[rules.required]"
                            label="Producto"
                            item-title="name"
                            item-value="id"
                            prepend-inner-icon="mdi-package-variant"
                            hide-details="auto"
                            :no-filter="true"
                            :return-object="false"
                            clearable
                        >
                            <!-- Cómo se muestran las opciones -->
                            <template #item="{ props, item }">
                                <v-list-item v-bind="props" :title="item?.raw?.name" :subtitle="item?.raw?.sku || item?.raw?.barcode" />
                            </template>
                            <!-- Cómo se ve lo seleccionado -->
                            <template #selection="{ item }">
                                <span>{{ item?.raw?.name ?? ('#' + (item?.raw?.id ?? form.product_id)) }}</span>
                            </template>
                            <template #no-data>
                                <div class="px-4 py-2 text-caption">
                                    {{ productSearch.length < MIN_CHARS ? `Escribe al menos ${MIN_CHARS} caracteres…` : 'Sin resultados' }}
                                </div>
                            </template>
                        </v-autocomplete>

                        <!-- Almacén -->
                        <v-select
                            v-model="form.warehouse_id"
                            :items="warehouses"
                            :rules="[rules.required]"
                            label="Almacén"
                            item-title="name"
                            item-value="id"
                            prepend-inner-icon="mdi-warehouse"
                            hide-details="auto"
                        />

                        <!-- Tipo movimiento -->
                        <v-select
                            v-model="form.movement"
                            :items="movementItems"
                            :rules="[rules.required]"
                            label="Movimiento"
                            item-title="text"
                            item-value="value"
                            prepend-inner-icon="mdi-swap-horizontal"
                            hide-details="auto"
                        />

                        <!-- Cantidad -->
                        <v-text-field
                            v-model.number="form.quantity"
                            :rules="[rules.integerPositive]"
                            type="number"
                            label="Cantidad"
                            prepend-inner-icon="mdi-counter"
                            hide-details="auto"
                        />

                        <!-- Costo unitario (solo entradas/ajuste positivo/transfer_in) -->
                        <v-text-field
                            v-model.number="form.unit_cost"
                            :disabled="!requiresCost"
                            :rules="requiresCost ? [rules.nonNegative] : []"
                            type="number"
                            label="Costo unitario"
                            prepend-inner-icon="mdi-currency-usd"
                            hide-details="auto"
                        />

                        <!-- Fecha -->
                        <v-text-field
                            v-model="form.movement_date"
                            type="date"
                            label="Fecha"
                            prepend-inner-icon="mdi-calendar"
                            hide-details="auto"
                        />
                    </div>

                    <!-- Dirección del ajuste -->
                    <div v-if="form.movement === 'ajuste'" class="mt-2">
                        <v-radio-group v-model="form.direction" inline>
                            <v-radio label="Positivo" value="positivo" />
                            <v-radio label="Negativo" value="negativo" />
                        </v-radio-group>
                    </div>

                    <v-text-field
                        v-model="form.reference"
                        label="Referencia (Factura/Boleta)"
                        class="mt-2"
                        prepend-inner-icon="mdi-receipt"
                        hide-details="auto"
                    />

                    <v-textarea
                        v-model="form.description"
                        label="Descripción"
                        rows="2"
                        auto-grow
                        class="mt-2"
                        hide-details="auto"
                    />
                </v-form>
            </v-card-text>

            <v-divider />
            <v-card-actions class="justify-end">
                <v-btn variant="text" @click="close">Cancelar</v-btn>
                <v-btn color="primary" :loading="loading" @click="submit">Guardar</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import API from '@/tenant/services/index.js';

export default {
    name: 'MovementDialog',
    props: {
        modelValue: { type: Boolean, default: false },
        warehouses: { type: Array, default: () => [] },
        presetProductId: { type: [Number, String], default: null },
        presetWarehouseId: { type: [Number, String], default: null },
        loading: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'submit'],
    data() {
        return {
            internalDialog: this.modelValue,
            isValid: false,

            productItems: [],
            productSearch: '',
            loadingProducts: false,

            // Configuración de búsqueda remota
            MIN_CHARS: 3,         // cambia a 4 si quieres
            DEBOUNCE_MS: 350,
            _searchTimer: null,

            form: this.initialForm(),
            rules: {
                required: v => (!!v || v === 0) || 'Requerido',
                integerPositive: v => (Number.isInteger(Number(v)) && Number(v) > 0) || 'Entero > 0',
                nonNegative: v => (v === null || v === '' || Number(v) >= 0) || 'No negativo',
            },
            movementItems: [
                { text: 'Entrada', value: 'entrada' },
                { text: 'Salida', value: 'salida' },
                { text: 'Ajuste', value: 'ajuste' },
                { text: 'Transferencia (origen)', value: 'transfer_out' },
                { text: 'Transferencia (destino)', value: 'transfer_in' },
            ],
        };
    },
    computed: {
        title() {
            return 'Registrar movimiento';
        },
        requiresCost() {
            return (
                this.form.movement === 'entrada' ||
                (this.form.movement === 'ajuste' && this.form.direction === 'positivo') ||
                this.form.movement === 'transfer_in'
            );
        },
    },
    watch: {
        modelValue(val) {
            this.internalDialog = val;
            if (val) {
                this.reset();
                this.preloadSelectedProduct();
            }
        },
        internalDialog(val) { this.$emit('update:modelValue', val); },

        // debounce del texto de búsqueda
        productSearch(q) { this.debouncedRemoteSearch(q); },
    },
    beforeUnmount() {
        clearTimeout(this._searchTimer);
    },
    methods: {
        initialForm() {
            return {
                product_id: this.presetProductId || null,
                warehouse_id: this.presetWarehouseId || null,
                movement: 'entrada',
                quantity: 1,
                unit_cost: null,
                movement_date: new Date().toISOString().slice(0, 10),
                direction: 'positivo', // solo ajuste
                reference: '',
                description: '',
            };
        },
        reset() {
            this.form = this.initialForm();
            this.productItems = [];
            this.productSearch = '';
            this.$refs.formRef?.resetValidation?.();
            clearTimeout(this._searchTimer);
        },
        close() { this.internalDialog = false; },

        debouncedRemoteSearch(q) {
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this.remoteSearch(q), this.DEBOUNCE_MS);
        },

        async remoteSearch(q) {
            if (!q || q.length < this.MIN_CHARS) {
                // conserva el seleccionado si existe
                this.productItems = this.form.product_id
                    ? this.productItems.filter(i => i.id === this.form.product_id)
                    : [];
                return;
            }
            this.loadingProducts = true;
            try {
                // Asegúrate de que tu helper GET use { params } en Axios
                const res = await API.products.search({ search: q, limit: 10 });
                // /api/products/search devuelve array simple (id, name, sku, barcode)
                this.productItems = Array.isArray(res) ? res : (res.data ?? []);
            } catch (e) {
                console.error(e);
            } finally {
                this.loadingProducts = false;
            }
        },

        // Intenta precargar el item seleccionado para mostrar el nombre
        async preloadSelectedProduct() {
            if (!this.form.product_id) return;

            // si ya está en la lista, no hagas nada
            if (this.productItems.some(i => i.id === this.form.product_id)) return;

            try {
                if (API?.products?.show) {
                    const p = await API.products.show(this.form.product_id); // GET /api/products/{id}
                    const item = Array.isArray(p?.data) ? p.data[0] : (p.data ?? p);
                    if (item) this.productItems = [item, ...this.productItems];
                } else {
                    // Fallback visible
                    this.productItems = [{ id: this.form.product_id, name: `#${this.form.product_id}` }, ...this.productItems];
                }
            } catch (_) {
                this.productItems = [{ id: this.form.product_id, name: `#${this.form.product_id}` }, ...this.productItems];
            }
        },

        async submit() {
            const ok = await this.$refs.formRef?.validate();
            if (!ok?.valid) return;
            this.$emit('submit', { ...this.form });
        },
    },
};
</script>
