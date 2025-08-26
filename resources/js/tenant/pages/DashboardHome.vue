<template>
    <v-container class="pa-4" fluid>
        <div class="d-flex align-center mb-3">
            <v-btn
                color="primary"
                prepend-icon="mdi-refresh"
                @click="loadKpis"
                :loading="loading"
            >
                Actualizar
            </v-btn>
            <v-spacer></v-spacer>
            <v-chip size="small" variant="flat" color="primary" class="mr-2">
                {{ filters.range === 'today' ? 'Hoy' : (filters.range || 'Rango') }}
            </v-chip>
            <v-chip size="small" variant="tonal">
                {{ filters.from }} → {{ filters.to }}
            </v-chip>
        </div>

        <v-alert
            v-if="error"
            type="error"
            variant="tonal"
            class="mb-4"
        >
            {{ error }}
        </v-alert>

        <!-- Pasa null sin miedo: KpiDashboard usa defaults seguros -->
        <KpiDashboard :kpis="kpis || { filters }" />
    </v-container>
</template>

<script>

import KpiDashboard from "@/tenant/components/dashboard/KpiDashboard.vue";
import API from "@/tenant/services/index.js";

export default {
    name: "DashboardKpisPage",
    components: { KpiDashboard },
    data() {
        return {
            // Filtros iniciales (puedes ligarlos a inputs/datepicker luego)
            filters: {
                from: "2025-08-22T00:00:00-05:00",
                to: "2025-08-22T23:59:59-05:00",
                range: "today",
                sessionId: null,
                currency: "PEN",
            },

            // Estado de red
            kpis: null,
            loading: false,
            error: null,

            // Cambia esto si tu front corre en otro host/domino
            baseUrl: "/api", // Laravel api.php => prefijo /api
        }
    },
    created() {
        this.loadKpis()
    },
    watch: {
        // Si cambias filtros desde la UI, volverá a pedir datos
        filters: {
            deep: true,
            handler() {
                this.loadKpis()
            },
        },
    },
    methods: {
        buildQuery(params) {
            const qs = new URLSearchParams()
            Object.entries(params).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== "") qs.append(k, v)
            })
            return qs.toString()
        },
        async loadKpis() {
            this.loading = true
            this.error = null
            try {
                const qs = this.buildQuery(this.filters)
                const response = await API.dashboard.list(this.filters)
                console.log(response)
                this.kpis = response
            } catch (e) {
                this.error = `No se pudo cargar KPIs: ${e.message || e}`
                // Fallback mínimo para que el hijo no rompa
                this.kpis = this.kpis || {
                    filters: this.filters,
                    totals: { total_sales: 0, documents_count: 0, avg_ticket: 0 },
                    by_payment_method: [],
                    by_document_type: [],
                    sales_over_time: [],
                    top_items: [],
                    cash: null,
                }
            } finally {
                this.loading = false
            }
        },
    },
}
</script>
