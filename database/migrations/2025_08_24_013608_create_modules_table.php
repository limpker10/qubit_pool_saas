<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();      // ej: dashboard, ventas, compras, clientes, inventario
            $table->string('name');               // etiqueta visible
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('modules');
    }
};
