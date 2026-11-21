<?php

use App\Http\Controllers\Api\Reseller\ResellerEndpointController;
use App\Http\Middleware\ResellerApiAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reseller API v1 (Chỉ cung cấp API bán hàng & quản trị dịch vụ cho Đại lý)
| Tuyệt đối không mở API quản trị riêng của Admin (Ticket gốc, Hủy hoàn tiền, Ví gốc).
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware([ResellerApiAuth::class, 'throttle:60,1'])->group(function () {
    // Tài khoản & Bảng giá
    Route::get('/profile', [ResellerEndpointController::class, 'profile']);
    Route::get('/plans', [ResellerEndpointController::class, 'plans']);

    // Cloud VPS
    Route::post('/vps/create', [ResellerEndpointController::class, 'createVps'])->middleware('vps.stock');
    Route::get('/vps/{order_id}/status', [ResellerEndpointController::class, 'vpsStatus']);
    Route::post('/vps/{order_id}/action', [ResellerEndpointController::class, 'actionVps']);
    Route::post('/vps/{order_id}/renew', [ResellerEndpointController::class, 'renewVps']);
    Route::post('/vps/{order_id}/rebuild', [ResellerEndpointController::class, 'rebuildVps']);
    Route::post('/vps/{order_id}/upgrade', [ResellerEndpointController::class, 'upgradeVps'])->middleware('vps.stock');
    Route::post('/vps/{order_id}/password', [ResellerEndpointController::class, 'changePasswordVps']);
    Route::post('/vps/{order_id}/change-ip', [ResellerEndpointController::class, 'changeIpVps']);
    Route::post('/vps/{order_id}/extend-disk', [ResellerEndpointController::class, 'extendDiskVps']);
    Route::post('/vps/{order_id}/delete', [ResellerEndpointController::class, 'deleteVps']);

    // Proxy IPv4
    Route::get('/proxy/plans', [ResellerEndpointController::class, 'proxyPlans']);
    Route::post('/proxy/create', [ResellerEndpointController::class, 'createProxy']);
    Route::get('/proxy/{proxy_id}/status', [ResellerEndpointController::class, 'proxyStatus'])->whereNumber('proxy_id');
    Route::post('/proxy/{proxy_id}/renew', [ResellerEndpointController::class, 'renewProxy'])->whereNumber('proxy_id');
    Route::post('/proxy/{proxy_id}/credentials', [ResellerEndpointController::class, 'updateProxyCredentials'])->whereNumber('proxy_id');
    Route::post('/proxy/{proxy_id}/change-type', [ResellerEndpointController::class, 'changeProxyType'])->whereNumber('proxy_id');
    Route::post('/proxy/{proxy_id}/sync-ip', [ResellerEndpointController::class, 'syncProxyIp'])->whereNumber('proxy_id');
    Route::post('/proxy/{proxy_id}/delete', [ResellerEndpointController::class, 'deleteProxy'])->whereNumber('proxy_id');
});
