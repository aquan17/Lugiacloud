export type CodeLang = 'curl' | 'php' | 'nodejs' | 'python';

export interface ApiParamSpec {
    name: string;
    location: 'body' | 'path';
    type: string;
    required: boolean;
    desc: string;
}

export interface ApiEndpointSpec {
    id: string;
    method: 'GET' | 'POST';
    isDestructive?: boolean;
    path: string;
    httpSuccess: string;
    title: string;
    summary: string;
    params?: ApiParamSpec[];
    requestJson?: string;
    responseJson: string;
    note?: string;
}

export interface DocNavItem {
    id: string;
    label: string;
    badge?: string;
    badgeTone?: 'get' | 'post' | 'delete' | 'hook' | 'code';
}

export interface DocGroupSpec {
    groupTitle: string;
    items: DocNavItem[];
}

export interface HttpErrorSpec {
    code: string;
    tone: 'emerald' | 'amber' | 'rose' | 'slate';
    name: string;
    desc: string;
    sample: string;
}

export const CODE_LANGUAGES: Array<{ id: CodeLang; label: string }> = [
    { id: 'curl', label: 'cURL' },
    { id: 'php', label: 'PHP' },
    { id: 'nodejs', label: 'Node.js' },
    { id: 'python', label: 'Python' },
];

export const DOC_GROUPS: DocGroupSpec[] = [
    {
        groupTitle: 'Bắt đầu tích hợp',
        items: [
            { id: 'intro', label: 'Tổng quan & Xác thực' },
            { id: 'get-profile', label: 'Thông tin & Số dư ví', badge: 'GET', badgeTone: 'get' },
            { id: 'get-plans', label: 'Danh mục Gói VPS & OS', badge: 'GET', badgeTone: 'get' },
        ],
    },
    {
        groupTitle: 'Khởi tạo & Nhận VPS',
        items: [
            { id: 'post-create', label: 'Tạo mới VPS (Async)', badge: 'POST', badgeTone: 'post' },
            { id: 'get-status', label: 'Kiểm tra trạng thái & IP', badge: 'GET', badgeTone: 'get' },
            { id: 'webhook', label: 'Webhook Trả về', badge: 'HOOK', badgeTone: 'hook' },
        ],
    },
    {
        groupTitle: 'Quản trị vòng đời VPS',
        items: [
            { id: 'post-action', label: 'Bật / Tắt / Khởi động lại', badge: 'POST', badgeTone: 'post' },
            { id: 'post-renew', label: 'Gia hạn thời gian', badge: 'POST', badgeTone: 'post' },
            { id: 'post-rebuild', label: 'Cài lại Hệ điều hành', badge: 'POST', badgeTone: 'post' },
            { id: 'post-upgrade', label: 'Nâng cấp CPU / RAM / SSD', badge: 'POST', badgeTone: 'post' },
            { id: 'post-password', label: 'Đổi mật khẩu OS', badge: 'POST', badgeTone: 'post' },
            { id: 'post-change-ip', label: 'Đổi địa chỉ IPv4', badge: 'POST', badgeTone: 'post' },
            { id: 'post-extend-disk', label: 'Mở rộng phân vùng SSD', badge: 'POST', badgeTone: 'post' },
            { id: 'post-delete', label: 'Xóa & Thu hồi VPS', badge: 'POST', badgeTone: 'delete' },
        ],
    },
    {
        groupTitle: 'Dịch vụ Proxy IPv4',
        items: [
            { id: 'get-proxy-plans', label: 'Danh mục Gói Proxy', badge: 'GET', badgeTone: 'get' },
            { id: 'post-proxy-create', label: 'Mua mới Proxy IPv4', badge: 'POST', badgeTone: 'post' },
            { id: 'get-proxy-status', label: 'Chi tiết & Trạng thái Proxy', badge: 'GET', badgeTone: 'get' },
            { id: 'post-proxy-renew', label: 'Gia hạn Proxy (5 chu kỳ)', badge: 'POST', badgeTone: 'post' },
            { id: 'post-proxy-credentials', label: 'Đổi User / Pass Proxy', badge: 'POST', badgeTone: 'post' },
            { id: 'post-proxy-change-type', label: 'Đổi HTTP ↔ SOCKS5', badge: 'POST', badgeTone: 'post' },
            { id: 'post-proxy-sync-ip', label: 'Đổi IP mới (Dân cư)', badge: 'POST', badgeTone: 'post' },
            { id: 'post-proxy-delete', label: 'Hủy Proxy', badge: 'POST', badgeTone: 'delete' },
        ],
    },
    {
        groupTitle: 'Tra cứu xử lý lỗi',
        items: [
            { id: 'errors', label: 'Bảng Mã lỗi HTTP', badge: 'CODE', badgeTone: 'code' },
        ],
    },
];

