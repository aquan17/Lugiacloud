<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'status')) {
                $table->string('status', 24)->default('completed')->after('type');
            }
            if (!Schema::hasColumn('transactions', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('provider_cost');
            }
            if (!Schema::hasColumn('transactions', 'balance_before')) {
                $table->decimal('balance_before', 15, 2)->nullable()->after('discount_amount');
            }
            if (!Schema::hasColumn('transactions', 'balance_after')) {
                $table->decimal('balance_after', 15, 2)->nullable()->after('balance_before');
            }
            if (!Schema::hasColumn('transactions', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('proxy_instance_id')->constrained('promotion_campaigns')->nullOnDelete();
            }
            if (!Schema::hasColumn('transactions', 'coupon_code')) {
                $table->string('coupon_code', 48)->nullable()->after('campaign_id');
            }
            if (!Schema::hasColumn('transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique()->after('code');
            }
        });

        Schema::table('topup_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('topup_requests', 'bonus_amount')) {
                $table->unsignedBigInteger('bonus_amount')->default(0)->after('approved_amount');
            }
            if (!Schema::hasColumn('topup_requests', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('bonus_amount')->constrained('promotion_campaigns')->nullOnDelete();
            }
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'expires_at'], 'idx_vps_user_status_exp');
        });

        Schema::table('proxy_instances', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'expires_at'], 'idx_proxy_user_status_exp');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'status', 'created_at'], 'idx_txn_user_type_status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_txn_user_type_status');
            $cols = array_filter(
                ['idempotency_key', 'coupon_code', 'status', 'discount_amount', 'balance_before', 'balance_after'],
                fn ($c) => Schema::hasColumn('transactions', $c)
            );
            if (Schema::hasColumn('transactions', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('topup_requests', function (Blueprint $table) {
            if (Schema::hasColumn('topup_requests', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
            if (Schema::hasColumn('topup_requests', 'bonus_amount')) {
                $table->dropColumn('bonus_amount');
            }
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->dropIndex('idx_vps_user_status_exp');
        });

        Schema::table('proxy_instances', function (Blueprint $table) {
            $table->dropIndex('idx_proxy_user_status_exp');
        });
    }
};
