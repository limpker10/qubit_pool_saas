<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Identificación (RUC / DNI)
            $table->uuid('uuid')->unique();
            $table->enum('document_type', ['DNI','RUC']);
            $table->string('document_number', 15);          // 8 (DNI) o 11 (RUC)
            $table->unique(['document_type','document_number']);

            // Datos generales
            $table->string('company');                       // Razón social o nombres/ apellidos

            // Plan (FK catálogos)
            $table->foreignId('plan_id')->nullable()
                ->constrained('plans')->nullOnDelete();

            // Estado / fechas
            $table->boolean('is_active')->default(true);
            $table->timestamp('onboarded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices útiles
            $table->index(['company']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('clients');
    }
};
