<template>
    <!-- Puedes envolver varios items con <v-list> en el padre; este componente representa 1 item -->
    <v-list-item
        :disabled="loading"
        class="rounded-lg elevation-1 mb-2 transition-surface"
        :class="[`is-${statusKey}`]"
        tabindex="0"
        role="group"
        :aria-label="`Mesa ${tableNumber}: ${statusLabel}`"
        @keyup.enter="$emit('start', tableNumber)"
    >
        <!-- PREPEND: media (avatar o imagen) -->
        <template #prepend>
            <v-list-item-media>
                <!-- Si hay imagen, mostramos miniatura, si no, avatar con número -->
                <template v-if="hasImage">
                    <!-- v-list-img envuelve a v-img -->
                    <v-list-img>
                        <v-img
                            :src="image"
                            alt="Foto de mesa"
                            width="56"
                            height="56"
                            cover
                            class="rounded"
                        />
                    </v-list-img>
                </template>
                <template v-else>
                    <v-avatar :color="chipColor" size="48" class="shadow-soft ring">
                        <span class="text-white font-weight-bold text-h6">{{ tableNumber }}</span>
                    </v-avatar>
                </template>
            </v-list-item-media>
        </template>

        <!-- CONTENIDO: título, subtítulo y métricas compactas -->
        <v-list-item-title class="text-uppercase truncate">
            {{ title }}
        </v-list-item-title>

        <v-list-item-subtitle class="text-medium-emphasis truncate">
            {{ subtitle }}
        </v-list-item-subtitle>

        <div class="d-flex ga-4 mt-1 text-caption flex-wrap">
            <span><v-icon size="16" color="info">mdi-timer-outline</v-icon> {{ formattedTime || '—' }}</span>
            <span><v-icon size="16" color="success">mdi-cash</v-icon> {{ formattedAmount }}</span>
            <span><v-icon size="16" color="primary">mdi-food</v-icon> {{ formattedConsumption }}</span>
        </div>

        <!-- APPEND: estado + acciones -->
        <template #append>
            <v-list-item-action class="d-flex flex-column align-end ga-2">
                <v-chip
                    :color="chipColor"
                    size="small"
                    variant="flat"
                    class="text-white font-weight-medium"
                >
                    <v-icon start size="14">{{ statusIcon }}</v-icon>
                    {{ statusLabel }}
                </v-chip>

                <div class="d-flex ga-1">
                    <!-- Cuando no está corriendo: botón Iniciar -->
                    <v-btn
                        v-if="!isRunning"
                        :color="startBtnColor"
                        size="small"
                        variant="elevated"
                        :loading="loading"
                        :disabled="startDisabled"
                        @click="$emit('start', tableNumber)"
                        prepend-icon="mdi-play"
                    >
                        {{ startLabel }}
                    </v-btn>

                    <!-- Cuando está corriendo: Agregar / Terminar -->
                    <template v-else>
                        <v-btn
                            color="secondary"
                            size="small"
                            variant="elevated"
                            prepend-icon="mdi-food"
                            @click="$emit('add-products', tableNumber)"
                            :aria-label="`Agregar productos en mesa ${tableNumber}`"
                        />
                        <v-btn
                            color="error"
                            size="small"
                            variant="elevated"
                            prepend-icon="mdi-flag-checkered"
                            @click="$emit('finish', tableNumber)"
                            :aria-label="`Terminar mesa ${tableNumber}`"
                        />
                    </template>

                    <!-- Editar siempre visible -->
                    <v-btn
                        icon
                        size="small"
                        variant="text"
                        color="secondary"
                        @click="$emit('editar', tableNumber)"
                        :aria-label="`Editar mesa ${tableNumber}`"
                    >
                        <v-icon>mdi-pencil</v-icon>
                    </v-btn>
                </div>
            </v-list-item-action>
        </template>
    </v-list-item>
</template>

<script>
export default {
    name: 'ListTablePool',
    emits: ['start', 'editar', 'finish', 'add-products'],
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
        image: { type: String, default: '' }, // ahora sí aprovechada para v-list-img
        loading: { type: Boolean, default: false },
        currency: { type: String, default: 'PEN' },
        locale: { type: String, default: 'es-PE' },
        startLabel: { type: String, default: 'Iniciar' },
    },
    computed: {
        hasImage() {
            return !!(this.image && String(this.image).trim());
        },
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
            if (isNaN(date.getTime())) return null;
            const options = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'America/Lima' };
            return new Intl.DateTimeFormat('es-PE', options).format(date);
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
    transition: background-color .2s ease;
}
.transition-surface:hover {
    background-color: rgba(0,0,0,.04);
}
.shadow-soft {
    box-shadow: 0 6px 16px rgba(0,0,0,.12);
}
.ring {
    outline: 3px solid rgba(255,255,255,.28);
    border-radius: 50%;
}
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
