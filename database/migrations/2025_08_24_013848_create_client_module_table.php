<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('client_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();

            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();           // si algún módulo necesita config específica

            $table->timestamps();

            $table->unique(['client_id','module_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('client_module');
    }
};
