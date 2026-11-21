<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddCodeToTransactionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'code')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('code', 32)->nullable()->unique()->after('id');
            });
        }

        // Chỉ backfill mã chuẩn LUG-... cho các giao dịch chưa có mã (không đè mã cũ)
        $txns = DB::table('transactions')->whereNull('code')->orderBy('id')->get();
        foreach ($txns as $t) {
            $prefix = 'TXN';
            if ($t->type === 'buy') {
                $prefix = !empty($t->proxy_instance_id) ? 'PRX' : 'VPS';
            } elseif ($t->type === 'renew') {
                $prefix = !empty($t->proxy_instance_id) ? 'PRX-RNW' : 'VPS-RNW';
            } elseif ($t->type === 'upgrade') {
                $prefix = 'UPG';
            } elseif ($t->type === 'refund') {
                $prefix = 'REF';
            }
            $uniqueCode = 'LUG-' . $prefix . '-' . str_pad($t->id, 6, '0', STR_PAD_LEFT);
            DB::table('transactions')->where('id', $t->id)->update(['code' => $uniqueCode]);
        }
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
}
