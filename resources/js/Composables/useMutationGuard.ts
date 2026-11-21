import { reactive, readonly } from 'vue';
import { router } from '@inertiajs/vue3';

interface MutationMeta {
    title: string;
    subtitle: string;
    isCritical: boolean;
}

const state = reactive({
    inFlight: false,
    visible: false,
    title: 'Đang xử lý yêu cầu...',
    subtitle: 'Vui lòng đợi trong giây lát, không đóng trình duyệt.',
    isCritical: false,
});

let initialized = false;
let showTimer: ReturnType<typeof setTimeout> | null = null;
let safetyTimer: ReturnType<typeof setTimeout> | null = null;

function clearTimers() {
    if (showTimer) {
        clearTimeout(showTimer);
        showTimer = null;
    }
    if (safetyTimer) {
        clearTimeout(safetyTimer);
        safetyTimer = null;
    }
}

function isMutationMethod(method?: string): boolean {
    if (!method) return false;
    const m = method.toLowerCase();
    return m === 'post' || m === 'put' || m === 'patch' || m === 'delete';
}

function resolveMutationMeta(url: string, method: string): MutationMeta {
    const path = url.toLowerCase();
    const m = method.toLowerCase();

    if (path.includes('/proxy/store')) {
        return {
            title: 'Đang thanh toán & cấp phát Proxy IPv4',
            subtitle: 'Hệ thống đang xác thực số dư và khởi tạo địa chỉ IP sạch cho bạn.',
            isCritical: true,
        };
    }

    if (path === '/store' || path.endsWith('/store') || path.includes('/store?')) {
        return {
            title: 'Đang thanh toán & khởi tạo Cloud VPS',
            subtitle: 'Đang trừ số dư ví và gửi lệnh cấp phát máy chủ tại FPT Datacenter.',
            isCritical: true,
        };
    }

    if (path.includes('/renew')) {
        return {
            title: 'Đang gia hạn thời gian sử dụng',
            subtitle: 'Hệ thống đang thanh toán và cộng thêm chu kỳ mới cho dịch vụ.',
            isCritical: true,
        };
    }

    if (path.includes('/upgrade')) {
        return {
            title: 'Đang nâng cấp cấu hình phần cứng',
            subtitle: 'Đang tính bù thời hạn và mở rộng CPU / RAM / SSD trực tiếp.',
            isCritical: true,
        };
    }

    if (path.includes('/rebuild')) {
        return {
            title: 'Đang gửi lệnh cài lại hệ điều hành',
            subtitle: 'Hệ thống đang cấu hình OS mới sạch cho máy chủ của bạn.',
            isCritical: true,
        };
    }

    if (path.includes('/power')) {
        return {
            title: 'Đang điều khiển nguồn máy chủ',
            subtitle: 'Đang gửi tín hiệu Bật / Tắt / Khởi động lại tới cụm máy chủ.',
            isCritical: true,
        };
    }

    if (path.includes('/topup') && !path.includes('/admin/')) {
        return {
            title: 'Đang khởi tạo mã VietQR thanh toán',
            subtitle: 'Đang kết nối cổng thanh toán tự động 24/7, vui lòng đợi giây lát.',
            isCritical: true,
        };
    }

    if (path.includes('/approve') || path.includes('/balance')) {
        return {
            title: 'Đang cập nhật giao dịch & số dư',
            subtitle: 'Đang hạch toán sổ cái và đồng bộ số dư tài khoản.',
            isCritical: true,
        };
    }

    if (path.includes('/vouchers/') && path.includes('/claim')) {
        return {
            title: 'Đang lưu mã Voucher vào ví của bạn',
            subtitle: 'Hệ thống đang xác thực lượt săn và thêm mã ưu đãi vào tài khoản.',
            isCritical: false,
        };
    }

    if (path.includes('/broadcast')) {
        return {
            title: 'Đang gửi Email quảng bá sự kiện',
            subtitle: 'Đang đưa danh sách email khách hàng vào hàng đợi gửi tự động.',
            isCritical: true,
        };
    }

    if (path.includes('/sync')) {
        return {
            title: 'Đang đồng bộ dữ liệu hạ tầng',
            subtitle: 'Đang lấy bảng giá và trạng thái mới nhất từ máy chủ đối tác.',
            isCritical: true,
        };
    }

    if (path.includes('/password')) {
        return {
            title: 'Đang cập nhật mật khẩu bảo mật',
            subtitle: 'Đang mã hóa và áp dụng mật khẩu mới cho hệ thống.',
            isCritical: true,
        };
    }

    if (path.includes('/login')) {
        return {
            title: 'Đang xác thực đăng nhập',
            subtitle: 'Đang kiểm tra thông tin và mở phiên làm việc an toàn.',
            isCritical: false,
        };
    }

    if (path.includes('/register')) {
        return {
            title: 'Đang khởi tạo tài khoản mới',
            subtitle: 'Đang thiết lập hồ sơ và ví tài khoản LugiaCloud của bạn.',
            isCritical: true,
        };
    }

    if (path.includes('/logout')) {
        return {
            title: 'Đang đăng xuất an toàn',
            subtitle: 'Đang đóng phiên đăng nhập hiện tại...',
            isCritical: false,
        };
    }

    if (m === 'delete' || path.includes('/destroy') || path.includes('/reject')) {
        return {
            title: 'Đang xử lý yêu cầu xóa / hủy',
            subtitle: 'Vui lòng chờ hệ thống hoàn tất cập nhật dữ liệu.',
            isCritical: true,
        };
    }

    return {
        title: 'Đang lưu thay đổi dữ liệu',
        subtitle: 'Vui lòng đợi trong giây lát, tránh nhấn lặp lại khi mạng chậm.',
        isCritical: false,
    };
}

