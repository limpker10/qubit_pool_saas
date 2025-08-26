<?php
// database/migrations/2025_08_10_000010_create_units_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('abbreviation', 10)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('units'); }
};
