<template>
    <v-dialog
        :model-value="visible"
        @update:model-value="$emit('close', $event)"
        max-width="860"
        class="rounded-lg"
        transition="dialog-bottom-transition"
        persistent
    >
        <v-card class="rounded-lg">
            <v-toolbar flat color="primary" class="rounded-t-lg">
                <v-toolbar-title class="d-flex align-center ga-2">
                    <v-icon>mdi-cart-plus</v-icon> Agregar productos
                </v-toolbar-title>
                <v-spacer />
                <v-btn icon @click="$emit('close', false)" aria-label="Cerrar">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-toolbar>

            <v-card-text class="pt-6">
                <!-- Buscador -->
                <div class="d-flex ga-2">
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
                        class="flex-1"
                        @update:model-value="onSelectProduct"
                    >
                        <template #no-data>
                            <div class="px-4 py-2 text-caption">
                                {{ productSearch.length < MIN_CHARS ? `Escribe al menos ${MIN_CHARS} caracteres…` : 'Sin resultados' }}
                            </div>
                        </template>
                        <template #item="{ props, item }">
                            <!-- Ítem con nombre + precio + SKU -->
                            <v-list-item v-bind="props" :title="item?.raw?.name">
                                <template #subtitle>
                  <span class="text-medium-emphasis">
                    {{ item?.raw?.sku ? `SKU ${item.raw.sku} • ` : '' }} {{ nf.format(parseMoney(item?.raw?.default_sale_price ?? item?.raw?.price)) }}
                  </span>
                                </template>
                            </v-list-item>
                        </template>
                    </v-autocomplete>

                    <v-btn
                        color="primary"
                        variant="tonal"
                        prepend-icon="mdi-magnify"
                        class="rounded-md"
                        :disabled="productSearch.length < MIN_CHARS || loadingProducts"
                        @click="remoteSearch(productSearch)"
                    >
                        Buscar
                    </v-btn>
                </div>

                <!-- Tabla de ítems -->
                <v-table density="compact" class="w-full border rounded-md overflow-hidden mt-4">
                    <thead>
                    <tr>
                        <th class="text-left text-sm font-semibold p-2">Cant.</th>
                        <th class="text-left text-sm font-semibold p-2">Producto</th>
                        <th class="text-left text-sm font-semibold p-2">Precio</th>
                        <th class="text-left text-sm font-semibold p-2">Desc.</th>
                        <th class="text-left text-sm font-semibold p-2">Subtotal</th>
                        <th class="text-left text-sm font-semibold p-2">Acciones</th>
                    </tr>
                    </thead>
                    <tbody v-if="tableRows.length">
                    <tr v-for="row in tableRows" :key="row.id">
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
                        <td class="p-2">
                            <div class="font-medium">{{ row.name }}</div>
                            <div class="text-caption text-medium-emphasis" v-if="row.sku">SKU {{ row.sku }}</div>
                        </td>
                        <td class="p-2" style="width:140px">
                            <v-text-field
                                v-model.number="row.price"
                                type="number"
                                min="0"
                                step="0.5"
                                density="compact"
                                variant="outlined"
                                hide-details
                                @change="recalcRow(row)"
                                :prefix="currencySymbol"
                            />
                        </td>
                        <td class="p-2" style="width:140px">
                            <v-text-field
                                v-model.number="row.discount"
                                type="number"
                                min="0"
                                step="0.5"
                                density="compact"
                                variant="outlined"
                                hide-details
                                @change="recalcRow(row)"
                                :prefix="currencySymbol"
                            />
                        </td>
                        <td class="p-2">{{ nf.format(row.total) }}</td>
                        <td class="p-2">
                            <v-btn icon variant="plain" size="small" @click="removeRow(row.id)">
                                <v-icon size="18">mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                    </tbody>
                    <tbody v-else>
                    <tr>
                        <td colspan="6" class="text-center text-medium-emphasis p-4">No hay productos</td>
                    </tr>
                    </tbody>
                </v-table>

                <!-- Resumen -->
                <div class="d-flex justify-end mt-4">
                    <div class="text-right">
                        <div class="text-body-2">Items: <strong>{{ tableRows.length }}</strong></div>
                        <div class="text-body-1">Total: <strong class="text-success">{{ nf.format(totales.total) }}</strong></div>
                    </div>
                </div>
            </v-card-text>

            <v-divider />

            <v-card-actions class="p-4 d-flex justify-end ga-2">
                <v-btn variant="text" color="secondary" @click="$emit('close', false)" prepend-icon="mdi-close-circle">
                    Cerrar
                </v-btn>
                <v-btn color="primary" :disabled="tableRows.length===0" @click="confirmar" prepend-icon="mdi-check" class="rounded-md">
                    Confirmar
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import API from "@/tenant/services/index.js";

