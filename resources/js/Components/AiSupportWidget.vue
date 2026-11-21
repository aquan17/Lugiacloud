<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import { MessageSquare, Send, X, Loader2 } from 'lucide-vue-next';

const isOpen = ref(false);
const input = ref('');
const loading = ref(false);
const messages = ref<Array<{ role: 'user' | 'bot'; content: string }>>([
    {
        role: 'bot',
        content: 'Xin chào! Tôi là trợ lý kỹ thuật LugiaCloud. Bạn cần kiểm tra trạng thái VPS, Proxy hay hỗ trợ thanh toán?',
    },
]);

async function send() {
    const text = input.value.trim();
    if (!text || loading.value) return;

    const history = messages.value.slice(-8);
    messages.value.push({ role: 'user', content: text });
    input.value = '';
    loading.value = true;

    try {
        const { data } = await axios.post('/chatbot/message', {
            message: text,
            history,
        });
        messages.value.push({
            role: 'bot',
            content: data.reply || 'Hệ thống chưa thể phản hồi lúc này.',
        });
    } catch {
        messages.value.push({
            role: 'bot',
            content: 'Phiên kết nối bận (giới hạn tốc độ). Vui lòng thử lại sau ít giây hoặc liên hệ trực tiếp Zalo hỗ trợ.',
        });
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="fixed bottom-4 right-4 sm:bottom-5 sm:right-5 z-40 no-print">
        <div
            v-if="isOpen"
            class="mb-3 w-[calc(100vw-2rem)] sm:w-[370px] bg-white rounded-xl border border-slate-200 shadow-xl overflow-hidden flex flex-col h-[440px] max-h-[75vh]"
        >
            <div class="px-4 py-3 bg-[#0B1437] text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <img src="/bird-icon.webp" alt="Lugia" width="24" height="24" loading="lazy" decoding="async" class="w-6 h-6 object-contain" />
                    <div>
                        <div class="text-xs font-semibold">Trợ lý Kỹ thuật LugiaCloud</div>
                        <div class="text-[10px] text-sky-300">Tra cứu dịch vụ & hướng dẫn 24/7</div>
                    </div>
                </div>
                <button @click="isOpen = false" class="text-slate-300 hover:text-white">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-3.5 space-y-3 bg-[#F8FAFC] text-xs">
                <div
                    v-for="(m, idx) in messages"
                    :key="idx"
                    class="flex"
                    :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[85%] px-3 py-2 rounded-lg leading-relaxed whitespace-pre-line"
                        :class="
                            m.role === 'user'
                                ? 'bg-[#1D6AE5] text-white'
                                : 'bg-white border border-slate-200 text-slate-800'
                        "
                    >
                        {{ m.content }}
                    </div>
                </div>
                <div v-if="loading" class="flex justify-start">
                    <div class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-slate-500 inline-flex items-center gap-2">
                        <Loader2 class="w-3.5 h-3.5 animate-spin" />
                        <span>Đang tra cứu...</span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="send" class="p-2.5 bg-white border-t border-slate-200 flex items-center gap-2">
                <input
                    v-model="input"
                    type="text"
                    placeholder="Nhập câu hỏi kỹ thuật..."
                    class="flex-1 text-xs px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:border-[#1D6AE5]"
                />
                <button
                    type="submit"
                    :disabled="loading"
                    class="p-2 rounded-lg bg-[#0B1437] hover:bg-[#162354] text-white disabled:opacity-50 cursor-pointer"
                >
                    <Send class="w-3.5 h-3.5" />
                </button>
            </form>
        </div>

        <button
            v-if="!isOpen"
            type="button"
            @click="isOpen = true"
            class="inline-flex items-center justify-center gap-2 w-11 h-11 sm:w-auto sm:h-auto sm:px-3.5 sm:py-2.5 rounded-full bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-semibold shadow-lg border border-slate-800 transition-transform active:scale-95 cursor-pointer"
            title="Hỗ trợ AI 24/7"
            aria-label="Hỗ trợ AI"
        >
            <MessageSquare class="w-4 h-4 text-sky-400" />
            <span class="hidden sm:inline">Hỗ trợ AI</span>
        </button>
    </div>
</template>
