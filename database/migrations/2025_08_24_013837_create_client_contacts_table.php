<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(true);

            $table->timestamps();

            $table->unique(['client_id','email']);          // un email por cliente
            $table->index(['client_id','is_primary']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('client_contacts');
    }
};
