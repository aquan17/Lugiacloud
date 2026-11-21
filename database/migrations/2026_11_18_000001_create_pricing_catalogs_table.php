<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pricing_catalogs')) {
            Schema::create('pricing_catalogs', function (Blueprint $table) {
                $table->id();
                $table->string('service_type', 24)->index(); // vps | proxy | addon
                $table->string('slug', 80)->unique();
                $table->string('provider_code', 80)->nullable()->index();
                $table->unsignedBigInteger('provider_product_id')->nullable()->index();
                $table->string('name', 140);
                $table->string('description', 255)->nullable();
                $table->string('badge', 60)->nullable();
                $table->json('specs')->nullable(); // cores, ram, disk, network_out_mbps, transfer_tb, proxy_category...

                // Giá gốc từ đối tác CloudSunny (cập nhật tự động qua đồng bộ API)
                $table->unsignedBigInteger('provider_cost_monthly')->default(0);
                $table->json('provider_cycle_costs')->nullable();

                // Quy tắc tăng giá Reseller (fixed_price: ấn định giá bán; percent_markup: tự nhân % theo giá gốc)
                $table->string('markup_mode', 24)->default('fixed_price');
                $table->decimal('markup_percent', 6, 2)->default(35.00);
                $table->unsignedBigInteger('retail_price_monthly')->default(0);
                $table->json('retail_cycle_prices')->nullable();

                // Chốt chặn bảo vệ biên lợi nhuận khi chạy Sự kiện / Coupon / Chiết khấu đại lý
                $table->boolean('protect_cost_floor')->default(true);
                $table->decimal('min_margin_percent', 5, 2)->default(5.00);

                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->index(['service_type', 'is_active', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_catalogs');
    }
};