export function buildEndpointSpecs(baseUrl: string, discountPercent: number): ApiEndpointSpec[] {
    return [
        {
            id: 'get-profile',
            method: 'GET',
            path: '/profile',
            httpSuccess: '200 OK',
            title: 'Truy xuất thông tin tài khoản & Số dư ví Đại lý',
            summary:
                'Kiểm tra số dư khả dụng (VNĐ), tỷ lệ chiết khấu Đại lý hiện tại và tổng số máy chủ đang hoạt động trước khi gửi lệnh mua mới.',
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    data: {
                        email: 'reseller@domain.com',
                        balance: 5500000,
                        vps_active: 12,
                        discount_percent: discountPercent || 10,
                        created_at: '2026-01-15T08:30:00+07:00',
                    },
                },
                null,
                2
            ),
        },
        {
            id: 'get-plans',
            method: 'GET',
            path: '/plans',
            httpSuccess: '200 OK',
            title: 'Lấy danh mục Gói cấu hình & Mã Hệ điều hành (OS ID)',
            summary:
                'Trả về toàn bộ danh sách gói Cloud VPS Việt Nam (đã tính sẵn đơn giá sau chiết khấu của riêng tài khoản Đại lý) cùng danh sách mã os_id hợp lệ để đồng bộ sang website của bạn.',
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    data: {
                        location: 'Vietnam (VN)',
                        os_list: [
                            { id: 7, name: 'Linux Ubuntu-22.04', type: 'linux' },
                            { id: 15, name: 'Windows Server 2022', type: 'windows' },
                        ],
                        plans: [
                            {
                                id: 1,
                                slug: 'lg-1',
                                name: 'Cloud LG-1',
                                cpu: 1,
                                ram: 1,
                                disk: 20,
                                price_monthly: 50000,
                                list_price_monthly: 50000,
                            },
                            {
                                id: 4,
                                slug: 'lg-8',
                                name: 'Cloud LG-8',
                                cpu: 4,
                                ram: 8,
                                disk: 50,
                                price_monthly: 220000,
                                list_price_monthly: 220000,
                            },
                        ],
                    },
                },
                null,
                2
            ),
        },
        {
            id: 'post-create',
            method: 'POST',
            path: '/vps/create',
            httpSuccess: '202 Accepted',
            title: 'Khởi tạo máy chủ Cloud VPS mới (Bất đồng bộ — Async)',
            summary:
                'Khấu trừ số dư ví Đại lý và đưa yêu cầu cấp phát vào hàng đợi tự động. Hệ thống phản hồi tức thì mã order_id (HTTP 202); sau khoảng 2–5 phút khi cài xong OS sẽ tự động bắn kết quả về Webhook hoặc truy vấn qua API /status.',
            params: [
                {
                    name: 'plan_id',
                    location: 'body',
                    type: 'string | integer',
                    required: true,
                    desc: 'Mã slug gói (khuyến nghị dùng "lg-1" .. "lg-32") hoặc ID số lấy từ API GET /plans.',
                },
                {
                    name: 'os_id',
                    location: 'body',
                    type: 'integer',
                    required: true,
                    desc: 'Mã Hệ điều hành lấy từ mảng os_list của API GET /plans (VD: 7 = Ubuntu 22.04, 15 = Windows Server 2022).',
                },
                {
                    name: 'duration',
                    location: 'body',
                    type: 'integer',
                    required: true,
                    desc: 'Chu kỳ thuê tính theo tháng. Chấp nhận: 1, 2, 3, 6 hoặc 12.',
                },
                {
                    name: 'hostname',
                    location: 'body',
                    type: 'string',
                    required: false,
                    desc: 'Tên định danh máy chủ (tối đa 64 ký tự). Nếu bỏ trống, hệ thống tự sinh theo dạng server-<timestamp>.',
                },
            ],
            requestJson: JSON.stringify(
                {
                    plan_id: 'lg-8',
                    os_id: 7,
                    duration: 1,
                    hostname: 'khach-hang-web-01',
                },
                null,
                2
            ),
            responseJson: JSON.stringify(
                {
                    status: 202,
                    message: 'VPS đang được khởi tạo. Hệ thống sẽ tự động gọi Webhook khi hoàn tất.',
                    data: {
                        order_id: '1763452890123',
                        amount_deducted: 198000,
                        balance_left: 5302000,
                        status: 'processing',
                        status_url: `${baseUrl}/vps/1763452890123/status`,
                    },
                },
                null,
                2
            ),
            note: 'Cơ chế bảo vệ ví Đại lý: Nếu cụm vật lý phát sinh lỗi cấp phát, hệ thống tự động hoàn trả 100% số tiền vào ví (Idempotent Refund) và bắn sự kiện vps.failed qua Webhook.',
        },
        {
            id: 'get-status',
            method: 'GET',
            path: '/vps/{order_id}/status',
            httpSuccess: '200 OK',
            title: 'Kiểm tra tiến độ cài đặt & Lấy IP / Mật khẩu VPS',
            summary:
                'Sử dụng khi bạn chưa cấu hình Webhook hoặc muốn làm nút "Đồng bộ trạng thái" cho khách hàng. Khuyến nghị gọi kiểm tra cách nhau mỗi 15–30 giây cho tới khi trường data.status chuyển sang "active".',
            params: [
                {
                    name: 'order_id',
                    location: 'path',
                    type: 'string',
                    required: true,
                    desc: 'Mã đơn hàng nhận được từ API POST /vps/create, truyền trực tiếp trên đường dẫn URL.',
                },
            ],
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    data: {
                        order_id: '1763452890123',
                        status: 'active',
                        vps_info: {
                            ip_address: '103.22.33.44',
                            username: 'root',
                            password: 'LugiaStrongPass99',
                            os_name: 'Linux Ubuntu-22.04',
                            created_at: '2026-11-18T15:10:00+07:00',
                            expired_at: '2026-12-18T15:10:00+07:00',
                        },
                    },
                },
                null,
                2
            ),
            note: 'Các trạng thái của data.status: "processing" (đang cài đặt OS, lúc này vps_info = null) · "active" (đang chạy ổn định) · "stopped" (đang tắt nguồn) · "failed" (cấp phát lỗi, đã hoàn tiền).',
        },
        {
            id: 'post-action',
            method: 'POST',
            path: '/vps/{order_id}/action',
            httpSuccess: '200 OK',
            title: 'Điều khiển nguồn máy chủ (Start / Stop / Restart)',
            summary: 'Gửi lệnh bật nguồn, tắt nguồn hoặc khởi động lại cứng (Hard Reboot) trực tiếp tới máy chủ ảo của khách.',
            params: [
                {
                    name: 'order_id',
                    location: 'path',
                    type: 'string',
                    required: true,
                    desc: 'Mã order_id của máy chủ cần điều khiển nguồn.',
                },
                {
                    name: 'action',
                    location: 'body',
                    type: 'string',
                    required: true,
                    desc: 'Lệnh nguồn cần thực thi. Chỉ nhận một trong 3 giá trị: "start", "stop" hoặc "restart".',
                },
            ],
            requestJson: JSON.stringify({ action: 'restart' }, null, 2),
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    message: 'Lệnh đã được thực thi.',
                },
                null,
                2
            ),
        },
        {
            id: 'post-renew',
            method: 'POST',
            path: '/vps/{order_id}/renew',
            httpSuccess: '200 OK',
            title: 'Gia hạn thời gian sử dụng VPS',
            summary:
                'Tự động tính phí gia hạn (bao gồm cả cấu hình Addon vCPU/RAM/SSD đã mua thêm nếu có), áp dụng chiết khấu Đại lý và cộng nối tiếp vào ngày hết hạn hiện tại.',
            params: [
                {
                    name: 'order_id',
                    location: 'path',
                    type: 'string',
                    required: true,
                    desc: 'Mã order_id của máy chủ cần gia hạn.',
                },
                {
                    name: 'duration',
                    location: 'body',
                    type: 'integer',
                    required: true,
                    desc: 'Số tháng gia hạn thêm. Chỉ nhận: 1, 3, 6 hoặc 12.',
                },
            ],
            requestJson: JSON.stringify({ duration: 3 }, null, 2),
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    message: 'Gia hạn thành công.',
                    data: {
                        expired_at: '2027-03-18T15:10:00+07:00',
                    },
                },
                null,
                2
            ),
        },
        {
            id: 'post-rebuild',
            method: 'POST',
            path: '/vps/{order_id}/rebuild',
            httpSuccess: '200 OK',
            title: 'Cài đặt lại Hệ điều hành (Rebuild / Reinstall OS)',
            summary:
                'Format toàn bộ ổ cứng SSD và cài mới lại Hệ điều hành khác cho máy chủ. Sau khoảng 2–5 phút, mật khẩu mới sẽ được cập nhật tại API GET /vps/{order_id}/status.',
            params: [
                {
                    name: 'order_id',
                    location: 'path',
                    type: 'string',
                    required: true,
                    desc: 'Mã order_id của máy chủ cần cài đặt lại Hệ điều hành.',
                },
                {
                    name: 'os_id',
                    location: 'body',
                    type: 'integer',
                    required: true,
                    desc: 'Mã Hệ điều hành mới lấy từ API GET /plans.',
                },
            ],
            requestJson: JSON.stringify({ os_id: 15 }, null, 2),
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    message: 'Đã gửi yêu cầu cài lại OS.',
                },
                null,
                2
            ),
            note: 'Lưu ý quan trọng: Thao tác cài lại OS sẽ xóa vĩnh viễn toàn bộ dữ liệu hiện có trên máy chủ và không thể khôi phục.',
        },
        {
            id: 'post-upgrade',
            method: 'POST',
            path: '/vps/{order_id}/upgrade',
            httpSuccess: '200 OK',
            title: 'Nâng cấp thêm phần cứng giữa kỳ (vCPU / RAM / SSD)',
            summary:
                'Cộng thêm tài nguyên vCPU, RAM hoặc dung lượng ổ cứng SSD mà không làm mất dữ liệu hay đổi IP. Hệ thống tự động tính phí bù theo tỷ lệ số ngày còn lại của chu kỳ hiện tại.',
            params: [
                {
                    name: 'order_id',
                    location: 'path',
                    type: 'string',
                    required: true,
                    desc: 'Mã order_id của máy chủ cần nâng cấp cấu hình.',
                },
                {
                    name: 'addon_cpu',
                    location: 'body',
                    type: 'integer',
                    required: false,
                    desc: 'Số nhân vCPU muốn cộng thêm (từ 0 đến 16 Core).',
                },
                {
                    name: 'addon_ram',
                    location: 'body',
                    type: 'integer',
                    required: false,
                    desc: 'Dung lượng RAM muốn cộng thêm tính theo GB (từ 0 đến 64 GB).',
                },
                {
                    name: 'addon_disk',
                    location: 'body',
                    type: 'integer',
                    required: false,
                    desc: 'Dung lượng SSD cộng thêm (GB) — bắt buộc là bội số của 10 (VD: 10, 20, 50, 100).',
                },
            ],
            requestJson: JSON.stringify(
                {
                    addon_cpu: 2,
                    addon_ram: 4,
                    addon_disk: 20,
                },
                null,
                2
            ),
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    message: 'Đã gửi yêu cầu nâng cấp VPS.',
                },
                null,
                2
            ),
        },
        {
            id: 'post-password',
            method: 'POST',
            path: '/vps/{order_id}/password',
            httpSuccess: '200 OK',
            title: 'Đổi mật khẩu Hệ điều hành (Root / Administrator)',
            summary: 'Đổi mật khẩu truy cập OS trực tiếp cho VPS đang hoạt động. Nếu bỏ trống password_old, hệ thống dùng mật khẩu đã lưu gần nhất.',
            params: [
                { name: 'order_id', location: 'path', type: 'string', required: true, desc: 'Mã order_id của máy chủ.' },
                { name: 'password_old', location: 'body', type: 'string', required: false, desc: 'Mật khẩu hiện tại của VPS (tùy chọn nếu chưa tự đổi trong OS).' },
                { name: 'password_new', location: 'body', type: 'string', required: true, desc: 'Mật khẩu mới từ 12 ký tự trở lên, gồm chữ hoa, chữ thường và chữ số (không ký tự đặc biệt).' },
            ],
            requestJson: JSON.stringify({ password_new: 'LugiaNewPass2026' }, null, 2),
            responseJson: JSON.stringify({ status: 'success', message: 'Đã cập nhật mật khẩu VPS thành công.' }, null, 2),
        },
        {
            id: 'post-change-ip',
            method: 'POST',
            path: '/vps/{order_id}/change-ip',
            httpSuccess: '200 OK',
            title: 'Đổi địa chỉ IPv4 mới cho VPS (Có tính phí)',
            summary: 'Yêu cầu cấp địa chỉ IPv4 mới cho máy chủ đang hoạt động (trừ phí theo mục addons.change_ip trong GET /plans). IP mới cập nhật sau 2–5 phút qua GET /vps/{order_id}/status.',
            params: [
                { name: 'order_id', location: 'path', type: 'string', required: true, desc: 'Mã order_id của máy chủ đang chạy.' },
                { name: 'password_vps', location: 'body', type: 'string', required: false, desc: 'Mật khẩu hiện tại của VPS để xác thực (bỏ trống sẽ dùng mật khẩu đã lưu).' },
            ],
            requestJson: JSON.stringify({ password_vps: 'LugiaStrongPass99' }, null, 2),
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    message: 'Yêu cầu đổi IPv4 đã được tiếp nhận. IP mới sẽ cập nhật sau 2–5 phút qua GET /vps/{order_id}/status.',
                    data: { order_id: '1763452890123', amount_deducted: 30000, status: 'processing' },
                },
                null,
                2
            ),
            note: 'Hệ thống kiểm tra mật khẩu đồng bộ (có thể mất 10–90 giây), nên đặt HTTP timeout >= 120 giây. Lỗi cấp phát tự động hoàn 100% tiền về ví Đại lý.',
        },
        {
            id: 'post-extend-disk',
            method: 'POST',
            path: '/vps/{order_id}/extend-disk',
            httpSuccess: '200 OK',
            title: 'Mở rộng phân vùng ổ cứng SSD (Miễn phí)',
            summary: 'Tự động gộp phần dung lượng SSD mới mua thêm qua /upgrade vào phân vùng chính của Windows/Linux mà không làm mất dữ liệu.',
            params: [
                { name: 'order_id', location: 'path', type: 'string', required: true, desc: 'Mã order_id của máy chủ.' },
                { name: 'password_vps', location: 'body', type: 'string', required: false, desc: 'Mật khẩu hiện tại của VPS (bỏ trống sẽ dùng mật khẩu đã lưu).' },
            ],
            requestJson: JSON.stringify({ password_vps: 'LugiaStrongPass99' }, null, 2),
            responseJson: JSON.stringify(
                { status: 'success', message: 'Đã gửi lệnh mở rộng phân vùng SSD. Máy chủ sẽ tạm bảo trì dưới 5 phút rồi tự bật lại.' },
                null,
                2
            ),
        },
        {
            id: 'post-delete',
            method: 'POST',
            isDestructive: true,
            path: '/vps/{order_id}/delete',
            httpSuccess: '200 OK',
            title: 'Hủy & Thu hồi vĩnh viễn máy chủ VPS',
            summary: 'Ngắt nguồn máy chủ trên cụm hạ tầng và đánh dấu hủy dịch vụ khỏi danh sách hoạt động của Đại lý.',
            params: [
                {
                    name: 'order_id',
                    location: 'path',
                    type: 'string',
                    required: true,
                    desc: 'Mã order_id của máy chủ cần hủy dịch vụ.',
                },
            ],
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    message: 'Đã xóa VPS thành công.',
                },
                null,
                2
            ),
            note: 'Lưu ý: Hủy dịch vụ trước thời hạn sẽ không hoàn lại chi phí của số ngày còn dư trong chu kỳ.',
        },
        // --- NHÓM PROXY IPv4 ---
        {
            id: 'get-proxy-plans',
            method: 'GET',
            path: '/proxy/plans',
            httpSuccess: '200 OK',
            title: 'Lấy danh mục Gói Proxy IPv4 & Bảng giá 5 chu kỳ',
            summary: 'Trả về danh sách gói Proxy Datacenter & Proxy Dân cư VN kèm đơn giá sau chiết khấu Proxy của tài khoản Đại lý theo đủ 5 chu kỳ thuê.',
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    data: {
                        cycles: ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually'],
                        plans: [
                            {
                                id: 12,
                                slug: 'proxy-12',
                                name: 'Proxy Datacenter — Shared',
                                category: 'datacenter',
                                dedicated: false,
                                protocols: ['HTTP', 'SOCKS5'],
                                country: 'VN',
                                price_monthly: 15000,
                                cycle_prices: { monthly: 15000, twomonthly: 29400, quarterly: 43650, semi_annually: 85500, annually: 165600 },
                            },
                        ],
                    },
                },
                null,
                2
            ),
        },
        {
            id: 'post-proxy-create',
            method: 'POST',
            path: '/proxy/create',
            httpSuccess: '201 Created',
            title: 'Mua mới Proxy IPv4 (1–10 IP / đơn, 5 chu kỳ)',
            summary: 'Kiểm tra tồn kho trước khi trừ ví, khởi tạo từ 1 đến 10 Proxy IPv4 HTTP hoặc SOCKS5 và trả về danh sách proxy_id cùng thông tin kết nối.',
            params: [
                { name: 'product_id', location: 'body', type: 'integer', required: true, desc: 'ID gói Proxy từ API GET /proxy/plans (VD: 12, 13, 14, 15).' },
                { name: 'billing_cycle', location: 'body', type: 'string', required: false, desc: 'Chu kỳ: monthly, twomonthly, quarterly, semi_annually, annually (hoặc dùng duration: 1, 2, 3, 6, 12).' },
                { name: 'quantity', location: 'body', type: 'integer', required: true, desc: 'Số lượng IP cần mua (1 – 10).' },
                { name: 'type', location: 'body', type: 'string', required: true, desc: 'Giao thức khởi tạo: "HTTP" hoặc "SOCKS5".' },
                { name: 'username', location: 'body', type: 'string', required: false, desc: 'Từ 5 – 31 ký tự, chỉ chữ cái và chữ số (bỏ trống sẽ tự sinh ngẫu nhiên).' },
                { name: 'password', location: 'body', type: 'string', required: false, desc: 'Từ 5 – 31 ký tự, chỉ chữ cái và chữ số (bỏ trống sẽ tự sinh ngẫu nhiên).' },
            ],
            requestJson: JSON.stringify(
                { product_id: 12, billing_cycle: 'monthly', quantity: 2, type: 'HTTP', username: 'reseller01', password: 'PassWord2026' },
                null,
                2
            ),
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    message: 'Đã tạo thành công 2 Proxy HTTP.',
                    data: {
                        amount_deducted: 30000,
                        balance_left: 5470000,
                        proxies: [
                            {
                                proxy_id: 335,
                                product_id: 12,
                                protocol: 'HTTP',
                                ip: '180.93.198.69',
                                port: '41023',
                                username: 'reseller01',
                                password: 'PassWord2026',
                                connection_string: '180.93.198.69:41023:reseller01:PassWord2026',
                                status: 'active',
                                expired_at: '2026-12-21T13:00:00+07:00',
                            },
                        ],
                    },
                },
                null,
                2
            ),
        },
        {
            id: 'get-proxy-status',
            method: 'GET',
            path: '/proxy/{proxy_id}/status',
            httpSuccess: '200 OK',
            title: 'Lấy trạng thái & Thông tin kết nối Proxy',
            summary: 'Đồng bộ và trả về IP, Port, giao thức, tài khoản xác thực và thời hạn hiện tại của Proxy.',
            params: [{ name: 'proxy_id', location: 'path', type: 'integer', required: true, desc: 'Mã proxy_id nhận được từ POST /proxy/create.' }],
            responseJson: JSON.stringify(
                {
                    status: 'success',
                    data: {
                        proxy_id: 335,
                        product_id: 12,
                        protocol: 'HTTP',
                        ip: '180.93.198.69',
                        port: '41023',
                        username: 'reseller01',
                        password: 'PassWord2026',
                        connection_string: '180.93.198.69:41023:reseller01:PassWord2026',
                        status: 'active',
                        expired_at: '2026-12-21T13:00:00+07:00',
                    },
                },
                null,
                2
            ),
        },
        {
            id: 'post-proxy-renew',
            method: 'POST',
            path: '/proxy/{proxy_id}/renew',
            httpSuccess: '200 OK',
            title: 'Gia hạn Proxy IPv4 theo 5 chu kỳ',
            summary: 'Gia hạn thêm 1, 2, 3, 6 hoặc 12 tháng cho Proxy đang hoạt động.',
            params: [
                { name: 'proxy_id', location: 'path', type: 'integer', required: true, desc: 'Mã proxy_id cần gia hạn.' },
                { name: 'billing_cycle', location: 'body', type: 'string', required: false, desc: 'monthly, twomonthly, quarterly, semi_annually, annually (hoặc truyền duration: 1, 2, 3, 6, 12).' },
            ],
            requestJson: JSON.stringify({ billing_cycle: 'quarterly' }, null, 2),
            responseJson: JSON.stringify(
                { status: 'success', message: 'Gia hạn Proxy thành công.', data: { proxy_id: 335, amount_deducted: 43650, expired_at: '2027-03-21T13:00:00+07:00' } },
                null,
                2
            ),
        },
        {
            id: 'post-proxy-credentials',
            method: 'POST',
            path: '/proxy/{proxy_id}/credentials',
            httpSuccess: '200 OK',
            title: 'Đổi tài khoản & mật khẩu Proxy',
            summary: 'Cập nhật Username và Password xác thực trực tiếp mà không đổi IP hay Port.',
            params: [
                { name: 'proxy_id', location: 'path', type: 'integer', required: true, desc: 'Mã proxy_id cần đổi mật khẩu.' },
                { name: 'username', location: 'body', type: 'string', required: true, desc: 'Username mới (5 – 100 ký tự, gồm chữ, số, _).' },
                { name: 'password', location: 'body', type: 'string', required: true, desc: 'Mật khẩu mới (5 – 100 ký tự, gồm chữ, số, _).' },
            ],
            requestJson: JSON.stringify({ username: 'newuser01', password: 'NewPass2026' }, null, 2),
            responseJson: JSON.stringify({ status: 'success', message: 'Đã cập nhật tài khoản & mật khẩu Proxy.' }, null, 2),
        },
        {
            id: 'post-proxy-change-type',
            method: 'POST',
            path: '/proxy/{proxy_id}/change-type',
            httpSuccess: '200 OK',
            title: 'Chuyển đổi giao thức Proxy (HTTP ↔ SOCKS5)',
            summary: 'Đổi giao thức hoạt động của Proxy sang HTTP hoặc SOCKS5 và tự đồng bộ lại Port mới.',
            params: [
                { name: 'proxy_id', location: 'path', type: 'integer', required: true, desc: 'Mã proxy_id cần đổi giao thức.' },
                { name: 'type', location: 'body', type: 'string', required: true, desc: 'Giao thức mới: "HTTP" hoặc "SOCKS5" (phải khác loại hiện tại).' },
            ],
            requestJson: JSON.stringify({ type: 'SOCKS5' }, null, 2),
            responseJson: JSON.stringify({ status: 'success', message: 'Đã chuyển giao thức Proxy sang SOCKS5.' }, null, 2),
        },
        {
            id: 'post-proxy-sync-ip',
            method: 'POST',
            path: '/proxy/{proxy_id}/sync-ip',
            httpSuccess: '200 OK',
            title: 'Cấp địa chỉ IP mới cho Proxy Dân cư (Residential)',
            summary: 'Yêu cầu cấp địa chỉ IP mới (miễn phí). Chỉ áp dụng cho dòng Proxy Dân cư (Residential); sau 5–10 giây gọi GET /proxy/{proxy_id}/status để lấy IP mới.',
            params: [{ name: 'proxy_id', location: 'path', type: 'integer', required: true, desc: 'Mã proxy_id thuộc dòng Proxy Dân cư.' }],
            responseJson: JSON.stringify(
                { status: 'success', message: 'Đã gửi yêu cầu cấp IP mới. Gọi GET /proxy/{proxy_id}/status sau 5–10 giây để nhận IP mới.' },
                null,
                2
            ),
        },
        {
            id: 'post-proxy-delete',
            method: 'POST',
            isDestructive: true,
            path: '/proxy/{proxy_id}/delete',
            httpSuccess: '200 OK',
            title: 'Hủy dịch vụ Proxy IPv4',
            summary: 'Tắt tự động gia hạn và đánh dấu hủy Proxy khỏi danh sách hoạt động.',
            params: [{ name: 'proxy_id', location: 'path', type: 'integer', required: true, desc: 'Mã proxy_id cần hủy.' }],
            responseJson: JSON.stringify({ status: 'success', message: 'Đã hủy Proxy thành công.' }, null, 2),
        },
    ];
}

