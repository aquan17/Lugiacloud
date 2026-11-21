<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VpsInstance;
use App\Models\ProxyInstance;
use App\Models\KnowledgeBase;
use App\Services\AiChatService;

class ChatbotController extends Controller
{
    protected $aiChatService;

    public function __construct(AiChatService $aiChatService)
    {
        $this->aiChatService = $aiChatService;
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array', // [['role' => 'user', 'content' => 'hi'], ...]
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['reply' => 'Vui lòng đăng nhập để sử dụng tính năng hỗ trợ.']);
        }

        $message = $request->input('message');
        $history = $request->input('history', []);

        // 1. Lấy dữ liệu VPS đang hoạt động của User
        $vpsList = VpsInstance::where('user_id', $user->id)
            ->where('status', '!=', 'Đã xóa')
            ->get(['id', 'label', 'public_ip', 'status', 'expires_at', 'cpu', 'ram', 'disk']);
            
        $vpsContext = "Người dùng chưa có VPS nào.";
        if ($vpsList->count() > 0) {
            $vpsContext = "Danh sách VPS của người dùng:\n";
            foreach ($vpsList as $vps) {
                $vpsContext .= "- Tên: {$vps->label}, IP: {$vps->public_ip}, Cấu hình: {$vps->cpu} vCPU / {$vps->ram} RAM / {$vps->disk} Disk, Trạng thái: {$vps->status}, Ngày hết hạn: {$vps->expires_at}\n";
            }
        }

        // 2. Lấy dữ liệu Proxy đang hoạt động của User
        $proxyList = ProxyInstance::where('user_id', $user->id)
            ->where('status', '!=', 'Đã xóa')
            ->get(['id', 'ip', 'port', 'status', 'expires_at']);
            
        $proxyContext = "Người dùng chưa có Proxy nào.";
        if ($proxyList->count() > 0) {
            $proxyContext = "Danh sách Proxy của người dùng:\n";
            foreach ($proxyList as $proxy) {
                $proxyContext .= "- IP: {$proxy->ip}:{$proxy->port}, Trạng thái: {$proxy->status}, Ngày hết hạn: {$proxy->expires_at}\n";
            }
        }

        // 3. Lấy dữ liệu Knowledge Base
        $knowledges = KnowledgeBase::where('is_active', true)->get();
        $knowledgeContext = "";
        if ($knowledges->count() > 0) {
            $knowledgeContext = "Dữ liệu hướng dẫn của hệ thống (Knowledge Base):\n";
            foreach ($knowledges as $kb) {
                $knowledgeContext .= "Chủ đề: {$kb->title}\nNội dung: {$kb->content}\n---\n";
            }
        }

        // 4. Xây dựng System Instruction
        $systemInstruction = "Bạn là trợ lý ảo AI (Chatbot) của hệ thống Lugiacloud. Nhiệm vụ của bạn là tư vấn, hỗ trợ khách hàng giải đáp thắc mắc về dịch vụ VPS, Proxy, nạp tiền và các vấn đề kỹ thuật cơ bản.
        Hãy xưng hô lịch sự, chuyên nghiệp. Không dùng ngôn ngữ quá máy móc. Trả lời ngắn gọn, súc tích. Bắt buộc dùng Markdown để định dạng câu trả lời cho đẹp (in đậm, danh sách).
        
        THÔNG TIN NGƯỜI ĐANG CHAT:
        - Tên: {$user->name}
        - Email: {$user->email}
        - Số dư hiện tại: " . number_format($user->balance) . " VNĐ.
        
        THÔNG TIN DỊCH VỤ CỦA HỌ:
        {$vpsContext}
        {$proxyContext}
        
        KIẾN THỨC BỔ SUNG TỪ HỆ THỐNG CỦA BẠN:
        {$knowledgeContext}
        
        LƯU Ý QUAN TRỌNG: 
        - Nếu khách hàng hỏi về thông tin VPS, Proxy hoặc số dư của họ, hãy dùng thông tin ở trên để trả lời.
        - TUYỆT ĐỐI KHÔNG viết code, KHÔNG làm bài tập lập trình, KHÔNG trả lời các kiến thức chuyên sâu về lập trình phần mềm (như PHP, Python, C++, HTML...) cho khách hàng. Nếu họ hỏi về code, hãy từ chối lịch sự và nói rằng bạn chỉ hỗ trợ các vấn đề liên quan đến Server/VPS/Proxy.
        - Nếu câu hỏi không liên quan đến dịch vụ của Lugiacloud (như hỏi thời tiết, tin tức, chém gió linh tinh), hãy lịch sự từ chối và hướng họ quay lại chủ đề VPS/Proxy.";

        // 5. Gọi AI Service
        $reply = $this->aiChatService->sendMessage($message, $history, $systemInstruction);

        return response()->json([
            'reply' => $reply
        ]);
    }
}
