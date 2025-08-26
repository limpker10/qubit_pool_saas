<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Historial de alquileres por mesa (cada sesión de juego)
         */
        Schema::create('table_rentals', function (Blueprint $table) {
            $table->id();

            // Mesa alquilada
            $table->foreignId('table_id')
                ->constrained('tables')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Tiempos
            $table->timestamp('started_at');          // inicio del alquiler
            $table->timestamp('ended_at')->nullable(); // fin (null mientras esté abierta)

            // Cálculos
            $table->unsignedInteger('elapsed_seconds')->nullable(); // segundos totales jugados
            $table->decimal('rate_per_hour', 10, 2)->default(0);    // tarifa/hora usada en esta sesión
            $table->decimal('amount_time', 10, 2)->default(0);      // monto por tiempo
            $table->decimal('consumption', 10, 2)->default(0);      // consumo (bebidas, etc.)
            $table->decimal('discount', 10, 2)->default(0);         // descuentos aplicados
            $table->decimal('surcharge', 10, 2)->default(0);        // recargos
            $table->decimal('total', 10, 2)->default(0);            // total final (amount_time + consumption - discount + surcharge)

            // Estado de la sesión
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');

            // Quién abrió/cerró (opcional)
            $table->foreignId('opened_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();

            // Datos extra (observaciones, motivo cancelación, etc.)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['table_id', 'status']);
            $table->index(['started_at', 'ended_at']);
        });

        /**
         * (Opcional pero útil) Índice compuesto para que consultes rápido
         * los alquileres abiertos por mesa.
         */
        Schema::table('table_rentals', function (Blueprint $table) {
            $table->index(['table_id', 'status'], 'table_rentals_table_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_rentals');
    }
};
