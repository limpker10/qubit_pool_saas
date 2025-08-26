<!-- src/components/ProductDialog.vue -->
<template>
    <v-dialog v-model="internalDialog" max-width="720" persistent>
        <v-card>
            <v-toolbar density="comfortable" color="primary">
                <v-toolbar-title>
                    {{ isEdit ? 'Editar producto' : 'Nuevo producto' }}
                </v-toolbar-title>
                <template #append>
                    <v-btn icon variant="text" @click="close"><v-icon>mdi-close</v-icon></v-btn>
                </template>
            </v-toolbar>

            <v-card-text>
                <v-form ref="formRef" v-model="isValid" lazy-validation>
                    <div class="grid md:grid-cols-2 gap-4">
                        <v-text-field
                            v-model="form.name"
                            :rules="[rules.required]"
                            label="Nombre"
                            prepend-inner-icon="mdi-tag"
                            hide-details="auto"
                            required
                        />
                        <v-text-field
                            v-model="form.sku"
                            :rules="[rules.required]"
                            label="SKU"
                            prepend-inner-icon="mdi-barcode"
                            hide-details="auto"
                            required
                        />
                        <v-text-field
                            v-model="form.barcode"
                            label="Código de barras"
                            prepend-inner-icon="mdi-barcode-scan"
                            hide-details="auto"
                        />
                        <v-text-field
                            v-model.number="form.default_sale_price"
                            :rules="[rules.nonNegative]"
                            label="Precio venta"
                            type="number"
                            prepend-inner-icon="mdi-cash"
                            hide-details="auto"
                        />
                        <v-text-field
                            v-model.number="form.default_cost_price"
                            :rules="[rules.nonNegative]"
                            label="Costo"
                            type="number"
                            prepend-inner-icon="mdi-cash-multiple"
                            hide-details="auto"
                        />
                        <v-text-field
                            v-model.number="form.min_stock"
                            :rules="[rules.integerNonNegative]"
                            label="Stock mínimo"
                            type="number"
                            prepend-inner-icon="mdi-cube"
                            hide-details="auto"
                        />
                        <v-select
                            v-model="form.category_id"
                            :items="categories"
                            item-title="name"
                            item-value="id"
                            :rules="[rules.required]"
                            label="Categoría"
                            prepend-inner-icon="mdi-shape"
                            hide-details="auto"
                        />
                        <v-select
                            v-model="form.unit_id"
                            :items="units"
                            item-title="name"
                            item-value="id"
                            :rules="[rules.required]"
                            label="Unidad"
                            prepend-inner-icon="mdi-ruler"
                            hide-details="auto"
                        />
                    </div>

                    <v-textarea
                        v-model="form.description"
                        label="Descripción"
                        rows="2"
                        auto-grow
                        class="mt-4"
                        hide-details="auto"
                    />

                    <v-switch
                        v-model="form.is_active"
                        color="primary"
                        inset
                        label="Activo"
                        class="mt-2"
                    />
                </v-form>
            </v-card-text>

            <v-divider />

            <v-card-actions class="justify-end">
                <v-btn variant="text" @click="close">Cancelar</v-btn>
                <v-btn color="primary" :loading="loading" @click="submit">
                    {{ isEdit ? 'Guardar cambios' : 'Crear' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
export default {
    name: 'ProductDialog',
    props: {
        modelValue: { type: Boolean, default: false },
        product: { type: Object, default: null }, // si viene => editar
        categories: { type: Array, default: () => [] },
        units: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'submit'],
    data() {
        return {
            internalDialog: this.modelValue,
            isValid: false,
            form: this.getInitialForm(this.product),
            rules: {
                required: v => (!!v || v === 0) || 'Campo requerido',
                nonNegative: v => (v === null || v === '' || Number(v) >= 0) || 'No negativo',
                integerNonNegative: v => (v === null || v === '' || (Number.isInteger(Number(v)) && Number(v) >= 0)) || 'Entero ≥ 0',
            },
        };
    },
    computed: {
        isEdit() { return !!(this.product && this.product.id); },
    },
    watch: {
        modelValue(val) { this.internalDialog = val; if (val) this.resetForm(); },
        internalDialog(val) { this.$emit('update:modelValue', val); },
        product: {
            handler() { this.form = this.getInitialForm(this.product); },
            deep: true,
        },
    },
    methods: {
        getInitialForm(p) {
            return {
                id: p?.id || null,
                name: p?.name || '',
                sku: p?.sku || '',
                barcode: p?.barcode || '',
                description: p?.description || '',
                brand: p?.brand || '',
                category_id: p?.category_id || null,
                unit_id: p?.unit_id || null,
                default_cost_price: p?.default_cost_price ?? null,
                default_sale_price: p?.default_sale_price ?? null,
                min_stock: p?.min_stock ?? 0,
                is_active: p?.is_active ?? true,
            };
        },
        resetForm() {
            this.form = this.getInitialForm(this.product);
            this.$refs.formRef?.resetValidation?.();
        },
        close() { this.internalDialog = false; },
        async submit() {
            const ok = await this.$refs.formRef?.validate();
            if (!ok?.valid) return;
            // Emitimos los datos limpios al padre
            const payload = { ...this.form };
            if (!this.isEdit) delete payload.id;
            this.$emit('submit', payload);
        },
    },
};
</script>
