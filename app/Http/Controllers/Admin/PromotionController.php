<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\PricingCatalog;
use App\Models\PromotionBanner;
use App\Models\PromotionCampaign;
use App\Services\CloudSunnyPricingService;
use App\Services\FlashSaleService;
use App\Services\MediaStorageService;
use App\Services\PromotionBroadcastService;
use App\Services\PromotionEngine;
use App\Services\WelcomeOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PromotionController extends Controller
{
    public function index()
    {
        $campaigns = PromotionCampaign::withCount(['banners', 'coupons'])
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PromotionCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'event_tag' => $c->event_tag,
                'badge_text' => $c->badge_text,
                'accent_color' => $c->accent_color,
                'description' => $c->description,
                'starts_at' => $c->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $c->ends_at?->format('Y-m-d\TH:i'),
                'starts_display' => $c->starts_at?->format('d/m/Y H:i') ?: 'Ngay lập tức',
                'ends_display' => $c->ends_at?->format('d/m/Y H:i') ?: 'Không giới hạn',
                'is_active' => (bool) $c->is_active,
                'is_running_now' => $c->isRunningNow(),
                'send_launch_email' => (bool) ($c->send_launch_email ?? true),
                'launch_email_sent_at' => $c->launch_email_sent_at?->format('d/m/Y H:i'),
                'launch_email_count' => (int) ($c->launch_email_count ?? 0),
                'priority' => (int) $c->priority,
                'discount_type' => $c->discount_type,
                'discount_value' => (int) $c->discount_value,
                'max_discount_amount' => $c->max_discount_amount ? (int) $c->max_discount_amount : null,
                'target_scope' => $c->target_scope,
                'min_months' => (int) $c->min_months,
                'apply_on_renew' => (bool) $c->apply_on_renew,
                'apply_on_upgrade' => (bool) ($c->apply_on_upgrade ?? false),
                'stack_with_user_discount' => (bool) $c->stack_with_user_discount,
                'protect_cost_floor' => (bool) $c->protect_cost_floor,
                'topup_bonus_percent' => (float) $c->topup_bonus_percent,
                'banners_count' => $c->banners_count,
                'coupons_count' => $c->coupons_count,
            ]);

        $banners = PromotionBanner::with('campaign:id,name')
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PromotionBanner $b) => [
                'id' => $b->id,
                'campaign_id' => $b->campaign_id,
                'campaign_name' => $b->campaign?->name,
                'placement' => $b->placement,
                'eyebrow' => $b->eyebrow,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'cta_text' => $b->cta_text,
                'cta_url' => $b->cta_url,
                'coupon_code_display' => $b->coupon_code_display,
                'image_desktop' => $b->image_desktop,
                'image_mobile' => $b->image_mobile,
                'theme_preset' => $b->theme_preset,
                'bg_from' => $b->bg_from,
                'bg_to' => $b->bg_to,
                'starts_at' => $b->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $b->ends_at?->format('Y-m-d\TH:i'),
                'is_active' => (bool) $b->is_active,
                'sort_order' => (int) $b->sort_order,
            ]);

        $coupons = Coupon::with('campaign:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Coupon $cp) => [
                'id' => $cp->id,
                'campaign_id' => $cp->campaign_id,
                'campaign_name' => $cp->campaign?->name,
                'code' => $cp->code,
                'description' => $cp->description,
                'discount_type' => $cp->discount_type,
                'discount_value' => (int) $cp->discount_value,
                'max_discount_amount' => $cp->max_discount_amount ? (int) $cp->max_discount_amount : null,
                'min_order_amount' => (int) $cp->min_order_amount,
                'applies_to' => $cp->applies_to,
                'apply_on_renew' => (bool) $cp->apply_on_renew,
                'protect_cost_floor' => (bool) $cp->protect_cost_floor,
                'max_uses' => $cp->max_uses,
                'used_count' => (int) $cp->used_count,
                'max_uses_per_user' => (int) $cp->max_uses_per_user,
                'is_huntable' => (bool) ($cp->is_huntable ?? false),
                'claim_limit' => $cp->claim_limit ? (int) $cp->claim_limit : null,
                'claimed_count' => (int) ($cp->claimed_count ?? 0),
                'starts_at' => $cp->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $cp->ends_at?->format('Y-m-d\TH:i'),
                'is_active' => (bool) $cp->is_active,
            ]);

        $catalogItems = PricingCatalog::where('is_active', true)
            ->orderBy('service_type')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PricingCatalog $item) => [
                'slug' => $item->slug,
                'name' => $item->name,
                'service_type' => $item->service_type, // vps | proxy | addon
                'provider_cost' => (int) $item->provider_cost_monthly,
                'retail_price' => $item->effectiveMonthlyRetail(),
                'min_margin_percent' => (float) ($item->min_margin_percent ?? 5.0),
            ])
            ->values()
            ->all();

        $catalogBySlug = collect($catalogItems)->keyBy('slug');
        $flashSales = Schema::hasTable('flash_sales')
            ? FlashSale::orderByDesc('is_active')
                ->orderByDesc('id')
                ->get()
                ->map(function (FlashSale $fs) use ($catalogBySlug) {
                    $cat = $catalogBySlug->get($fs->plan_slug);
                    $listPrice = (int) ($cat['retail_price'] ?? 0);
                    $cost = (int) ($cat['provider_cost'] ?? 0);
                    $salePrice = app(FlashSaleService::class)->computeUnitSalePrice(
                        $fs,
                        $listPrice,
                        $cost,
                        (float) ($cat['min_margin_percent'] ?? 5.0)
                    );

                    return [
                        'id' => $fs->id,
                        'name' => $fs->name,
                        'badge_text' => $fs->badge_text,
                        'service_type' => $fs->service_type,
                        'plan_slug' => $fs->plan_slug,
                        'plan_name' => $cat['name'] ?? $fs->plan_slug,
                        'list_price' => $listPrice,
                        'provider_cost' => $cost,
                        'sale_price' => $salePrice,
                        'discount_type' => $fs->discount_type,
                        'discount_value' => (int) $fs->discount_value,
                        'total_slots' => (int) $fs->total_slots,
                        'sold_slots' => (int) $fs->sold_slots,
                        'remaining_slots' => $fs->remainingSlots(),
                        'max_per_user' => (int) $fs->max_per_user,
                        'max_months' => (int) $fs->max_months,
                        'protect_cost_floor' => (bool) $fs->protect_cost_floor,
                        'starts_at' => $fs->starts_at?->format('Y-m-d\TH:i'),
                        'ends_at' => $fs->ends_at?->format('Y-m-d\TH:i'),
                        'starts_display' => $fs->starts_at?->format('d/m/Y H:i') ?: 'Ngay lập tức',
                        'ends_display' => $fs->ends_at?->format('d/m/Y H:i') ?: 'Hết suất thì đóng',
                        'is_active' => (bool) $fs->is_active,
                        'is_live_now' => $fs->isLiveNow(),
                        'is_upcoming' => $fs->isUpcoming(),
                    ];
                })
                ->values()
                ->all()
            : [];

        return Inertia::render('Admin/Promotions/Index', [
            'campaigns' => $campaigns,
            'banners' => $banners,
            'coupons' => $coupons,
            'flashSales' => $flashSales,
            'catalogItems' => $catalogItems,
            'welcomeOffer' => app(WelcomeOfferService::class)->getConfig(),
        ]);
    }

    public function storeCampaign(Request $request, PromotionEngine $engine, CloudSunnyPricingService $pricing)
    {
        $data = $this->validateCampaign($request);
        $data['slug'] = Str::slug($data['name']) . '-' . strtolower(Str::random(4));

        PromotionCampaign::create($data);
        $engine->flushCache();
        $pricing->flushCache();

        return back()->with('success', "Đã tạo chiến dịch sự kiện \"{$data['name']}\".");
    }

    public function updateCampaign(Request $request, PromotionCampaign $campaign, PromotionEngine $engine, CloudSunnyPricingService $pricing)
    {
        $data = $this->validateCampaign($request);
        $campaign->update($data);
        $engine->flushCache();
        $pricing->flushCache();

        return back()->with('success', "Đã cập nhật sự kiện \"{$campaign->name}\".");
    }

    public function toggleCampaign(PromotionCampaign $campaign, PromotionEngine $engine, CloudSunnyPricingService $pricing)
    {
        $campaign->update(['is_active' => !$campaign->is_active]);
        $engine->flushCache();
        $pricing->flushCache();

        return back()->with('success', $campaign->is_active ? 'Đã bật chiến dịch sự kiện.' : 'Đã tạm dừng chiến dịch sự kiện.');
    }

    /**
     * Đẩy lịch sự kiện (+ toàn bộ Coupon & Banner gắn kèm) sang năm tiếp theo (+1 năm) để tái sử dụng hàng năm.
     */
    public function rollCampaignNextYear(
        PromotionCampaign $campaign,
        PromotionEngine $engine,
        CloudSunnyPricingService $pricing,
        FlashSaleService $flashSales
    ) {
        if (!$campaign->starts_at && !$campaign->ends_at) {
            return back()->with('error', 'Sự kiện này không giới hạn ngày nên không cần đẩy lịch năm sau.');
        }

        $newStart = $campaign->starts_at?->copy()->addYear();
        $newEnd = $campaign->ends_at?->copy()->addYear();

        $campaign->update([
            'starts_at' => $newStart,
            'ends_at' => $newEnd,
            'launch_email_sent_at' => null,
            'launch_email_count' => 0,
        ]);

        foreach ($campaign->coupons as $cp) {
            $cp->update([
                'starts_at' => $cp->starts_at ? $cp->starts_at->copy()->addYear() : $newStart,
                'ends_at' => $cp->ends_at ? $cp->ends_at->copy()->addYear() : $newEnd,
            ]);
        }

        foreach ($campaign->banners as $b) {
            $b->update([
                'starts_at' => $b->starts_at ? $b->starts_at->copy()->addYear() : $newStart,
                'ends_at' => $b->ends_at ? $b->ends_at->copy()->addYear() : $newEnd,
            ]);
        }

        $engine->flushCache();
        $pricing->flushCache();
        $flashSales->flushCache();

        $range = ($newStart?->format('d/m/Y') ?: '—') . ' → ' . ($newEnd?->format('d/m/Y') ?: '—');

        return back()->with('success', "Đã đẩy lịch \"{$campaign->name}\" cùng Coupon/Banner liên quan sang năm sau ({$range}).");
    }

    public function broadcastCampaign(PromotionCampaign $campaign, PromotionBroadcastService $broadcaster)
    {
        $sent = $broadcaster->broadcast($campaign, true);

        return back()->with('success', "Đã đưa vào hàng đợi gửi Email quảng bá sự kiện \"{$campaign->name}\" tới {$sent} khách hàng.");
    }

    public function destroyCampaign(PromotionCampaign $campaign, PromotionEngine $engine, CloudSunnyPricingService $pricing)
    {
        $campaign->delete();
        $engine->flushCache();
        $pricing->flushCache();

        return back()->with('success', 'Đã xóa chiến dịch sự kiện.');
    }

    public function uploadBannerImage(Request $request, MediaStorageService $storage)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'slot' => 'nullable|in:desktop,mobile',
        ], [
            'image.required' => 'Vui lòng chọn tệp ảnh cần tải lên.',
            'image.mimes' => 'Chỉ hỗ trợ ảnh JPG, PNG, WebP hoặc GIF.',
            'image.max' => 'Dung lượng ảnh tối đa 5MB.',
        ]);

        try {
            $slot = (string) $request->input('slot', 'desktop');
            $result = $storage->uploadImage($request->file('image'), 'banners', 'banner_' . $slot);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function storeBanner(Request $request, PromotionEngine $engine)
    {
        $data = $this->validateBanner($request);
        PromotionBanner::create($data);
        $engine->flushCache();

        return back()->with('success', 'Đã thêm Banner hiển thị mới.');
    }

    public function updateBanner(Request $request, PromotionBanner $banner, PromotionEngine $engine)
    {
        $data = $this->validateBanner($request);
        $banner->update($data);
        $engine->flushCache();

        return back()->with('success', 'Đã cập nhật nội dung Banner.');
    }

    public function toggleBanner(PromotionBanner $banner, PromotionEngine $engine)
    {
        $banner->update(['is_active' => !$banner->is_active]);
        $engine->flushCache();

        return back()->with('success', $banner->is_active ? 'Đã hiển thị Banner.' : 'Đã ẩn Banner.');
    }

    public function destroyBanner(PromotionBanner $banner, PromotionEngine $engine, MediaStorageService $storage)
    {
        $storage->deleteByUrl($banner->image_desktop);
        $storage->deleteByUrl($banner->image_mobile);
        $banner->delete();
        $engine->flushCache();

        return back()->with('success', 'Đã xóa Banner.');
    }

    public function storeCoupon(Request $request, PromotionEngine $engine, FlashSaleService $flashSales)
    {
        $data = $this->validateCoupon($request);
        $data['code'] = strtoupper(trim($data['code']));
        Coupon::create($data);
        $engine->flushCache();
        $flashSales->flushCache();

        return back()->with('success', "Đã phát hành mã giảm giá {$data['code']}.");
    }

    public function updateCoupon(Request $request, Coupon $coupon, PromotionEngine $engine, FlashSaleService $flashSales)
    {
        $data = $this->validateCoupon($request, $coupon->id);
        $data['code'] = strtoupper(trim($data['code']));
        $coupon->update($data);
        $engine->flushCache();
        $flashSales->flushCache();

        return back()->with('success', "Đã cập nhật mã {$coupon->code}.");
    }

    public function toggleCoupon(Coupon $coupon, PromotionEngine $engine, FlashSaleService $flashSales)
    {
        $coupon->update(['is_active' => !$coupon->is_active]);
        $engine->flushCache();
        $flashSales->flushCache();
        return back()->with('success', $coupon->is_active ? 'Đã kích hoạt mã giảm giá.' : 'Đã khóa mã giảm giá.');
    }

    public function destroyCoupon(Coupon $coupon, PromotionEngine $engine, FlashSaleService $flashSales)
    {
        $coupon->delete();
        $engine->flushCache();
        $flashSales->flushCache();
        return back()->with('success', 'Đã xóa mã giảm giá.');
    }

    public function storeFlashSale(
        Request $request,
        FlashSaleService $flashSales,
        PromotionEngine $engine,
        CloudSunnyPricingService $pricing
    ) {
        $data = $this->validateFlashSale($request);
        FlashSale::create($data);
        $flashSales->flushCache();
        $engine->flushCache();
        $pricing->flushCache();

        return back()->with('success', "Đã mở phiên Flash Sale \"{$data['name']}\" ({$data['total_slots']} suất).");
    }

    public function updateFlashSale(
        Request $request,
        FlashSale $flashSale,
        FlashSaleService $flashSales,
        PromotionEngine $engine,
        CloudSunnyPricingService $pricing
    ) {
        $data = $this->validateFlashSale($request);
        $flashSale->update($data);
        $flashSales->flushCache();
        $engine->flushCache();
        $pricing->flushCache();

        return back()->with('success', "Đã cập nhật Flash Sale \"{$flashSale->name}\".");
    }

    public function toggleFlashSale(
        FlashSale $flashSale,
        FlashSaleService $flashSales,
        PromotionEngine $engine,
        CloudSunnyPricingService $pricing
    ) {
        $flashSale->update(['is_active' => !$flashSale->is_active]);
        $flashSales->flushCache();
        $engine->flushCache();
        $pricing->flushCache();

        return back()->with('success', $flashSale->is_active ? 'Đã mở lại phiên Flash Sale.' : 'Đã tạm dừng phiên Flash Sale.');
    }

    public function destroyFlashSale(
        FlashSale $flashSale,
        FlashSaleService $flashSales,
        PromotionEngine $engine,
        CloudSunnyPricingService $pricing
    ) {
        $flashSale->delete();
        $flashSales->flushCache();
        $engine->flushCache();
        $pricing->flushCache();

        return back()->with('success', 'Đã xóa phiên Flash Sale.');
    }

    public function updateWelcomeOffer(
        Request $request,
        WelcomeOfferService $welcomeOffer,
        PromotionEngine $engine,
        FlashSaleService $flashSales
    ) {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'code' => 'required|string|alpha_num|min:3|max:32',
            'percent' => 'required|integer|min:1|max:50',
            'max_discount' => 'required|integer|min:0|max:5000000',
            'valid_days' => 'required|integer|min:1|max:90',
            'max_months' => 'required|integer|min:1|max:12',
        ]);

        $welcomeOffer->updateConfig($data);
        $engine->flushCache();
        $flashSales->flushCache();

        return back()->with('success', 'Đã lưu cấu hình ưu đãi thành viên mới.');
    }

    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:160',
            'event_tag' => 'required|string|max:40',
            'badge_text' => 'nullable|string|max:60',
            'accent_color' => 'required|string|max:24',
            'description' => 'nullable|string|max:1000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'required|boolean',
            'send_launch_email' => 'sometimes|boolean',
            'priority' => 'required|integer|min:0|max:999',
            'discount_type' => 'required|in:percent,fixed,none',
            'discount_value' => 'required|integer|min:0',
            'max_discount_amount' => 'nullable|integer|min:0',
            'target_scope' => 'required|in:all,vps,proxy,upgrade',
            'min_months' => 'required|integer|min:1|max:36',
            'apply_on_renew' => 'required|boolean',
            'apply_on_upgrade' => 'sometimes|boolean',
            'stack_with_user_discount' => 'required|boolean',
            'protect_cost_floor' => 'required|boolean',
            'topup_bonus_percent' => 'required|numeric|min:0|max:100',
        ]);
    }

    private function validateBanner(Request $request): array
    {
        return $request->validate([
            'campaign_id' => 'nullable|exists:promotion_campaigns,id',
            'placement' => 'required|in:top_bar,hero_showcase,popup_modal,dashboard_banner',
            'eyebrow' => 'nullable|string|max:80',
            'title' => 'required|string|max:200',
            'subtitle' => 'nullable|string|max:350',
            'cta_text' => 'nullable|string|max:80',
            'cta_url' => 'nullable|string|max:255',
            'coupon_code_display' => 'nullable|string|max:50',
            'image_desktop' => 'nullable|string|max:255',
            'image_mobile' => 'nullable|string|max:255',
            'theme_preset' => 'required|string|max:32',
            'bg_from' => 'required|string|max:24',
            'bg_to' => 'required|string|max:24',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:999',
        ]);
    }

    private function validateCoupon(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'campaign_id' => 'nullable|exists:promotion_campaigns,id',
            'code' => 'required|string|max:48|unique:coupons,code' . ($ignoreId ? ',' . $ignoreId : ''),
            'description' => 'nullable|string|max:200',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|integer|min:1',
            'max_discount_amount' => 'nullable|integer|min:0',
            'min_order_amount' => 'required|integer|min:0',
            'applies_to' => 'required|in:all,vps,proxy',
            'apply_on_renew' => 'required|boolean',
            'protect_cost_floor' => 'required|boolean',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'required|integer|min:1|max:100',
            'is_huntable' => 'sometimes|boolean',
            'claim_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);
    }

    private function validateFlashSale(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'badge_text' => 'nullable|string|max:60',
            'plan_slug' => 'required|string|exists:pricing_catalogs,slug',
            'discount_type' => 'required|in:percent,fixed,fixed_price',
            'discount_value' => 'required|integer|min:1',
            'total_slots' => 'required|integer|min:1|max:10000',
            'max_per_user' => 'required|integer|min:1|max:50',
            'max_months' => 'required|integer|min:1|max:36',
            'protect_cost_floor' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $cat = PricingCatalog::where('slug', $validated['plan_slug'])->firstOrFail();
        $validated['service_type'] = $cat->service_type === 'proxy' ? 'proxy' : 'vps';

        return $validated;
    }
}
