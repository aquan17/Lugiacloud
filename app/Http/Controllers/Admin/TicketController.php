<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudSunnyAccount;
use App\Models\DedicatedServer;
use App\Models\ProxyInstance;
use App\Models\VpsInstance;
use App\Services\CloudSunnyAccountRouter;
use App\Services\CloudSunnyApiService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request, CloudSunnyApiService $api, CloudSunnyAccountRouter $router)
    {
        $account = $this->resolveAccount($request, $router);
        $status = (string) $request->input('status', 'all');
        if (!in_array($status, ['all', 'moi', 'dang_xu_ly', 'cho_khach', 'da_dong'], true)) {
            $status = 'all';
        }
        $page = max(1, (int) $request->input('page', 1));

        $tickets = [];
        $counts = ['moi' => 0, 'dang_xu_ly' => 0, 'cho_khach' => 0, 'da_dong' => 0];
        $meta = ['total' => 0, 'per_page' => 20, 'current_page' => $page];
        $apiError = null;

        if ($account) {
            try {
                $res = $api->forAccount($account)->listTickets($status === 'all' ? null : $status, $page);
                $tickets = is_array($res['data'] ?? null) ? $res['data'] : [];
                $counts = array_merge($counts, is_array($res['dem'] ?? null) ? $res['dem'] : []);
                $meta = [
                    'total' => (int) ($res['total'] ?? count($tickets)),
                    'per_page' => (int) ($res['perPage'] ?? 20),
                    'current_page' => (int) ($res['current_page'] ?? $page),
                ];
            } catch (\Throwable $e) {
                $apiError = CloudSunnyApiService::sanitizeErrorMessage($e->getMessage());
            }
        } else {
            $apiError = 'Chưa có tài khoản đại lý CloudSunny đang hoạt động.';
        }

        // Danh sách dịch vụ đang hoạt động để Admin chọn nhanh khi mở ticket kỹ thuật
        $vpsOptions = VpsInstance::whereNotNull('provider_vps_id')
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá'])
            ->latest()
            ->take(100)
            ->get(['id', 'provider_vps_id', 'label', 'public_ip'])
            ->map(fn (VpsInstance $v) => [
                'id' => (int) $v->provider_vps_id,
                'local_id' => $v->id,
                'label' => "#{$v->provider_vps_id} — " . ($v->public_ip ?: 'Chờ IP') . " ({$v->label})",
            ])
            ->values();

        $proxyOptions = ProxyInstance::whereNotNull('provider_proxy_id')
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá'])
            ->latest()
            ->take(100)
            ->get(['id', 'provider_proxy_id', 'ip', 'port', 'type_proxy'])
            ->map(fn (ProxyInstance $p) => [
                'id' => (int) $p->provider_proxy_id,
                'local_id' => $p->id,
                'label' => "#{$p->provider_proxy_id} — " . ($p->ip ? "{$p->ip}:{$p->port}" : 'Chờ IP') . ' (' . strtoupper($p->type_proxy ?: 'HTTP') . ')',
            ])
            ->values();

        $serverOptions = DedicatedServer::whereNotNull('provider_server_id')
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá'])
            ->latest()
            ->take(100)
            ->get(['id', 'provider_server_id', 'label', 'plan_name', 'public_ip'])
            ->map(fn (DedicatedServer $s) => [
                'id' => (int) $s->provider_server_id,
                'local_id' => $s->id,
                'label' => "#{$s->provider_server_id} — " . ($s->public_ip ?: $s->plan_name) . " ({$s->label})",
            ])
            ->values();

        return Inertia::render('Admin/Tickets/Index', [
            'tickets' => $tickets,
            'counts' => $counts,
            'meta' => $meta,
            'filters' => [
                'status' => $status,
                'page' => $page,
                'service_type' => $request->input('service_type', 'vps'),
                'service_id' => $request->input('service_id') ? (int) $request->input('service_id') : null,
            ],
            'vpsOptions' => $vpsOptions,
            'proxyOptions' => $proxyOptions,
            'serverOptions' => $serverOptions,
            'apiError' => $apiError,
        ]);
    }

    public function show(int $ticketId, Request $request, CloudSunnyApiService $api, CloudSunnyAccountRouter $router)
    {
        $account = $this->resolveAccount($request, $router);
        if (!$account) {
            return redirect()->route('admin.tickets.index')->with('error', 'Chưa cấu hình tài khoản đại lý.');
        }

        try {
            $ticket = $api->forAccount($account)->getTicket($ticketId);

            return Inertia::render('Admin/Tickets/Show', [
                'ticket' => $ticket,
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.tickets.index')
                ->with('error', 'Không tải được chi tiết Ticket: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function store(Request $request, CloudSunnyApiService $api, CloudSunnyAccountRouter $router)
    {
        $account = $this->resolveAccount($request, $router);
        if (!$account) {
            return back()->with('error', 'Chưa có tài khoản đại lý hoạt động.');
        }

        $validated = $request->validate([
            'loai_dich_vu' => 'required|in:vps,proxy,server',
            'dich_vu_id' => 'required|integer|min:1',
            'tieu_de' => 'required|string|min:5|max:150',
            'noi_dung' => 'required|string|min:10|max:5000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'tieu_de.min' => 'Tiêu đề ticket cần tối thiểu 5 ký tự.',
            'noi_dung.min' => 'Nội dung mô tả lỗi cần tối thiểu 10 ký tự.',
            'images.max' => 'Tối đa 5 ảnh đính kèm mỗi lần gửi.',
            'images.*.mimes' => 'Chỉ chấp nhận ảnh định dạng JPG, PNG hoặc WEBP.',
            'images.*.max' => 'Mỗi ảnh tối đa 5 MB.',
        ]);

        $encodedImages = $this->encodeUploadedImages($request);

        try {
            $created = $api->forAccount($account)->createTicket(
                $validated['loai_dich_vu'],
                (int) $validated['dich_vu_id'],
                trim($validated['tieu_de']),
                trim($validated['noi_dung']),
                $encodedImages
            );

            $newId = (int) ($created['id'] ?? 0);
            if ($newId > 0) {
                return redirect()->route('admin.tickets.show', $newId)
                    ->with('success', "Đã tạo Ticket hỗ trợ #{$newId} thành công.");
            }

            return redirect()->route('admin.tickets.index')->with('success', 'Đã gửi Ticket hỗ trợ thành công.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Tạo Ticket thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function reply(int $ticketId, Request $request, CloudSunnyApiService $api, CloudSunnyAccountRouter $router)
    {
        $account = $this->resolveAccount($request, $router);
        if (!$account) {
            return back()->with('error', 'Chưa có tài khoản đại lý hoạt động.');
        }

        $validated = $request->validate([
            'noi_dung' => 'nullable|string|max:5000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $content = trim((string) ($validated['noi_dung'] ?? ''));
        $encodedImages = $this->encodeUploadedImages($request);

        if ($content === '' && empty($encodedImages)) {
            return back()->with('error', 'Vui lòng nhập nội dung phản hồi hoặc đính kèm ít nhất 1 ảnh.');
        }

        try {
            $api->forAccount($account)->replyTicket($ticketId, $content, $encodedImages);

            return back()->with('success', 'Đã gửi phản hồi vào Ticket #' . $ticketId . '.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Gửi phản hồi thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function close(int $ticketId, Request $request, CloudSunnyApiService $api, CloudSunnyAccountRouter $router)
    {
        $account = $this->resolveAccount($request, $router);
        if (!$account) {
            return back()->with('error', 'Chưa có tài khoản đại lý hoạt động.');
        }

        try {
            $api->forAccount($account)->closeTicket($ticketId);

            return back()->with('success', "Đã đóng Ticket #{$ticketId}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Đóng Ticket thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    /**
     * Proxy ảnh đính kèm riêng tư của Ticket (chỉ Admin đăng nhập mới xem được, tuyệt đối không lộ token gốc).
     */
    public function image(int $ticketId, int $messageId, int $index, Request $request, CloudSunnyApiService $api, CloudSunnyAccountRouter $router): Response
    {
        $account = $this->resolveAccount($request, $router);
        if (!$account) {
            abort(404);
        }

        try {
            $data = $api->forAccount($account)->getTicketImage($ticketId, $messageId, $index);
            $mime = (string) ($data['mime'] ?? 'image/png');
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                abort(415);
            }
            $binary = base64_decode((string) ($data['base64'] ?? ''), true);
            if ($binary === false) {
                abort(404);
            }

            return response($binary, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'private, max-age=3600')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    private function resolveAccount(Request $request, CloudSunnyAccountRouter $router): ?CloudSunnyAccount
    {
        return $router->firstActive();
    }

    private function encodeUploadedImages(Request $request): array
    {
        $files = $request->file('images', []);
        if (!is_array($files) || empty($files)) {
            return [];
        }

        $result = [];
        foreach (array_slice($files, 0, 5) as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }
            $bytes = @file_get_contents($file->getRealPath());
            if ($bytes === false) {
                continue;
            }
            $result[] = [
                'ten' => $file->getClientOriginalName() ?: ('attachment.' . $file->extension()),
                'data' => base64_encode($bytes),
            ];
        }

        return $result;
    }
}
