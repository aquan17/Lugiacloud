<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    protected $client;
    protected $apiKeys = [];

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        // Lấy danh sách keys từ config (tương thích hoàn toàn với php artisan config:cache)
        $keysString = (string) config('services.gemini.api_keys', '');
        $this->apiKeys = array_values(array_filter(array_map('trim', explode(',', $keysString))));
    }

    /**
     * Gửi tin nhắn đến Gemini API với cơ chế tự động xoay vòng Key
     */
    public function sendMessage(string $prompt, array $history = [], string $systemInstruction = '')
    {
        if (empty($this->apiKeys)) {
            Log::error('AiChatService: Chưa cấu hình GEMINI_API_KEYS trong .env');
            return "Hệ thống Chatbot chưa được cấu hình khóa API. Vui lòng liên hệ Admin.";
        }

        $formattedContents = $this->formatHistoryForGemini($prompt, $history);

        foreach ($this->apiKeys as $index => $apiKey) {
            try {
                return $this->callGeminiApi($apiKey, $formattedContents, $systemInstruction);
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                
                // Nếu lỗi 429 (Too Many Requests) -> Chuyển sang Key tiếp theo
                if ($statusCode == 429) {
                    Log::warning("AiChatService: Key số {$index} bị giới hạn (429). Đang thử Key tiếp theo.");
                    continue; 
                }

                // Nếu lỗi khác (ví dụ sai key, lỗi format) -> Ghi log và dừng lại
                Log::error('AiChatService Error: ' . $e->getResponse()->getBody()->getContents());
                return "Đã xảy ra lỗi khi kết nối đến AI. Vui lòng thử lại sau.";
            } catch (\Exception $e) {
                Log::error('AiChatService Exception: ' . $e->getMessage());
                return "Đã xảy ra lỗi hệ thống, xin vui lòng thử lại sau.";
            }
        }

        // Nếu tất cả các Key đều bị 429
        return "Hiện tại hệ thống đang xử lý quá nhiều yêu cầu. Vui lòng thử lại sau 1 phút nhé!";
    }

    /**
     * Format lại lịch sử chat theo chuẩn của Gemini
     */
    private function formatHistoryForGemini(string $currentPrompt, array $history): array
    {
        $contents = [];

        foreach ($history as $msg) {
            if (empty($msg['content'])) continue;
            
            $contents[] = [
                'role' => ($msg['role'] === 'bot' || $msg['role'] === 'model') ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        // Thêm câu hỏi hiện tại
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $currentPrompt]]
        ];

        return $contents;
    }

    /**
     * Gọi API thực tế
     */
    private function callGeminiApi(string $apiKey, array $contents, string $systemInstruction = ''): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}";

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 4096,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_ONLY_HIGH'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_ONLY_HIGH'
                ]
            ]
        ];

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]]
            ];
        }

        $response = $this->client->post($url, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30, // Timeout 30s
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }

        return "Xin lỗi, tôi không thể trả lời câu hỏi này vào lúc này.";
    }
}

