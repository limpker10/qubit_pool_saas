<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Tipos de mesa (Pool, Billar, 3 Bandas, etc)
         */
        Schema::create('table_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();         // 'Pool', 'Billar', '3 Bandas'
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed básico de tipos
        DB::table('table_types')->insert([
            ['name' => 'Pool', 'description' => 'Mesa de pool clásico', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Billar', 'description' => 'Billar francés (carambolas)', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '3 Bandas', 'description' => 'Modalidad a tres bandas', 'created_at' => now(), 'updated_at' => now()],
        ]);

        /**
         * Estados
         */
        Schema::create('table_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // available, in_progress, paused, completed, cancelled
            $table->timestamps();
        });

        /**
         * Mesas
         * Nota: renombrado de 'table' -> 'tables' para evitar palabra reservada
         */
        Schema::create('tables', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('number')->unique();
            $table->string('name');

            // FK tipo de mesa
            $table->foreignId('type_id')
                ->constrained('table_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();

            // Importe por tiempo y consumo
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('consumption', 10, 2)->default(0);

            // Campos que usa tu modelo
            $table->decimal('rate_per_hour', 10, 2)->default(0); // tarifa/hora
            $table->unsignedInteger('final_seconds')->nullable(); // segundos facturables finales
            $table->decimal('final_amount', 10, 2)->nullable();   // importe final por tiempo al cerrar

            // Estado
            $table->foreignId('status_id')
                ->constrained('table_statuses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            // Índices útiles
            $table->index(['status_id', 'type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
        Schema::dropIfExists('table_statuses');
        Schema::dropIfExists('table_types');
    }
};
