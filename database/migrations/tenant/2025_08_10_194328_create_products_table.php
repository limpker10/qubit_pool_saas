<?php
// database/migrations/2025_08_10_000030_create_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique(); // reemplaza internal_code
            $table->string('barcode')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('brand')->nullable();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('default_cost_price', 12, 4)->default(0); // precisión mayor
            $table->decimal('default_sale_price', 12, 4)->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['name']);
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
