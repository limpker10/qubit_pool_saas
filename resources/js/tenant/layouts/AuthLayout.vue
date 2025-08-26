<template>
    <v-app>
        <div
            class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">

            <div class="relative hidden h-full flex-col bg-muted p-10  dark:border-r lg:flex">

            </div>

            <!-- Columna derecha (formulario) -->
            <div class="min-h-screen flex items-center justify-center  px-4 lg:px-8">
                <div class="w-full max-w-md space-y-8">
                    <v-card variant="outlined" class="pa-5">
                        <!-- Encabezado -->
                        <div class="text-center mb-3">
                            <h1 v-if="title" class="text-2xl font-bold tracking-tight ">
                                {{ title }}
                            </h1>
                            <p v-if="description" class="mt-2 text-sm ">
                                {{ description }}
                            </p>
                        </div>
                        <router-view/>
                    </v-card>
                </div>
            </div>

        </div>
    </v-app>
</template>

<script setup>
const name = 'MiApp'

// Puedes convertir estos en props si lo deseas
const quote = {
    message: 'El software es una gran combinación entre arte e ingeniería.',
    author: 'Bill Gates'
}

const title = 'Bienvenido'
const description = 'Por favor inicia sesión para continuar.'
</script>