function jsonToPhpArray(jsonStr?: string): string {
    if (!jsonStr) return '[]';
    try {
        const obj = JSON.parse(jsonStr) as Record<string, unknown>;
        const entries = Object.entries(obj);
        if (entries.length === 0) return '[]';
        const lines = entries.map(([k, v]) => {
            const val = typeof v === 'string' ? `'${v}'` : String(v);
            return `    '${k}' => ${val},`;
        });
        return `[\n${lines.join('\n')}\n]`;
    } catch {
        return '[]';
    }
}

export function generateQuickstartSnippet(baseUrl: string, token: string, lang: CodeLang): string {
    if (lang === 'curl') {
        return `# Gọi thử API kiểm tra kết nối & số dư ví Đại lý
curl -X GET "${baseUrl}/profile" \\
  -H "Authorization: Bearer ${token}" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json"`;
    }

    if (lang === 'php') {
        return `<?php
// Hàm dùng chung cho toàn bộ dự án PHP / Laravel / WordPress của bạn
function callLugiaApi(string $method, string $endpoint, array $data = []): array
{
    $baseUrl = "${baseUrl}";
    $apiKey  = "${token}";

    $ch = curl_init($baseUrl . $endpoint);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$apiKey}",
            "Accept: application/json",
            "Content-Type: application/json",
        ],
    ];

    if (!empty($data)) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $options);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['http_code' => $code, 'body' => json_decode((string) $raw, true)];
}

// Gọi thử kiểm tra số dư ví:
$res = callLugiaApi('GET', '/profile');
print_r($res);`;
    }

    if (lang === 'nodejs') {
        return `// Hàm helper dùng chung cho dự án Node.js / Next.js / NestJS (Node 18+)
const LUGIA_BASE_URL = "${baseUrl}";
const LUGIA_API_KEY = "${token}";

export async function callLugiaApi(method, endpoint, payload = null) {
  const options = {
    method,
    headers: {
      "Authorization": \`Bearer \${LUGIA_API_KEY}\`,
      "Accept": "application/json",
      "Content-Type": "application/json",
    },
  };

  if (payload) {
    options.body = JSON.stringify(payload);
  }

  const res = await fetch(\`\${LUGIA_BASE_URL}\${endpoint}\`, options);
  const data = await res.json();
  return { status: res.status, data };
}

// Gọi thử kiểm tra số dư ví:
console.log(await callLugiaApi("GET", "/profile"));`;
    }

    return `# Hàm helper dùng chung cho dự án Python / FastAPI / Django / Tool MMO
import requests

LUGIA_BASE_URL = "${baseUrl}"
LUGIA_API_KEY = "${token}"

def call_lugia_api(method: str, endpoint: str, payload: dict | None = None) -> tuple[int, dict]:
    res = requests.request(
        method=method.upper(),
        url=f"{LUGIA_BASE_URL}{endpoint}",
        headers={
            "Authorization": f"Bearer {LUGIA_API_KEY}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        },
        json=payload,
        timeout=30,
    )
    return res.status_code, res.json()

# Gọi thử kiểm tra số dư ví:
status_code, body = call_lugia_api("GET", "/profile")
print(status_code, body)`;
}

