<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\PromotionBanner;
use App\Models\PromotionCampaign;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserVoucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PromotionEngine
{
    public const CACHE_KEY = 'lugiacloud.promotions.active_payload';

    private ?array $activeStateMemo = null;

    public function flushCache(): void
    {
        $this->activeStateMemo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Lấy danh sách chiến dịch & banner đang chạy (memoize trong RAM + cache 60s, 0 truy vấn lặp).
     */
    public function getActiveState(): array
    {
        if ($this->activeStateMemo !== null) {
            return $this->activeStateMemo;
        }

        return $this->activeStateMemo = Cache::remember(self::CACHE_KEY, 60, function () {
            if (!Schema::hasTable('promotion_campaigns') || !Schema::hasTable('promotion_banners')) {
                return [
                    'campaigns' => [],
                    'banners' => [
                        'top_bar' => null,
                        'hero_showcase' => [],
                        'popup_modal' => null,
                        'dashboard_banner' => null,
                    ],
                ];
            }

            $campaigns = PromotionCampaign::activeNow()->get();
            $banners = PromotionBanner::activeNow()->with('campaign')->get();

            $formatCampaign = fn (PromotionCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'event_tag' => $c->event_tag,
                'badge_text' => $c->badge_text ?: ($c->discount_value > 0 ? "-{$c->discount_value}%" : $c->name),
                'accent_color' => $c->accent_color,
                'description' => $c->description,
                'starts_at' => $c->starts_at?->toIso8601String(),
                'ends_at' => $c->ends_at?->toIso8601String(),
                'discount_type' => $c->discount_type,
                'discount_value' => (int) $c->discount_value,
                'max_discount_amount' => $c->max_discount_amount ? (int) $c->max_discount_amount : null,
                'target_scope' => $c->target_scope,
                'target_plans' => $c->target_plans,
                'min_months' => (int) $c->min_months,
                'apply_on_renew' => (bool) $c->apply_on_renew,
                'apply_on_upgrade' => (bool) ($c->apply_on_upgrade ?? false),
                'stack_with_user_discount' => (bool) $c->stack_with_user_discount,
                'protect_cost_floor' => (bool) $c->protect_cost_floor,
                'topup_bonus_percent' => (float) $c->topup_bonus_percent,
            ];

            $formatBanner = fn (PromotionBanner $b) => [
                'id' => $b->id,
                'campaign_id' => $b->campaign_id,
                'placement' => $b->placement,
                'eyebrow' => $b->eyebrow,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'cta_text' => $b->cta_text ?: 'Xem ưu đãi',
                'cta_url' => $b->cta_url ?: '#pricing',
                'coupon_code_display' => $b->coupon_code_display,
                'image_desktop' => $b->image_desktop,
                'image_mobile' => $b->image_mobile,
                'theme_preset' => $b->theme_preset,
                'bg_from' => $b->bg_from,
                'bg_to' => $b->bg_to,
                'ends_at' => ($b->ends_at ?? $b->campaign?->ends_at)?->toIso8601String(),
                'badge_text' => $b->campaign?->badge_text,
            ];

            $topBar = $banners->firstWhere('placement', 'top_bar');
            $popup = $banners->firstWhere('placement', 'popup_modal');
            $dash = $banners->firstWhere('placement', 'dashboard_banner');
            $heroes = $banners->where('placement', 'hero_showcase')->values()->map($formatBanner)->all();

            return [
                'campaigns' => $campaigns->map($formatCampaign)->values()->all(),
                'banners' => [
                    'top_bar' => $topBar ? $formatBanner($topBar) : null,
                    'hero_showcase' => $heroes,
                    'popup_modal' => $popup ? $formatBanner($popup) : null,
                    'dashboard_banner' => $dash ? $formatBanner($dash) : null,
                ],
            ];
        });
    }

    /**
     * Tìm chiến dịch giảm giá tốt nhất khớp với gói dịch vụ.
     */
    public function bestCampaignForItem(
        string $serviceType,
        string $planSlug,
        int $baseRetailPrice,
        int $months = 1,
        bool $isRenew = false,
        bool $isUpgrade = false
    ): ?array {
        $state = $this->getActiveState();
        $best = null;
        $bestDiscount = 0;

        foreach ($state['campaigns'] as $campaign) {
            if ($campaign['discount_type'] === 'none' || $campaign['discount_value'] <= 0) {
                continue;
            }
            if ($isUpgrade) {
                // Nâng cấp phần cứng giữa kỳ: chỉ áp dụng cho sự kiện scope 'upgrade' hoặc có bật apply_on_upgrade
                $allowedForUpgrade = ($campaign['target_scope'] === 'upgrade') || !empty($campaign['apply_on_upgrade']);
                if (!$allowedForUpgrade) {
                    continue;
                }
                if (!in_array($campaign['target_scope'], ['upgrade', 'all', 'vps'], true)) {
                    continue;
                }
            } else {
                // Mua mới hoặc Gia hạn: bỏ qua sự kiện chỉ dành riêng cho nâng cấp phần cứng
                if ($campaign['target_scope'] === 'upgrade') {
                    continue;
                }
                if ($isRenew && empty($campaign['apply_on_renew'])) {
                    continue;
                }
                if ($campaign['target_scope'] !== 'all' && $campaign['target_scope'] !== $serviceType) {
                    continue;
                }
            }
            if ($months < (int) ($campaign['min_months'] ?? 1)) {
                continue;
            }
            if (!empty($campaign['target_plans']) && is_array($campaign['target_plans'])) {
                if (!in_array($planSlug, $campaign['target_plans'], true)) {
                    continue;
                }
            }

            $discount = $this->computeRawDiscount(
                $baseRetailPrice,
                $campaign['discount_type'],
                (int) $campaign['discount_value'],
                $campaign['max_discount_amount']
            );

            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $best = array_merge($campaign, ['computed_discount' => $discount]);
            }
        }

        return $best;
    }

    /**
     * Tính toán báo giá cuối cùng có tích hợp:
     * 1. Chiết khấu cá nhân (User VIP/Reseller discount)
     * 2. Sự kiện theo năm (PromotionCampaign)
     * 3. Mã giảm giá (Coupon)
     * 4. Chốt chặn Bảo vệ Giá Vốn Gốc CloudSunny + Hoa hồng Affiliate 5% (Cost-Floor Guard)
     */
    public function quoteOrder(array $params): array
    {
        $serviceType = (string) ($params['service_type'] ?? 'vps'); // vps | proxy
        $planSlug = (string) ($params['plan_slug'] ?? '');
        $listPrice = max(0, (int) ($params['list_price'] ?? 0));
        $basePlanListPrice = max(0, (int) ($params['base_plan_list_price'] ?? $listPrice));
        $providerCost = max(0, (int) ($params['provider_cost'] ?? 0));
        $months = max(1, (int) ($params['months'] ?? 1));
        $quantity = max(1, (int) ($params['quantity'] ?? 1));
        $isRenew = (bool) ($params['is_renew'] ?? false);
        $isUpgrade = (bool) ($params['is_upgrade'] ?? false);
        /** @var ?User $user */
        $user = $params['user'] ?? null;
        $couponCode = isset($params['coupon_code']) ? strtoupper(trim((string) $params['coupon_code'])) : '';
        $protectFloor = (bool) ($params['protect_cost_floor'] ?? true);
        $minMarginPercent = (float) ($params['min_margin_percent'] ?? 5.0);

        // 0. Ưu tiên cao nhất: Flash Sale theo suất (ĐỘC LẬP — không cộng dồn Sự kiện / VIP / Coupon)
        $flashService = app(FlashSaleService::class);
        $flashDeal = $flashService->findActiveDealForItem(
            $serviceType,
            $planSlug,
            $months,
            $user,
            $quantity,
            $isRenew,
            $isUpgrade
        );

        if ($flashDeal) {
            if ($flashDeal->discount_type === 'fixed_price') {
                $targetBase = max(0, (int) $flashDeal->discount_value) * $months * $quantity;
                $rawFlashDiscount = max(0, $basePlanListPrice - $targetBase);
            } elseif ($flashDeal->discount_type === 'fixed') {
                $rawFlashDiscount = min($basePlanListPrice, max(0, (int) $flashDeal->discount_value) * $months * $quantity);
            } else {
                $pct = min(90, max(0, (int) $flashDeal->discount_value));
                $rawFlashDiscount = (int) round($basePlanListPrice * ($pct / 100));
            }

            $rawFinalPrice = max(0, $listPrice - $rawFlashDiscount);
            $floorApplied = false;
            $flashProtectFloor = (bool) $flashDeal->protect_cost_floor;

            if ($flashProtectFloor && $providerCost > 0) {
                $targetNetRevenue = (int) ceil($providerCost * (1 + max(0, $minMarginPercent) / 100));
                $hasReferrer = $user && !empty($user->referred_by);
                $floorPrice = $hasReferrer
                    ? (int) ceil($targetNetRevenue / max(0.5, 1 - AffiliateService::COMMISSION_RATE))
                    : $targetNetRevenue;

                if ($listPrice > 0 && $floorPrice > $listPrice) {
                    $floorPrice = max($providerCost, $listPrice);
                }

                if ($rawFinalPrice < $floorPrice) {
                    $rawFinalPrice = $floorPrice;
                    $floorApplied = true;
                }
            }

            $finalPrice = (int) $rawFinalPrice;
            $totalDiscount = max(0, $listPrice - $finalPrice);

            $couponNotice = $couponCode !== '' ? [
                'code' => $couponCode,
                'valid' => false,
                'message' => 'Giá Flash Sale không áp dụng kèm mã giảm giá.',
                'discount_amount' => 0,
                'coupon_id' => null,
            ] : null;

            return [
                'list_price' => $listPrice,
                'final_price' => $finalPrice,
                'provider_cost' => $providerCost,
                'estimated_profit' => $finalPrice - $providerCost,
                'discount_amount' => $totalDiscount,
                'user_discount_percent' => 0,
                'user_discount_amount' => 0,
                'campaign' => null,
                'flash_sale' => [
                    'id' => $flashDeal->id,
                    'name' => $flashDeal->name,
                    'badge_text' => $flashDeal->badge_text ?: 'FLASH SALE',
                    'remaining_slots' => $flashDeal->remainingSlots(),
                    'total_slots' => (int) $flashDeal->total_slots,
                    'sold_slots' => (int) $flashDeal->sold_slots,
                    'max_months' => (int) $flashDeal->max_months,
                    'max_per_user' => (int) $flashDeal->max_per_user,
                    'ends_at' => $flashDeal->ends_at?->toIso8601String(),
                    'discount_amount' => $totalDiscount,
                ],
                'coupon' => $couponNotice,
                'floor_applied' => $floorApplied,
            ];
        }

        // 1. User personal discount
        $userDiscountPercent = 0;
        if ($user) {
            $userDiscountPercent = $serviceType === 'proxy'
                ? min(100, max(0, (int) $user->proxy_discount_percent))
                : min(100, max(0, (int) $user->discount_percent));
        }
        $userDiscountAmount = $userDiscountPercent > 0
            ? (int) round($listPrice * ($userDiscountPercent / 100))
            : 0;

        // 2. Active seasonal campaign discount
        $campaign = $this->bestCampaignForItem($serviceType, $planSlug, $listPrice, $months, $isRenew, $isUpgrade);
        $campaignDiscountAmount = $campaign ? (int) $campaign['computed_discount'] : 0;

        if ($campaign && !empty($campaign['stack_with_user_discount'])) {
            $afterAutoDiscounts = max(0, $listPrice - $userDiscountAmount);
            $campaignDiscountAmount = $this->computeRawDiscount(
                $afterAutoDiscounts,
                $campaign['discount_type'],
                (int) $campaign['discount_value'],
                $campaign['max_discount_amount']
            );
            $autoDiscountTotal = $userDiscountAmount + $campaignDiscountAmount;
        } else {
            // Mặc định chọn mức giảm có lợi hơn cho khách (giữa VIP cá nhân và Sự kiện)
            if ($userDiscountAmount >= $campaignDiscountAmount) {
                $autoDiscountTotal = $userDiscountAmount;
                if ($userDiscountAmount > 0) {
                    $campaign = null;
                    $campaignDiscountAmount = 0;
                }
            } else {
                $autoDiscountTotal = $campaignDiscountAmount;
                $userDiscountAmount = 0;
            }
        }

        $priceAfterAuto = max(0, $listPrice - $autoDiscountTotal);

        // 3. Optional Coupon code
        $couponResult = null;
        $couponDiscountAmount = 0;
        $couponModel = null;

        if ($couponCode !== '') {
            $validation = $this->validateCoupon($couponCode, $user, $priceAfterAuto, $serviceType, $isRenew, $months);
            $couponResult = $validation;
            if ($validation['valid']) {
                $couponModel = $validation['model'];
                // Nếu mã không cho cộng dồn sự kiện (VD: mã thành viên mới WELCOME10) mà đang có sự kiện: chọn mức lợi hơn cho khách
                if ($campaign && $couponModel && $couponModel->stack_with_campaign === false) {
                    $standaloneBase = max(0, $listPrice - $userDiscountAmount);
                    $standaloneDisc = $this->computeRawDiscount(
                        $standaloneBase,
                        $couponModel->discount_type,
                        (int) $couponModel->discount_value,
                        $couponModel->max_discount_amount
                    );

                    if ($standaloneDisc > $campaignDiscountAmount) {
                        $campaign = null;
                        $campaignDiscountAmount = 0;
                        $autoDiscountTotal = $userDiscountAmount;
                        $priceAfterAuto = $standaloneBase;
                        $couponDiscountAmount = $standaloneDisc;
                        $couponResult['discount_amount'] = $standaloneDisc;
                    } else {
                        $couponDiscountAmount = 0;
                        $couponModel = null;
                        $couponResult = [
                            'valid' => false,
                            'message' => 'Đơn hàng đã hưởng ưu đãi sự kiện tốt hơn (không dùng kèm mã thành viên mới).',
                            'discount_amount' => 0,
                            'model' => null,
                        ];
                    }
                } else {
                    $couponDiscountAmount = (int) $validation['discount_amount'];
                }

                if ($couponModel && !$couponModel->protect_cost_floor) {
                    $protectFloor = false;
                }
            }
        }

        if ($campaign && empty($campaign['protect_cost_floor'])) {
            // Only relax floor if catalog itself doesn't enforce it
            $protectFloor = $protectFloor && (bool) ($params['protect_cost_floor'] ?? true);
        }

        $rawFinalPrice = max(0, $priceAfterAuto - $couponDiscountAmount);

        // 4. Cost-Floor Protection (Giá gốc CloudSunny + Biên an toàn + Dự phòng 5% Affiliate nếu khách có người giới thiệu)
        $floorPrice = 0;
        $floorApplied = false;
        if ($protectFloor && $providerCost > 0) {
            $targetNetRevenue = (int) ceil($providerCost * (1 + max(0, $minMarginPercent) / 100));
            $hasReferrer = $user && !empty($user->referred_by);
            if ($hasReferrer) {
                $affiliateRate = AffiliateService::COMMISSION_RATE;
                $floorPrice = (int) ceil($targetNetRevenue / max(0.5, 1 - $affiliateRate));
            } else {
                $floorPrice = $targetNetRevenue;
            }

            // Đảm bảo không ép giá sàn vượt quá giá niêm yết gốc nếu Admin cố tình đặt giá niêm yết sát gốc
            if ($listPrice > 0 && $floorPrice > $listPrice) {
                $floorPrice = max($providerCost, $listPrice);
            }

            if ($rawFinalPrice < $floorPrice) {
                $rawFinalPrice = $floorPrice;
                $floorApplied = true;
            }
        }

        $finalPrice = (int) $rawFinalPrice;
        $totalDiscount = max(0, $listPrice - $finalPrice);

        return [
            'list_price' => $listPrice,
            'final_price' => $finalPrice,
            'provider_cost' => $providerCost,
            'estimated_profit' => $finalPrice - $providerCost,
            'discount_amount' => $totalDiscount,
            'user_discount_percent' => $userDiscountPercent,
            'user_discount_amount' => $userDiscountAmount,
            'campaign' => $campaign ? [
                'id' => $campaign['id'],
                'name' => $campaign['name'],
                'badge_text' => $campaign['badge_text'],
                'accent_color' => $campaign['accent_color'],
                'ends_at' => $campaign['ends_at'],
                'discount_amount' => $campaignDiscountAmount,
            ] : null,
            'flash_sale' => null,
            'coupon' => $couponResult ? [
                'code' => $couponCode,
                'valid' => $couponResult['valid'],
                'message' => $couponResult['message'],
                'discount_amount' => $couponDiscountAmount,
                'coupon_id' => $couponModel?->id,
            ] : null,
            'floor_applied' => $floorApplied,
        ];
    }

    public function validateCoupon(
        string $code,
        ?User $user,
        int $orderAmount,
        string $serviceType = 'vps',
        bool $isRenew = false,
        int $months = 1
    ): array {
        if (!Schema::hasTable('coupons')) {
            return ['valid' => false, 'message' => 'Mã giảm giá không tồn tại.', 'discount_amount' => 0, 'model' => null];
        }

        $normalized = strtoupper(trim($code));
        $coupon = Coupon::where('code', $normalized)->first();

        if (!$coupon || !$coupon->is_active) {
            return ['valid' => false, 'message' => 'Mã giảm giá không tồn tại hoặc đã ngừng hoạt động.', 'discount_amount' => 0, 'model' => null];
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return ['valid' => false, 'message' => 'Mã giảm giá chưa tới thời gian mở.', 'discount_amount' => 0, 'model' => null];
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return ['valid' => false, 'message' => 'Mã giảm giá đã hết hạn.', 'discount_amount' => 0, 'model' => null];
        }

        if ($coupon->campaign_id && $coupon->campaign && !$coupon->campaign->isRunningNow()) {
            return ['valid' => false, 'message' => 'Chương trình sự kiện của mã này đã kết thúc.', 'discount_amount' => 0, 'model' => null];
        }

        if ($isRenew && !$coupon->apply_on_renew) {
            return ['valid' => false, 'message' => 'Mã giảm giá này chỉ áp dụng cho đơn mua mới.', 'discount_amount' => 0, 'model' => null];
        }

        if (!empty($coupon->max_months) && $months > (int) $coupon->max_months) {
            return ['valid' => false, 'message' => "Mã ưu đãi này chỉ áp dụng cho chu kỳ tối đa {$coupon->max_months} tháng.", 'discount_amount' => 0, 'model' => null];
        }

        if (!empty($coupon->first_order_only)) {
            if (!$user) {
                return ['valid' => false, 'message' => 'Vui lòng đăng nhập tài khoản mới để sử dụng ưu đãi này.', 'discount_amount' => 0, 'model' => null];
            }
            if (app(WelcomeOfferService::class)->hasCompletedServiceOrder($user)) {
                return ['valid' => false, 'message' => 'Mã ưu đãi này chỉ áp dụng cho đơn hàng đầu tiên của thành viên mới.', 'discount_amount' => 0, 'model' => null];
            }
            // Kiểm tra hạn dùng riêng từ lúc đăng ký (7 ngày) nếu có trong ví hoặc fallback theo created_at
            $uv = Schema::hasTable('user_vouchers')
                ? UserVoucher::where('user_id', $user->id)->where('coupon_id', $coupon->id)->first()
                : null;
            if ($uv && $uv->expires_at && $uv->expires_at->isPast()) {
                return ['valid' => false, 'message' => 'Ưu đãi thành viên mới của bạn đã hết hạn sử dụng.', 'discount_amount' => 0, 'model' => null];
            }
            if (!$uv) {
                $validDays = app(WelcomeOfferService::class)->getConfig()['valid_days'] ?? 7;
                if ($user->created_at && $user->created_at->copy()->addDays($validDays)->isPast()) {
                    return ['valid' => false, 'message' => "Ưu đãi thành viên mới chỉ có hiệu lực trong {$validDays} ngày từ lúc đăng ký.", 'discount_amount' => 0, 'model' => null];
                }
            }
        } elseif ($user && Schema::hasTable('user_vouchers')) {
            $uv = UserVoucher::where('user_id', $user->id)->where('coupon_id', $coupon->id)->first();
            if ($uv && $uv->expires_at && $uv->expires_at->isPast()) {
                return ['valid' => false, 'message' => 'Mã ưu đãi trong ví của bạn đã hết hạn sử dụng.', 'discount_amount' => 0, 'model' => null];
            }
        }

        if ($coupon->applies_to !== 'all' && $coupon->applies_to !== $serviceType) {
            return ['valid' => false, 'message' => 'Mã giảm giá không áp dụng cho loại dịch vụ này.', 'discount_amount' => 0, 'model' => null];
        }

        if ($coupon->min_order_amount > 0 && $orderAmount < $coupon->min_order_amount) {
            return [
                'valid' => false,
                'message' => 'Đơn tối thiểu ' . number_format($coupon->min_order_amount, 0, ',', '.') . 'đ mới áp dụng được mã này.',
                'discount_amount' => 0,
                'model' => null,
            ];
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return ['valid' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng.', 'discount_amount' => 0, 'model' => null];
        }

        if ($user && $coupon->max_uses_per_user > 0) {
            $userUsed = CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count();
            if ($userUsed >= $coupon->max_uses_per_user) {
                return ['valid' => false, 'message' => 'Bạn đã sử dụng hết số lượt cho phép của mã này.', 'discount_amount' => 0, 'model' => null];
            }
        }

        $discount = $this->computeRawDiscount(
            $orderAmount,
            $coupon->discount_type,
            (int) $coupon->discount_value,
            $coupon->max_discount_amount
        );

        return [
            'valid' => true,
            'message' => 'Áp dụng thành công mã ' . $coupon->code,
            'discount_amount' => $discount,
            'model' => $coupon,
        ];
    }

    public function recordCouponUsage(string $code, User $user, Transaction $txn, int $discountAmount): void
    {
        if (!$code || $discountAmount <= 0 || !Schema::hasTable('coupons')) {
            return;
        }
        $coupon = Coupon::where('code', strtoupper(trim($code)))->lockForUpdate()->first();
        if (!$coupon) {
            return;
        }

        $coupon->increment('used_count');
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'transaction_id' => $txn->id,
            'discount_amount' => $discountAmount,
        ]);

        if (Schema::hasTable('user_vouchers')) {
            UserVoucher::where('user_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);
        }

        app(WelcomeOfferService::class)->forgetUserCache((int) $user->id);
    }

    /**
     * Hoàn lại lượt dùng Coupon & mở khóa lại UserVoucher khi đơn hàng lỗi và bị hoàn tiền.
     */
    public function releaseCouponByTransaction(?int $transactionId): void
    {
        if (!$transactionId || !Schema::hasTable('coupon_usages')) {
            return;
        }

        $usages = CouponUsage::where('transaction_id', $transactionId)->lockForUpdate()->get();
        if ($usages->isEmpty()) {
            return;
        }

        $hasUserVouchers = Schema::hasTable('user_vouchers');

        foreach ($usages as $usage) {
            $coupon = Coupon::whereKey($usage->coupon_id)->lockForUpdate()->first();
            if ($coupon && $coupon->used_count > 0) {
                $coupon->decrement('used_count');
            }

            if ($hasUserVouchers && $usage->user_id) {
                UserVoucher::where('user_id', $usage->user_id)
                    ->where('coupon_id', $usage->coupon_id)
                    ->whereNotNull('used_at')
                    ->update(['used_at' => null]);
            }

            $userId = (int) $usage->user_id;
            $usage->delete();

            if ($userId > 0) {
                app(WelcomeOfferService::class)->forgetUserCache($userId);
            }
        }
    }

    /**
     * Tính tiền thưởng nạp tiền nếu đang có Sự kiện Lễ/Tết bật topup_bonus_percent > 0.
     */
    public function calculateTopupBonus(int $depositAmount): array
    {
        $state = $this->getActiveState();
        $bestBonus = 0;
        $bestPercent = 0.0;
        $bestCampaign = null;

        foreach ($state['campaigns'] as $campaign) {
            $pct = (float) ($campaign['topup_bonus_percent'] ?? 0);
            if ($pct > $bestPercent) {
                $bestPercent = $pct;
                $bestBonus = (int) floor($depositAmount * ($pct / 100));
                $bestCampaign = $campaign;
            }
        }

        return [
            'bonus_amount' => $bestBonus,
            'bonus_percent' => $bestPercent,
            'campaign' => $bestCampaign,
        ];
    }

    private function computeRawDiscount(int $amount, string $type, int $value, ?int $maxCap = null): int
    {
        if ($amount <= 0 || $value <= 0) {
            return 0;
        }

        if ($type === 'percent') {
            $pct = min(100, max(0, $value));
            $discount = (int) round($amount * ($pct / 100));
        } else {
            $discount = min($amount, $value);
        }

        if ($maxCap !== null && $maxCap > 0) {
            $discount = min($discount, $maxCap);
        }

        return min($amount, max(0, $discount));
    }
}
