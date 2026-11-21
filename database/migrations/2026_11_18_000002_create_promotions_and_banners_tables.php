<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('promotion_campaigns')) {
            Schema::create('promotion_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('slug', 160)->unique();
                $table->string('event_tag', 40)->default('seasonal'); // tet, black_friday, cyber_monday, anniversary, flash_sale, seasonal
                $table->string('badge_text', 60)->nullable(); // VD: BLACK FRIDAY -25%
                $table->string('accent_color', 24)->default('#2563EB');
                $table->text('description')->nullable();

                $table->timestamp('starts_at')->nullable()->index();
                $table->timestamp('ends_at')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('priority')->default(10);

                // Giảm giá dịch vụ
                $table->string('discount_type', 16)->default('percent'); // percent | fixed | none
                $table->unsignedBigInteger('discount_value')->default(0);
                $table->unsignedBigInteger('max_discount_amount')->nullable();
                $table->string('target_scope', 24)->default('all'); // all | vps | proxy
                $table->json('target_plans')->nullable(); // null = tất cả gói trong scope
                $table->unsignedSmallInteger('min_months')->default(1);
                $table->boolean('apply_on_renew')->default(false);
                $table->boolean('stack_with_user_discount')->default(false);
                $table->boolean('protect_cost_floor')->default(true);

                // Khuyến mãi tặng thêm % nạp tiền trong dịp sự kiện
                $table->decimal('topup_bonus_percent', 5, 2)->default(0);

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('promotion_banners')) {
            Schema::create('promotion_banners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->nullable()->constrained('promotion_campaigns')->nullOnDelete();
                $table->string('placement', 32)->index(); // top_bar | hero_showcase | popup_modal | dashboard_banner
                $table->string('eyebrow', 80)->nullable();
                $table->string('title', 200);
                $table->string('subtitle', 350)->nullable();
                $table->string('cta_text', 80)->nullable();
                $table->string('cta_url', 255)->nullable();
                $table->string('coupon_code_display', 50)->nullable();
                $table->string('image_desktop', 255)->nullable();
                $table->string('image_mobile', 255)->nullable();
                $table->string('theme_preset', 32)->default('navy_ocean'); // navy_ocean | crimson_fest | obsidian_gold | emerald_tech
                $table->string('bg_from', 24)->default('#0F1A42');
                $table->string('bg_to', 24)->default('#1D4ED8');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->nullable()->constrained('promotion_campaigns')->nullOnDelete();
                $table->string('code', 48)->unique();
                $table->string('description', 200)->nullable();
                $table->string('discount_type', 16)->default('percent'); // percent | fixed
                $table->unsignedBigInteger('discount_value')->default(0);
                $table->unsignedBigInteger('max_discount_amount')->nullable();
                $table->unsignedBigInteger('min_order_amount')->default(0);
                $table->string('applies_to', 24)->default('all'); // all | vps | proxy
                $table->boolean('apply_on_renew')->default(false);
                $table->boolean('protect_cost_floor')->default(true);
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->unsignedInteger('max_uses_per_user')->default(1);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coupon_usages')) {
            Schema::create('coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->unsignedBigInteger('discount_amount')->default(0);
                $table->timestamps();

                $table->index(['coupon_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotion_banners');
        Schema::dropIfExists('promotion_campaigns');
    }
};