export function generateCodeSnippet(doc: ApiEndpointSpec, baseUrl: string, token: string, lang: CodeLang): string {
    const fullUrl = `${baseUrl}${doc.path.replace('{order_id}', '1763452890123').replace('{proxy_id}', '335')}`;

    if (lang === 'curl') {
        if (doc.method === 'GET') {
            return `curl -X GET "${fullUrl}" \\\n  -H "Authorization: Bearer ${token}" \\\n  -H "Accept: application/json"`;
        }
        const bodyPart = doc.requestJson ? ` \\\n  -d '${doc.requestJson}'` : '';
        return `curl -X POST "${fullUrl}" \\\n  -H "Authorization: Bearer ${token}" \\\n  -H "Accept: application/json" \\\n  -H "Content-Type: application/json"${bodyPart}`;
    }

    if (lang === 'nodejs') {
        if (doc.method === 'GET') {
            return `const response = await fetch("${fullUrl}", {
  method: "GET",
  headers: {
    "Authorization": "Bearer ${token}",
    "Accept": "application/json",
  },
});
const result = await response.json();
console.log(response.status, result);`;
        }
        const bodyBlock = doc.requestJson ? `\n  body: JSON.stringify(${doc.requestJson.replace(/\n/g, '\n  ')}),` : '';
        return `const response = await fetch("${fullUrl}", {
  method: "POST",
  headers: {
    "Authorization": "Bearer ${token}",
    "Accept": "application/json",
    "Content-Type": "application/json",
  },${bodyBlock}
});
const result = await response.json();
console.log(response.status, result);`;
    }

    if (lang === 'python') {
        if (doc.method === 'GET') {
            return `import requests

res = requests.get(
    "${fullUrl}",
    headers={
        "Authorization": "Bearer ${token}",
        "Accept": "application/json",
    },
    timeout=30,
)
print(res.status_code, res.json())`;
        }
        const jsonArg = doc.requestJson ? `\n    json=${doc.requestJson.replace(/\n/g, '\n    ')},` : '';
        return `import requests

res = requests.post(
    "${fullUrl}",
    headers={
        "Authorization": "Bearer ${token}",
        "Accept": "application/json",
    },${jsonArg}
    timeout=30,
)
print(res.status_code, res.json())`;
    }

    // PHP cURL
    if (doc.method === 'GET') {
        return `<?php
$ch = curl_init("${fullUrl}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ${token}",
        "Accept: application/json",
    ],
]);
$response = curl_exec($ch);
curl_close($ch);
print_r(json_decode($response, true));`;
    }

    if (!doc.requestJson) {
        return `<?php
$ch = curl_init("${fullUrl}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ${token}",
        "Accept: application/json",
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
print_r(json_decode($response, true));`;
    }

    return `<?php
$payload = ${jsonToPhpArray(doc.requestJson)};

$ch = curl_init("${fullUrl}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ${token}",
        "Accept: application/json",
        "Content-Type: application/json",
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);`;
}

