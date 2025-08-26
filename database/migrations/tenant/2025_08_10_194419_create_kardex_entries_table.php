<?php
// database/migrations/2025_08_10_000050_create_kardex_entries_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kardex_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->enum('movement', ['entrada','salida','ajuste','transfer_in','transfer_out']);
            $table->integer('quantity_in')->default(0);
            $table->integer('quantity_out')->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0); // costo del movimiento
            $table->decimal('total_cost', 14, 4)->default(0); // qty_in * unit_cost (o equivalente en salida si requieres)
            // Saldos después del movimiento (para auditoría rápida)
            $table->integer('balance_qty')->default(0);
            $table->decimal('balance_avg_unit_cost', 12, 4)->default(0);
            $table->decimal('balance_total_cost', 14, 4)->default(0);

            // Referencia polimórfica a documento origen (opcional)
            $table->string('document_type')->nullable(); // p.ej. 'App\Models\Sale'
            $table->unsignedBigInteger('document_id')->nullable();

            $table->timestamp('movement_date')->useCurrent();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['product_id','warehouse_id','movement_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('kardex_entries'); }
};
