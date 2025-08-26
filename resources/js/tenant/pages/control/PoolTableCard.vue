<template>
    <v-card class="rounded-xl overflow-hidden elevation-2">
        <!-- Cover -->
        <div class="relative">
            <v-img :src="coverSrc" height="120" class="bg-grey-lighten-3"/>

            <!-- Number badge -->
            <div class="absolute left-3 top-3">
                <v-avatar color="primary" size="36">
                    <span class="text-button">{{ table.number }}</span>
                </v-avatar>
            </div>

            <!-- Status chip -->
            <div class="absolute right-3 top-3">
                <v-chip :color="statusColor" size="small" class="text-white" label>
                    <v-icon start size="16">mdi-check-decagram-outline</v-icon>
                    {{ statusLabel }}
                </v-chip>
            </div>

            <!-- Edit/Cancel/POS -->
            <div class="absolute right-3 bottom-3 d-flex align-center gap-2">
                <v-btn icon size="small" variant="tonal" color="primary" @click.stop="onEdit">
                    <v-icon>mdi-pencil</v-icon>
                </v-btn>
                <v-btn icon size="small" variant="tonal" color="warning" @click.stop="onCancel">
                    <v-icon>mdi-cancel</v-icon>
                </v-btn>
                <v-btn icon size="small" variant="tonal" color="primary" @click.stop="openPOS(table)">
                    <v-icon>mdi-food-fork-drink</v-icon>
                </v-btn>
            </div>
        </div>

        <!-- Body -->
        <v-card-text class="pb-0">
            <div class="text-subtitle-1 font-weight-medium">{{ table.name }}</div>
            <div class="text-caption text-medium-emphasis">{{ table.type?.name || 'Pool' }}</div>

            <v-divider class="my-3"/>

            <v-row dense>
                <v-col cols="4" class="text-center">
                    <div class="text-overline text-medium-emphasis d-flex align-center justify-center gap-1">
                        <v-icon size="18">mdi-timer-outline</v-icon>
                        Tiempo
                    </div>
                    <div class="text-h6">{{ elapsedText }}</div>
                </v-col>

                <v-col cols="4" class="text-center">
                    <div class="text-overline text-medium-emphasis d-flex align-center justify-center gap-1">
                        <v-icon size="18">mdi-cash-multiple</v-icon>
                        Importe
                    </div>
                    <!-- En juego: importe en vivo; si no, el guardado -->
                    <div class="text-h6">{{ money(liveAmount) }}</div>
                    <div v-if="isInProgress" class="text-caption text-medium-emphasis">
                        Tarifa: {{ money(rate) }}/h
                    </div>
                </v-col>

                <v-col cols="4" class="text-center">
                    <div class="text-overline text-medium-emphasis d-flex align-center justify-center gap-1">
                        <v-icon size="18">mdi-food-fork-drink</v-icon>
                        Consumo
                    </div>
                    <div class="text-h6">{{ money(table.consumption) }}</div>
                </v-col>
            </v-row>
        </v-card-text>

        <!-- Actions -->
        <v-card-actions class="pt-0">
            <v-btn
                block
                :color="isAvailable ? 'success' : isInProgress ? 'primary' : 'grey'"
                :prepend-icon="isAvailable ? 'mdi-play' : isInProgress ? 'mdi-flag-checkered' : 'mdi-help'"
                :disabled="!isAvailable && !isInProgress"
                @click="isAvailable ? onStart() : onFinish()"
            >
                {{ isAvailable ? 'INICIAR' : isInProgress ? 'FINALIZAR Y COBRAR' : '—' }}
            </v-btn>
        </v-card-actions>

        <!-- POS Consumo -->
        <ConsumptionPOSDialog
            v-model="dialogs.pos"
            :table-number="current?.number"
            @confirm="onPosConfirm"
        />
    </v-card>
</template>

<script>
import ConsumptionPOSDialog from "@/tenant/pages/control/ConsumptionPOSDialog.vue";

