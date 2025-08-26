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
        Schema::create('documents_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->string('item_type')->nullable();
            $t->unsignedBigInteger('item_id')->nullable();
            $t->decimal('quantity', 12, 3)->default(1);
            $t->string('unit')->nullable();
            $t->decimal('unit_price', 12, 2);
            $t->decimal('line_total', 12, 2);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents_details');
    }
};
