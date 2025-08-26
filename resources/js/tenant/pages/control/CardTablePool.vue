<template>
    <v-card
        :disabled="loading"
        :loading="loading"
        class="card-table-pool rounded-xl elevation-2 transition-surface"
        :class="[`is-${statusKey}`]"
        tabindex="0"
        role="group"
        :aria-label="`Mesa ${tableNumber}: ${statusLabel}`"
        @keyup.enter="$emit('start', tableNumber)"
    >
        <!-- Loader -->
        <template #loader="{ isActive }">
            <v-progress-linear :active="isActive" color="success" height="3" indeterminate />
        </template>

        <!-- Hero -->
        <div class="card-hero">
            <v-img
                :src="backgroundImage"
                height="148"
                cover
                class="hero-img"
                :alt="`Imagen de ${title}`"
            >
                <template #placeholder>
                    <v-skeleton-loader type="image" height="148" />
                </template>
                <div class="hero-overlay" />
            </v-img>

            <div class="hero-content">
                <div class="d-flex align-center justify-space-between w-100">
                    <!-- Avatar y textos -->
                    <div class="d-flex align-center ga-3 min-w-0">
                        <v-avatar
                            size="56"
                            variant="flat"
                            :color="chipColor"
                            class="shadow-soft ring"
                            :aria-label="`Mesa ${tableNumber}`"
                        >
                            <span class="text-white font-weight-bold text-h5">{{ tableNumber }}</span>
                        </v-avatar>

                        <div class="truncate">
                            <v-card-title class="text-uppercase lh-1 truncate">{{ title }}</v-card-title>
                            <v-card-subtitle class="text-medium-emphasis mt-n1 truncate">{{ subtitle }}</v-card-subtitle>
                        </div>
                    </div>

                    <!-- Estado y editar -->
                    <div class="d-flex align-center ga-2">
                        <v-tooltip :text="`Estado: ${statusLabel}`" location="top">
                            <template #activator="{ props }">
                                <v-chip
                                    v-bind="props"
                                    :color="chipColor"
                                    size="small"
                                    variant="flat"
                                    class="text-white font-weight-medium shadow-soft"
                                >
                                    <v-icon start size="16">{{ statusIcon }}</v-icon>
                                    {{ statusLabel }}
                                </v-chip>
                            </template>
                        </v-tooltip>

                        <v-tooltip text="Editar mesa" location="top">
                            <template #activator="{ props }">
                                <v-btn
                                    v-bind="props"
                                    icon
                                    size="small"
                                    variant="text"
                                    color="secondary"
                                    @click="$emit('editar', tableNumber)"
                                    :aria-label="`Editar mesa ${tableNumber}`"
                                >
                                    <v-icon>mdi-pencil</v-icon>
                                </v-btn>
                            </template>
                        </v-tooltip>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info -->
        <v-card-text class="pt-4">
            <v-row class="text-center" align="center" justify="center" dense>
                <!-- Tiempo -->
                <v-col cols="12" sm="4" class="py-3">
                    <v-icon size="20" color="info" class="mb-1">mdi-timer-outline</v-icon>
                    <div class="text-caption text-medium-emphasis">Tiempo</div>
                    <div class="text-body-1 font-weight-medium">{{ formattedTime  || '—' }}</div>
                </v-col>

                <!-- Importe -->
                <v-col cols="12" sm="4" class="py-3">
                    <v-icon size="20" color="success" class="mb-1">mdi-cash</v-icon>
                    <div class="text-caption text-medium-emphasis">Importe</div>
                    <div class="text-body-1 font-weight-medium">{{ formattedAmount }}</div>
                </v-col>

                <!-- Consumo -->
                <v-col cols="12" sm="4" class="py-3">
                    <v-icon size="20" color="primary" class="mb-1">mdi-food</v-icon>
                    <div class="text-caption text-medium-emphasis">Consumo</div>
                    <div class="text-body-1 font-weight-medium">{{ formattedConsumption }}</div>
                </v-col>
            </v-row>
        </v-card-text>


        <v-divider class="mx-4 mb-1" />

        <!-- Botón acción -->
        <v-card-actions class="px-4 pb-4">
            <v-slide-y-transition mode="out-in">
                <!-- Estado: NO iniciada -> botón Iniciar -->
                <div v-if="!isRunning" key="start" class="w-100">
                    <v-btn
                        :color="startBtnColor"
                        block
                        variant="elevated"
                        :loading="loading"
                        :disabled="startDisabled"
                        @click="$emit('start', tableNumber)"
                        prepend-icon="mdi-play"
                        :aria-label="`Iniciar mesa ${tableNumber}`"
                        class="rounded-lg shadow-soft"
                    >
                        {{ startLabel }}
                    </v-btn>
                </div>

                <!-- Estado: iniciada -> botones Agregar / Terminar con animación -->
                <div
                    v-else
                    key="running"
                    class="d-flex ga-2 w-100 started-actions"
                    :class="{'started-pulse': justStarted}"
                >
                    <v-btn
                        color="secondary"
                        class="rounded-lg shadow-soft flex-1"
                        variant="elevated"
                        prepend-icon="mdi-food"
                        @click="$emit('add-products', tableNumber)"
                        :aria-label="`Agregar productos en mesa ${tableNumber}`"
                    >
                    </v-btn>

                    <v-btn
                        color="error"
                        class="rounded-lg shadow-soft flex-1"
                        variant="elevated"
                        prepend-icon="mdi-flag-checkered"
                        @click="$emit('finish', tableNumber)"
                        :aria-label="`Terminar mesa ${tableNumber}`"
                    >
                    </v-btn>
                </div>
            </v-slide-y-transition>
        </v-card-actions>
    </v-card>
