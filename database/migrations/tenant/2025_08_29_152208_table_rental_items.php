<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('table_rental_items', function (Blueprint $table) {
            $table->id();

            // FK al alquiler
            $table->foreignId('table_rental_id')
                ->constrained('table_rentals')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Producto (puede quedar null si luego borran el producto)
            $table->foreignId('product_id')->nullable()
                ->constrained('products')->nullOnDelete();

            // Snapshots para reportes históricos
            $table->string('product_name');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_name')->nullable();

            // Cantidad y precio del momento
            $table->decimal('qty', 12, 4);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Estado del ítem
            $table->enum('status', ['ok', 'voided'])->default('ok');

            // Observación opcional
            $table->text('observation')->nullable();

            // Idempotencia (opcional): identifica la operación del POS para evitar duplicados
            $table->string('client_op_id')->nullable();

            // Auditoría
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Índices útiles
            $table->index(['table_rental_id', 'status'], 'tri_rental_status_idx');
            $table->index(['product_id']);
            // Si decides usar idempotencia desde el POS, activa el unique:
            // $table->unique(['table_rental_id','client_op_id'], 'tri_rental_opid_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_rental_items');
    }
};
