<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotion_campaigns')) {
            Schema::table('promotion_campaigns', function (Blueprint $table) {
                if (!Schema::hasColumn('promotion_campaigns', 'send_launch_email')) {
                    $table->boolean('send_launch_email')->default(true)->after('is_active');
                }
                if (!Schema::hasColumn('promotion_campaigns', 'launch_email_sent_at')) {
                    $table->timestamp('launch_email_sent_at')->nullable()->after('send_launch_email');
                }
                if (!Schema::hasColumn('promotion_campaigns', 'launch_email_count')) {
                    $table->unsignedInteger('launch_email_count')->default(0)->after('launch_email_sent_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('promotion_campaigns')) {
            Schema::table('promotion_campaigns', function (Blueprint $table) {
                $cols = array_filter(
                    ['send_launch_email', 'launch_email_sent_at', 'launch_email_count'],
                    fn ($col) => Schema::hasColumn('promotion_campaigns', $col)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