</template>

<script>
export default {
    name: 'CardTablePool',
    emits: ['start', 'editar', 'finish', 'add-products'],
    data() {
        return {
            justStarted: false, // para animación al pasar a in_progress
        };
    },
    props: {
        title: { type: String, default: 'Mesa' },
        subtitle: { type: String, default: '' },
        tableNumber: { type: [String, Number], required: true },
        time: { type: String, default: '—' },
        amount: { type: [String, Number], default: 0 },
        consumption: { type: [String, Number], default: 0 },
        status: { type: String, default: 'disponible' },
        statusColor: { type: String, default: '' },
        price: { type: [String, Number], default: '' },
        image: { type: String, default: '' },
        loading: { type: Boolean, default: false },
        currency: { type: String, default: 'PEN' },
        locale: { type: String, default: 'es-PE' },
        startLabel: { type: String, default: 'Iniciar' },
    },
    watch: {
        statusKey(newVal, oldVal) {
            // cuando cambie de "no iniciado" a "in_progress", dispara animación breve
            if (oldVal !== 'in_progress' && newVal === 'in_progress') {
                this.justStarted = true;
                setTimeout(() => (this.justStarted = false), 900);
            }
        },
    },
    computed: {
        isRunning() {
            return this.statusKey === 'in_progress';
        },
        statusKey() {
            return String(this.status || '').toLowerCase().replace(/\s+/g, '_');
        },
        statusLabel() {
            const map = {
                disponible: 'Disponible',
                in_progress: 'En progreso',
                occupied: 'Ocupada',
                mantenimiento: 'Mantenimiento',
            };
            return map[this.statusKey] || this.status;
        },
        chipColor() {
            return this.statusColor || {
                disponible: 'success',
                occupied: 'error',
                in_progress: 'warning',
                mantenimiento: 'secondary',
            }[this.statusKey] || 'secondary';
        },
        backgroundImage() {
            return this.image || '/images/default-mesa-billar.png';
        },
        statusIcon() {
            const map = {
                disponible: 'mdi-check-circle',
                in_progress: 'mdi-timer-outline',
                occupied: 'mdi-account',
                mantenimiento: 'mdi-wrench',
            };
            return map[this.statusKey] || 'mdi-information-outline';
        },
        nf() {
            return new Intl.NumberFormat(this.locale, {
                style: 'currency',
                currency: this.currency,
                minimumFractionDigits: 2,
            });
        },
        formattedAmount() {
            const val = Number(String(this.amount).replace(/[^\d.-]/g, ''));
            return isFinite(val) ? this.nf.format(val) : '—';
        },
        formattedConsumption() {
            const val = Number(String(this.consumption).replace(/[^\d.-]/g, ''));
            return isFinite(val) ? this.nf.format(val) : '—';
        },
        formattedTime() {
            const date = new Date(this.time);

            const options = {
                hour: "2-digit",
                minute: "2-digit",
                hour12: false,
                timeZone: "America/Lima"
            };

            return new Intl.DateTimeFormat("es-PE", options).format(date);

        },
        startDisabled() {
            return this.loading || ['in_progress', 'occupied'].includes(this.statusKey);
        },
        startBtnColor() {
            return this.startDisabled ? 'grey' : 'success';
        },

    },
};
</script>

<style scoped>
.transition-surface {
    transition: transform .2s ease, box-shadow .2s ease;
}
.transition-surface:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,.08);
}

.card-hero {
    position: relative;
}
.hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,.0) 20%, rgba(0,0,0,.4) 85%);
}
.hero-content {
    position: absolute;
    inset: 0;
    padding: 12px 16px;
    display: flex;
    align-items: flex-end;
}
.truncate {
    min-width: 0;
}
.truncate > * {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.lh-1 {
    line-height: 1;
}
.shadow-soft {
    box-shadow: 0 6px 16px rgba(0,0,0,.12);
}
.ring {
    outline: 3px solid rgba(255,255,255,.28);
    border-radius: 50%;
}
.flex-1 { flex: 1 1 0; }

.started-actions {
    /* ayuda a que el pulse se note en el contenedor de los dos botones */
    will-change: transform, box-shadow;
}

@keyframes pulse-in {
    0%   { transform: scale(0.98); box-shadow: 0 0 0 0 rgba(16,185,129,0); }
    60%  { transform: scale(1.01); box-shadow: 0 0 0 10px rgba(16,185,129,0.18); }
    100% { transform: scale(1);    box-shadow: 0 0 0 0 rgba(16,185,129,0); }
}
.started-pulse {
    animation: pulse-in .9s ease-out;
}
</style>
