<template>
    <v-card class="rounded-lg overflow-hidden elevation-2">

        <div
            class="relative cover-area"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
        >
            <!-- Fondo como background -->
            <div class="absolute inset-0 cover-bg" :style="coverStyle"></div>

            <!-- Borde al arrastrar -->
            <div
                v-if="isDragging"
                class="absolute inset-0 d-flex align-center justify-center"
                style="border:2px dashed rgba(255,255,255,.9); backdrop-filter: blur(2px);"
            >
                <span class="text-button font-weight-medium">Suelta la imagen para subirla</span>
            </div>

            <!-- Botón cámara -->
            <div class="absolute left-3 bottom-3">
                <v-tooltip text="Cambiar portada">
                    <template #activator="{ props }">
                        <v-btn
                            v-bind="props"
                            icon
                            size="small"
                            variant="elevated"
                            color="primary"
                            @click.stop="openFilePicker"
                            :loading="uploading"
                        >
                            <v-icon>mdi-camera</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    class="d-none"
                    @change="onFileChange"
                />
            </div>

            <!-- Badge número -->
            <div class="absolute left-3 top-3">
                <v-avatar color="secondary" size="36" variant="elevated">
                    <span class="text-button font-weight-bold">{{ table.number }}</span>
                </v-avatar>
            </div>

            <!-- Chip estado -->
            <div class="absolute right-3 top-3">
                <v-chip :color="statusColor" size="small" class="text-white" label variant="elevated">
                    <v-icon start size="16" variant="elevated">mdi-check-decagram-outline</v-icon>
                    {{ statusLabel }}
                </v-chip>
            </div>

            <!-- Edit/Cancel/POS -->
            <div class="absolute right-3 bottom-3 d-flex align-center gap-2">
                <v-btn icon size="small" variant="elevated" color="primary" @click.stop="onEdit">
                    <v-icon>mdi-pencil</v-icon>
                </v-btn>
                <v-btn icon size="small" variant="elevated" color="warning" @click.stop="onCancel">
                    <v-icon>mdi-cancel</v-icon>
                </v-btn>
                <v-btn icon size="small" variant="elevated" color="primary"  @click="$emit('open-pos', table)">
                    <v-icon>mdi-food-fork-drink</v-icon>
                </v-btn>
            </div>

            <!-- Indicador de subida -->
            <div v-if="uploading" class="absolute right-3 top-12">
                <v-progress-circular indeterminate size="24"></v-progress-circular>
            </div>
        </div>


        <!-- Error de subida -->
        <v-alert
            v-if="uploadError"
            type="error"
            density="comfortable"
            class="ma-3"
            :text="uploadError"
            @click="uploadError = ''"
        />

        <!-- Body -->
        <v-card-text class="pb-0">
            <!--            <div class="text-subtitle-1 font-weight-medium">{{ table.name }}</div>-->
            <!--            <div class="text-caption text-medium-emphasis">{{ table.type?.name || 'Pool' }}</div>-->

            <!--            <v-divider class="my-3"/>-->

            <v-row dense>
                <v-col cols="4" class="text-center">
                    <div class="text-overline text-medium-emphasis d-flex align-center justify-center gap-1">
                        <v-icon size="18">mdi-timer-outline</v-icon>
                    </div>
                    <div class="text-h6">{{ elapsedText }}</div>
                </v-col>

                <v-col cols="4" class="text-center">
                    <div class="text-overline text-medium-emphasis d-flex align-center justify-center gap-1">
                        <v-icon size="18">mdi-cash-multiple</v-icon>
                    </div>
                    <div class="text-h6">{{ money(liveAmount) }}</div>
                    <div v-if="isInProgress" class="text-caption text-medium-emphasis">
                        Tarifa: {{ money(rate) }}/h
                    </div>
                </v-col>

                <v-col cols="4" class="text-center">
                    <div class="text-overline text-medium-emphasis d-flex align-center justify-center gap-1">
                        <v-icon size="18">mdi-food-fork-drink</v-icon>
                    </div>
                    <div class="text-h6">{{ money(table.consumption) }}</div>
                </v-col>
            </v-row>
        </v-card-text>

        <!-- Actions -->
        <v-card-actions class="pt-0">
            <v-btn
                block
                variant="flat"
                :color="isAvailable ? 'success' : isInProgress ? 'primary' : 'grey'"
                :prepend-icon="isAvailable ? 'mdi-play' : isInProgress ? 'mdi-flag-checkered' : 'mdi-help'"
                :disabled="!isAvailable && !isInProgress"
                @click="isAvailable ? onStart() : onFinish()"
            >
                {{ isAvailable ? 'INICIAR' : isInProgress ? 'FINALIZAR Y COBRAR' : '—' }}
            </v-btn>
        </v-card-actions>


    </v-card>
</template>

<script>
import API from "@/tenant/services/index.js";

