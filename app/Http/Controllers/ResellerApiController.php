<?php

namespace App\Http\Controllers;

use App\Services\CloudSunnyPricingService;
use App\Services\CloudSunnyProxyPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResellerApiController extends Controller
{
    public function index(CloudSunnyPricingService $pricing, CloudSunnyProxyPricingService $proxyPricing)
    {
        $user = Auth::user();
        $canUseApi = $user && ($user->isReseller() || $user->isAdmin());

        if ($canUseApi && empty($user->api_key)) {
            $user->api_key = 'sk_' . Str::random(40);
            $user->save();
        }

        $plansData = $pricing->getPublicPlans($user);
        $firstPlan = reset($plansData);
        $imagesData = $pricing->getImages((int) ($firstPlan['product_id'] ?? 1));

        $catalogPlans = collect($plansData)->map(fn (array $p, string $slug) => [
            'id' => $p['product_id'] ?? $slug,
            'slug' => $slug,
            'name' => $p['name'],
            'cpu' => (int) $p['cores'],
            'ram' => (int) $p['ram'],
            'disk' => (int) $p['disk'],
            'network_mbps' => (int) ($p['network_out_mbps'] ?? 100),
            'price_monthly' => (int) $p['price_per_month'],
        ])->values()->all();

        $proxyPlans = collect($proxyPricing->getPublicCatalogProducts($user))->map(fn (array $p) => [
            'id' => (int) $p['id'],
            'slug' => $p['slug'],
            'name' => $p['title'],
            'category' => $p['category'],
            'dedicated' => (bool) $p['dedicated'],
            'price_monthly' => (int) $p['price'],
        ])->values()->all();

        $osList = collect($imagesData)->map(fn (array $img, $id) => [
            'id' => (int) ($img['id'] ?? $id),
            'name' => $img['label'],
            'group' => $img['group'] ?? 'Linux',
        ])->values()->all();

        return Inertia::render('ResellerApi/Index', [
            'canUseApi' => $canUseApi,
            'roleLabel' => $user?->roleLabel() ?? 'Khách hàng',
            'discountPercent' => (int) ($user?->discount_percent ?? 0),
            'proxyDiscountPercent' => (int) ($user?->proxy_discount_percent ?? 0),
            'apiKey' => $canUseApi ? $user->api_key : null,
            'webhookUrl' => $canUseApi ? $user->webhook_url : null,
            'baseUrl' => url('/api/v1'),
            'catalogPlans' => $catalogPlans,
            'proxyPlans' => $proxyPlans,
            'osList' => $osList,
        ]);
    }

    public function updateWebhook(Request $request)
    {
        $user = Auth::user();
        if (!$user || (!$user->isReseller() && !$user->isAdmin())) {
            abort(403, 'Tài khoản của bạn chưa được kích hoạt quyền Đại lý API.');
        }

        $request->validate([
            'webhook_url' => [
                'nullable',
                'url:http,https',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && !self::isSafePublicWebhookUrl((string) $value)) {
                        $fail('Đường dẫn Webhook không được trỏ tới địa chỉ nội bộ (localhost/LAN/private IP).');
                    }
                },
            ],
        ], [
            'webhook_url.url' => 'Đường dẫn Webhook phải là URL hợp lệ (bắt đầu bằng http:// hoặc https://).',
        ]);

        $user->webhook_url = $request->webhook_url;
        $user->save();

        return back()->with('success', 'Đã lưu cấu hình Webhook nhận sự kiện cấp phát.');
    }

    /**
     * Chống SSRF: chỉ cho phép Webhook http/https trỏ tới tên miền/IP công khai (chặn localhost, LAN, link-local, metadata).
     */
    public static function isSafePublicWebhookUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        $ips = @gethostbynamel($host);
        if (!is_array($ips) || empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    public function regenerateKey()
    {
        $user = Auth::user();
        if (!$user || (!$user->isReseller() && !$user->isAdmin())) {
            abort(403, 'Tài khoản của bạn chưa được kích hoạt quyền Đại lý API.');
        }

        $user->api_key = 'sk_' . Str::random(40);
        $user->save();

        return back()->with('success', 'Đã cấp mới khóa API thành công. Hãy cập nhật khóa mới trên hệ thống của bạn.');
    }
}
