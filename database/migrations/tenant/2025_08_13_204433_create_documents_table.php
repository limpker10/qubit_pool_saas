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
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['sale_note','invoice','receipt'])->default('sale_note');
            $t->string('series')->nullable();   // ej. "NV01"
            $t->unsignedInteger('number')->nullable(); // correlativo
            $t->dateTime('issue_date')->useCurrent();
            $t->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete(); // opcional
            $t->enum('currency', ['PEN','USD'])->default('PEN');
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->enum('payment_method', ['cash','card','transfer','other'])->default('cash');
            $t->enum('status', ['issued','voided'])->default('issued');
            $t->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete(); // si es cash
            $t->json('meta')->nullable(); // info extra (ej. mesa #)
            $t->timestamps();
            $t->unique(['type','series','number']); // correlativo único por tipo/serie
            $t->index(['issue_date','payment_method','status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
