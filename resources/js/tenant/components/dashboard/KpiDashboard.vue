<template>
    <v-container class="pa-4" fluid>
        <!-- Filtros / resumen -->
        <v-row class="mb-2" align="center" no-gutters>
            <v-col cols="12" class="d-flex align-center gap-2">
                <v-chip variant="flat" color="primary" class="mr-2" size="small">
                    {{ filters.range === 'today' ? 'Hoy' : (filters.range || 'Rango') }}
                </v-chip>
                <v-chip variant="tonal" size="small" class="mr-2">
                    {{ formatDate(filters.from) }} → {{ formatDate(filters.to) }}
                </v-chip>
                <v-spacer />
                <v-alert
                    v-if="cash && cash.has_session === false"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    border="start"
                    class="ma-0"
                >
                    {{ cash.message }}
                </v-alert>
            </v-col>
        </v-row>

        <!-- KPIs -->
        <v-row>
            <v-col cols="12" sm="4">
                <v-card density="compact" class="rounded-xl">
                    <v-card-text>
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <div class="text-overline text-medium-emphasis">Ventas Totales</div>
                                <div class="text-h5 font-weight-bold">{{ formatCurrency(totals.total_sales) }}</div>
                            </div>
                            <v-icon size="36" color="success">mdi-cash</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" sm="4">
                <v-card density="compact" class="rounded-xl">
                    <v-card-text>
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <div class="text-overline text-medium-emphasis">Comprobantes</div>
                                <div class="text-h5 font-weight-bold">{{ totals.documents_count }}</div>
                            </div>
                            <v-icon size="36" color="info">mdi-file-document-outline</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" sm="4">
                <v-card density="compact" class="rounded-xl">
                    <v-card-text>
                        <div class="d-flex align-center justify-space-between">
                            <div>
                                <div class="text-overline text-medium-emphasis">Ticket Promedio</div>
                                <div class="text-h5 font-weight-bold">{{ formatCurrency(totals.avg_ticket) }}</div>
                            </div>
                            <v-icon size="36" color="primary">mdi-cash-multiple</v-icon>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Gráficos fila 1 -->
        <v-row class="mt-1">
            <v-col cols="12" md="8">
                <v-card class="rounded-xl" :loading="isLoadingChart(salesOverTime)">
                    <v-card-title class="text-subtitle-1 d-flex align-center">
                        Ventas en el tiempo
                        <v-spacer />
                        <v-chip size="x-small" variant="flat" color="primary">{{ (salesOverTime && salesOverTime.length) || 0 }} día(s)</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text style="height: 320px;">
                        <LineChart
                            v-if="salesOverTime && salesOverTime.length"
                            :data="lineData"
                            :options="lineOptions"
                        />
                        <div v-else class="text-medium-emphasis text-center mt-6">Sin datos para mostrar</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card class="rounded-xl" :loading="isLoadingChart(byPaymentMethod)">
                    <v-card-title class="text-subtitle-1">Por método de pago</v-card-title>
                    <v-divider />
                    <v-card-text style="height: 320px;">
                        <DoughnutChart
                            v-if="byPaymentMethod && byPaymentMethod.length"
                            :data="pmDoughnutData"
                            :options="doughnutOptions"
                        />
                        <div v-else class="text-medium-emphasis text-center mt-6">Sin datos para mostrar</div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- Gráficos fila 2 -->
        <v-row class="mt-1">
            <v-col cols="12" md="6">
                <v-card class="rounded-xl" :loading="isLoadingChart(byDocumentType)">
                    <v-card-title class="text-subtitle-1">Totales por tipo de documento</v-card-title>
                    <v-divider />
                    <v-card-text style="height: 320px;">
                        <BarChart
                            v-if="byDocumentType && byDocumentType.length"
                            :data="docTypeBarData"
                            :options="barOptions"
                        />
                        <div v-else class="text-medium-emphasis text-center mt-6">Sin datos para mostrar</div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="6">
                <v-card class="rounded-xl" :loading="isLoadingChart(topItems)">
                    <v-card-title class="text-subtitle-1 d-flex align-center">
                        Ítems más vendidos
                        <v-spacer />
                        <v-chip size="x-small" variant="tonal">{{ (topItems && topItems.length) || 0 }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text style="height: 320px;">
                        <BarChart
                            v-if="topItems && topItems.length"
                            :data="topItemsBarData"
                            :options="horizontalBarOptions"
                        />
                        <div v-else class="text-medium-emphasis text-center mt-6">Sin datos para mostrar</div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { Line as LineChart, Doughnut as DoughnutChart, Bar as BarChart } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
} from 'chart.js'

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
)

