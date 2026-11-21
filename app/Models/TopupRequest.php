<?php

namespace App\Models;

use App\Mail\TopupSucceeded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TopupRequest extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'amount',
        'note',
        'status',
        'provider',
        'provider_order_code',
        'transaction_ref',
        'raw_payload',
        'paid_at',
        'approved_amount',
        'bonus_amount',
        'campaign_id',
        'admin_note',
        'approved_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'approved_amount' => 'integer',
        'bonus_amount' => 'integer',
        'processed_at' => 'datetime',
        'raw_payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function campaign()
    {
        return $this->belongsTo(PromotionCampaign::class, 'campaign_id');
    }

    public function getVietQrUrlAttribute(): string
    {
        $bankId = (string) (data_get($this->raw_payload, 'data.bin') ?: config('deposit.bank_id'));
        $accountNo = (string) (data_get($this->raw_payload, 'data.accountNumber') ?: config('deposit.account_no'));
        $accountName = (string) (data_get($this->raw_payload, 'data.accountName') ?: config('deposit.account_name'));
        $addInfo = (string) (data_get($this->raw_payload, 'data.description') ?: $this->code);

        return 'https://img.vietqr.io/image/' . rawurlencode($bankId) . '-' . rawurlencode($accountNo) . '-compact2.png?' . http_build_query([
            'amount' => $this->amount,
            'addInfo' => $addInfo,
            'accountName' => $accountName,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved' || $this->status === 'paid';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function statusLabel(): string
    {
        if ($this->status === 'paid') {
            return 'Đã thanh toán';
        }

        return [
            'pending' => 'Chờ thanh toán',
            'approved' => 'Đã duyệt',
            'rejected' => 'Đã hủy',
        ][$this->status] ?? $this->status;
    }

    protected static function booted(): void
    {
        static::updated(function (TopupRequest $topup) {
            if ($topup->wasChanged('status') && in_array($topup->status, ['paid', 'approved'], true)) {
                DB::afterCommit(function () use ($topup) {
                    $cacheKey = "topup_receipt_mail_sent_{$topup->id}";
                    if (!Cache::add($cacheKey, true, 86400 * 30)) {
                        return;
                    }

                    try {
                        $freshTopup = $topup->fresh(['user']);
                        if ($freshTopup && $freshTopup->user && !empty($freshTopup->user->email)) {
                            Mail::to($freshTopup->user->email)->queue(
                                new TopupSucceeded($freshTopup, $freshTopup->user)
                            );
                        }
                    } catch (\Throwable $e) {
                        Log::error('Failed to queue topup receipt email', [
                            'topup_id' => $topup->id,
                            'msg' => $e->getMessage(),
                        ]);
                    }
                });
            }
        });
    }
}
