<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { askConfirm } from '@/Composables/useConfirmAction';
import { Plus, Edit3, Trash2, X } from 'lucide-vue-next';

defineProps<{
    knowledges: { data: Array<any>; links: Array<any> };
}>();

const modalOpen = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    title: '',
    content: '',
    is_active: true,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.is_active = true;
    modalOpen.value = true;
}

function openEdit(k: any) {
    editingId.value = k.id;
    form.title = k.title;
    form.content = k.content;
    form.is_active = k.is_active;
    modalOpen.value = true;
}

function save() {
    if (editingId.value) {
        form.put(`/admin/knowledge-bases/${editingId.value}`, { onSuccess: () => (modalOpen.value = false) });
    } else {
        form.post('/admin/knowledge-bases', { onSuccess: () => (modalOpen.value = false) });
    }
}
</script>

<template>
    <AdminLayout title="Kho Tri thức Trợ lý AI (Knowledge Base)">
        <Head title="Kho tri thức AI" />

        <div class="space-y-5">
            <div class="flex justify-end">
                <button
                    type="button"
                    @click="openCreate"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#0B1437] text-white text-xs font-semibold cursor-pointer"
                >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Thêm bài hướng dẫn AI</span>
                </button>
            </div>

            <div class="surface-card overflow-hidden">
                <div class="divide-y divide-slate-200 text-xs">
                    <div v-for="k in knowledges.data" :key="k.id" class="p-4 flex items-start justify-between gap-4">
                        <div class="space-y-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-sm">{{ k.title }}</span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold"
                                    :class="k.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ k.is_active ? 'Đang nạp cho AI' : 'Tạm ẩn' }}
                                </span>
                            </div>
                            <p class="text-slate-600 line-clamp-2 leading-relaxed">{{ k.content }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="openEdit(k)" class="p-1.5 rounded border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <Edit3 class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="
                                    askConfirm({
                                        title: 'Xóa mục tri thức AI',
                                        message: `Xóa bài hướng dẫn '${k.title}' khỏi kho tri thức AI?`,
                                        onConfirm: () => router.delete(`/admin/knowledge-bases/${k.id}`, { preserveScroll: true }),
                                    })
                                "
                                class="p-1.5 rounded border border-rose-200 hover:bg-rose-50 text-rose-600 cursor-pointer"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <PaginationBar :links="knowledges.links" />

            <Teleport to="body">
                <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="modalOpen = false">
                    <form @submit.prevent="save" class="w-full max-w-lg bg-white rounded-xl border border-slate-200 shadow-2xl p-6 space-y-4 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h3 class="text-sm font-bold text-[#0B1437]">{{ editingId ? 'Sửa tài liệu AI' : 'Thêm tài liệu cho AI Chatbot' }}</h3>
                            <button type="button" @click="modalOpen = false"><X class="w-4 h-4 text-slate-400" /></button>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Chủ đề câu hỏi</label>
                            <input v-model="form.title" type="text" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Nội dung hướng dẫn chuẩn</label>
                            <textarea v-model="form.content" rows="6" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                            <span>Cho phép AI sử dụng nội dung này để trả lời khách hàng</span>
                        </label>
                        <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                            <button type="button" @click="modalOpen = false" class="px-4 py-2 rounded-lg border border-slate-300 font-semibold">Đóng</button>
                            <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded-lg bg-[#0B1437] text-white font-semibold cursor-pointer">
                                Lưu lại
                            </button>
                        </div>
                    </form>
                </div>
            </Teleport>
        </div>
    </AdminLayout>
</template>
