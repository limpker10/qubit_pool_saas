<template>
    <v-dialog :model-value="modelValue" @update:model-value="v => $emit('update:modelValue', v)" max-width="85%">
        <v-card>
            <v-card-title class="text-h6 d-flex align-center">
                <v-icon class="mr-2">mdi-cart-outline</v-icon>
                Agregar consumo <span v-if="tableNumber" class="ml-2 text-medium-emphasis">— Mesa #{{
                    tableNumber
                }}</span>
                <v-spacer/>
                <v-btn icon variant="text" @click="$emit('update:modelValue', false)">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>

            <v-card-text class="pt-2">
                <v-row >
                    <!-- LEFT: Products catalog -->
                    <!-- Controles de búsqueda + vista -->
                    <v-col cols="12" md="6" >
                        <v-card density="compact" variant="text" >
                            <v-card-text>
                                <v-row dense>
                                    <v-col cols="12" md="4" >
                                        <v-text-field
                                            v-model.trim="filters.search"
                                            label="Buscar productos (nombre / SKU / código)"
                                            prepend-inner-icon="mdi-magnify"
                                            clearable
                                            density="compact"
                                            @keyup="onSearchKeyup"
                                            hide-details
                                            variant="solo"
                                        />
                                    </v-col>
                                    <v-col cols="12" md="4" >
                                        <v-text-field
                                            v-model.trim="scanCode"
                                            label="Escáner / Código de barras (Enter para agregar)"
                                            prepend-inner-icon="mdi-barcode-scan"
                                            density="compact"
                                            @keyup.enter="onScanEnter"
                                            hide-details
                                            variant="solo"
                                        />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <div class="d-flex justify-end">
                                            <v-btn-toggle v-model="catalogView" mandatory density="compact">
                                                <v-btn value="cards" prepend-icon="mdi-view-grid">Tarjetas</v-btn>
                                                <v-btn value="list" prepend-icon="mdi-format-list-bulleted">Lista</v-btn>
                                            </v-btn-toggle>
                                        </div>
                                    </v-col>
                                </v-row>

                            </v-card-text>
                        </v-card>

                        <!-- VISTA: CARDS -->
                        <!-- Contenido scrollable del catálogo -->
                        <!-- Contenido scrollable del catálogo -->
                        <v-sheet style="flex:1; overflow-y:auto; height: 60vh" class="rounded-lg">
                            <!-- VISTA: CARDS -->
                            <template v-if="catalogView === 'cards'">
                                <v-row dense>
                                    <v-col v-for="p in products" :key="p.id" cols="12" sm="6" lg="4">
                                        <v-card class="h-100 rounded-lg pa-0">
                                            <v-card-text>
                                                <div class="text-subtitle-1 font-weight-medium mb-1">{{ p.name }}</div>
                                                <div class="text-caption text-medium-emphasis">
                                                    SKU: {{ p.sku || '—' }}<span v-if="p.brand"> · {{ p.brand }}</span>
                                                </div>
                                                <div class="text-h6 mt-2">{{ money(p.default_sale_price) }}</div>
                                                <div class="text-caption text-medium-emphasis">{{ p.unit?.name || 'unidad' }}</div>
                                            </v-card-text>
                                            <v-card-actions class="pt-0">
                                                <v-btn block color="primary" prepend-icon="mdi-plus" @click="addToCart(p)">
                                                    Agregar
                                                </v-btn>
                                            </v-card-actions>
                                        </v-card>
                                    </v-col>
                                </v-row>
                            </template>

                            <!-- VISTA: LISTA -->
                            <template v-else>
                                <v-list lines="two" density="compact" class="rounded-lg" >
                                    <v-list-item v-for="p in products" :key="p.id">
                                        <template #prepend>
                                            <v-avatar size="36">
                                                <v-icon>mdi-package-variant</v-icon>
                                            </v-avatar>
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
                        </v-sheet>


                        <!-- Paginación del catálogo -->
                        <div class="d-flex justify-end ">
                            <v-pagination
                                v-model="prodPage"
                                :length="prodPages"
                                :total-visible="7"
                                @update:model-value="fetchProducts"
                                density="compact"
                                color="primary"
                            />
                        </div>

                    </v-col>
                    <!-- RIGHT: Cart -->
                    <v-col cols="12" md="6">
                        <v-card class="rounded-lg elevation-2">
                            <v-card-title class="text-subtitle-1 font-weight-bold">Carrito</v-card-title>
                            <v-divider/>
                            <v-card-text class="py-0">
                                <template v-if="cart.length">
                                    <v-table density="compact" fixed-header height="40vh" class="cart-table" >
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
                                        <tr v-for="(it, idx) in cart" :key="it.id">
                                            <td>
                                                <div class="text-body-2">{{ it.name }}</div>
                                                <div class="text-caption text-medium-emphasis">{{ it.sku || '—' }}</div>
                                            </td>
                                            <td class="text-right">{{ money(it.price) }}</td>
                                            <td class="text-center" style="min-width:110px">
                                                <div class="d-flex align-center justify-center">
                                                    <v-btn icon size="x-small" variant="text" @click="decQty(idx)">
                                                        <v-icon size="16">mdi-minus</v-icon>
                                                    </v-btn>
                                                    <v-text-field v-model.number="it.qty" type="number" min="1" step="1"
                                                                  hide-details density="compact" style="width:60px"
                                                                  @change="normalizeQty(idx)"/>
                                                    <v-btn icon size="x-small" variant="text" @click="incQty(idx)">
                                                        <v-icon size="16">mdi-plus</v-icon>
                                                    </v-btn>
                                                </div>
                                            </td>
                                            <td class="text-right">{{ money(it.qty * it.price) }}</td>
                                            <td class="text-right">
                                                <v-btn icon size="x-small" color="error" variant="text"
                                                       @click="removeItem(idx)">
                                                    <v-icon size="16">mdi-close</v-icon>
                                                </v-btn>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </v-table>
                                </template>
                                <v-empty-state v-else title="Sin productos" text="Busca y agrega desde el catálogo"/>
                            </v-card-text>

                            <v-divider class="my-2"/>

                            <v-card-text>
                                <div class="d-flex justify-space-between mb-1">
                                    <span class="text-body-2 text-medium-emphasis">Items</span>
                                    <span class="text-body-2">{{ itemsCount }}</span>
                                </div>
                                <div class="d-flex justify-space-between mb-1">
                                    <span class="text-body-2 text-medium-emphasis">Subtotal</span>
                                    <span class="text-body-2">{{ money(subtotal) }}</span>
                                </div>
                                <v-divider class="my-2"/>
                                <div class="d-flex justify-space-between">
                                    <span class="text-subtitle-1 font-weight-bold">Total</span>
                                    <span class="text-subtitle-1 font-weight-bold">{{ money(total) }}</span>
                                </div>
                            </v-card-text>

                            <v-card-actions class="pt-0 d-flex flex-column gap-2">
                                <v-btn block variant="tonal" color="warning" prepend-icon="mdi-delete"
                                       @click="clearCart" :disabled="!cart.length">Vaciar
                                </v-btn>
                                <v-btn block color="success" prepend-icon="mdi-check" :disabled="!cart.length"
                                       @click="confirm">Aplicar consumo
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>
            </v-card-text>

            <v-snackbar v-model="snack.show" :color="snack.color" timeout="3000">{{ snack.text }}</v-snackbar>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios'
