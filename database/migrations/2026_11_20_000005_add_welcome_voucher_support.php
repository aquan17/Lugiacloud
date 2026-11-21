<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_vouchers') && !Schema::hasColumn('user_vouchers', 'expires_at')) {
            Schema::table('user_vouchers', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('claimed_at')->index();
            });
        }

        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (!Schema::hasColumn('coupons', 'first_order_only')) {
                    $table->boolean('first_order_only')->default(false)->after('apply_on_renew');
                }
                if (!Schema::hasColumn('coupons', 'max_months')) {
                    $table->unsignedTinyInteger('max_months')->nullable()->after('first_order_only');
                }
                if (!Schema::hasColumn('coupons', 'stack_with_campaign')) {
                    $table->boolean('stack_with_campaign')->default(true)->after('max_months');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_vouchers') && Schema::hasColumn('user_vouchers', 'expires_at')) {
            Schema::table('user_vouchers', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }

        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                $cols = array_filter(
                    ['first_order_only', 'max_months', 'stack_with_campaign'],
                    fn ($col) => Schema::hasColumn('coupons', $col)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