export const WEBHOOK_CREATED_SAMPLE = JSON.stringify(
    {
        event: 'vps.created',
        order_id: '1763452890123',
        timestamp: 1763453015,
        data: {
            vps_id: 99,
            ip_address: '103.22.33.44',
            username: 'root',
            password: 'LugiaStrongPass99',
            status: 'active',
            expired_at: '2026-12-18T15:10:00+07:00',
        },
    },
    null,
    2
);

export const WEBHOOK_FAILED_SAMPLE = JSON.stringify(
    {
        event: 'vps.failed',
        order_id: '1763452890123',
        timestamp: 1763453060,
        data: {
            vps_id: 99,
            status: 'failed',
            refunded: true,
            reason: 'Cụm máy chủ hiện đã đầy hạn mức cấp phát.',
        },
    },
    null,
    2
);

export const WEBHOOK_PHP_RECEIVER = `<?php
// File: webhook-lugiacloud.php đặt trên server của bạn
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
$event   = $payload['event'] ?? '';
$orderId = $payload['order_id'] ?? null;

if ($event === 'vps.created' && $orderId) {
    $ip       = $payload['data']['ip_address'] ?? null;
    $username = $payload['data']['username'] ?? 'root';
    $password = $payload['data']['password'] ?? '';
    $expired  = $payload['data']['expired_at'] ?? null;

    // TODO: Kích hoạt đơn $orderId & lưu IP + mật khẩu vào Database của bạn
} elseif ($event === 'vps.failed' && $orderId) {
    // Ví Đại lý tại LugiaCloud đã tự động được hoàn tiền 100%
    // TODO: Hủy đơn $orderId & hoàn tiền cho khách hàng trên website của bạn
}

// BẮT BUỘC trả về HTTP 200 OK trong vòng 10 giây
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['received' => true]);`;

