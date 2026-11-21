<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProxyDiscountPercentToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'proxy_discount_percent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('proxy_discount_percent')->default(0)->after('discount_percent')->comment('Phần trăm chiết khấu Proxy (VD: 10)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('proxy_discount_percent');
        });
    }
}
