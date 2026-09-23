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
        Schema::table('menu', function (Blueprint $table) {
            if (! Schema::hasColumn('menu', 'discount_percentage')) {
                $table->decimal('discount_percentage', 5, 2)->nullable()->default(null)->after('categoria');
            }

            if (! Schema::hasColumn('menu', 'discount_expires_at')) {
                $table->timestamp('discount_expires_at')->nullable()->default(null)->after('discount_percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['discount_percentage', 'discount_expires_at'],
                fn (string $column) => Schema::hasColumn('menu', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