export const HTTP_ERROR_SPECS: HttpErrorSpec[] = [
    {
        code: '200 / 202',
        tone: 'emerald',
        name: 'OK / Accepted — Yêu cầu thành công',
        desc: 'HTTP 200 trả về cho các thao tác xử lý tức thì (/profile, /plans, /action, /renew...). HTTP 202 trả về riêng cho lệnh POST /vps/create báo hiệu đơn hàng đã vào hàng đợi cài đặt Hệ điều hành.',
        sample: '{"status": 202, "message": "VPS đang được khởi tạo. Hệ thống sẽ tự động gọi Webhook khi hoàn tất."}',
    },
    {
        code: '401',
        tone: 'amber',
        name: 'Unauthorized — Thiếu hoặc sai khóa API Key',
        desc: 'Request không đính kèm Header Authorization: Bearer <API_KEY>, hoặc khóa API đã bị cấp đổi mới / không tồn tại trên hệ thống.',
        sample: '{"status": 401, "message": "Unauthorized: Invalid API Key"}',
    },
    {
        code: '403',
        tone: 'rose',
        name: 'Forbidden — Chưa được mở quyền Đại lý (Reseller)',
        desc: 'Tài khoản đang ở cấp Khách hàng tiêu chuẩn. Vui lòng nhắn Zalo Admin (0862.579.104) để nâng cấp lên tài khoản Đại lý.',
        sample: '{"status": 403, "message": "Forbidden: Account does not have API permission"}',
    },
    {
        code: '402',
        tone: 'amber',
        name: 'Payment Required — Số dư ví Đại lý không đủ',
        desc: 'Số dư khả dụng trong ví Đại lý nhỏ hơn giá trị đơn khởi tạo mới, gia hạn hoặc nâng cấp phần cứng.',
        sample: '{"status": "error", "message": "Số dư không đủ. Vui lòng nạp thêm tiền."}',
    },
    {
        code: '400 / 422',
        tone: 'amber',
        name: 'Bad Request / Unprocessable — Dữ liệu đầu vào không hợp lệ',
        desc: 'Thiếu tham số bắt buộc (plan_id, os_id, duration), truyền sai chu kỳ (chỉ nhận 1, 3, 6, 12) hoặc addon_disk không phải bội số của 10 GB. Luôn gửi kèm Header Accept: application/json để nhận chi tiết trường lỗi.',
        sample: '{"message": "The duration field must be 1, 3, 6, or 12.", "errors": {"duration": ["The selected duration is invalid."]}}',
    },
    {
        code: '404',
        tone: 'slate',
        name: 'Not Found — Không tìm thấy mã đơn hàng (Order ID)',
        desc: 'Mã order_id không tồn tại hoặc không thuộc quyền sở hữu của tài khoản Đại lý đang thực hiện truy vấn.',
        sample: '{"status": 404, "message": "Order ID không tồn tại."}',
    },
    {
        code: '500 / 503',
        tone: 'rose',
        name: 'Server Busy / Out of Stock — Cụm vật lý tạm đầy hoặc bảo trì',
        desc: 'Cụm node vật lý tạm hết tài nguyên cấp phát hoặc đang bảo trì định kỳ. Mọi giao dịch phát sinh lỗi đều được hoàn tiền 100% tự động về ví Đại lý.',
        sample: '{"status": "error", "message": "Hệ thống đang bảo trì hoặc quá tải tạm thời."}',
    },
];
