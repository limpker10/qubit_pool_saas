<?php
// database/migrations/2025_08_29_120000_add_warehouse_id_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Relación con warehouses. Null-on-delete para no borrar usuarios si llegaran a eliminar el almacén.
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('password')
                ->constrained('warehouses')
                ->nullOnDelete();
        });


    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
