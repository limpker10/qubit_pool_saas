<!-- src/views/ProductsPage.vue -->
<template>
    <v-container fluid class="pa-0">
        <v-card class="mb-4">
            <v-card-title class="d-flex items-center justify-between gap-4">
                <v-text-field
                    v-model="search"
                    density="compact"
                    placeholder="Buscar por nombre o SKU"
                    prepend-inner-icon="mdi-magnify"
                    hide-details
                    @keyup.enter="onSearch"
                />
                <v-btn color="primary" @click="openCreate">
                    <v-icon start>mdi-plus</v-icon>
                    Nuevo
                </v-btn>
            </v-card-title>
        </v-card>
        <v-data-table-server
            :headers="headers"
            :items="items"
            :items-length="total"
            :loading="loading"
            :page="page"
            :items-per-page="itemsPerPage"
            item-key="id"
            density="compact"
            fixed-header
            height="73vh"
            hover
            @update:page="p => { page = p; fetch(); }"
            @update:items-per-page="val => { itemsPerPage = val; page = 1; fetch(); }"
        >
            <template v-slot:headers="{ columns, toggleSort, getSortIcon }">
                <tr>
                    <th
                        v-for="(column, index) in columns"
                        :key="column.key"
                        class="bg-accent text-white pa-3"
                        :style="{
                            borderTopLeftRadius: index === 0 ? '8px' : '0',
                            borderTopRightRadius: index === columns.length - 1 ? '8px' : '0'
                          }"
                        @click="toggleSort(column)">
                        {{ column.title }}
                    </th>
                </tr>
            </template>

            <template #item.is_active="{ item }">
                <v-chip :color="item.is_active ? 'success' : 'error'" size="small" variant="flat">
                    {{ item.is_active ? 'Sí' : 'No' }}
                </v-chip>
            </template>

            <template #item.actions="{ item }">
                <v-btn icon size="small" variant="text" @click="openEdit(item)">
                    <v-icon>mdi-pencil</v-icon>
                </v-btn>
                <v-btn icon size="small" variant="text" @click="confirmDelete(item)">
                    <v-icon>mdi-delete</v-icon>
                </v-btn>
            </template>

            <template #no-data>
                <div class="text-center py-10 text-gray-500">Sin resultados</div>
            </template>
        </v-data-table-server>

        <!-- Dialogo reutilizable -->
        <product-dialog
            v-model="dialog"
            :product="selectedProduct"
            :categories="categories"
            :units="units"
            :loading="saving"
            @submit="handleSubmit"
        />

        <!-- Snackbar -->
        <v-snackbar v-model="snackbar.show" :timeout="2500">
            {{ snackbar.text }}
        </v-snackbar>

        <!-- Diálogo confirmación borrar -->
        <v-dialog v-model="deleteDialog" max-width="480">
            <v-card>
                <v-card-title>Eliminar producto</v-card-title>
                <v-card-text>¿Seguro que deseas eliminar <b>{{ selectedProduct?.name }}</b>?</v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="deleteDialog=false">Cancelar</v-btn>
                    <v-btn color="error" :loading="saving" @click="doDelete">Eliminar</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script>
import API from "@/tenant/services/index.js";
import ProductDialog from "@/tenant/pages/items/ProductDialog.vue";

export default {
    name: 'ProductsPage',
    components: {ProductDialog},
    data() {
        return {
            headers: [
                {title: 'ID', key: 'id', width: 70, sortable: false},
                {title: 'Nombre', key: 'name', sortable: false},
                {title: 'SKU', key: 'sku', sortable: false},
                {title: 'Precio Venta', key: 'default_sale_price', sortable: false},
                {title: 'Mínimo', key: 'min_stock', sortable: false},
                {title: 'Activo', key: 'is_active', sortable: false},
                {title: '', key: 'actions', sortable: false},
            ],
            items: [],
            total: 0,
            loading: false,
            search: '',
            page: 1,
            itemsPerPage: 15,

            // diálogo
            dialog: false,
            saving: false,
            selectedProduct: null,

            // catálogos
            categories: [],
            units: [],

            // delete
            deleteDialog: false,

            snackbar: {show: false, text: ''},
        };
    },
    created() {
        this.fetch();
        this.fetchCatalogs();
    },
    methods: {
        async fetch() {
            try {
                this.loading = true;

                const params = {
                    search: this.search,
                    page: this.page,
                    per_page: this.itemsPerPage, // Usa snake_case para Laravel
                };

                const res = await API.products.list(params);
                console.log(res);

                // Laravel pagination estándar
                this.items = Array.isArray(res.data) ? res.data : [];
                this.total = Number(res.total ?? 0);
                this.page = Number(res.current_page ?? 1);
                this.itemsPerPage = Number(res.per_page ?? this.itemsPerPage);

            } catch (e) {
                console.error(e);
                this.toast('Error cargando productos');
            } finally {
                this.loading = false;
            }
        },
        async fetchCatalogs() {
            try {
                const [cats, units] = await Promise.all([
                    API.categories.list(),
                    API.units.list(),
                ]);
                this.categories = Array.isArray(cats?.data) ? cats.data : cats;
                this.units = Array.isArray(units?.data) ? units.data : units;
            } catch (e) {
                console.error(e);
                this.toast('Error cargando catálogos');
            }
        },
        onSearch() {
            this.page = 1;
            this.fetch();
        },
        openCreate() {
            this.selectedProduct = null;
            this.dialog = true;
        },
        openEdit(item) {
            // Opcional: cargar show() para obtener stocks/relaciones
            this.selectedProduct = {...item};
            this.dialog = true;
        },
        async handleSubmit(payload) {
            try {
                this.saving = true;
                if (payload.id) {
                    await API.products.update(payload.id, payload);
                    this.toast('Producto actualizado');
                } else {
                    await API.products.create(payload);
                    this.toast('Producto creado');
                }
                this.dialog = false;
                await this.fetch();
            } catch (e) {
                console.error(e);
                const msg = e?.response?.data?.message || 'Error al guardar';
                this.toast(msg);
            } finally {
                this.saving = false;
            }
        },
        confirmDelete(item) {
            this.selectedProduct = item;
            this.deleteDialog = true;
        },
        async doDelete() {
            try {
                this.saving = true;
                await API.products.delete(this.selectedProduct.id);
                this.toast('Producto eliminado');
                this.deleteDialog = false;
                await this.fetch();
            } catch (e) {
                console.error(e);
                this.toast('No se pudo eliminar');
            } finally {
                this.saving = false;
            }
        },
        toast(text) {
            this.snackbar.text = text;
            this.snackbar.show = true;
        },
    },
};
</script>
