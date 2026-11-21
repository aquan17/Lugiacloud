<?php

namespace App\Services;

use App\Models\CloudSunnyAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CloudSunnyApiService
{
    private ?CloudSunnyAccount $account = null;
    private ?string $token = null;

    /**
     * Che dấu tuyệt đối tên nhà cung cấp gốc, URL, lỗi cURL/HTTP và thông tin số dư đại lý
     * trước khi hiển thị bất kỳ thông báo lỗi nào ra giao diện khách hàng hoặc Reseller API.
     */
    public static function sanitizeErrorMessage(string $message): string
    {
        $raw = trim(strip_tags($message));
        if ($raw === '') {
            return 'Hệ thống máy chủ tạm thời bận. Vui lòng thử lại sau ít phút.';
        }

        // 1. Lỗi số dư / hạn mức tài khoản đại lý bên nhà cung cấp -> tuyệt đối không lộ!
        if (preg_match('/(credit|balance|insufficient|not\s+enough|số\s*dư|không\s*đủ\s*(tiền|số\s*dư)|đại\s*lý|wallet)/iu', $raw)) {
            return 'Cụm máy chủ hiện đang bảo trì hạn mức cấp phát. Vui lòng thử lại sau ít phút hoặc liên hệ hỗ trợ.';
        }

        // 2. Lỗi mạng / cURL / Timeout / DNS / SSL -> không in chuỗi kỹ thuật raw hay domain gốc!
        if (preg_match('/(cURL\s*error|Could\s+not\s+resolve|timed\s*out|timeout|Connection\s+refused|SSL\s+certificate|Guzzle|SQLSTATE|Stack\s+trace)/i', $raw)) {
            return 'Kết nối tới cụm máy chủ đang phản hồi chậm. Vui lòng thử lại sau ít phút.';
        }

        // 3. Lỗi token / auth upstream -> che hoàn toàn
        if (preg_match('/(unauthorized|unauthenticated|invalid\s+token|access_token|api_secret|api_app|HTTP\s*40[13])/i', $raw)) {
            return 'Phiên kết nối hạ tầng đang được làm mới. Vui lòng thử lại sau ít giây.';
        }

        // 4. Lỗi 5xx nội bộ nhà cung cấp
        if (preg_match('/(HTTP\s*5\d{2}|Internal\s+Server\s+Error|Bad\s+Gateway|Service\s+Unavailable)/i', $raw)) {
            return 'Cụm máy chủ đang bảo trì ngắn hạn. Vui lòng thử lại sau ít phút.';
        }

        // 5. Lọc sạch mọi URL & tên thương hiệu nhà cung cấp (mọi đuôi miền .vn/.net/.com...)
        $masked = preg_replace('/https?:\/\/[^\s\)\]]+/i', '', $raw) ?? $raw;
        $masked = preg_replace(
            '/(api\.)?(cloud\s*sunny|cloudsunny|sunny\s*cloud)(\.[a-z]{2,6})?|\bagency\b/iu',
            'LugiaCloud Node',
            $masked
        ) ?? $masked;
        $masked = trim(preg_replace('/\s{2,}/', ' ', $masked) ?? $masked);

        if ($masked === '' || mb_strlen($masked) > 220) {
            return 'Yêu cầu chưa thể thực hiện lúc này. Vui lòng thử lại sau ít phút hoặc liên hệ Zalo hỗ trợ.';
        }

        return $masked;
    }

    public function forAccount(CloudSunnyAccount $account): self
    {
        $clone = clone $this;
        $clone->account = $account;
        $clone->token = $account->access_token;

        return $clone;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function authenticate(?CloudSunnyAccount $account = null): array
    {
        if ($account) {
            $this->account = $account;
        }

        $username = $this->account ? $this->account->api_username : config('cloudsunny.api_username');
        $app = $this->account ? $this->account->api_app : config('cloudsunny.api_app');
        $secret = $this->account ? $this->account->api_secret : config('cloudsunny.api_secret');

        if (!$username || !$app || !$secret) {
            throw new RuntimeException('Chưa cấu hình kết nối tới cụm hạ tầng gốc.');
        }

        $data = $this->postPublic('/agency/get-access-token', [
            'api_username' => $username,
            'api_app' => $app,
            'api_secret' => $secret,
        ]);

        $token = $data['access_token'] ?? null;
        if (!$token) {
            throw new RuntimeException('Không thể xác thực kết nối cụm hạ tầng.');
        }

        $this->token = $token;

        if ($this->account) {
            $this->account->access_token = $token;
            $this->account->refresh_token = $data['refresh_token'] ?? $this->account->refresh_token;
            $this->account->token_expires_at = $data['expires_at'] ?? null;
            $this->account->sync_error = null;
            $this->account->save();
        }

        return $data;
    }

    /**
     * Trả về Webhook Callback URL công khai (chỉ dùng khi APP_URL là HTTPS public, không bao giờ gửi localhost).
     */
    public function publicCallbackUrl(): ?string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl === '' || !str_starts_with(strtolower($appUrl), 'https://')) {
            return null;
        }

        $host = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        if (
            $host === ''
            || $host === 'localhost'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test')
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP) !== false
        ) {
            return null;
        }

        return $appUrl . '/webhooks/cloudsunny';
    }

    public function refreshToken(?CloudSunnyAccount $account = null): array
    {
        if ($account) {
            $this->account = $account;
        }

        $refresh = $this->account?->refresh_token;
        if (!$refresh) {
            return $this->authenticate($this->account);
        }

        $data = $this->postPublic('/agency/refresh-token', [
            'refresh_token' => $refresh,
        ]);

        $token = $data['access_token'] ?? null;
        if (!$token) {
            throw new RuntimeException('Không thể làm mới khóa truy cập hạ tầng.');
        }

        $this->token = $token;

        if ($this->account) {
            $this->account->access_token = $token;
            // Quan trọng: luôn lưu refresh_token MỚI (token cũ bị vô hiệu hóa ngay sau mỗi lần refresh)
            if (!empty($data['refresh_token'])) {
                $this->account->refresh_token = $data['refresh_token'];
            }
            $this->account->token_expires_at = $data['expires_at'] ?? null;
            $this->account->sync_error = null;
            $this->account->save();
        }

        return $data;
    }

    public function getAgencyInfo(): array
    {
        return $this->get('/agency/thong-tin-dai-ly');
    }

    public function checkPrice(string $action, array $params = []): array
    {
        return $this->post('/agency/kiem-tra-gia', array_merge(['action' => $action], $params));
    }

    public function getChangeIpConfig(?int $vpsId = null, bool $forceRefresh = false): array
    {
        if ($vpsId !== null) {
            return $this->get('/agency/cau-hinh-doi-ip-vps', ['id' => $vpsId]);
        }

        $cacheKey = 'cloudsunny_change_ip_cfg_' . ($this->account ? $this->account->id : 'default');
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 300, function () {
            return $this->get('/agency/cau-hinh-doi-ip-vps');
        });
    }

    public function listProducts(bool $forceRefresh = false): array
    {
        $cacheKey = 'cloudsunny_products_' . ($this->account ? $this->account->id : 'default');
        $ttl = (int) config('cloudsunny.cache_ttl', 300);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $ttl, function () {
            return $this->get('/agency/danh-sach-san-pham');
        });
    }

    public function listOperatingSystems(int $productId): array
    {
        $cacheKey = 'cloudsunny_os_' . ($this->account ? $this->account->id : 'default') . '_' . $productId;
        $ttl = (int) config('cloudsunny.cache_ttl', 600);

        return Cache::remember($cacheKey, $ttl, function () use ($productId) {
            return $this->get('/agency/danh-sach-he-dieu-hanh-vps-vn', [
                'product_id' => $productId,
            ]);
        });
    }

    public function listBillingCycles(): array
    {
        $cacheKey = 'cloudsunny_billing_cycles_' . ($this->account ? $this->account->id : 'default');
        $ttl = (int) config('cloudsunny.cache_ttl', 600);

        return Cache::remember($cacheKey, $ttl, function () {
            return $this->get('/agency/danh-sach-thoi-gian-thue');
        });
    }

    public function createVpsOrder(
        int $productId,
        string $billingCycle,
        int $osId,
        int $quantity = 1,
        int $addonCpu = 0,
        int $addonRam = 0,
        int $addonDisk = 0,
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'product_id' => $productId,
            'billing_cycle' => $billingCycle,
            'os' => $osId,
            'quantity' => $quantity,
            'addon_cpu' => $addonCpu,
            'addon_ram' => $addonRam,
            'addon_disk' => $addonDisk,
        ];

        $cb = $callbackUrl ?? $this->publicCallbackUrl();
        if ($cb) {
            $payload['callback_url'] = $cb;
        }

        $data = $this->post('/agency/tao-moi-don-hang-vps', $payload);

        foreach (['vps', 'servers', 'items'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return $data;
    }

    public function listVps(): array
    {
        return $this->get('/agency/danh-sach-vps');
    }

    public function getVps(int $id): array
    {
        return $this->extractInstance($this->get('/agency/chi-tiet-vps', ['id' => $id]));
    }

    public function actionVps(array $ids, string $action, array $extra = [], int $timeout = 45, bool $retryOnNetworkError = false): array
    {
        $callbackSupported = in_array($action, [
            'on', 'off', 'restart', 'renew', 'upgrade', 'confirm_rebuild', 'change-password', 'change-ip', 'extend_disk',
        ], true);

        if ($callbackSupported && !array_key_exists('callback_url', $extra)) {
            $cb = $this->publicCallbackUrl();
            if ($cb) {
                $extra['callback_url'] = $cb;
            }
        }

        return $this->post('/agency/thao-tac-vps', array_merge([
            'ids' => array_values($ids),
            'action' => $action,
        ], $extra), $timeout, $retryOnNetworkError);
    }

    /**
     * Lấy ticket VNC Console (chỉ đọc/tạo phiên, không trừ tiền) — timeout 60s + tự thử lại 1 lần nếu kết nối chập chờn.
     */
    public function consoleVps(int $id): array
    {
        return $this->actionVps([$id], 'console-vps', [], 60, true);
    }

    public function bootVps(int $id): array
    {
        return $this->actionVps([$id], 'on');
    }

    public function shutdownVps(int $id): array
    {
        return $this->actionVps([$id], 'off');
    }

    public function rebootVps(int $id): array
    {
        return $this->actionVps([$id], 'restart');
    }

    public function renewVps(int $id, string $billingCycle): array
    {
        return $this->actionVps([$id], 'renew', [
            'billing_cycle' => $billingCycle,
        ]);
    }

    public function deleteVps(int $id): array
    {
        return $this->actionVps([$id], 'delete');
    }

    public function rebuildVps(int $id, int $osId): array
    {
        return $this->actionVps([$id], 'confirm_rebuild', [
            'os' => $osId,
        ]);
    }

    public function changePasswordVps(int $id, string $passwordOld, string $passwordNew, ?string $passwordNewConfirmation = null): array
    {
        return $this->actionVps([$id], 'change-password', [
            'password_old' => $passwordOld,
            'password_new' => $passwordNew,
            'password_new_confirmation' => $passwordNewConfirmation ?? $passwordNew,
        ]);
    }

    /**
     * Đổi địa chỉ IPv4 cho VPS (có tính phí). CloudSunny kiểm tra mật khẩu đồng bộ (có thể tới ~90s -> timeout 120s).
     */
    public function changeIpVps(int $id, string $passwordVps, ?string $callbackUrl = null): array
    {
        $extra = ['password_vps' => $passwordVps];
        if ($callbackUrl !== null) {
            $extra['callback_url'] = $callbackUrl;
        }

        return $this->actionVps([$id], 'change-ip', $extra, 120);
    }

    /**
     * Mở rộng phân vùng ổ cứng sau khi mua Addon Disk.
     * Lưu ý theo tài liệu CloudSunny: tham số mật khẩu hiện tại có tên là `password_new`, timeout >= 120s.
     */
    public function extendDiskVps(int $id, string $currentPassword, ?string $callbackUrl = null): array
    {
        $extra = ['password_new' => $currentPassword];
        if ($callbackUrl !== null) {
            $extra['callback_url'] = $callbackUrl;
        }

        return $this->actionVps([$id], 'extend_disk', $extra, 120);
    }

    public function upgradeVps(int $id, int $addonCpu, int $addonRam, int $addonDisk = 0): array
    {
        $data = $this->actionVps([$id], 'upgrade', [
            'addon_cpu' => (int) $addonCpu,
            'addon_ram' => (int) $addonRam,
            'addon_disk' => (int) $addonDisk,
        ]);

        if (isset($data['error']) && ($data['error'] === true || $data['error'] === 1)) {
            throw new RuntimeException(self::sanitizeErrorMessage($data['message'] ?? 'Nâng cấp thất bại'));
        }

        return $data;
    }

    public function createProxy(array $data): array
    {
        if (!array_key_exists('callback_url', $data)) {
            $cb = $this->publicCallbackUrl();
            if ($cb) {
                $data['callback_url'] = $cb;
            }
        }

        $response = $this->post('/agency/tao-moi-don-hang-proxy', $data);

        foreach (['proxy', 'proxies', 'items'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return isset($response[$key]['id']) ? [$response[$key]] : $response[$key];
            }
        }

        return isset($response['id']) ? [$response] : $response;
    }

    public function listProxies(): array
    {
        return $this->get('/agency/danh-sach-proxy');
    }

    public function getProxy(int $id): array
    {
        return $this->extractInstance($this->get('/agency/chi-tiet-proxy', ['id' => $id]));
    }

    public function actionProxy(array $ids, string $action, array $extra = []): array
    {
        $callbackSupported = in_array($action, ['renew', 'edit', 'edit-sock5', 'change-type', 'sync-ip'], true);
        if ($callbackSupported && !array_key_exists('callback_url', $extra)) {
            $cb = $this->publicCallbackUrl();
            if ($cb) {
                $extra['callback_url'] = $cb;
            }
        }

        return $this->post('/agency/thao-tac-proxy', array_merge([
            'ids' => array_values($ids),
            'action' => $action,
        ], $extra));
    }

    public function editProxy(int $id, string $username, string $password): array
    {
        return $this->actionProxy([$id], 'edit', [
            'username' => $username,
            'password' => $password,
        ]);
    }

    public function changeProxyType(int $id, string $type): array
    {
        return $this->actionProxy([$id], 'change-type', [
            'type' => strtolower($type) === 'socks5' ? 'socks5' : 'http',
        ]);
    }

    public function syncProxyIp(int $id): array
    {
        return $this->actionProxy([$id], 'sync-ip');
    }

    /**
     * Xem trước số tiền hoàn khi hủy VPS (chỉ dùng nội bộ Admin — không trừ tiền, không hủy).
     */
    public function checkRefundVps(int $id): array
    {
        return $this->actionVps([$id], 'check-refund');
    }

    /**
     * Hủy VPS ngay lập tức và hoàn tiền về số dư đại lý (chỉ dùng nội bộ Admin — không thể hoàn tác).
     */
    public function cancelRefundVps(int $id): array
    {
        return $this->actionVps([$id], 'cancel-refund');
    }

    /**
     * Ticket Hỗ trợ CloudSunny (chỉ dùng nội bộ Admin Backoffice).
     */
    public function listTickets(?string $status = null, int $page = 1): array
    {
        $payload = ['page' => max(1, $page)];
        if ($status !== null && $status !== '' && $status !== 'all') {
            $payload['trang_thai'] = $status;
        }

        return $this->requestRaw('get', '/agency/danh-sach-ticket', $payload);
    }

    public function getTicket(int $id): array
    {
        return $this->get('/agency/chi-tiet-ticket', ['id' => $id]);
    }

    public function createTicket(string $serviceType, int $serviceId, string $title, string $content, array $images = []): array
    {
        $payload = [
            'loai_dich_vu' => $serviceType,
            'dich_vu_id' => $serviceId,
            'tieu_de' => $title,
            'noi_dung' => $content,
        ];
        if (!empty($images)) {
            $payload['anh'] = array_values($images);
        }

        return $this->post('/agency/tao-ticket', $payload, 60);
    }

    public function replyTicket(int $id, string $content = '', array $images = []): array
    {
        $payload = ['id' => $id];
        if (trim($content) !== '') {
            $payload['noi_dung'] = $content;
        }
        if (!empty($images)) {
            $payload['anh'] = array_values($images);
        }

        return $this->post('/agency/phan-hoi-ticket', $payload, 60);
    }

    public function closeTicket(int $id): array
    {
        return $this->post('/agency/dong-ticket', ['id' => $id]);
    }

    public function getTicketImage(int $id, int $messageId, int $index): array
    {
        return $this->get('/agency/anh-ticket', [
            'id' => $id,
            'tin_id' => $messageId,
            'so' => $index,
        ], 45);
    }

    public function deleteProxy(int $id): array
    {
        return $this->actionProxy([$id], 'delete');
    }

    /**
     * Máy chủ vật lý (Dedicated Server) APIs.
     */
    public function createServerOrder(
        int $productId,
        string $billingCycle,
        int $quantity = 1,
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'product_id' => $productId,
            'billing_cycle' => $billingCycle,
            'quantity' => max(1, min(50, $quantity)),
        ];

        $cb = $callbackUrl ?? $this->publicCallbackUrl();
        if ($cb) {
            $payload['callback_url'] = $cb;
        }

        $data = $this->post('/agency/tao-moi-don-hang-server', $payload, 60);

        foreach (['server', 'servers', 'items'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return isset($data[$key]['id']) ? [$data[$key]] : $data[$key];
            }
        }

        return isset($data['id']) ? [$data] : $data;
    }

    public function listServers(): array
    {
        $res = $this->get('/agency/danh-sach-server');
        if (isset($res['data']) && is_array($res['data'])) {
            return $res['data'];
        }

        return is_array($res) ? $res : [];
    }

    public function getServer(int $id): array
    {
        return $this->extractInstance($this->get('/agency/chi-tiet-server', ['id' => $id]));
    }

    public function actionServer(array $ids, string $action, array $extra = []): array
    {
        if ($action === 'renew' && !array_key_exists('callback_url', $extra)) {
            $cb = $this->publicCallbackUrl();
            if ($cb) {
                $extra['callback_url'] = $cb;
            }
        }

        return $this->post('/agency/thao-tac-server', array_merge([
            'ids' => array_values($ids),
            'action' => $action,
        ], $extra), 60);
    }

    public function renewServer(int $id, string $billingCycle): array
    {
        return $this->actionServer([$id], 'renew', [
            'billing_cycle' => $billingCycle,
        ]);
    }

    private function get(string $path, array $query = [], int $timeout = 45): array
    {
        return $this->request('get', $path, $query, $timeout, true);
    }

    private function post(string $path, array $payload = [], int $timeout = 45, bool $retryOnNetworkError = false): array
    {
        return $this->request('post', $path, $payload, $timeout, $retryOnNetworkError);
    }

    private function postPublic(string $path, array $payload): array
    {
        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(15)
            ->timeout(30)
            ->post($this->url($path), $payload);

        return $this->parseResponse($response);
    }

    private function request(string $method, string $path, array $data = [], int $timeout = 45, bool $retryOnNetworkError = false): array
    {
        $response = $this->executeAuthenticatedRequest($method, $path, $data, $timeout, $retryOnNetworkError);

        return $this->parseResponse($response);
    }

    /**
     * Giống request() nhưng giữ nguyên toàn bộ envelope JSON cấp ngoài (dùng cho API có total, perPage, current_page, dem như danh-sach-ticket).
     */
    private function requestRaw(string $method, string $path, array $data = [], int $timeout = 45): array
    {
        $response = $this->executeAuthenticatedRequest($method, $path, $data, $timeout, $method === 'get');
        $json = $response->json();

        if ($response->successful() && is_array($json)) {
            if (($json['error'] ?? 0) === 0) {
                return $json;
            }
            throw new RuntimeException(self::sanitizeErrorMessage($json['message'] ?? $json['msg'] ?? 'Cụm máy chủ từ chối yêu cầu.'));
        }

        throw new RuntimeException(self::sanitizeErrorMessage($json['message'] ?? $json['msg'] ?? 'Lỗi giao tiếp hạ tầng (HTTP ' . $response->status() . ')'));
    }

    private function executeAuthenticatedRequest(string $method, string $path, array $data = [], int $timeout = 45, bool $retryOnNetworkError = false)
    {
        $token = $this->validToken();

        $send = function (string $bearer) use ($method, $path, $data, $timeout) {
            $pending = Http::withToken($bearer)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(15)
                ->timeout($timeout);

            return $method === 'get'
                ? $pending->withBody(json_encode($data), 'application/json')->get($this->url($path))
                : $pending->post($this->url($path), $data);
        };

        $maxAttempts = $retryOnNetworkError ? 2 : 1;
        $response = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $send($token);
            } catch (ConnectionException $e) {
                Log::warning('Upstream API Connection Exception', [
                    'path' => $path,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $maxAttempts) {
                    usleep(400_000);
                    continue;
                }
                throw $e;
            }

            if ($attempt < $maxAttempts && in_array($response->status(), [502, 503, 504], true)) {
                Log::warning('Upstream API transient 5xx, retrying once', [
                    'path' => $path,
                    'status' => $response->status(),
                ]);
                usleep(400_000);
                continue;
            }

            break;
        }

        // Chỉ refresh/auth lại khi nhận HTTP 401 (hết hạn token). Không retry 403 (tài khoản đại lý bị khóa).
        if ($response->status() === 401 && $this->account) {
            try {
                if (!empty($this->account->refresh_token)) {
                    $this->refreshToken($this->account);
                } else {
                    $this->authenticate($this->account);
                }
            } catch (\Throwable $e) {
                $this->authenticate($this->account);
            }

            $response = $send((string) $this->token);
        }

        $json = $response->json();
        $isApiError = is_array($json) && (($json['error'] ?? 0) !== 0 || (isset($json['data']['error']) && ($json['data']['error'] === true || $json['data']['error'] === 1)));

        if (!$response->successful() || $isApiError) {
            Log::warning('Upstream API Error Response', [
                'url' => $this->url($path),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    private function validToken(): string
    {
        if ($this->account) {
            $needsRefresh = !$this->account->access_token
                || ($this->account->token_expires_at && $this->account->token_expires_at->copy()->subMinutes(5)->isPast());

            if ($needsRefresh) {
                if (!empty($this->account->refresh_token)) {
                    try {
                        $this->refreshToken($this->account);
                    } catch (\Throwable $e) {
                        $this->authenticate($this->account);
                    }
                } else {
                    $this->authenticate($this->account);
                }
            }
        }

        if (!$this->token) {
            $this->authenticate($this->account);
        }

        if (!$this->token) {
            throw new RuntimeException('Không thể lấy khóa truy cập hạ tầng.');
        }

        return $this->token;
    }

    private function parseResponse($response): array
    {
        $json = $response->json();

        if ($response->successful() && is_array($json)) {
            if (($json['error'] ?? 0) === 0) {
                $data = $json['data'] ?? [];

                if (is_array($data) && isset($data['error']) && ($data['error'] === true || $data['error'] === 1)) {
                    throw new RuntimeException(self::sanitizeErrorMessage($data['message'] ?? $data['msg'] ?? 'Lỗi xử lý tại cụm máy chủ.'));
                }

                return $data;
            }

            throw new RuntimeException(self::sanitizeErrorMessage($json['message'] ?? $json['msg'] ?? 'Cụm máy chủ từ chối yêu cầu.'));
        }

        throw new RuntimeException(self::sanitizeErrorMessage($json['message'] ?? $json['msg'] ?? 'Lỗi giao tiếp hạ tầng (HTTP ' . $response->status() . ')'));
    }

    private function url(string $path): string
    {
        return rtrim(config('cloudsunny.api_base'), '/') . '/' . ltrim($path, '/');
    }

    private function extractInstance(array $data): array
    {
        foreach (['vps', 'proxy', 'server', 'item', 'detail', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->extractInstance($data[$key]);
            }
        }

        if (isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return $data;
    }
}
