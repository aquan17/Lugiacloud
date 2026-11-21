<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsAutoRenewToProxyInstancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('proxy_instances') && !Schema::hasColumn('proxy_instances', 'is_auto_renew')) {
            Schema::table('proxy_instances', function (Blueprint $table) {
                $table->boolean('is_auto_renew')->default(false)->after('status');
            });
        }
    }

    public function down()
    {
        Schema::table('proxy_instances', function (Blueprint $table) {
            $table->dropColumn('is_auto_renew');
        });
    }
}
