<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckVpsStock
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $isOutOfStock = \Illuminate\Support\Facades\Cache::remember('setting_vps_out_of_stock', 3600, function () {
            return \App\Models\Setting::where('key', 'vps_out_of_stock')->value('value') == '1';
        });

        if ($isOutOfStock) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hệ thống tạm thời hết tài nguyên VPS. Vui lòng quay lại sau!'
                ], 503);
            }
            
            return redirect()->back()->with('error', 'Hệ thống tạm thời hết tài nguyên VPS. Chức năng này đang tạm khóa.');
        }

        return $next($request);
    }
}
