<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Bell, AlertTriangle } from 'lucide-vue-next';

defineProps<{
    alerts: Array<{
        id: string;
        level: string;
        title: string;
        body: string;
        href: string;
        time: string;
    }>;
}>();
</script>

<template>
    <ClientLayout title="Cảnh báo dịch vụ">
        <Head title="Thông báo & Cảnh báo" />

        <div class="max-w-3xl surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
            <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex items-center gap-2">
                <Bell class="w-4 h-4 text-[#1D6AE5]" />
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800">Thông báo trạng thái dịch vụ</h2>
            </div>

            <div v-if="alerts.length === 0" class="p-12 text-center text-xs text-slate-600 font-medium">
                Hiện không có cảnh báo sắp hết hạn nào.
            </div>

            <div v-else class="divide-y divide-slate-200/80 text-xs">
                <Link
                    v-for="a in alerts"
                    :key="a.id"
                    :href="a.href"
                    class="p-4 flex items-start gap-3 hover:bg-slate-50/80 transition"
                >
                    <AlertTriangle
                        class="w-4 h-4 shrink-0 mt-0.5"
                        :class="a.level === 'critical' ? 'text-rose-600' : 'text-amber-600'"
                    />
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-[#0B1437]">{{ a.title }}</div>
                        <div class="text-slate-600 font-mono-num font-medium mt-0.5">{{ a.body }}</div>
                    </div>
                    <span class="text-xs font-mono-num font-medium text-slate-600 shrink-0">{{ a.time }}</span>
                </Link>
            </div>
        </div>
    </ClientLayout>
</template>
