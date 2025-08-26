<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('client_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            // Puedes guardar todo como FQDN o separar subdominio y dominio
            $table->string('fqdn')->unique();                // ej: acme.innovaservicios.pe
            $table->boolean('is_primary')->default(true);

            $table->timestamps();

            $table->index(['client_id','is_primary']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('client_domains');
    }
};
