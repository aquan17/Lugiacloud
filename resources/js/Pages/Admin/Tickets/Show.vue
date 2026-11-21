<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { askConfirm } from '@/Composables/useConfirmAction';
import { ArrowLeft, Send, Lock, Image as ImageIcon, Loader2, Clock, RefreshCw } from 'lucide-vue-next';

const props = defineProps<{
    ticket: any;
}>();

const fileInputRef = ref<HTMLInputElement | null>(null);
const replyForm = useForm({
    noi_dung: '',
    images: [] as File[],
});

function onSelectFiles(e: Event) {
    replyForm.images = Array.from((e.target as HTMLInputElement).files || []).slice(0, 5);
}

function submitReply() {
    if (!replyForm.noi_dung.trim() && replyForm.images.length === 0) return;
    replyForm.post(`/admin/tickets/${props.ticket.id}/reply`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            if (fileInputRef.value) fileInputRef.value.value = '';
        },
    });
}

function confirmClose() {
    askConfirm({
        title: `Đóng Ticket ${props.ticket.ma || '#' + props.ticket.id}`,
        message: 'Xác nhận đóng Ticket hỗ trợ này? (Bạn vẫn có thể gửi phản hồi để mở lại trong vòng 7 ngày.)',
        confirmText: 'Đóng Ticket',
        variant: 'danger',
        onConfirm: () => router.post(`/admin/tickets/${props.ticket.id}/close`, {}, { preserveScroll: true }),
    });
}
</script>

<template>
    <AdminLayout :title="`Ticket ${ticket.ma || '#' + ticket.id} — ${ticket.tieu_de}`">
        <Head :title="`Ticket ${ticket.ma || '#' + ticket.id}`" />

        <div class="space-y-5 max-w-4xl">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link href="/admin/tickets" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-[#0B1437]">
                    <ArrowLeft class="w-4 h-4" />
                    <span>Quay lại Danh sách Ticket</span>
                </Link>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="router.reload({ only: ['ticket'] })"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer"
                    >
                        <RefreshCw class="w-3.5 h-3.5" /><span>Làm mới</span>
                    </button>
                    <button
                        v-if="ticket.trang_thai !== 'da_dong'"
                        type="button"
                        @click="confirmClose"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white text-xs font-bold cursor-pointer transition"
                    >
                        <Lock class="w-3.5 h-3.5" /><span>Đóng Ticket</span>
                    </button>
                </div>
            </div>

            <!-- Ticket Header Card -->
            <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-2.5 text-xs">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-extrabold text-[#0B1437]">
                        <span class="font-mono text-slate-500 mr-1.5">{{ ticket.ma || `#${ticket.id}` }}</span>
                        <span>{{ ticket.tieu_de }}</span>
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-md border bg-blue-50 text-[#1D6AE5] border-blue-200 font-bold">
                        {{ ticket.ten_trang_thai || ticket.trang_thai }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-slate-600 font-medium">
                    <span>Dịch vụ: <strong class="text-[#0B1437] font-mono">{{ ticket.ten_loai_dich_vu }} · {{ ticket.dich_vu }} (#{{ ticket.dich_vu_id }})</strong></span>
                    <span>Tạo lúc: <strong class="font-mono-num">{{ ticket.tao_luc }}</strong></span>
                    <span v-if="ticket.tu_dong_dong_luc && ticket.trang_thai === 'cho_khach'" class="text-amber-700 font-semibold inline-flex items-center gap-1">
                        <Clock class="w-3.5 h-3.5" /> Tự đóng: {{ ticket.tu_dong_dong_luc }}
                    </span>
                </div>
            </div>

            <!-- Conversation Thread -->
            <div class="space-y-3">
                <div
                    v-for="msg in ticket.tin_nhan || []"
                    :key="msg.id"
                    class="surface-card p-4 sm:p-5 rounded-2xl border text-xs space-y-2.5"
                    :class="
                        msg.nguoi_gui === 'he_thong'
                            ? 'bg-slate-50 border-slate-200 text-slate-600'
                            : msg.nguoi_gui === 'admin'
                              ? 'bg-blue-50/40 border-blue-200/80'
                              : 'bg-white border-slate-200/90'
                    "
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="font-extrabold"
                            :class="msg.nguoi_gui === 'admin' ? 'text-[#1D6AE5]' : msg.nguoi_gui === 'he_thong' ? 'text-slate-500' : 'text-[#0B1437]'"
                        >
                            {{ msg.ten_nguoi_gui || (msg.nguoi_gui === 'admin' ? 'Kỹ thuật hạ tầng' : 'Bạn (Đại lý)') }}
                        </span>
                        <span class="font-mono-num text-[11px] text-slate-500">{{ msg.tao_luc }}</span>
                    </div>

                    <p v-if="msg.noi_dung" class="text-[13px] text-slate-800 whitespace-pre-wrap leading-relaxed">{{ msg.noi_dung }}</p>

                    <!-- Private Authenticated Images -->
                    <div v-if="msg.anh && msg.anh.length > 0" class="flex flex-wrap gap-3 pt-1">
                        <a
                            v-for="img in msg.anh"
                            :key="`${msg.id}-${img.so}`"
                            :href="`/admin/tickets/${ticket.id}/image/${msg.id}/${img.so}`"
                            target="_blank"
                            class="block group rounded-xl overflow-hidden border border-slate-200 bg-slate-100 w-36"
                        >
                            <img
                                :src="`/admin/tickets/${ticket.id}/image/${msg.id}/${img.so}`"
                                :alt="img.ten || 'attachment'"
                                loading="lazy"
                                class="w-full h-24 object-cover group-hover:opacity-90 transition"
                            />
                            <div class="px-2 py-1 text-[10.5px] font-mono text-slate-600 truncate bg-white">
                                {{ img.ten || `anh-${img.so}` }}
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Reply Form -->
            <form
                v-if="ticket.co_the_phan_hoi"
                @submit.prevent="submitReply"
                class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-3 text-xs"
            >
                <div class="font-extrabold text-[#0B1437]">
                    {{ ticket.trang_thai === 'da_dong' ? 'Gửi phản hồi để mở lại Ticket (trong vòng 7 ngày)' : 'Gửi phản hồi tới đội kỹ thuật hạ tầng' }}
                </div>

                <textarea
                    v-model="replyForm.noi_dung"
                    rows="3"
                    maxlength="5000"
                    placeholder="Nhập nội dung phản hồi..."
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-[#1D6AE5] outline-none resize-y"
                />

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <ImageIcon class="w-4 h-4 text-slate-400 shrink-0" />
                        <input
                            ref="fileInputRef"
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp"
                            @change="onSelectFiles"
                            class="text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700 file:font-bold"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="replyForm.processing || (!replyForm.noi_dung.trim() && replyForm.images.length === 0)"
                        class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] disabled:opacity-45 text-white font-bold cursor-pointer transition shrink-0"
                    >
                        <Loader2 v-if="replyForm.processing" class="w-4 h-4 animate-spin" />
                        <Send v-else class="w-4 h-4" />
                        <span>{{ replyForm.processing ? 'Đang gửi...' : 'Gửi phản hồi' }}</span>
                    </button>
                </div>
            </form>

            <div v-else class="p-4 rounded-xl bg-slate-100 border border-slate-200 text-xs text-slate-600 font-semibold text-center">
                Ticket đã đóng quá 7 ngày và không thể phản hồi thêm. Hãy tạo Ticket mới nếu cần hỗ trợ tiếp.
            </div>
        </div>
    </AdminLayout>
</template>
