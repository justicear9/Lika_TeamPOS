<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_lines', 'freight_allocation')) {
            Schema::table('purchase_lines', function (Blueprint $table) {
                $table->decimal('freight_allocation', 22, 4)->default(0)->after('purchase_price_inc_tax');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_lines', 'freight_allocation')) {
            Schema::table('purchase_lines', function (Blueprint $table) {
                $table->dropColumn('freight_allocation');
            });
        }
    }
};
