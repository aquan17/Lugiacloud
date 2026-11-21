<?php

use App\Http\Controllers\Admin\AffiliateController as AdminAffiliateController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\CloudSunnyAccountController;
use App\Http\Controllers\Admin\DedicatedServerController as AdminDedicatedServerController;
use App\Http\Controllers\Admin\InstanceController as AdminInstanceController;
use App\Http\Controllers\Admin\KnowledgeBaseController as AdminKnowledgeBaseController;
use App\Http\Controllers\Admin\LedgerController as AdminLedgerController;
use App\Http\Controllers\Admin\MediaUploadController as AdminMediaUploadController;
use App\Http\Controllers\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\ProxyController as AdminProxyController;
use App\Http\Controllers\Admin\RevenueController as AdminRevenueController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CloudSunnyWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DedicatedServerController;
use App\Http\Controllers\DedicatedServerStoreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionQuoteController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\ProxyStoreController;
use App\Http\Controllers\ResellerApiController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TopupController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\VoucherHuntController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/support', [HomeController::class, 'support'])->name('support.index');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/sitemap.xml', function () {
    $content = \Illuminate\Support\Facades\Cache::remember('lugiacloud.sitemap_xml_v1', 300, function () {
        $lastmod = now()->toDateString();
        $urls = [
            ['loc' => url('/'), 'lastmod' => $lastmod, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => url('/pricing'), 'lastmod' => $lastmod, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => url('/blog'), 'lastmod' => $lastmod, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => url('/support'), 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.6'],
        ];

        if (\Illuminate\Support\Facades\Schema::hasTable('articles')) {
            foreach (\App\Models\Article::published()->select('slug', 'updated_at')->orderByDesc('published_at')->get() as $a) {
                $urls[] = [
                    'loc' => url('/blog/' . $a->slug),
                    'lastmod' => $a->updated_at?->toDateString() ?: $lastmod,
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }
        }

        $xmlRows = collect($urls)->map(fn ($u) => sprintf(
            '    <url><loc>%s</loc><lastmod>%s</lastmod><changefreq>%s</changefreq><priority>%s</priority></url>',
            e($u['loc']),
            e($u['lastmod']),
            e($u['changefreq']),
            e($u['priority'])
        ))->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$xmlRows}\n</urlset>";
    });

    return response($content, 200)
        ->header('Content-Type', 'application/xml; charset=utf-8')
        ->header('Cache-Control', 'public, max-age=300');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:5,1')->name('password.email');
    Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset', [ResetPasswordController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [VerificationController::class, 'show'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware(['throttle:6,1'])->name('verification.resend');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/pricing', [StoreController::class, 'index'])->name('pricing');
Route::get('/servers/store', [DedicatedServerStoreController::class, 'index'])->name('servers.store.index');

// Live Order Quote & Coupon Preview
Route::post('/promotions/quote', [PromotionQuoteController::class, 'preview'])->middleware('throttle:60,1')->name('promotions.quote');

// Săn Voucher vào ví cá nhân
Route::post('/vouchers/{coupon}/claim', [VoucherHuntController::class, 'claim'])
    ->middleware(['auth', 'throttle:20,1'])
    ->name('vouchers.claim');

Route::middleware(['auth', 'verified'])->group(function () {
    // Tổng quan Dashboard (tương thích ngược /overview -> /dashboard)
    Route::get('/dashboard', [DashboardController::class, 'overview'])->name('dashboard');
    Route::redirect('/overview', '/dashboard', 301);

    // Quản lý Cloud VPS
    Route::get('/vps', [DashboardController::class, 'index'])->name('vps.index');
    Route::get('/vps/sync-pending', [DashboardController::class, 'syncPending'])->name('vps.syncPending');
    Route::get('/vps/{vps}', [DashboardController::class, 'show'])->name('vps.show');
    Route::get('/vps/{vps}/status', [DashboardController::class, 'statusJson'])->name('vps.status');
    Route::post('/vps/{vps}/sync', [DashboardController::class, 'sync'])->name('vps.sync');

    // Power & Lifecycle
    Route::patch('/vps/{vps}/label', [DashboardController::class, 'updateLabel'])->name('vps.label');
    Route::post('/vps/{vps}/reboot', [DashboardController::class, 'reboot'])->name('vps.reboot');
    Route::post('/vps/{vps}/shutdown', [DashboardController::class, 'shutdown'])->name('vps.shutdown');
    Route::post('/vps/{vps}/boot', [DashboardController::class, 'boot'])->name('vps.boot');
    Route::post('/vps/{vps}/renew', [DashboardController::class, 'renew'])->name('vps.renew');
    Route::post('/vps/{vps}/auto-renew', [DashboardController::class, 'toggleAutoRenew'])->name('vps.toggleAutoRenew');
    Route::post('/vps/{vps}/upgrade', [DashboardController::class, 'upgrade'])->middleware('vps.stock')->name('vps.upgrade');
    Route::post('/vps/{vps}/password', [DashboardController::class, 'changePassword'])->name('vps.password');
    Route::post('/vps/{vps}/rebuild', [DashboardController::class, 'rebuild'])->name('vps.rebuild');
    Route::post('/vps/{vps}/change-ip', [DashboardController::class, 'changeIp'])->middleware('throttle:10,1')->name('vps.changeIp');
    Route::post('/vps/{vps}/extend-disk', [DashboardController::class, 'extendDisk'])->middleware('throttle:10,1')->name('vps.extendDisk');
    Route::get('/vps/{vps}/console', [DashboardController::class, 'console'])->name('vps.console');
    Route::delete('/vps/{vps}', [DashboardController::class, 'destroy'])->name('vps.destroy');

    // Tương thích ngược đường dẫn chi tiết VPS cũ trong Email trước đây
    Route::get('/dashboard/vps/{vps}', fn ($vps) => redirect()->route('vps.show', $vps));

    // VPS Store
    Route::get('/store', [StoreController::class, 'index'])->name('store.index');
    Route::get('/store/{plan}', [StoreController::class, 'create'])->name('store.create');
    Route::post('/store', [StoreController::class, 'store'])->middleware('vps.stock')->name('store.store');

    // Proxy Store & Management
    Route::get('/proxy/store', [ProxyStoreController::class, 'index'])->name('proxy.store.index');
    Route::get('/proxy/store/{product}', [ProxyStoreController::class, 'create'])->whereNumber('product')->name('proxy.store.create');
    Route::post('/proxy/store', [ProxyStoreController::class, 'store'])->name('proxy.store.store');
    Route::get('/proxy', [ProxyController::class, 'index'])->name('proxy.index');
    Route::post('/proxy/bulk-action', [ProxyController::class, 'bulkAction'])->name('proxy.bulkAction');
    Route::get('/proxy/{proxy}', [ProxyController::class, 'show'])->name('proxy.show');
    Route::get('/proxy/{proxy}/status', [ProxyController::class, 'statusJson'])->name('proxy.statusJson');
    Route::post('/proxy/{proxy}/renew', [ProxyController::class, 'renew'])->name('proxy.renew');
    Route::post('/proxy/{proxy}/auto-renew', [ProxyController::class, 'toggleAutoRenew'])->name('proxy.toggleAutoRenew');
    Route::post('/proxy/{proxy}/credentials', [ProxyController::class, 'updateCredentials'])->middleware('throttle:15,1')->name('proxy.credentials');
    Route::post('/proxy/{proxy}/change-type', [ProxyController::class, 'changeType'])->middleware('throttle:15,1')->name('proxy.changeType');
    Route::post('/proxy/{proxy}/sync-ip', [ProxyController::class, 'syncIp'])->middleware('throttle:10,1')->name('proxy.syncIp');
    Route::delete('/proxy/{proxy}', [ProxyController::class, 'destroy'])->name('proxy.destroy');

    // Dedicated Server (Máy chủ vật lý) Store & Management
    Route::get('/servers/store/{slug}', [DedicatedServerStoreController::class, 'create'])->name('servers.store.create');
    Route::post('/servers/store', [DedicatedServerStoreController::class, 'store'])->middleware('throttle:10,1')->name('servers.store.store');
    Route::get('/servers', [DedicatedServerController::class, 'index'])->name('servers.index');
    Route::get('/servers/{server}', [DedicatedServerController::class, 'show'])->name('servers.show');
    Route::post('/servers/{server}/sync', [DedicatedServerController::class, 'sync'])->name('servers.sync');
    Route::post('/servers/{server}/renew', [DedicatedServerController::class, 'renew'])->middleware('throttle:10,1')->name('servers.renew');
    Route::post('/servers/{server}/auto-renew', [DedicatedServerController::class, 'toggleAutoRenew'])->name('servers.toggleAutoRenew');
    Route::patch('/servers/{server}/label', [DedicatedServerController::class, 'updateLabel'])->name('servers.label');

    // Top-ups, Billing & Invoices
    Route::get('/topup', [TopupController::class, 'index'])->name('topup.index');
    Route::post('/topup', [TopupController::class, 'store'])->middleware('throttle:15,1')->name('topup.store');
    Route::get('/topup/{id}', [TopupController::class, 'show'])->name('topup.show');
    Route::get('/topup/{id}/status', [TopupController::class, 'status'])->name('topup.status');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/invoice/{code}', [InvoiceController::class, 'show'])->name('billing.invoice');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{code}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/vouchers', [VoucherHuntController::class, 'index'])->name('vouchers.index');

    // Profile, Affiliate, Reseller API, Notifications & AI Chatbot
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/affiliate', [AffiliateController::class, 'index'])->name('affiliate.index');
    Route::get('/reseller-api', [ResellerApiController::class, 'index'])->name('reseller.api.index');
    Route::post('/reseller-api/webhook', [ResellerApiController::class, 'updateWebhook'])->name('reseller.api.webhook');
    Route::post('/reseller-api/regenerate', [ResellerApiController::class, 'regenerateKey'])->name('reseller.api.regenerate');

    Route::get('/notifications', [HomeController::class, 'notifications'])->name('notifications.index');
    Route::post('/chatbot/message', [ChatbotController::class, 'chat'])->middleware('throttle:10,1')->name('chatbot.message');
});

