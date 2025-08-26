<template>
    <v-dialog :model-value="modelValue" @update:model-value="v => $emit('update:modelValue', v)" max-width="560">
        <v-card>
            <v-card-title class="text-h6">{{ isEdit ? 'Editar mesa' : 'Nueva mesa' }}</v-card-title>

            <v-card-text>
                <v-alert v-if="serverError" type="error" density="comfortable" class="mb-3">{{ serverError }}</v-alert>

                <v-form ref="formRef" v-model="valid">
                    <v-row dense>
                        <v-col cols="12" md="4">
                            <v-text-field v-model.number="form.number" label="N° mesa" type="number" min="1" :rules="[req, posInt]" />
                        </v-col>
                        <v-col cols="12" md="8">
                            <v-text-field v-model.trim="form.name" label="Nombre" :rules="[req]" />
                        </v-col>

                        <!-- Solo creación: tipo y tarifa base -->
                        <template v-if="!isEdit">
                            <v-col cols="12" md="6">
                                <v-combobox
                                    v-model="form.type"
                                    :items="types"
                                    label="Tipo de mesa"
                                    hint="Debe existir en table_types o se usará 'Pool'"
                                    persistent-hint
                                    clearable
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field v-model.number="form.rate_per_hour" label="Tarifa por hora (PEN)" type="number" min="0" step="0.1" />
                            </v-col>
                        </template>

                        <!-- Solo edición: estado (según controlador) -->
                        <template v-else>
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.status"
                                    :items="statusItems"
                                    label="Estado"
                                    :hint="'El controlador permite cambiar estado, no tipo/ tarifa base'"
                                    persistent-hint
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field label="Tarifa base (no editable en este endpoint)" :model-value="money(table?.rate_per_hour)" readonly />
                            </v-col>
                        </template>
                    </v-row>
                </v-form>
            </v-card-text>

            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="$emit('update:modelValue', false)" :disabled="loading">Cancelar</v-btn>
                <v-btn color="primary" :loading="loading" @click="submit">{{ isEdit ? 'Guardar cambios' : 'Crear mesa' }}</v-btn>
            </v-card-actions>
        </v-card>

    </v-dialog>
</template>

<script>
import API from "@/tenant/services/index.js";

export default {
    name: 'PoolTableFormDialog',
    props: {
        modelValue: { type: Boolean, default: false },
        table: { type: Object, default: null },
        // Sugerencias de tipos a mostrar en el combobox (creación)
        types: { type: Array, default: () => ['Pool', 'Snooker', 'Carambola'] },
    },
    data () {
        return {
            api: axios.create({ baseURL: '/api' }),
            loading: false,
            valid: false,
            serverError: '',
            form: {
                number: null,
                name: '',
                type: 'Pool',
                rate_per_hour: 0,
                status: 'available',
            },
            statusItems: [
                { title: 'Disponible', value: 'available' },
                { title: 'En juego', value: 'in_progress' },
                { title: 'Pausada', value: 'paused' },
                { title: 'Cancelada', value: 'cancelled' },
            ],
        }
    },
    computed: {
        isEdit () { return !!(this.table && this.table.id) },
    },
    watch: {
        modelValue (v) {
            if (v) this.hydrate()
            else this.resetErrors()
        },
        table: {
            deep: true,
            handler () { if (this.modelValue) this.hydrate() },
        },
    },
    methods: {
        req (v) { return (v !== null && v !== undefined && String(v).trim() !== '') || 'Requerido' },
        posInt (v) { return (Number.isInteger(Number(v)) && Number(v) > 0) || 'Debe ser entero positivo' },
        money (n) { return new Intl.NumberFormat('es-PE',{ style:'currency', currency:'PEN'}).format(Number(n||0)) },

        hydrate () {
            // Cargar datos para crear o editar
            if (this.isEdit) {
                const t = this.table || {}
                this.form.number = t.number ?? null
                this.form.name = t.name ?? ''
                this.form.status = t.status?.name || 'available'
                // type y rate_per_hour no son editables según el controlador update()
            } else {
                this.form.number = null
                this.form.name = ''
                this.form.type = 'Pool'
                this.form.rate_per_hour = 0
                this.form.status = 'available'
            }
        },

        resetErrors () { this.serverError = '' },

        async submit () {
            const frm = await this.$refs.formRef?.validate()
            if (!frm?.valid) return

            this.loading = true
            this.serverError = ''
            try {
                if (this.isEdit) {
                    // UPDATE soporta: number, name, status, amount, consumption, start_time, end_time
                    const payload = {
                        number: Number(this.form.number),
                        name: this.form.name,
                        status: this.form.status,
                    }
                    const response = await API.pool_tables.update(this.table.id,payload)
                    // const { data } = await this.api.put(`/tables/${this.table.id}`, payload)
                    this.$emit('saved', response)
                    this.$emit('updated', response)
                } else {
                    // CREATE soporta: number, name, type (o type_id), rate_per_hour
                    const payload = {
                        number: Number(this.form.number),
                        name: this.form.name,
                        type: this.form.type || 'Pool',
                        rate_per_hour: Number(this.form.rate_per_hour || 0),
                    }
                    const response = await API.pool_tables.create(payload)
                    // const { data } = await this.api.post('/tables', payload)
                    this.$emit('saved', response)
                    this.$emit('created', response)
                }
                this.$emit('update:modelValue', false)
            } catch (err) {
                const msg = err?.response?.data?.message || err?.message || 'Ocurrió un error'
                this.serverError = msg
            } finally {
                this.loading = false
            }
        },
    },
}
</script>

<style scoped>
</style>
