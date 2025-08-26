<?php
// database/migrations/2025_08_10_000040_create_product_stocks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->decimal('avg_unit_cost', 12, 4)->default(0); // costo promedio en ese almacén
            $table->timestamps();
            $table->unique(['product_id','warehouse_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('product_stocks'); }
};