// PayOS / Bank & CloudSunny Async Callback Webhooks
Route::post('/webhooks/topups', [TopupController::class, 'webhook'])->middleware('throttle:120,1')->name('webhooks.topups');
Route::post('/webhooks/cloudsunny', [CloudSunnyWebhookController::class, 'handle'])->middleware('throttle:120,1')->name('webhooks.cloudsunny');

// Admin Backoffice
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/revenue', [AdminRevenueController::class, 'index'])->name('revenue.index');
    Route::get('/revenue/yearly', [AdminRevenueController::class, 'yearly'])->name('revenue.yearly');
    Route::delete('/revenue/{transaction}', [AdminRevenueController::class, 'destroy'])->name('revenue.destroy');

    // Reseller Pricing & Markup Catalog
    Route::get('/pricing', [AdminPricingController::class, 'index'])->name('pricing.index');
    Route::put('/pricing/defaults', [AdminPricingController::class, 'updateDefaults'])->name('pricing.defaults');
    Route::post('/pricing/sync', [AdminPricingController::class, 'syncUpstream'])->name('pricing.sync');
    Route::put('/pricing/{catalog}', [AdminPricingController::class, 'update'])->name('pricing.update');

    // Seasonal Event Campaigns, Banners & Coupons
    Route::get('/promotions', [AdminPromotionController::class, 'index'])->name('promotions.index');
    Route::post('/promotions/campaigns', [AdminPromotionController::class, 'storeCampaign'])->name('promotions.campaigns.store');
    Route::put('/promotions/campaigns/{campaign}', [AdminPromotionController::class, 'updateCampaign'])->name('promotions.campaigns.update');
    Route::post('/promotions/campaigns/{campaign}/toggle', [AdminPromotionController::class, 'toggleCampaign'])->name('promotions.campaigns.toggle');
    Route::post('/promotions/campaigns/{campaign}/roll-year', [AdminPromotionController::class, 'rollCampaignNextYear'])->name('promotions.campaigns.roll-year');
    Route::post('/promotions/campaigns/{campaign}/broadcast', [AdminPromotionController::class, 'broadcastCampaign'])->name('promotions.campaigns.broadcast');
    Route::delete('/promotions/campaigns/{campaign}', [AdminPromotionController::class, 'destroyCampaign'])->name('promotions.campaigns.destroy');

    Route::post('/promotions/banners/upload-image', [AdminPromotionController::class, 'uploadBannerImage'])->name('promotions.banners.upload');
    Route::post('/promotions/banners', [AdminPromotionController::class, 'storeBanner'])->name('promotions.banners.store');
    Route::put('/promotions/banners/{banner}', [AdminPromotionController::class, 'updateBanner'])->name('promotions.banners.update');
    Route::post('/promotions/banners/{banner}/toggle', [AdminPromotionController::class, 'toggleBanner'])->name('promotions.banners.toggle');
    Route::delete('/promotions/banners/{banner}', [AdminPromotionController::class, 'destroyBanner'])->name('promotions.banners.destroy');

    Route::post('/promotions/coupons', [AdminPromotionController::class, 'storeCoupon'])->name('promotions.coupons.store');
    Route::put('/promotions/coupons/{coupon}', [AdminPromotionController::class, 'updateCoupon'])->name('promotions.coupons.update');
    Route::post('/promotions/coupons/{coupon}/toggle', [AdminPromotionController::class, 'toggleCoupon'])->name('promotions.coupons.toggle');
    Route::delete('/promotions/coupons/{coupon}', [AdminPromotionController::class, 'destroyCoupon'])->name('promotions.coupons.destroy');

    Route::post('/promotions/flash-sales', [AdminPromotionController::class, 'storeFlashSale'])->name('promotions.flash-sales.store');
    Route::put('/promotions/flash-sales/{flashSale}', [AdminPromotionController::class, 'updateFlashSale'])->name('promotions.flash-sales.update');
    Route::post('/promotions/flash-sales/{flashSale}/toggle', [AdminPromotionController::class, 'toggleFlashSale'])->name('promotions.flash-sales.toggle');
    Route::delete('/promotions/flash-sales/{flashSale}', [AdminPromotionController::class, 'destroyFlashSale'])->name('promotions.flash-sales.destroy');
    Route::put('/promotions/welcome-offer', [AdminPromotionController::class, 'updateWelcomeOffer'])->name('promotions.welcome-offer.update');

    // Upstream Account (Ví & API CloudSunny cố định)
    Route::get('/accounts', [CloudSunnyAccountController::class, 'index'])->name('accounts.index');
    Route::put('/accounts/primary', [CloudSunnyAccountController::class, 'savePrimary'])->name('accounts.save-primary');
    Route::post('/accounts/sync-primary', [CloudSunnyAccountController::class, 'syncPrimary'])->name('accounts.sync-primary');
    Route::post('/accounts', [CloudSunnyAccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}/edit', [CloudSunnyAccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/accounts/{account}', [CloudSunnyAccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/sync-all', [CloudSunnyAccountController::class, 'syncAll'])->name('accounts.sync-all');
    Route::post('/accounts/{account}/sync', [CloudSunnyAccountController::class, 'sync'])->name('accounts.sync');
    Route::post('/accounts/{account}/toggle', [CloudSunnyAccountController::class, 'toggle'])->name('accounts.toggle');
    Route::delete('/accounts/{account}', [CloudSunnyAccountController::class, 'destroy'])->name('accounts.destroy');

    // Users & Topups
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/search', [AdminUserController::class, 'search'])->middleware('throttle:120,1')->name('users.search');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/balance', [AdminUserController::class, 'adjustBalance'])->name('users.balance');
    Route::post('/users/{user}/discount', [AdminUserController::class, 'updateDiscount'])->name('users.discount');
    Route::post('/users/{user}/toggle-admin', [AdminUserController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/topups', [AdminUserController::class, 'topups'])->name('topups.index');
    Route::post('/topups/{topup}/approve', [AdminUserController::class, 'approveTopup'])->name('topups.approve');
    Route::post('/topups/{topup}/reject', [AdminUserController::class, 'rejectTopup'])->name('topups.reject');
    Route::get('/ledger', [AdminLedgerController::class, 'index'])->name('ledger.index');

    // Blog & Hướng dẫn SEO
    Route::get('/articles', [AdminArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/create', [AdminArticleController::class, 'create'])->name('articles.create');
    Route::post('/articles', [AdminArticleController::class, 'store'])->name('articles.store');
    Route::get('/articles/{article}/edit', [AdminArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articles/{article}', [AdminArticleController::class, 'update'])->name('articles.update');
    Route::post('/articles/{article}/toggle', [AdminArticleController::class, 'toggle'])->name('articles.toggle');
    Route::delete('/articles/{article}', [AdminArticleController::class, 'destroy'])->name('articles.destroy');

    // Knowledge Base, Affiliates, Instances, Proxies, Tickets & Settings
    Route::resource('/knowledge-bases', AdminKnowledgeBaseController::class)->except(['show']);
    Route::get('/affiliates', [AdminAffiliateController::class, 'index'])->name('affiliates.index');

    Route::get('/instances', [AdminInstanceController::class, 'index'])->name('instances.index');
    Route::get('/instances/{vps}', [AdminInstanceController::class, 'show'])->name('instances.show');
    Route::post('/instances/{vps}/sync', [AdminInstanceController::class, 'sync'])->name('instances.sync');
    Route::post('/instances/{vps}/power', [AdminInstanceController::class, 'power'])->name('instances.power');
    Route::post('/instances/{vps}/password', [AdminInstanceController::class, 'changePassword'])->name('instances.password');
    Route::post('/instances/{vps}/rebuild', [AdminInstanceController::class, 'rebuild'])->name('instances.rebuild');
    Route::post('/instances/{vps}/extend-disk', [AdminInstanceController::class, 'extendDisk'])->name('instances.extendDisk');
    Route::get('/instances/{vps}/console', [AdminInstanceController::class, 'console'])->name('instances.console');
    Route::post('/instances/{vps}/check-refund', [AdminInstanceController::class, 'checkRefund'])->name('instances.checkRefund');
    Route::post('/instances/{vps}/cancel-refund', [AdminInstanceController::class, 'cancelRefund'])->name('instances.cancelRefund');
    Route::post('/instances/{vps}/reassign', [AdminInstanceController::class, 'reassign'])->name('instances.reassign');

    Route::get('/proxies', [AdminProxyController::class, 'index'])->name('proxies.index');
    Route::get('/proxies/{proxy}', [AdminProxyController::class, 'show'])->name('proxies.show');
    Route::post('/proxies/{proxy}/sync', [AdminProxyController::class, 'sync'])->name('proxies.sync');
    Route::post('/proxies/{proxy}/credentials', [AdminProxyController::class, 'updateCredentials'])->name('proxies.credentials');
    Route::post('/proxies/{proxy}/change-type', [AdminProxyController::class, 'changeType'])->name('proxies.changeType');
    Route::post('/proxies/{proxy}/sync-ip', [AdminProxyController::class, 'syncIp'])->name('proxies.syncIp');
    Route::post('/proxies/{proxy}/reassign', [AdminProxyController::class, 'reassign'])->name('proxies.reassign');
    Route::post('/proxies/bulk-reassign', [AdminProxyController::class, 'bulkReassign'])->name('proxies.bulkReassign');
    Route::delete('/proxies/{proxy}', [AdminProxyController::class, 'destroy'])->name('proxies.destroy');
    Route::post('/proxies/bulk-destroy', [AdminProxyController::class, 'bulkDestroy'])->name('proxies.bulkDestroy');

    Route::get('/servers', [AdminDedicatedServerController::class, 'index'])->name('servers.index');
    Route::get('/servers/{server}', [AdminDedicatedServerController::class, 'show'])->name('servers.show');
    Route::post('/servers/{server}/sync', [AdminDedicatedServerController::class, 'sync'])->name('servers.sync');
    Route::post('/servers/{server}/handover', [AdminDedicatedServerController::class, 'handover'])->name('servers.handover');
    Route::post('/servers/{server}/reassign', [AdminDedicatedServerController::class, 'reassign'])->name('servers.reassign');

    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [AdminTicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticketId}', [AdminTicketController::class, 'show'])->whereNumber('ticketId')->name('tickets.show');
    Route::post('/tickets/{ticketId}/reply', [AdminTicketController::class, 'reply'])->whereNumber('ticketId')->name('tickets.reply');
    Route::post('/tickets/{ticketId}/close', [AdminTicketController::class, 'close'])->whereNumber('ticketId')->name('tickets.close');
    Route::get('/tickets/{ticketId}/image/{messageId}/{index}', [AdminTicketController::class, 'image'])
        ->whereNumber(['ticketId', 'messageId', 'index'])
        ->name('tickets.image');

    Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/toggle-vps-stock', [AdminSettingController::class, 'toggleVpsStock'])->name('settings.toggleVpsStock');
    Route::post('/media/upload', [AdminMediaUploadController::class, 'upload'])->name('media.upload');
});
