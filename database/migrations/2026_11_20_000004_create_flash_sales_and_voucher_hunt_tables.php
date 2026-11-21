<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('flash_sales')) {
            Schema::create('flash_sales', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('badge_text', 60)->nullable();
                $table->string('service_type', 20)->default('vps'); // vps | proxy
                $table->string('plan_slug', 64)->index();
                $table->string('discount_type', 20)->default('percent'); // percent | fixed | fixed_price
                $table->unsignedInteger('discount_value')->default(20);
                $table->unsignedInteger('total_slots')->default(10);
                $table->unsignedInteger('sold_slots')->default(0);
                $table->unsignedSmallInteger('max_per_user')->default(1);
                $table->unsignedSmallInteger('max_months')->default(1);
                $table->boolean('protect_cost_floor')->default(true);
                $table->timestamp('starts_at')->nullable()->index();
                $table->timestamp('ends_at')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('flash_sale_claims')) {
            Schema::create('flash_sale_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('transaction_id')->nullable()->index();
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->unsignedInteger('discount_amount')->default(0);
                $table->timestamps();

                $table->index(['flash_sale_id', 'user_id']);
            });
        }

        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (!Schema::hasColumn('coupons', 'is_huntable')) {
                    $table->boolean('is_huntable')->default(false)->after('is_active')->index();
                }
                if (!Schema::hasColumn('coupons', 'claim_limit')) {
                    $table->unsignedInteger('claim_limit')->nullable()->after('is_huntable');
                }
                if (!Schema::hasColumn('coupons', 'claimed_count')) {
                    $table->unsignedInteger('claimed_count')->default(0)->after('claim_limit');
                }
            });
        }

        if (!Schema::hasTable('user_vouchers')) {
            Schema::create('user_vouchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->timestamp('claimed_at')->useCurrent();
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'coupon_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_vouchers');
        Schema::dropIfExists('flash_sale_claims');
        Schema::dropIfExists('flash_sales');

        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                $cols = array_filter(
                    ['is_huntable', 'claim_limit', 'claimed_count'],
                    fn ($col) => Schema::hasColumn('coupons', $col)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
