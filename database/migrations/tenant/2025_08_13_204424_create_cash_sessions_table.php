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
        Schema::create('cash_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnUpdate();
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
            $t->decimal('opening_cash', 12, 2);
            $t->decimal('expected_cash', 12, 2)->default(0);
            $t->decimal('counted_cash', 12, 2)->nullable();
            $t->decimal('difference', 12, 2)->nullable();
            $t->text('notes')->nullable();
            $t->enum('status', ['open','closed'])->default('open');
            $t->timestamps();
            $t->index(['user_id','status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