export default {
    name: "DialogAgregarProductos",
    emits: ["close", "confirm"],
    props: {
        visible: { type: Boolean, required: true },
        currency: { type: String, default: "PEN" },
        locale: { type: String, default: "es-PE" },
        // opcional: pre-cargar filas (ej. si ya hay consumo)
        initialRows: { type: Array, default: () => [] },
    },
    data() {
        return {
            // búsqueda
            productItems: [],
            productSearch: "",
            loadingProducts: false,
            filters: { productId: null },
            MIN_CHARS: 3,
            _searchTimer: null,

            // filas seleccionadas
            tableRows: [],
            _rowIndexByProductId: new Map(),
        };
    },
    computed: {
        nf() {
            try {
                return new Intl.NumberFormat(this.locale, {
                    style: "currency",
                    currency: this.currency,
                    minimumFractionDigits: 2,
                });
            } catch {
                return new Intl.NumberFormat("es-PE", {
                    style: "currency",
                    currency: "PEN",
                    minimumFractionDigits: 2,
                });
            }
        },
        currencySymbol() {
            // Solo visual, el nf ya formatea totales
            try {
                return (0).toLocaleString(this.locale, { style: "currency", currency: this.currency }).replace(/\d|[.,\s]/g, '').trim() || '';
            } catch { return 'S/'; }
        },
        totales() {
            return this.tableRows.reduce((acc, r) => {
                acc.total += r.total;
                return acc;
            }, { total: 0 });
        },
    },
    watch: {
        productSearch(q) { this.debouncedRemoteSearch(q); },
        visible(val) {
            if (val) this.seedRows();
        },
    },
    mounted() {
        if (this.visible) this.seedRows();
    },
    methods: {
        seedRows() {
            // Carga inicial si vienen filas precargadas
            this.tableRows = [];
            this._rowIndexByProductId = new Map();
            for (const r of this.initialRows) {
                const row = {
                    id: r.id,
                    name: r.name,
                    sku: r.sku ?? null,
                    price: this.parseMoney(r.price),
                    qty: Number(r.qty ?? 1),
                    discount: this.parseMoney(r.discount ?? 0),
                    total: 0,
                };
                this.recalcRow(row);
                this.tableRows.push(row);
                this._rowIndexByProductId.set(row.id, this.tableRows.length - 1);
            }
        },

        // === búsqueda remota ===
        debouncedRemoteSearch(q) {
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this.remoteSearch(q), 350);
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
                const arr = Array.isArray(res) ? res : (res?.data ?? []);
                // Normaliza: deja price en base a default_sale_price si existe
                this.productItems = arr.map(p => ({
                    ...p,
                    price: this.parseMoney(p.default_sale_price ?? p.price ?? 0),
                }));
            } catch (e) {
                console.error(e);
            } finally {
                this.loadingProducts = false;
            }
        },

        // === selección ===
        onSelectProduct(productId) {
            if (!productId) return; // cleared
            const prod = this.productItems.find(p => p.id === productId);
            if (!prod) return;

            const priceNum = this.parseMoney(prod.default_sale_price ?? prod.price ?? 0);

            if (this._rowIndexByProductId.has(prod.id)) {
                const idx = this._rowIndexByProductId.get(prod.id);
                this.tableRows[idx].qty += 1;
                this.recalcRow(this.tableRows[idx]);
            } else {
                const row = {
                    id: prod.id,
                    name: prod.name,
                    sku: prod.sku ?? null,
                    price: priceNum,
                    qty: 1,
                    discount: 0,
                    total: 0,
                };
                this.recalcRow(row);
                this.tableRows.push(row);
                this._rowIndexByProductId.set(prod.id, this.tableRows.length - 1);
            }

            // limpia para siguiente búsqueda
            this.filters.productId = null;
            this.productSearch = '';
        },

        // === utilidades ===
        parseMoney(v) {
            const n = Number(String(v ?? 0).replace(/[^\d.-]/g, ''));
            return Number.isFinite(n) ? n : 0;
        },
        recalcRow(row) {
            row.qty = Math.max(1, Number(row.qty) || 1);
            row.price = this.parseMoney(row.price);
            row.discount = Math.max(0, this.parseMoney(row.discount));
            const subtotal = row.qty * row.price;
            row.total = Math.max(0, subtotal - row.discount);
        },
        removeRow(id) {
            const idx = this._rowIndexByProductId.get(id);
            if (idx === undefined) return;
            this.tableRows.splice(idx, 1);
            this._rowIndexByProductId.delete(id);
            // reindex
            this._rowIndexByProductId = new Map(this.tableRows.map((r, i) => [r.id, i]));
        },

        confirmar() {
            // Emite filas y resumen (por si lo quieres guardar)
            this.$emit('confirm', {
                items: this.tableRows.map(r => ({
                    product_id: r.id,
                    name: r.name,
                    sku: r.sku,
                    qty: r.qty,
                    price: r.price,
                    discount: r.discount,
                    total: r.total,
                })),
                total: this.totales.total,
            });
            this.$emit('close', false);
        },
    },
};
</script>

<style scoped>
.border { border: 1px solid rgba(0,0,0,.08); }
.flex-1 { flex: 1 1 0; }
</style>
