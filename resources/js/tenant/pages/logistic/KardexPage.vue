<template>
    <div class="p-4">
        <v-card>
            <v-card-title class="flex items-center justify-between gap-4">
                <div class="text-xl font-medium">Kardex</div>
                <div class="flex items-center gap-2">
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
                        style="min-width: 320px"
                        :no-filter="true"
                    clearable
                    >
                    <template #no-data>
                        <div class="px-4 py-2 text-caption">
                            {{ productSearch.length < MIN_CHARS ? `Escribe al menos ${MIN_CHARS} caracteres…` : 'Sin resultados' }}
                        </div>
                    </template>
                    </v-autocomplete>
                    <v-select
                        v-model="filters.warehouseId"
                        :items="warehouses"
                        item-title="name"
                        item-value="id"
                        placeholder="Almacén"
                        density="compact"
                        hide-details
                        clearable
                        style="min-width: 200px"
                    />
                    <v-text-field v-model="filters.from" type="date" density="compact" hide-details style="max-width: 160px" />
                    <v-text-field v-model="filters.to" type="date" density="compact" hide-details style="max-width: 160px" />
                    <v-btn variant="flat" color="primary" @click="applyFilters"><v-icon start>mdi-magnify</v-icon>Filtrar</v-btn>
                    <v-btn variant="text" @click="resetFilters">Limpiar</v-btn>
                    <v-btn color="secondary" @click="openMovement"><v-icon start>mdi-swap-horizontal</v-icon>Movimiento</v-btn>
                </div>
            </v-card-title>

            <v-data-table-server
                :headers="headers"
                :items="items"
                :items-length="total"
                :loading="loading"
                :page.sync="page"
                :items-per-page.sync="itemsPerPage"
                class="border-t"
                item-key="id"
                @update:page="fetch"
                @update:items-per-page="fetch"
            >
                <template #item.movement="{ item }">
                    <v-chip size="small" :color="movementColor(item.movement)" variant="flat">{{ item.movement }}</v-chip>
                </template>
                <template #item.product="{ item }">
                    {{ item.product?.name || '-' }}
                </template>
                <template #item.warehouse="{ item }">
                    {{ item.warehouse?.name || '-' }}
                </template>
                <template #no-data>
                    <div class="text-center py-10 text-gray-500">Sin resultados</div>
                </template>
            </v-data-table-server>
        </v-card>

        <!-- Diálogo movimiento -->
        <movement-dialog
            v-model="dialog"
            :warehouses="warehouses"
            :preset-product-id="filters.productId"
            :preset-warehouse-id="filters.warehouseId"
            :loading="saving"
            @submit="handleMovement"
        />

        <v-snackbar v-model="snackbar.show" :timeout="2500">{{ snackbar.text }}</v-snackbar>
    </div>
</template>

<script>
import MovementDialog from "@/tenant/pages/logistic/MovementDialog.vue";
import API from "@/tenant/services/index.js";

export default {
    name: 'KardexPage',
    components: { MovementDialog },
    data() {
        return {
            headers: [
                { title: 'Fecha', key: 'movement_date', width: 140 },
                { title: 'Producto', key: 'product', width: 220 },
                { title: 'Almacén', key: 'warehouse', width: 180 },
                { title: 'Mov.', key: 'movement', width: 120 },
                { title: 'Ent', key: 'quantity_in', width: 90 },
                { title: 'Sal', key: 'quantity_out', width: 90 },
                { title: 'Costo U', key: 'unit_cost', width: 120 },
                { title: 'Total', key: 'total_cost', width: 120 },
                { title: 'Saldo Qty', key: 'balance_qty', width: 120 },
                { title: 'Saldo C.U.', key: 'balance_avg_unit_cost', width: 140 },
            ],
            items: [],
            total: 0,
            loading: false,
            page: 1,
            itemsPerPage: 20,

            warehouses: [],

            // búsqueda de producto
            productItems: [],
            productSearch: '',
            loadingProducts: false,

            // filtros
            filters: {
                productId: null,
                warehouseId: null,
                from: null,
                to: null,
            },

            // diálogo movimiento
            dialog: false,
            saving: false,

            snackbar: { show: false, text: '' },

            MIN_CHARS: 3,          // cámbialo a 4 si quieres
            _searchTimer: null,
        };
    },
    created() {
        this.fetch();
        this.loadWarehouses();
    },
    watch: {
        productSearch(q) { this.debouncedRemoteSearch(q); },
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
        async fetch() {
            try {
                this.loading = true;
                const res = await API.kardex.list({
                    productId: this.filters.productId,
                    warehouseId: this.filters.warehouseId,
                    from: this.filters.from,
                    to: this.filters.to,
                    page: this.page,
                    perPage: this.itemsPerPage,
                });
                // Estructuras posibles: { data:[], meta:{} } o {data:[], total:...}
                const rows = res.data || res?.data?.data || [];
                // Asegura que cada fila traiga product y warehouse (KardexController->with)
                this.items = rows.map(r => ({
                    ...r,
                    movement_date: (r.movement_date || '').toString().replace('T', ' ').slice(0,16),
                }));
                this.total = res.total || res?.meta?.total || 0;
                if (res?.meta?.current_page) this.page = res.meta.current_page;
                if (res?.meta?.per_page) this.itemsPerPage = res.meta.per_page;
            } catch (e) {
                console.error(e);
                this.toast('Error cargando kardex');
            } finally {
                this.loading = false;
            }
        },
        async loadWarehouses() {
            try {
                const res = await API.warehouses.list();
                this.warehouses = Array.isArray(res?.data) ? res.data : res;
            } catch (e) {
                console.error(e);
                this.toast('No se pudieron cargar almacenes');
            }
        },
        async onProductSearch(q) {
            try {
                this.loadingProducts = true;
                const { data } = await API.products.search( { search: q, limit: 10 } );
                this.productItems = data;
            } finally {
                this.loadingProducts = false;
            }
        },
        applyFilters() {
            this.page = 1;
            this.fetch();
        },
        resetFilters() {
            this.filters = { productId: null, warehouseId: null, from: null, to: null };
            this.applyFilters();
        },
        movementColor(type) {
            switch (type) {
                case 'entrada':
                case 'transfer_in': return 'success';
                case 'salida':
                case 'transfer_out': return 'error';
                case 'ajuste': return 'warning';
                default: return 'primary';
            }
        },
        openMovement() { this.dialog = true; },
        async handleMovement(payload) {
            try {
                this.saving = true;
                await API.kardex.create(payload);
                this.toast('Movimiento registrado');
                this.dialog = false;
                await this.fetch();
            } catch (e) {
                console.error(e);
                const msg = e?.response?.data?.message || 'Error al registrar movimiento';
                this.toast(msg);
            } finally {
                this.saving = false;
            }
        },
        toast(text) { this.snackbar.text = text; this.snackbar.show = true; },
    },
};
</script>
