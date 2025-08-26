<template>
    <v-container fluid>
        <v-card class="rounded-lg mb-4">
            <v-toolbar color="primary" class="rounded-t-lg ma-0">
                <v-toolbar-title class="text-white d-flex align-center ga-2">
                    <v-icon>mdi-cash-multiple</v-icon>
                    Listado de cajas
                </v-toolbar-title>
                <v-spacer/>
                <v-btn icon @click="fetchSessions" :loading="loading" aria-label="Recargar">
                    <v-icon>mdi-refresh</v-icon>
                </v-btn>
            </v-toolbar>
            <v-card-text>
                <!-- Filtros -->
                <v-row dense align="end">
                    <v-col cols="12" sm="4">
                        <v-select
                            v-model="filters.status"
                            :items="statusItems"
                            label="Estado"
                            density="compact"
                            variant="outlined"
                            clearable
                            hide-details
                            prepend-inner-icon="mdi-filter"
                            @update:model-value="applyFilters"
                        />
                    </v-col>
                    <v-col cols="12" sm="4">
                        <v-text-field
                            v-model="filters.from"
                            label="Desde"
                            type="date"
                            density="compact"
                            variant="outlined"
                            hide-details
                            prepend-inner-icon="mdi-calendar-start"
                            @change="applyFilters"
                        />
                    </v-col>
                    <v-col cols="12" sm="4">
                        <v-text-field
                            v-model="filters.to"
                            label="Hasta"
                            type="date"
                            density="compact"
                            variant="outlined"
                            prepend-inner-icon="mdi-calendar-end"
                            @change="applyFilters"
                            hide-details
                        />
                    </v-col>
                </v-row>
            </v-card-text>
            <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="2500">{{ snackbar.text }}</v-snackbar>
        </v-card>
        <v-data-table
            :headers="headers"
            :items="itemsComputed"
            :items-per-page="10"
            :loading="loading"
            item-key="id"
            hover
            fixed-header
            class="border rounded"
        >
            <template #item.user="{ item }">
                <div class="d-flex align-center ga-2">
                    <v-avatar size="24" color="primary" variant="tonal">
                        <v-icon size="16">mdi-account</v-icon>
                    </v-avatar>
                    <span>{{ item.user?.name || `#${item.user_id}` }}</span>
                </div>
            </template>

            <template #item.status="{ item }">
                <v-chip :color="item.status === 'open' ? 'success' : 'secondary'" size="small">
                    <v-icon start>{{ item.status === 'open' ? 'mdi-circle' : 'mdi-check' }}</v-icon>
                    {{ item.status === 'open' ? 'Abierta' : 'Cerrada' }}
                </v-chip>
            </template>
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

            <template #item.opening_cash="{ item }">
                {{ nf(item.opening_cash) }}
            </template>
            <template #item.expected_cash="{ item }">
                {{ nf(item.expected_cash) }}
            </template>
            <template #item.counted_cash="{ item }">
                {{ item.counted_cash != null ? nf(item.counted_cash) : '—' }}
            </template>
            <template #item.difference="{ item }">
          <span :class="Number(item.difference||0) >= 0 ? 'text-success' : 'text-error'">
            {{ item.difference != null ? nf(item.difference) : '—' }}
          </span>
            </template>

            <template #item.actions="{ item }">
                <v-btn size="small" variant="text" color="primary" @click="openDetail(item)"
                       :loading="loadingDetail && selected?.id===item.id">
                    <v-icon start size="18">mdi-eye</v-icon>
                    Ver
                </v-btn>
            </template>

            <template #no-data>
                <div class="text-center text-medium-emphasis py-8">Sin datos</div>
            </template>
        </v-data-table>
        <!-- Dialog detalle -->
        <v-dialog v-model="detailDialog" max-width="900" class="rounded-lg">
            <v-card class="rounded-lg">
                <v-toolbar flat color="primary" class="rounded-t-lg">
                    <v-toolbar-title class="text-white d-flex align-center ga-2">
                        <v-icon>mdi-eye</v-icon>
                        Caja #{{ selected?.id || '—' }} — {{ selectedStatusLabel }}
                    </v-toolbar-title>
                    <v-spacer/>
                    <v-btn icon @click="detailDialog=false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-toolbar>
                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="5">
                            <v-list density="compact">
                                <v-list-item>
                                    <v-list-item-title class="text-medium-emphasis">Usuario</v-list-item-title>
                                    <v-list-item-subtitle>{{
                                            selected?.user?.name || `#${selected?.user_id}`
                                        }}
                                    </v-list-item-subtitle>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title class="text-medium-emphasis">Apertura</v-list-item-title>
                                    <v-list-item-subtitle>{{ dt(selected?.opened_at) }}</v-list-item-subtitle>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title class="text-medium-emphasis">Cierre</v-list-item-title>
                                    <v-list-item-subtitle>{{ dt(selected?.closed_at) }}</v-list-item-subtitle>
                                </v-list-item>
                                <v-divider class="my-2"/>
                                <v-list-item>
                                    <v-list-item-title class="text-medium-emphasis">Inicial</v-list-item-title>
                                    <v-list-item-subtitle>{{ nf(selected?.opening_cash) }}</v-list-item-subtitle>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title class="text-medium-emphasis">Esperado</v-list-item-title>
                                    <v-list-item-subtitle>{{ nf(totals.expected) }}</v-list-item-subtitle>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title class="text-medium-emphasis">Contado</v-list-item-title>
                                    <v-list-item-subtitle>
                                        {{ selected?.counted_cash != null ? nf(selected?.counted_cash) : '—' }}
                                    </v-list-item-subtitle>
                                </v-list-item>
                                <v-list-item>
                                    <v-list-item-title class="text-medium-emphasis">Diferencia</v-list-item-title>
                                    <v-list-item-subtitle
                                        :class="(selected?.difference||0)>=0 ? 'text-success' : 'text-error'">
                                        {{ selected?.difference != null ? nf(selected?.difference) : '—' }}
                                    </v-list-item-subtitle>
                                </v-list-item>
                            </v-list>
                        </v-col>
                        <v-col cols="12" md="7">
                            <v-table density="compact" class="border rounded">
                                <thead>
                                <tr>
                                    <th class="text-left p-2">Fecha</th>
                                    <th class="text-left p-2">Tipo</th>
                                    <th class="text-right p-2">Monto</th>
                                    <th class="text-left p-2">Descripción</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="m in movements" :key="m.id">
                                    <td class="p-2">{{ dt(m.created_at) }}</td>
                                    <td class="p-2">{{ typeLabel(m.type) }}</td>
                                    <td class="p-2 text-right" :class="m.amount>=0 ? 'text-success' : 'text-error'">
                                        {{ nf(m.amount) }}
                                    </td>
                                    <td class="p-2">{{ m.description || '—' }}</td>
                                </tr>
                                <tr v-if="!movements.length">
                                    <td colspan="4" class="text-center text-medium-emphasis p-6">Sin movimientos
                                    </td>
                                </tr>
                                </tbody>
                            </v-table>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script>
