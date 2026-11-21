<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dedicated_servers')) {
            Schema::create('dedicated_servers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('cloudsunny_account_id')->nullable()->constrained('cloud_sunny_accounts')->nullOnDelete();
                $table->unsignedBigInteger('provider_server_id')->nullable()->index();
                $table->string('provider_order_id', 80)->nullable()->index();
                $table->unsignedInteger('provider_product_id')->index();
                $table->string('plan_slug', 64)->index();
                $table->string('plan_name', 160);
                $table->string('label', 160);
                $table->json('hardware_config')->nullable();
                $table->string('billing_cycle', 32)->default('monthly');
                $table->string('status', 48)->default('Chờ bàn giao')->index();
                $table->boolean('is_auto_renew')->default(false);

                // Thông tin bàn giao thủ công từ nhà cung cấp / Admin (mật khẩu được mã hóa Crypt trong Model)
                $table->string('public_ip', 64)->nullable()->index();
                $table->string('os_installed', 120)->nullable();
                $table->string('login_username', 80)->nullable();
                $table->text('root_password')->nullable();
                $table->string('ipmi_url', 255)->nullable();
                $table->string('ipmi_username', 80)->nullable();
                $table->text('ipmi_password')->nullable();
                $table->text('handover_note')->nullable();

                // Tài chính & đối soát
                $table->unsignedBigInteger('setup_fee')->default(0);
                $table->unsignedBigInteger('provider_setup_fee')->default(0);
                $table->unsignedBigInteger('paid_amount')->default(0);
                $table->unsignedBigInteger('provider_cost')->default(0);
                $table->json('provider_payload')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('handed_over_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'dedicated_server_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('dedicated_server_id')
                    ->nullable()
                    ->after('proxy_instance_id')
                    ->constrained('dedicated_servers')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'dedicated_server_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('dedicated_server_id');
            });
        }

        Schema::dropIfExists('dedicated_servers');
    }
};
