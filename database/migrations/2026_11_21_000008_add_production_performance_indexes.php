<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('vps_instances', ['provider_order_id'], 'idx_vps_provider_order_id');
        $this->addIndexIfMissing('vps_instances', ['public_ip'], 'idx_vps_public_ip');

        $this->addIndexIfMissing('proxy_instances', ['is_auto_renew', 'expires_at'], 'idx_proxy_autorenew_exp');
        $this->addIndexIfMissing('proxy_instances', ['ip'], 'idx_proxy_ip');

        $this->addIndexIfMissing('transactions', ['type', 'status', 'created_at'], 'idx_txn_type_status_created');
        $this->addIndexIfMissing('transactions', ['coupon_code'], 'idx_txn_coupon_code');

        $this->addIndexIfMissing('topup_requests', ['provider_order_code'], 'idx_topup_provider_order_code');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('vps_instances', 'idx_vps_provider_order_id');
        $this->dropIndexIfExists('vps_instances', 'idx_vps_public_ip');
        $this->dropIndexIfExists('proxy_instances', 'idx_proxy_autorenew_exp');
        $this->dropIndexIfExists('proxy_instances', 'idx_proxy_ip');
        $this->dropIndexIfExists('transactions', 'idx_txn_type_status_created');
        $this->dropIndexIfExists('transactions', 'idx_txn_coupon_code');
        $this->dropIndexIfExists('topup_requests', 'idx_topup_provider_order_code');
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        foreach ($columns as $col) {
            if (!Schema::hasColumn($table, $col)) {
                return;
            }
        }

        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $rows = DB::select(
                'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
                [$table, $indexName]
            );
            return !empty($rows);
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? '') === $indexName) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }
};
