<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogArticleSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('is_admin', 1)->value('id');

        $articles = [
            [
                'title' => 'Hướng dẫn đăng nhập Cloud VPS Windows & Linux (Remote Desktop và SSH) trong 2 phút',
                'slug' => 'huong-dan-dang-nhap-cloud-vps-windows-linux-rdp-ssh',
                'category' => 'vps',
                'excerpt' => 'Hướng dẫn chi tiết cách kết nối vào máy chủ Cloud VPS Windows bằng Remote Desktop (PC & điện thoại) và VPS Linux bằng SSH Terminal cực nhanh cho người mới.',
                'meta_title' => 'Cách đăng nhập Cloud VPS Windows & Linux (RDP/SSH) nhanh nhất — LugiaCloud',
                'meta_description' => 'Hướng dẫn từng bước kết nối Cloud VPS Windows qua Remote Desktop (mstsc, RD Client) và VPS Ubuntu/CentOS qua SSH Terminal chỉ trong 2 phút.',
                'cta_service' => 'vps',
                'is_featured' => true,
                'views' => 428,
                'published_at' => now()->subDays(3),
                'content' => <<<'MD'
Sau khi khởi tạo thành công Cloud VPS tại **LugiaCloud**, hệ thống sẽ cấp đầy đủ **Địa chỉ IPv4**, **Tài khoản** (`Administrator` cho Windows hoặc `root` cho Linux) và **Mật khẩu** ngay trong trang Quản lý VPS.

## 1. Đăng nhập Cloud VPS Windows trên máy tính (Windows)

Bạn không cần cài thêm phần mềm bên thứ 3, hãy dùng công cụ **Remote Desktop Connection** có sẵn trên Windows:

1. Nhấn tổ hợp phím `Win + R` để mở hộp thoại **Run**.
2. Nhập lệnh `mstsc /v:IP_VPS_CUA_BAN` (ví dụ `mstsc /v:103.145.200.10`) rồi nhấn **Enter**.
3. Hoặc bấm trực tiếp nút **Tải file .RDP** trong trang chi tiết VPS tại LugiaCloud để mở nhanh 1 click.
4. Nhập tài khoản `Administrator` và mật khẩu OS -> tích **Remember me** -> bấm **OK**.
5. Nếu xuất hiện cảnh báo chứng chỉ bảo mật (Certificate), tích chọn **Don't ask me again** rồi nhấn **Yes**.

> **Mẹo bảo mật:** Sau lần đăng nhập đầu tiên, bạn có thể đổi mật khẩu OS mới trực tiếp bằng nút **Đổi mật khẩu OS** trên trang quản lý VPS (tối thiểu 12 ký tự gồm chữ hoa, chữ thường và số).

## 2. Đăng nhập VPS Windows bằng điện thoại (Android / iOS)

- Cài ứng dụng chính chủ **Windows App Mobile** (tên cũ là *Microsoft Remote Desktop*) từ App Store hoặc Google Play.
- Bấm dấu `+` -> chọn **Add PC** -> điền `IP` vào ô **PC name**.
- Ở mục **User Account**, thêm tài khoản `Administrator` và mật khẩu -> bấm **Save** rồi chạm vào máy chủ để điều khiển từ xa.

## 3. Đăng nhập Cloud VPS Linux (Ubuntu / Debian / CentOS) qua SSH

Trên Windows 10/11, macOS hoặc Linux, bạn mở **Terminal / PowerShell** và chạy lệnh:

```bash
ssh root@103.145.200.10
```

- Lần đầu kết nối hệ thống hỏi `Are you sure you want to continue connecting (yes/no)?` -> gõ `yes` và nhấn **Enter**.
- Dán mật khẩu (chuột phải trong PowerShell) rồi nhấn **Enter** (*lưu ý: khi nhập mật khẩu SSH màn hình sẽ không hiện dấu sao vì lý do bảo mật*).

## 4. Xử lý nhanh nếu không kết nối được

- Kiểm tra trạng thái máy chủ trong trang quản lý xem đã ở trạng thái **Đang chạy (Running)** chưa.
- Dùng tính năng **Mở Console Web** trên LugiaCloud để kiểm tra màn hình trực tiếp ngay trên trình duyệt mà không cần mạng RDP/SSH.
- Nếu cần hỗ trợ kỹ thuật, nhắn ngay Zalo **0862.579.104** để kỹ thuật viên hỗ trợ 24/7.
MD,
            ],
            [
                'title' => 'Hướng dẫn cài Proxy IPv4 Việt Nam vào AdsPower, GoLogin, Proxifier và SwitchyOmega',
                'slug' => 'huong-dan-cai-proxy-ipv4-viet-nam-adspower-gologin-proxifier',
                'category' => 'proxy',
                'excerpt' => 'Cách gắn Proxy IPv4 Dân cư & Datacenter chuẩn định dạng IP:Port:User:Pass vào trình duyệt Antidetect (AdsPower, GoLogin), Proxifier và trình duyệt Chrome.',
                'meta_title' => 'Cách cài Proxy IPv4 vào AdsPower, GoLogin, Proxifier chi tiết — LugiaCloud',
                'meta_description' => 'Hướng dẫn cấu hình Proxy IPv4 HTTP/SOCKS5 của LugiaCloud lên AdsPower, GoLogin, Proxifier và SwitchyOmega chuẩn 100% IP sạch Việt Nam.',
                'cta_service' => 'proxy',
                'is_featured' => false,
                'views' => 315,
                'published_at' => now()->subDays(2),
                'content' => <<<'MD'
Toàn bộ Proxy IPv4 tại **LugiaCloud** đều hỗ trợ đồng thời giao thức **HTTP** và **SOCKS5**, băng thông không giới hạn và có sẵn nút **Sao chép chuỗi `IP:Port:User:Pass`** giúp bạn nhập nhanh chỉ trong vài giây.

## 1. Cài Proxy vào AdsPower / GoLogin / Hidemyacc / Genlogin

1. Vào trang **Proxy IPv4 Việt Nam** trên LugiaCloud và bấm **Copy** chuỗi kết nối dạng:
```text
180.93.200.88:10080:user_lugia:pass_123456
```
2. Mở phần mềm Antidetect -> tạo mới hoặc sửa Profile -> chọn tab **Proxy**.
3. Chọn loại giao thức **HTTP** hoặc **SOCKS5**.
4. Dán toàn bộ chuỗi `IP:Port:User:Pass` vào ô **Proxy Host / Quick Import** — phần mềm sẽ tự động tách ra đúng 4 trường Host, Port, Username và Password.
5. Bấm **Check Proxy**: thấy báo vị trí **Vietnam (VN)** và múi giờ `Asia/Ho_Chi_Minh` là đã sẵn sàng chạy.

## 2. Gắn Proxy cho toàn bộ phần mềm bằng Proxifier

Proxifier rất tiện khi bạn muốn ép riêng Telegram, Chrome hoặc giả lập Android đi qua Proxy IPv4 riêng:

1. Mở **Proxifier** -> chọn **Profile** -> **Proxy Servers...** -> bấm **Add**.
2. Điền **Address** (IP) và **Port**, chọn Protocol là **HTTPS** hoặc **SOCKS Version 5**.
3. Tích chọn **Enable Authentication** -> điền Username và Password -> bấm **Check** kiểm tra xanh.
4. Vào **Proxification Rules** để chỉ định ứng dụng cần đi qua Proxy vừa thêm.

## 3. Dùng trên Chrome / Brave qua SwitchyOmega

- Cài extension **Proxy SwitchyOmega** trên Chrome Web Store.
- Tạo Profile mới -> chọn **HTTP** -> điền Server & Port -> bấm biểu tượng **ổ khóa** để nhập Username và Password -> bấm **Apply changes**.

> **Lưu ý:** Nếu dùng nuôi tài khoản quan trọng (Facebook, TikTok, Shopee, Google Ads), bạn nên ưu tiên dòng **Proxy Dân Cư VN — Riêng 1:1** để đạt độ sạch IP và độ trust cao nhất.
MD,
            ],
            [
                'title' => 'Cách mở rộng ổ C trên Windows sau khi nâng cấp thêm dung lượng ổ cứng SSD',
                'slug' => 'cach-mo-rong-o-c-windows-sau-khi-nang-cap-ssd-vps',
                'category' => 'guide',
                'excerpt' => 'Mua thêm ổ cứng SSD giữa kỳ nhưng trong Windows chưa thấy tăng dung lượng ổ C? Làm theo 4 bước Extend Volume cực đơn giản không mất dữ liệu.',
                'meta_title' => 'Cách mở rộng ổ C VPS Windows sau khi nâng cấp SSD — LugiaCloud',
                'meta_description' => 'Hướng dẫn dùng Disk Management (diskmgmt.msc) để gộp dung lượng Unallocated vào ổ C trên VPS Windows chỉ trong 30 giây, giữ nguyên 100% dữ liệu.',
                'cta_service' => 'vps',
                'is_featured' => false,
                'views' => 264,
                'published_at' => now()->subDay(),
                'content' => <<<'MD'
Khi bạn nâng cấp thêm ổ cứng SSD giữa kỳ tại **LugiaCloud**, hệ thống hạ tầng sẽ cấp ngay dung lượng mới vào đĩa vật lý của VPS mà **giữ nguyên 100% dữ liệu**. Tuy nhiên, trên Windows phần dung lượng mới sẽ nằm ở vùng **Unallocated** (chưa phân bổ), nên bạn cần thêm 30 giây thao tác để gộp vào ổ `C:`.

## Các bước mở rộng ổ C không mất dữ liệu

1. Đăng nhập vào VPS Windows bằng Remote Desktop.
2. Nhấn tổ hợp phím `Win + R` -> gõ lệnh sau rồi nhấn **Enter**:
```text
diskmgmt.msc
```
3. Trong cửa sổ **Disk Management**, nhìn vào thanh ổ đĩa `Disk 0` sẽ thấy phân vùng **C:** nằm cạnh vùng màu đen ghi **Unallocated** (chính là số GB SSD bạn vừa mua thêm).
4. **Chuột phải vào ổ C:** -> chọn **Extend Volume...** -> bấm **Next** -> **Next** -> **Finish**.

Ngay lập tức, ổ `C:` sẽ tăng đúng đủ tổng dung lượng SSD mới mà không cần khởi động lại máy chủ!

## Trường hợp nút Extend Volume bị mờ

Đôi khi có một phân vùng **Recovery Partition** nhỏ (khoảng 500 MB) nằm chắn giữa ổ `C:` và vùng `Unallocated`. Trong trường hợp này, bạn có thể tạo vùng `Unallocated` thành ổ `D:` mới (chuột phải -> **New Simple Volume**), hoặc nhắn Zalo hỗ trợ **0862.579.104** để kỹ thuật viên hỗ trợ gộp trực tiếp vào ổ `C:` giúp bạn.
MD,
            ],
            [
                'title' => 'So sánh Proxy Dân cư Việt Nam và Proxy Datacenter: Nên chọn loại nào cho công việc?',
                'slug' => 'so-sanh-proxy-dan-cu-viet-nam-va-proxy-datacenter',
                'category' => 'seo',
                'excerpt' => 'Phân tích ưu nhược điểm thực tế giữa Proxy Dân cư VN (ISP FPT/Viettel/VNPT) và Proxy Datacenter Tĩnh giúp bạn chọn đúng gói tối ưu chi phí nhất.',
                'meta_title' => 'So sánh Proxy Dân cư Việt Nam và Proxy Datacenter — Nên dùng loại nào?',
                'meta_description' => 'Phân tích chi tiết sự khác biệt giữa Proxy Dân cư ISP Việt Nam và Proxy Datacenter Tĩnh về độ sạch IP, tốc độ, chi phí và ứng dụng MMO/Automation.',
                'cta_service' => 'proxy',
                'is_featured' => false,
                'views' => 198,
                'published_at' => now()->subHours(6),
                'content' => <<<'MD'
Rất nhiều bạn làm MMO, nuôi tài khoản hay chạy automation thường phân vân không biết nên mua **Proxy Datacenter** giá rẻ hay đầu tư hẳn **Proxy Dân cư Việt Nam**. Chọn đúng loại ngay từ đầu vừa giúp tiết kiệm chi phí, vừa tránh bị checkpoint/khóa tài khoản.

## 1. Proxy Datacenter Tĩnh là gì?

Proxy Datacenter được cấp phát từ các dải IP đặt tại trung tâm dữ liệu (Datacenter).

- **Ưu điểm:** Chi phí cực rẻ (chỉ từ **15.000đ/tháng**), tốc độ mạng rất cao, độ trễ thấp, online ổn định 24/7.
- **Nhược điểm:** Các nền tảng chống spam gắt (TikTok, Facebook Ads, Shopee, Google) dễ nhận diện đây là IP thuộc ASN Datacenter hơn so với IP nhà mạng dân cư.
- **Phù hợp nhất cho:** Crawl dữ liệu website, chạy bot Telegram/Discord, test hệ thống, fake IP xem nội dung hoặc tác vụ không yêu cầu độ trust cao.

## 2. Proxy Dân cư Việt Nam (Residential ISP) là gì?

Proxy Dân cư sử dụng dải IP thuộc các nhà mạng phổ biến tại Việt Nam (VNPT, Viettel, FPT) giống hệt đường truyền internet hộ gia đình.

- **Ưu điểm:** Độ sạch và độ tin cậy (Trust Score) cực cao, giảm thiểu tối đa tình trạng đòi xác minh danh tính / checkpoint.
- **Nhược điểm:** Giá cao hơn Datacenter (nhưng tại LugiaCloud gói Shared chỉ từ **20.000đ/tháng** và gói Riêng 1:1 sạch tuyệt đối chỉ **75.000đ/tháng**).
- **Phù hợp nhất cho:** Nuôi tài khoản Facebook, TikTok, Instagram, Shopee, quản trị nhiều hồ sơ trên AdsPower / GoLogin, chạy quảng cáo hoặc seeding chuyên nghiệp.

## 3. Nên chọn gói Shared hay Riêng 1:1?

- **Gói Shared (dùng chung giới hạn người):** Rất tiết kiệm nếu bạn chạy các nền tảng khác nhau hoặc cần số lượng IP lớn với ngân sách nhỏ.
- **Gói Riêng 1:1 (Private Dedicated):** Toàn bộ 1 IP chỉ cấp duy nhất cho 1 mình bạn, không bị ảnh hưởng bởi hành vi người dùng khác — lựa chọn bắt buộc nếu bạn nuôi dàn tài khoản giá trị cao.
MD,
            ],
        ];

        foreach ($articles as $item) {
            Article::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    ...$item,
                    'user_id' => $adminId,
                    'is_published' => true,
                    'reading_minutes' => Article::estimateReadingMinutes($item['content']),
                ]
            );
        }
    }
}