// Defaults globales (texto/grids más marcados)
ChartJS.defaults.color = '#334155'       // slate-700 aprox
ChartJS.defaults.borderColor = '#e2e8f0' // grid claro

export default {
    name: 'KpiDashboard',
    components: { LineChart, DoughnutChart, BarChart },
    props: {
        kpis: { type: Object, required: true },
    },
    computed: {
        // Aliases seguros
        filters() { return (this.kpis && this.kpis.filters) || {} },
        totals() { return (this.kpis && this.kpis.totals) || { total_sales: 0, documents_count: 0, avg_ticket: 0 } },
        cash() { return (this.kpis && this.kpis.cash) || null },

        salesOverTime() { return (this.kpis && this.kpis.sales_over_time) || [] },
        byPaymentMethod() { return (this.kpis && this.kpis.by_payment_method) || [] },
        byDocumentType() { return (this.kpis && this.kpis.by_document_type) || [] },
        topItems() { return (this.kpis && this.kpis.top_items) || [] },

        currencyCode() { return (this.filters && this.filters.currency) || 'PEN' },
        locale() { return this.currencyCode === 'PEN' ? 'es-PE' : 'es' },

        // Colores base (desde variables de Vuetify si existen, con fallback a tu paleta)
        themeColors() {
            const getVar = (name, fb) => getComputedStyle(document.documentElement).getPropertyValue(name)?.trim() || fb
            return {
                primary:   getVar('--v-theme-primary',   '#283347'),
                secondary: getVar('--v-theme-secondary', '#586c91'),
                accent:    getVar('--v-theme-accent',    '#015366'),
                error:     getVar('--v-theme-error',     '#B00020'),
                info:      getVar('--v-theme-info',      '#2196F3'),
                success:   getVar('--v-theme-success',   '#4CAF50'),
                warning:   getVar('--v-theme-warning',   '#FB8C00'),
            }
        },

        // Versión "más fuerte" (más oscura) de tu paleta
        strongPalette() {
            return {
                primary:   this.darkenHex(this.themeColors.primary,   0.70), // 70% brillo
                secondary: this.darkenHex(this.themeColors.secondary, 0.75),
                accent:    this.darkenHex(this.themeColors.accent,    0.70),
                info:      this.darkenHex(this.themeColors.info,      0.75),
                success:   this.darkenHex(this.themeColors.success,   0.72),
                warning:   this.darkenHex(this.themeColors.warning,   0.80),
                error:     this.darkenHex(this.themeColors.error,     0.80),
            }
        },

        // Paleta rotatoria para series/segmentos
        chartPalette() {
            return [
                this.strongPalette.primary,
                this.strongPalette.accent,
                this.strongPalette.secondary,
                this.strongPalette.success,
                this.strongPalette.warning,
                this.strongPalette.info,
                this.strongPalette.error,
            ]
        },

        // Datos de gráficos con colores fuertes
        lineData() {
            const labels = this.salesOverTime.map(d => d.day)
            const data = this.salesOverTime.map(d => Number(d.total || 0))
            const base = this.strongPalette.primary
            return {
                labels,
                datasets: [{
                    label: 'Ventas',
                    data,
                    borderWidth: 2,
                    tension: 0.25,
                    pointRadius: 3,
                    fill: true,
                    borderColor: base,
                    backgroundColor: this.hexToRgba(base, 0.25),
                }],
            }
        },

        pmDoughnutData() {
            const labels = this.byPaymentMethod.map(p => (p.payment_method ? String(p.payment_method).toUpperCase() : '—'))
            const data = this.byPaymentMethod.map(p => Number(p.total || 0))
            const bg = labels.map((_, i) => this.chartPalette[i % this.chartPalette.length])
            return { labels, datasets: [{ label: 'Total', data, backgroundColor: bg, borderWidth: 0 }] }
        },

        docTypeBarData() {
            const labels = this.byDocumentType.map(d => this.docTypeLabel(d.type))
            const data = this.byDocumentType.map(d => Number(d.total || 0))
            const base = this.strongPalette.info
            return {
                labels,
                datasets: [{
                    label: 'Total',
                    data,
                    borderWidth: 1,
                    backgroundColor: this.hexToRgba(base, 0.35),
                    borderColor: base,
                }],
            }
        },

        topItemsBarData() {
            const labels = this.topItems.map(i => i.description || '—')
            const data = this.topItems.map(i => Number(i.total_amount || 0))
            const base = this.strongPalette.success
            return {
                labels,
                datasets: [{
                    label: 'Importe',
                    data,
                    borderWidth: 1,
                    backgroundColor: this.hexToRgba(base, 0.35),
                    borderColor: base,
                }],
            }
        },

        // Opciones de gráficos
        lineOptions() {
            const vm = this
            return {
                maintainAspectRatio: false,
                responsive: true,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: true },
                    tooltip: { callbacks: { label(ctx) { return `${ctx.dataset.label}: ${vm.formatCurrency(ctx.parsed.y)}` } } },
                    title: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback(v) {
                                return new Intl.NumberFormat(vm.locale, { style: 'currency', currency: vm.currencyCode, maximumFractionDigits: 0 }).format(v)
                            },
                        },
                        grid: { drawBorder: false },
                    },
                    x: { grid: { display: false } },
                },
            }
        },

        doughnutOptions() {
            const vm = this
            return {
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label(ctx) { return `${ctx.label}: ${vm.formatCurrency(ctx.parsed)}` } } },
                },
            }
        },

        barOptions() {
            const vm = this
            return {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: { display: true },
                    tooltip: { callbacks: { label(ctx) { return `${ctx.dataset.label}: ${vm.formatCurrency(ctx.parsed.y)}` } } },
                },
                scales: {
                    y: { beginAtZero: true, grid: { drawBorder: false } },
                    x: { grid: { display: false } },
                },
            }
        },

        horizontalBarOptions() {
            const vm = this
            return {
                indexAxis: 'y',
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: { display: true },
                    tooltip: { callbacks: { label(ctx) { return `${ctx.dataset.label}: ${vm.formatCurrency(ctx.parsed.x)}` } } },
                },
                scales: {
                    x: { beginAtZero: true, grid: { drawBorder: false } },
                    y: { grid: { display: false } },
                },
            }
        },
    },
    methods: {
        formatCurrency(amount) {
            const num = Number(amount || 0)
            return new Intl.NumberFormat(this.locale, { style: 'currency', currency: this.currencyCode }).format(num)
        },
        formatDate(iso) {
            if (!iso) return '—'
            try {
                const d = new Date(iso)
                return d.toLocaleString(this.locale, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
            } catch (e) {
                return iso
            }
        },
        isLoadingChart(arr) { return !Array.isArray(arr) },
        docTypeLabel(type) {
            const map = {
                invoice: 'Factura',
                boleta: 'Boleta',
                ticket: 'Ticket',
                sale_note: 'Nota de venta',
                credit_note: 'Nota de crédito',
                debit_note: 'Nota de débito',
            }
            return map[type] || (type ? String(type).replaceAll('_', ' ') : '—')
        },
        hexToRgba(hex, alpha = 0.2) {
            const m = hex.replace('#','')
            const v = m.length === 3 ? m.split('').map(c=>c+c).join('') : m
            const bigint = parseInt(v, 16)
            const r = (bigint >> 16) & 255, g = (bigint >> 8) & 255, b = bigint & 255
            return `rgba(${r}, ${g}, ${b}, ${alpha})`
        },
        darkenHex(hex, factor = 0.8) { // factor < 1 oscurece
            const m = hex.replace('#','')
            const v = m.length === 3 ? m.split('').map(c=>c+c).join('') : m
            const bigint = parseInt(v, 16)
            let r = Math.round(((bigint >> 16) & 255) * factor)
            let g = Math.round(((bigint >> 8) & 255) * factor)
            let b = Math.round((bigint & 255) * factor)
            r = Math.max(0, Math.min(255, r))
            g = Math.max(0, Math.min(255, g))
            b = Math.max(0, Math.min(255, b))
            return `#${r.toString(16).padStart(2,'0')}${g.toString(16).padStart(2,'0')}${b.toString(16).padStart(2,'0')}`
        },
    },
}
</script>

<style scoped>
.gap-2 { gap: 8px; }
</style>
