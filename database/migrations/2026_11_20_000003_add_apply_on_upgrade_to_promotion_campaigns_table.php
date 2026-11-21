<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotion_campaigns') && !Schema::hasColumn('promotion_campaigns', 'apply_on_upgrade')) {
            Schema::table('promotion_campaigns', function (Blueprint $table) {
                $table->boolean('apply_on_upgrade')->default(false)->after('apply_on_renew');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('promotion_campaigns') && Schema::hasColumn('promotion_campaigns', 'apply_on_upgrade')) {
            Schema::table('promotion_campaigns', function (Blueprint $table) {
                $table->dropColumn('apply_on_upgrade');
            });
        }
    }
};
