<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vps_instances') && !Schema::hasColumn('vps_instances', 'is_auto_renew')) {
            Schema::table('vps_instances', function (Blueprint $table) {
                $table->boolean('is_auto_renew')->default(false)->after('status')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vps_instances') && Schema::hasColumn('vps_instances', 'is_auto_renew')) {
            Schema::table('vps_instances', function (Blueprint $table) {
                $table->dropColumn('is_auto_renew');
            });
        }
    }
};