export default {
    name: 'PoolTableCard',
    components: { ConsumptionPOSDialog },
    props: {
        table: { type: Object, required: true },
        coverSrc: { type: String, default: '/img/8ball-cover.jpg' },
    },
    data() {
        return {
            // RELOJ REACTIVO (sin refresh de página)
            nowTs: Date.now(),      // marca de tiempo "actual" reactiva
            tickerId: null,         // id del setInterval
            tickMs: 1000,           // cada cuántos ms actualizar

            dialogs: { finish: false, pos: false },
            current: null,
            finishForm: { consumption: 0, payment_method: 'cash', rate_per_hour: null, discount: 0, surcharge: 0 },
            posItems: []
        }
    },
    computed: {
        isAvailable() { return this.table?.status?.name === 'available' },
        isInProgress() { return this.table?.status?.name === 'in_progress' },

        statusLabel() {
            const s = this.table?.status?.name
            return s === 'available' ? 'Disponible'
                : s === 'in_progress' ? 'En juego'
                    : s === 'paused' ? 'Pausada'
                        : s === 'cancelled' ? 'Cancelada'
                            : '—'
        },
        statusColor() {
            const s = this.table?.status?.name
            return s === 'available' ? 'success'
                : s === 'in_progress' ? 'primary'
                    : s === 'paused' ? 'orange'
                        : s === 'cancelled' ? 'error'
                            : 'default'
        },

        // ======= Tiempo e importe en vivo =======
        rate() { return Number(this.table?.rate_per_hour || 0) },
        startTs() {
            const iso = this.table?.start_time
            return iso ? new Date(iso).getTime() : null
        },
        elapsedMs() {
            if (!this.isInProgress || !this.startTs) return 0
            // Usa nowTs (reactivo) en lugar de Date.now() directamente
            return Math.max(0, this.nowTs - this.startTs)
        },
        elapsedText() {
            if (!this.isInProgress || !this.startTs) return '00:00'
            const total = Math.floor(this.elapsedMs / 1000)
            const h = Math.floor(total / 3600).toString().padStart(2, '0')
            const m = Math.floor((total % 3600) / 60).toString().padStart(2, '0')
            const s = (total % 60).toString().padStart(2, '0')
            return h !== '00' ? `${h}:${m}:${s}` : `${m}:${s}`
        },
        liveAmount() {
            // Si no está en juego, muestra el monto guardado
            if (!this.isInProgress || !this.startTs) {
                return Number(this.table?.amount || 0)
            }
            const hours = this.elapsedMs / 3_600_000
            const amount = this.rate * hours
            return Math.round(amount * 100) / 100
        },
    },
    mounted() {
        // Un solo intervalo que actualiza 'nowTs' => todo lo demás reacciona
        this.tickerId = setInterval(() => { this.nowTs = Date.now() }, this.tickMs)

        // (Opcional) pausar cuando la pestaña no esté visible
        document.addEventListener('visibilitychange', this.onVisibilityChange)
    },
    beforeUnmount() {
        clearInterval(this.tickerId)
        document.removeEventListener('visibilitychange', this.onVisibilityChange)
    },
    methods: {
        onVisibilityChange() {
            if (document.hidden) {
                clearInterval(this.tickerId)
                this.tickerId = null
            } else if (!this.tickerId) {
                this.nowTs = Date.now()
                this.tickerId = setInterval(() => { this.nowTs = Date.now() }, this.tickMs)
            }
        },

        money(n) {
            const v = Number(n || 0)
            return new Intl.NumberFormat('es-PE', {
                style: 'currency', currency: 'PEN', minimumFractionDigits: 2
            }).format(v)
        },
        onStart() { this.$emit('start', this.table) },
        onFinish() { this.$emit('finish', this.table) },
        onCancel() { this.$emit('cancel', this.table) },
        onEdit() { this.$emit('edit', this.table) },

        openPOS(table) {
            this.current = table
            this.dialogs.pos = true
        },
        onPosConfirm({ items, total }) {
            this.posItems = items
            this.table.consumption = total
            this.finishForm.consumption = total
            this.$emit('update-consumption', { tableId: this.table.id, total, items })
        }
    },
}
</script>


<style scoped>
.relative { position: relative; }
.absolute { position: absolute; }
.gap-2 { gap: 8px; }
</style>
