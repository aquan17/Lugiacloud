<?php

namespace App\Console\Commands;

use App\Models\VpsInstance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteExpiredVps extends Command
{
    protected $signature = 'vps:delete-expired';
    protected $description = 'Danh dau VPS và Proxy da qua han tren he thong.';

    public function handle(): int
    {
        // Đồng bộ trạng thái VPS quá hạn (> 5 ngày -> Đã xóa, <= 5 ngày -> Hết hạn)
        VpsInstance::syncOverdueStatus();
        $this->info("Đã đồng bộ trạng thái VPS quá hạn (> 5 ngày: Đã xóa, <= 5 ngày: Hết hạn).");

        // Proxy hết hạn -> thu hồi IP ngay, chuyển thẳng sang Đã xóa và tắt tự động gia hạn
        \App\Models\ProxyInstance::whereNotIn('status', ['Đã xóa', 'Đã xoá'])
            ->where(function ($q) {
                $q->whereIn('status', ['Hết hạn', 'Đã tắt'])
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('expires_at')->where('expires_at', '<', Carbon::now());
                    });
            })
            ->update([
                'status' => 'Đã xóa',
                'is_auto_renew' => false,
            ]);
        $this->info('Đã đồng bộ Proxy hết hạn sang Đã xóa.');

        return 0;
    }
}
