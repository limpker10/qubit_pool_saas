<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->enum('billing_mode', ['time','unit'])->default('time'); // 'time' para alquiler
            $t->string('unit')->default('hour'); // h, unit, etc.
            $t->decimal('unit_price', 12, 2)->default(0); // tarifa por unidad
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