function finishMutation() {
    clearTimers();
    state.inFlight = false;
    state.visible = false;
}

export function initMutationGuard(): void {
    if (initialized) return;
    initialized = true;

    // 1. Chặn tuyệt đối double-submit khi đang có mutation POST/PUT/PATCH/DELETE chạy dở
    router.on('before', (event) => {
        const visit = event.detail.visit;
        if (!isMutationMethod(visit.method)) {
            return;
        }
        if (state.inFlight) {
            return false;
        }
    });

    // 2. Kích hoạt lock + hiển thị overlay ngữ cảnh đẹp mắt
    router.on('start', (event) => {
        const visit = event.detail.visit;
        if (!isMutationMethod(visit.method)) {
            return;
        }

        clearTimers();
        const urlStr = typeof visit.url === 'string' ? visit.url : visit.url.pathname;
        const meta = resolveMutationMeta(urlStr, visit.method);

        state.inFlight = true;
        state.title = meta.title;
        state.subtitle = meta.subtitle;
        state.isCritical = meta.isCritical;

        if (meta.isCritical) {
            // Giao dịch tài chính / khởi tạo dịch vụ: khóa màn hình ngay lập tức (0ms)
            state.visible = true;
        } else {
            // Thao tác cập nhật nhẹ: hiện sau 180ms để mạng nhanh không bị chớp nháy, mạng lag vẫn khóa mượt
            showTimer = setTimeout(() => {
                if (state.inFlight) {
                    state.visible = true;
                }
            }, 180);
        }

        // Fail-safe: tự mở khóa sau 60s nếu kết nối bị treo bất thường
        safetyTimer = setTimeout(() => {
            finishMutation();
        }, 60000);
    });

    // 3. Luôn gỡ khóa sạch sẽ khi kết thúc (thành công, lỗi validate, lỗi mạng hoặc hủy)
    router.on('finish', (event) => {
        const visit = event.detail.visit;
        if (isMutationMethod(visit.method)) {
            finishMutation();
        }
    });

    router.on('invalid', () => finishMutation());
    router.on('exception', () => finishMutation());
}

export async function withActionOverlay<T>(
    title: string,
    subtitle: string,
    task: () => Promise<T>,
    isCritical = true
): Promise<T> {
    if (state.inFlight) {
        return Promise.reject(new Error('Đang có thao tác khác đang xử lý.'));
    }

    clearTimers();
    state.inFlight = true;
    state.title = title;
    state.subtitle = subtitle;
    state.isCritical = isCritical;
    state.visible = true;

    try {
        return await task();
    } finally {
        finishMutation();
    }
}

export function useMutationGuard() {
    return readonly(state);
}
