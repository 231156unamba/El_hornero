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
        if (Schema::hasColumn('pedido', 'mesero_id') && ! Schema::hasColumn('pedido', 'usuario_id')) {
            Schema::table('pedido', function (Blueprint $table) {
                $table->renameColumn('mesero_id', 'usuario_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('pedido', 'usuario_id') && ! Schema::hasColumn('pedido', 'mesero_id')) {
            Schema::table('pedido', function (Blueprint $table) {
                $table->renameColumn('usuario_id', 'mesero_id');
            });
        }
    }
};
