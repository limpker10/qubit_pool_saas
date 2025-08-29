<template>
    <v-dialog
        :model-value="modelValue"
        @update:model-value="v => $emit('update:modelValue', v)"
        max-width="1200"
    >
        <v-card class="rounded-xl">
            <!-- Header -->
            <v-card-title class="text-h6 d-flex align-center">
                <v-icon class="mr-2">mdi-cart-outline</v-icon>
                Agregar consumo
                <span v-if="tableNumber" class="ml-2 text-medium-emphasis">— Mesa #{{ tableNumber }}</span>
                <v-spacer />
                <v-btn icon variant="text" @click="$emit('update:modelValue', false)">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>

            <!-- Body -->
            <v-card-text class="pt-2">
                <v-row>
                    <!-- LEFT: Catálogo -->
                    <v-col cols="12" md="6">
                        <!-- Controles -->
                        <v-card density="compact" variant="text">
                            <v-card-text>
                                <v-row dense>
                                    <v-col cols="12" md="5">
                                        <v-text-field
                                            v-model.trim="filters.search"
                                            label="Buscar (nombre / SKU / código)"
                                            prepend-inner-icon="mdi-magnify"
                                            clearable
                                            density="compact"
                                            hide-details
                                            variant="solo"
                                            @update:model-value="onSearchDebounced"
                                            @keydown.enter.prevent="addFirstIfAny"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model.trim="scanCode"
                                            label="Escáner / Código de barras"
                                            prepend-inner-icon="mdi-barcode-scan"
                                            density="compact"
                                            hide-details
                                            variant="solo"
                                            @keyup.enter="onScanEnter"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="3">
                                        <div class="d-flex justify-end">
                                            <v-btn-toggle v-model="catalogView" mandatory density="compact" class="mr-2">
                                                <v-btn value="cards" icon><v-icon>mdi-view-grid</v-icon></v-btn>
                                                <v-btn value="list"  icon><v-icon>mdi-format-list-bulleted</v-icon></v-btn>
                                            </v-btn-toggle>
                                            <v-btn icon :loading="loading" @click="refreshProducts" :disabled="loading">
                                                <v-icon>mdi-refresh</v-icon>
                                            </v-btn>
                                        </div>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>

                        <!-- Catálogo -->
                        <v-sheet style="flex:1; overflow-y:auto; height:60vh" class="rounded-lg">
                            <template v-if="loading">
                                <v-skeleton-loader type="article, list-item, list-item, list-item" />
                            </template>

                            <template v-else>
                                <!-- Cards -->
                                <template v-if="catalogView === 'cards'">
                                    <v-row dense>
                                        <v-col v-for="p in products" :key="p.id" cols="12" sm="6" lg="4">
                                            <v-card class="h-100 rounded-lg">
                                                <v-card-text>
                                                    <div class="text-subtitle-1 font-weight-medium mb-1">{{ p.name }}</div>
                                                    <div class="text-caption text-medium-emphasis">
                                                        SKU: {{ p.sku || '—' }}<span v-if="p.brand"> · {{ p.brand }}</span>
                                                    </div>
                                                    <div class="text-h6 mt-2">{{ money(p.default_sale_price) }}</div>
                                                    <div class="text-caption text-medium-emphasis">{{ p.unit?.name || 'unidad' }}</div>
                                                </v-card-text>
                                                <v-card-actions class="pt-0">
                                                    <v-btn block color="primary" prepend-icon="mdi-plus" @click="addToCart(p)">Agregar</v-btn>
                                                </v-card-actions>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                </template>

                                <!-- Lista -->
                                <template v-else>
                                    <v-list lines="two" density="compact" class="rounded-lg">
                                        <v-list-item v-for="p in products" :key="p.id" @dblclick="addToCart(p)">
                                            <template #prepend>
                                                <v-avatar size="36"><v-icon>mdi-package-variant</v-icon></v-avatar>
                                            </template>
                                            <v-list-item-title class="font-weight-medium">{{ p.name }}</v-list-item-title>
                                            <v-list-item-subtitle>
                                                SKU: {{ p.sku || '—' }}<span v-if="p.brand"> · {{ p.brand }}</span>
                                                <span class="ml-1 text-medium-emphasis">({{ p.unit?.name || 'unidad' }})</span>
                                            </v-list-item-subtitle>
                                            <template #append>
                                                <div class="text-right">
                                                    <div class="text-subtitle-2 mb-1">{{ money(p.default_sale_price) }}</div>
                                                    <v-btn size="small" color="primary" prepend-icon="mdi-plus" @click="addToCart(p)">
                                                        Agregar
                                                    </v-btn>
                                                </div>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </template>

                                <!-- Sin resultados -->
                                <div v-if="!products.length" class="text-center text-medium-emphasis py-6">
                                    No hay productos que coincidan.
                                </div>
                            </template>
                        </v-sheet>

                        <!-- Paginación -->
                        <div class="d-flex justify-end mt-2">
                            <v-pagination
                                v-model="prodPage"
                                :length="prodPages"
                                :total-visible="7"
                                density="compact"
                                color="primary"
                                @update:model-value="fetchProducts"
                            />
                        </div>
                    </v-col>

                    <!-- RIGHT: Guardados + Carrito -->
                    <v-col cols="12" md="6">
                        <v-card class="rounded-lg elevation-2">
                            <!-- Guardados -->
                            <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center">
                                Guardados
                                <v-spacer/>
                                <v-chip v-if="rentalId" size="small" label>Rental #{{ rentalId }}</v-chip>
                            </v-card-title>
                            <v-divider/>
                            <v-card-text class="py-2">
                                <template v-if="existingItems?.length">
                                    <v-table density="compact" fixed-header height="22vh" class="rounded-lg">
                                        <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th class="text-right">P.U</th>
                                            <th class="text-center">Cant.</th>
                                            <th class="text-right">Subtotal</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(it, i) in existingItems" :key="it.id ?? i">
                                            <td>{{ it.name }}</td>
                                            <td class="text-right">{{ money(it.price) }}</td>
                                            <td class="text-center">{{ fmt(it.qty) }}</td>
                                            <td class="text-right">{{ money(it.subtotal) }}</td>
                                        </tr>
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right"><strong>Total guardado:</strong></td>
                                            <td class="text-right"><strong>{{ money(totalExisting) }}</strong></td>
                                        </tr>
                                        </tfoot>
                                    </v-table>
                                </template>
                                <v-empty-state v-else title="Sin ítems guardados" text="Confirma para enviar a DB" />
                            </v-card-text>

                            <v-divider class="my-2" />

                            <!-- Carrito -->
                            <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center">
                                Nuevos ítems
                                <v-spacer />
                                <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="addRowQuick">
                                    Agregar línea
                                </v-btn>
                            </v-card-title>
                            <v-divider/>
                            <v-card-text class="py-0">
                                <template v-if="cart.length">
                                    <v-table density="compact" fixed-header height="32vh" class="cart-table">
                                        <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th class="text-right">P.U</th>
                                            <th class="text-center">Cant.</th>
                                            <th class="text-right">Subtotal</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="(it, idx) in cart" :key="`${it.id}-${idx}`">
                                            <td>
                                                <div class="text-body-2">{{ it.name }}</div>
                                                <div class="text-caption text-medium-emphasis">{{ it.sku || '—' }}</div>
                                            </td>
                                            <td class="text-right">{{ money(it.price) }}</td>
                                            <td class="text-center" style="min-width:120px">
                                                <div class="d-flex align-center justify-center">
                                                    <v-btn icon size="x-small" variant="text" @click="decQty(idx)">
                                                        <v-icon size="16">mdi-minus</v-icon>
                                                    </v-btn>
                                                    <v-text-field
                                                        v-model.number="it.qty"
                                                        type="number" min="1" step="1" style="width:70px"
                                                        hide-details density="compact" class="mx-1 text-center"
                                                        @change="normalizeQty(idx)" @keydown.enter.prevent="normalizeQty(idx)"
                                                    />
                                                    <v-btn icon size="x-small" variant="text" @click="incQty(idx)">
                                                        <v-icon size="16">mdi-plus</v-icon>
                                                    </v-btn>
                                                </div>
                                            </td>
                                            <td class="text-right">{{ money(it.qty * it.price) }}</td>
                                            <td class="text-right">
                                                <v-btn icon size="x-small" color="error" variant="text" @click="removeItem(idx)">
                                                    <v-icon size="16">mdi-close</v-icon>
                                                </v-btn>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </v-table>
                                </template>
                                <v-empty-state v-else title="Carrito vacío" text="Busca y agrega desde el catálogo" />
                            </v-card-text>

                            <v-divider class="my-2" />

                            <!-- Totales + acciones (sticky) -->
                            <v-card-text class="pt-0 pb-2">
                                <div class="d-flex justify-space-between mb-1">
                                    <span class="text-body-2 text-medium-emphasis">Ítems</span>
                                    <span class="text-body-2">{{ itemsCount }}</span>
                                </div>
                                <div class="d-flex justify-space-between mb-1">
                                    <span class="text-body-2 text-medium-emphasis">Subtotal (nuevos)</span>
                                    <span class="text-body-2">{{ money(subtotal) }}</span>
                                </div>
                                <v-divider class="my-2" />
                                <div class="d-flex justify-space-between">
                                    <span class="text-subtitle-1 font-weight-bold">Total nuevos</span>
                                    <span class="text-subtitle-1 font-weight-bold">{{ money(total) }}</span>
                                </div>
                            </v-card-text>

                            <v-card-actions class="pt-0 pb-4 px-4 ">
                                <v-btn
                                    block
                                    color="success"
                                    prepend-icon="mdi-check"
                                    class="mt-2"
                                    :disabled="!canConfirm"
                                    @click="confirm"
                                >
                                    Aplicar consumo
                                </v-btn>
                                <v-btn
                                    block
                                    variant="tonal"
                                    color="warning"
                                    prepend-icon="mdi-delete"
                                    @click="clearCart"
                                    :disabled="!cart.length"
                                >
                                    Vaciar carrito
                                </v-btn>

                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>
            </v-card-text>

            <!-- Snackbar -->
            <v-snackbar v-model="snack.show" :color="snack.color" timeout="2500">
                {{ snack.text }}
            </v-snackbar>
        </v-card>
    </v-dialog>