import API from "@/tenant/services/index.js";

export default {
    name: 'ConsumptionPOSDialog',
    props: {
        modelValue: {type: Boolean, default: false},
        tableNumber: {type: [Number, String], default: null},
        onlyActive: {type: Boolean, default: true},
        perPage: {type: Number, default: 12},
        // Permite precargar items en el carrito: [{ id, name, sku, price, qty }]
        initialCart: {type: Array, default: () => []},
    },
    data() {
        return {
            catalogView: 'cards',
            // catalog
            filters: {search: ''},
            products: [],
            prodPage: 1,
            prodPages: 1,
            // scanner
            scanCode: '',
            // cart
            cart: [],
            snack: {show: false, color: 'success', text: ''},
            debounce: null,
            loading: false,
        }
    },
    watch: {
        modelValue(v) {
            if (v) {
                this.cart = this.initialCart?.length ? JSON.parse(JSON.stringify(this.initialCart)) : []
                this.fetchProducts()
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
    },
    methods: {
        money(n) {
            return new Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN'}).format(Number(n || 0))
        },

        async fetchProducts() {
            this.loading = true
            try {
                const params = {
                    per_page: this.perPage,
                    page: this.prodPage,
                    search: this.filters.search || undefined,
                    only_active: this.onlyActive ? 1 : 0,
                }
                // const {data} = await this.http.get('/products', {params})
                const response = await API.products.list(params)
                console.log(response)
                const pg = response
                this.products = pg.data || []
                this.prodPages = pg.last_page || 1
                this.prodPage = pg.current_page || 1
            } catch (e) {
                this.showErr(e)
            } finally {
                this.loading = false
            }
        },

        onSearchKeyup() {
            clearTimeout(this.debounce)
            this.debounce = setTimeout(() => {
                this.prodPage = 1;
                this.fetchProducts()
            }, 300)
        },

        async onScanEnter() {
            const code = (this.scanCode || '').trim()
            if (!code) return
            try {
                const response = await API.products.search({search: code, limit: 1})
                console.log(response)
                // const { data } = await this.http.get('/products/search', { params: { search: code, limit: 1 } })
                const p = Array.isArray(data) ? data[0] : null
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

        addToCart(p) {
            const idx = this.cart.findIndex(i => i.id === p.id)
            const price = Number(p.default_sale_price || 0)
            if (idx >= 0) {
                this.cart[idx].qty = Number(this.cart[idx].qty || 0) + 1
            } else {
                this.cart.push({id: p.id, name: p.name, sku: p.sku, price, qty: 1})
            }
        },
        incQty(i) {
            this.cart[i].qty = Number(this.cart[i].qty || 0) + 1
        },
        decQty(i) {
            this.cart[i].qty = Math.max(1, Number(this.cart[i].qty || 1) - 1)
        },
        normalizeQty(i) {
            const v = Math.max(1, parseInt(this.cart[i].qty || 1, 10))
            this.cart[i].qty = isNaN(v) ? 1 : v
        },
        removeItem(i) {
            this.cart.splice(i, 1)
        },
        clearCart() {
            this.cart = []
        },

        confirm() {
            // Entrega al padre el total y el detalle elegido
            this.$emit('confirm', {
                items: this.cart.map(i => ({...i, subtotal: Number(i.qty) * Number(i.price)})),
                total: this.total
            })
            this.$emit('update:modelValue', false)
        },

        toast(text, color = 'success') {
            this.snack = {show: true, text, color}
        },
        showErr(err) {
            const msg = err?.response?.data?.message || err?.message || 'Ocurrió un error'
            this.toast(msg, 'error')
        },
    },
}
</script>

<style scoped>
.cart-table thead th {
    font-weight: 600;
}
</style>
