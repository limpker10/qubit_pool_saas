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
        Schema::create('cash_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['open','sale','income','expense','withdrawal','refund','adjust']);
            $t->decimal('amount', 12, 2); // + entra, - sale
            $t->string('reference_type')->nullable(); // 'Document'
            $t->unsignedBigInteger('reference_id')->nullable(); // documents.id
            $t->string('description')->nullable();
            $t->timestamps();
            $t->index(['cash_session_id','type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