</template>

<script>
import API from '@/tenant/services/index.js'

export default {
    name: 'ConsumptionPOSDialog',
    props: {
        modelValue:    { type: Boolean, default: false },
        tableNumber:   { type: [Number, String], default: null },
        rentalId:      { type: [Number, String], required: true },
        existingItems: { type: Array, default: () => [] },

        onlyActive:    { type: Boolean, default: true },
        perPage:       { type: Number,  default: 12 },
        initialCart:   { type: Array,   default: () => [] },

        // opcional: formateo
        currency:      { type: String,  default: 'PEN' },
        locale:        { type: String,  default: 'es-PE' },
    },
    data() {
        return {
            catalogView: 'cards',
            filters: { search: '' },
            products: [],
            prodPage: 1,
            prodPages: 1,

            scanCode: '',
            cart: [],

            snack: { show: false, color: 'success', text: '' },
            debounce: null,
            loading: false,
        }
    },
    watch: {
        modelValue(v) {
            if (v) {
                // reset carrito al abrir
                this.cart = this.initialCart?.length ? JSON.parse(JSON.stringify(this.initialCart)) : []
                this.prodPage = 1
                this.fetchProducts()
                this.$nextTick(() => {
                    // foco rápido al buscador
                    const el = document.querySelector('input[type="text"][aria-label="Buscar (nombre / SKU / código)"]')
                    el && el.focus()
                })
            } else {
                // limpieza rápida al cerrar
                this.scanCode = ''
                this.filters.search = ''
            }
        },
    },
    computed: {
        itemsCount() {
            return this.cart.reduce((a, b) => a + Number(b.qty || 0), 0)
        },
        subtotal() {
            return this.cart.reduce((a, b) => a + Number(b.qty || 0) * Number(b.price || 0), 0)
        },
        total() {
            return Math.round(this.subtotal * 100) / 100
        },
        totalExisting() {
            return (this.existingItems || []).reduce((a, r) => a + Number(r.subtotal || 0), 0)
        },
        canConfirm() {
            return this.cart.length > 0 && this.total > 0
        },
    },
    methods: {
        // ---- UI helpers
        money(n) {
            return new Intl.NumberFormat(this.locale, { style: 'currency', currency: this.currency })
                .format(Number(n || 0))
        },
        fmt(v) {
            return Number(v ?? 0).toFixed(2)
        },

        // ---- Catálogo
        async fetchProducts() {
            this.loading = true
            try {
                const params = {
                    per_page: this.perPage,
                    page: this.prodPage,
                    search: this.filters.search || undefined,
                    only_active: this.onlyActive ? 1 : 0,
                }
                const pg = await API.products.list(params)
                this.products  = pg?.data ?? []
                this.prodPages = pg?.last_page ?? 1
                this.prodPage  = pg?.current_page ?? 1
            } catch (e) {
                this.showErr(e)
            } finally {
                this.loading = false
            }
        },
        refreshProducts() {
            this.fetchProducts()
        },
        onSearchDebounced() {
            clearTimeout(this.debounce)
            this.debounce = setTimeout(() => {
                this.prodPage = 1
                this.fetchProducts()
            }, 300)
        },
        addFirstIfAny() {
            if (this.products && this.products[0]) this.addToCart(this.products[0])
        },

        // ---- Escáner
        async onScanEnter() {
            const code = (this.scanCode || '').trim()
            if (!code) return
            try {
                const { data } = await API.products.search({ search: code, limit: 1 })
                const arr = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
                const p = arr[0] || null
                if (p) {
                    this.addToCart(p)
                    this.scanCode = ''
                    this.toast('Producto agregado por código')
                } else {
                    this.toast('No se encontró producto', 'error')
                }
            } catch (e) {
                this.showErr(e)
            }
        },

        // ---- Carrito
        mapProduct(p) {
            return {
                id: p.id,
                product_id: p.id,
                name: p.name,
                sku: p.sku,
                price: Number(p.default_sale_price || 0),
                qty: 1,
            }
        },
        addRowQuick() {
            this.cart.push({ id: null, name: 'Artículo', sku: '', price: 0, qty: 1 })
        },
        addToCart(p) {
            // merge por product_id / id
            const pid = p.id
            const idx = this.cart.findIndex(i => (i.product_id ?? i.id) === pid)
            if (idx >= 0) {
                this.cart[idx].qty = Number(this.cart[idx].qty || 0) + 1
            } else {
                this.cart.push(this.mapProduct(p))
            }
        },
        incQty(i) { this.cart[i].qty = Number(this.cart[i].qty || 0) + 1 },
        decQty(i) { this.cart[i].qty = Math.max(1, Number(this.cart[i].qty || 1) - 1) },
        normalizeQty(i) {
            const v = Math.max(1, parseInt(this.cart[i].qty || 1, 10))
            this.cart[i].qty = isNaN(v) ? 1 : v
        },
        removeItem(i) { this.cart.splice(i, 1) },
        clearCart() { this.cart = [] },

        // ---- Confirmar
        confirm() {
            const items = this.cart.map(i => ({
                product_id: i.product_id ?? i.id ?? null,
                name:       i.name,
                unit_id:    i.unit_id ?? null,
                unit_name:  i.unit_name ?? null,
                qty:        Number(i.qty || 0),
                price:      Number(i.price || 0),
                discount:   Number(i.discount || 0),
                subtotal:   Number((Number(i.qty || 0) * Number(i.price || 0) - Number(i.discount || 0)).toFixed(2)),
            }))
            const total = Number(this.total.toFixed(2))

            this.$emit('confirm', { items, total })
            this.$emit('update:modelValue', false)
            this.toast('Consumo listo para aplicar')
        },

        // ---- Mensajería
        toast(text, color = 'success') { this.snack = { show: true, text, color } },
        showErr(err) {
            const msg = err?.response?.data?.message || err?.message || 'Ocurrió un error'
            this.toast(msg, 'error')
        },
    }
}
</script>

<style scoped>
.cart-table thead th { font-weight: 600; }
.sticky-actions { position: sticky; bottom: 0; background: rgba(255,255,255,.9); backdrop-filter: blur(6px); }
</style>