export default {
    name: 'PoolTableCard',
    props: {
        table: {type: Object, required: true},
        coverSrc: {type: String, default: '/img/8ball-cover.jpg'},
        imageField: {type: String, default: 'image'},
        maxSizeMB: {type: Number, default: 4},
        cacheBust: {type: Boolean, default: true},
    },

    data() {
        return {
            // RELOJ REACTIVO
            nowTs: Date.now(),
            tickerId: null,
            tickMs: 1000,

            current: null,
            finishForm: {consumption: 0, payment_method: 'cash', rate_per_hour: null, discount: 0, surcharge: 0},
            posItems: [],

            // Imagen
            isDragging: false,
            localCover: null,
            objectUrl: null,
            uploading: false,
            uploadError: '',
        }
    },
    computed: {
        isAvailable() {
            return this.table?.status?.name === 'available'
        },
        isInProgress() {
            return this.table?.status?.name === 'in_progress'
        },

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
        rate() {
            return Number(this.table?.rate_per_hour || 0)
        },
        startTs() {
            const iso = this.table?.start_time
            return iso ? new Date(iso).getTime() : null
        },
        elapsedMs() {
            if (!this.isInProgress || !this.startTs) return 0
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
            if (!this.isInProgress || !this.startTs) {
                return Number(this.table?.amount || 0)
            }
            const hours = this.elapsedMs / 3_600_000
            const amount = this.rate * hours
            return Math.round(amount * 100) / 100
        },
        coverStyle() {
            const src = this.table.cover_path;
            const bg = src ? `url('${src}')` : 'none';
            return {
                backgroundImage: `linear-gradient(rgba(0,0,0,.08), rgba(0,0,0,.08)), ${bg}`,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
                backgroundRepeat: 'no-repeat',
                backgroundColor: '#ECEFF1',
            };
        },

    },

    mounted() {
        this.tickerId = setInterval(() => {
            this.nowTs = Date.now()
        }, this.tickMs)
        console.log(this.table)
        document.addEventListener('visibilitychange', this.onVisibilityChange)
    },
    beforeUnmount() {
        clearInterval(this.tickerId)
        document.removeEventListener('visibilitychange', this.onVisibilityChange)
        this.revokeObjectUrl()
    },
    methods: {
        // ====== Imagen ======
        openFilePicker() {
            this.$refs.fileInput?.click()
        },
        onFileChange(e) {
            const file = e.target.files?.[0]
            if (!file) return
            this.handleIncomingFile(file)
            // limpiar input para permitir la misma imagen nuevamente
            e.target.value = ''
        },
        onDrop(e) {
            this.isDragging = false
            const file = e.dataTransfer?.files?.[0]
            if (!file) return
            this.handleIncomingFile(file)
        },
        handleIncomingFile(file) {
            this.uploadError = ''
            // Validaciones
            if (!file.type.startsWith('image/')) {
                this.uploadError = 'El archivo debe ser una imagen.'
                return
            }
            const max = this.maxSizeMB * 1024 * 1024
            if (file.size > max) {
                this.uploadError = `La imagen supera el máximo de ${this.maxSizeMB} MB.`
                return
            }
            // Vista previa inmediata
            this.revokeObjectUrl()
            this.objectUrl = URL.createObjectURL(file)
            this.localCover = this.objectUrl

            // Subida
            // if (this.uploadUrl) {
            this.uploadFile(file).catch(err => {
                this.uploadError = err?.message || 'No se pudo subir la imagen.'
                // si falla, mantenemos la vista previa local pero no actualizamos URL final
                this.$emit('upload-error', {tableId: this.table.id, message: this.uploadError})
            })

        },
        revokeObjectUrl() {
            if (this.objectUrl) {
                URL.revokeObjectURL(this.objectUrl)
                this.objectUrl = null
            }
        },
        async uploadFile(file) {
            this.uploading = true
            try {
                const fd = new FormData()
                fd.append(this.imageField, file)

                let json = await API.pool_tables.images(this.table.id, fd);
                console.log(json)
                this.localCover = json.table.cover_path;
                this.table.cover_path = json.table.cover_path;

            } catch (err) {
                // Mensaje legible
                const msg =
                    err?.response?.data?.message ||
                    (typeof err?.response?.data === 'string' ? err.response.data : '') ||
                    err?.message ||
                    'No se pudo subir la imagen.'
                this.uploadError = msg
                this.$emit('upload-error', {tableId: this.table.id, message: msg})
            } finally {
                this.uploading = false
            }
        },


        // ====== Reloj ======
        onVisibilityChange() {
            if (document.hidden) {
                clearInterval(this.tickerId)
                this.tickerId = null
            } else if (!this.tickerId) {
                this.nowTs = Date.now()
                this.tickerId = setInterval(() => {
                    this.nowTs = Date.now()
                }, this.tickMs)
            }
        },

        // ====== Util ======
        money(n) {
            const v = Number(n || 0)
            return new Intl.NumberFormat('es-PE', {
                style: 'currency', currency: 'PEN', minimumFractionDigits: 2
            }).format(v)
        },

        // ====== Acciones existentes ======
        onStart() {
            this.$emit('start', this.table)
        },
        onFinish() {
            this.$emit('finish', this.table)
        },
        onCancel() {
            this.$emit('cancel', this.table)
        },
        onEdit() {
            this.$emit('edit', this.table)
        },

    },
}
</script>

<style scoped>
.relative {
    position: relative;
}

.absolute {
    position: absolute;
}

.inset-0 {
    inset: 0;
}

.gap-2 {
    gap: 8px;
}

.d-none {
    display: none;
}

.cover-area {
    height: 13rem
}

/* igual que antes con <v-img height="180"> */
.relative {
    position: relative;
}

.absolute {
    position: absolute;
}

.inset-0 {
    inset: 0;
}

.gap-2 {
    gap: 8px;
}

.d-none {
    display: none;
}

.cover-bg {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