import axios from 'axios';
import API from "@/services/index.js";

export default {
    name: 'CashSessionList',
    data() {
        return {
            loading: false,
            sessions: [],
            filters: {status: null, from: null, to: null},
            statusItems: [
                {title: 'Todas', value: null},
                {title: 'Abiertas', value: 'open'},
                {title: 'Cerradas', value: 'closed'},
            ],
            headers: [
                {title: 'ID', key: 'id', width: 80},
                {title: 'Usuario', key: 'user'},
                {title: 'Apertura', key: 'opened_at'},
                {title: 'Cierre', key: 'closed_at'},
                {title: 'Estado', key: 'status'},
                {title: 'Inicial', key: 'opening_cash', align: 'end'},
                {title: 'Esperado', key: 'expected_cash', align: 'end'},
                {title: 'Contado', key: 'counted_cash', align: 'end'},
                {title: 'Diferencia', key: 'difference', align: 'end'},
                {title: 'Acciones', key: 'actions', sortable: false, width: 120},
            ],
            detailDialog: false,
            loadingDetail: false,
            selected: null,
            movements: [],
            snackbar: {show: false, text: '', color: 'success'},
        };
    },
    computed: {
        itemsComputed() {
            let arr = [...this.sessions];
            if (this.filters.status) {
                arr = arr.filter(s => s.status === this.filters.status);
            }
            if (this.filters.from) {
                const from = new Date(this.filters.from + 'T00:00:00');
                arr = arr.filter(s => new Date(s.opened_at) >= from);
            }
            if (this.filters.to) {
                const to = new Date(this.filters.to + 'T23:59:59');
                arr = arr.filter(s => new Date(s.opened_at) <= to);
            }
            return arr.map(s => ({
                ...s,
                opened_at: s.opened_at ? new Date(s.opened_at).toLocaleString() : '—',
                closed_at: s.closed_at ? new Date(s.closed_at).toLocaleString() : '—',
            }));
        },
        selectedStatusLabel() {
            return this.selected?.status === 'open' ? 'Abierta' : 'Cerrada';
        },
        totals() {
            const expected = this.movements.reduce((a, m) => a + Number(m.amount || 0), 0);
            return {expected};
        },
    },
    mounted() {
        this.fetchSessions();
    },
    methods: {
        nf(n) {
            try {
                return new Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN'}).format(Number(n || 0));
            } catch {
                return Number(n || 0).toFixed(2);
            }
        },
        dt(s) {
            const d = new Date(s);
            return isNaN(d) ? '—' : d.toLocaleString();
        },
        typeLabel(t) {
            return ({
                open: 'Apertura',
                sale: 'Venta',
                income: 'Ingreso',
                expense: 'Egreso',
                withdrawal: 'Retiro',
                refund: 'Devolución',
                adjust: 'Ajuste'
            })[t] || t;
        },

        async fetchSessions() {
            try {
                this.loading = true;
                const response = await API.cash_sessions.list();
                this.sessions = response?.data || data || [];
            } catch (e) {
                this.toast(e?.response?.data?.message || 'No se pudo cargar el listado', 'error');
            } finally {
                this.loading = false;
            }
        },
        applyFilters() {
            // Si quieres filtrar server-side, llama de nuevo a fetchSessions enviando los params
            // Aquí filtramos client-side para simplicidad
        },
        async openDetail(item) {
            this.selected = item;
            this.detailDialog = true;
            try {
                this.loadingDetail = true;
                // const {data} = await axios.get(`/api/cash-sessions/${item.id}?include=movements`);
                const response = await API.cash_sessions.show(item.id,{include:'movements'});
                console.log(response)
                this.selected = response?.session || item;
                this.movements = response?.movements || [];
            } catch (e) {
                this.toast(e?.response?.data?.message || 'No se pudo cargar el detalle', 'error');
            } finally {
                this.loadingDetail = false;
            }
        },
        toast(text, color = 'success') {
            this.snackbar = {show: true, text, color};
        },
    },
};
</script>

<style scoped>
.border {
    border: 1px solid rgba(0, 0, 0, .08);
}
</style>
