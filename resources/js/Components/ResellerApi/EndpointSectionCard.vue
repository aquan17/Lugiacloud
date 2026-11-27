<script setup lang="ts">
import CodeBlockCard from '@/Components/ResellerApi/CodeBlockCard.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { generateCodeSnippet, type ApiEndpointSpec, type CodeLang } from '@/Data/resellerApiSpec';
import { Copy, AlertTriangle, Layers, Cpu } from 'lucide-vue-next';

const props = defineProps<{
    doc: ApiEndpointSpec;
    baseUrl: string;
    sampleToken: string;
    codeLang: CodeLang;
    catalogPlans?: Array<{
        id: number | string;
        slug: string;
        name: string;
        cpu: number;
        ram: number;
        disk: number;
        network_mbps: number;
        price_monthly: number;
    }>;
    osList?: Array<{
        id: number;
        name: string;
        group: string;
    }>;
}>();

const emit = defineEmits<{
    (e: 'update:codeLang', val: CodeLang): void;
}>();

const toast = useToast();

async function quickCopy(val: string, label: string) {
    if (await copyToClipboard(val)) {
        toast.success(`Đã sao chép ${label}`);
    }
}
</script>

<template>
    <section :id="`sec-${doc.id}`" class="surface-card p-6 sm:p-8 space-y-6 scroll-mt-[84px]">
        <!-- Endpoint Header + URL Copy Pill -->
        <div class="space-y-3 pb-5 border-b border-slate-200/90">
            <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200/90">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span
                        class="px-2.5 py-1 rounded-md font-mono text-xs font-extrabold whitespace-nowrap"
                        :class="
                            doc.isDestructive
                                ? 'bg-rose-100 text-rose-800'
                                : doc.method === 'GET'
                                  ? 'bg-sky-100 text-sky-800'
                                  : 'bg-emerald-100 text-emerald-800'
                        "
                    >
                        {{ doc.method }}
                    </span>
                    <code class="text-[14px] sm:text-[14.5px] font-mono font-bold text-[#0B1437] break-all">
                        {{ baseUrl }}{{ doc.path }}
                    </code>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="px-2.5 py-1 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 font-mono text-xs font-bold whitespace-nowrap">
                        {{ doc.httpSuccess }}
                    </span>
                    <button
                        type="button"
                        @click="quickCopy(`${baseUrl}${doc.path}`, 'Endpoint URL')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700 cursor-pointer whitespace-nowrap"
                    >
                        <Copy class="w-3.5 h-3.5" />
                        <span>Copy URL</span>
                    </button>
                </div>
            </div>

            <h2 class="text-lg sm:text-[20px] font-extrabold text-[#0B1437] tracking-tight pt-1">
                {{ doc.title }}
            </h2>
            <p class="text-[15px] text-slate-700 leading-7">
                {{ doc.summary }}
            </p>
        </div>

        <!-- Bảng tra cứu trực tiếp plan_id & os_id thực tế ngay trong mục GET /plans -->
        <div v-if="doc.id === 'get-plans' && catalogPlans && osList" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Layers class="w-4 h-4 text-[#1D6AE5]" />
                        <span class="text-[13px] font-extrabold uppercase tracking-wide text-slate-700">
                            Bảng mã `plan_id` hiện hành
                        </span>
                    </div>
                    <span class="text-xs text-slate-500">Bấm mã để chép</span>
                </div>
                <!-- Mobile Cards -->
                <div class="sm:hidden divide-y divide-slate-100">
                    <div v-for="pl in catalogPlans" :key="`m-${pl.slug}`" class="p-3.5 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <button
                                type="button"
                                @click="quickCopy(pl.slug, `mã gói ${pl.slug}`)"
                                class="px-2 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-[#1D6AE5] font-mono font-bold cursor-pointer"
                            >
                                "{{ pl.slug }}"
                            </button>
                            <span class="font-mono-num font-bold text-[#0B1437]">{{ formatVnd(pl.price_monthly) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 text-slate-600">
                            <span class="font-semibold text-slate-800">{{ pl.name }}</span>
                            <span class="font-mono-num">{{ pl.cpu }}C · {{ pl.ram }}G · {{ pl.disk }}G SSD</span>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-[13.5px]">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs uppercase text-slate-400 bg-slate-50/40">
                                <th class="py-2.5 px-4 whitespace-nowrap">plan_id</th>
                                <th class="py-2.5 px-4 whitespace-nowrap">Tên gói</th>
                                <th class="py-2.5 px-4 whitespace-nowrap">Cấu hình</th>
                                <th class="py-2.5 px-4 text-right whitespace-nowrap">Giá của bạn</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="pl in catalogPlans" :key="pl.slug" class="hover:bg-slate-50/70">
                                <td class="py-2.5 px-4 whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="quickCopy(pl.slug, `mã gói ${pl.slug}`)"
                                        class="px-2 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-[#1D6AE5] font-mono font-bold text-[13px] cursor-pointer"
                                    >
                                        "{{ pl.slug }}"
                                    </button>
                                </td>
                                <td class="py-2.5 px-4 font-semibold text-slate-800 whitespace-nowrap">{{ pl.name }}</td>
                                <td class="py-2.5 px-4 font-mono-num text-slate-600 whitespace-nowrap">
                                    {{ pl.cpu }}C · {{ pl.ram }}G · {{ pl.disk }}G SSD
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono-num font-bold text-[#0B1437] whitespace-nowrap">
                                    {{ formatVnd(pl.price_monthly) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Cpu class="w-4 h-4 text-[#1D6AE5]" />
                        <span class="text-[13px] font-extrabold uppercase tracking-wide text-slate-700">
                            Bảng mã `os_id` Hệ điều hành
                        </span>
                    </div>
                    <span class="text-xs text-slate-500">{{ osList.length }} bản cài đặt</span>
                </div>
                <div class="max-h-[310px] overflow-y-auto">
                    <!-- Mobile Cards -->
                    <div class="sm:hidden divide-y divide-slate-100">
                        <div v-for="os in osList" :key="`m-${os.id}`" class="p-3 flex items-center justify-between gap-2.5 text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <button
                                    type="button"
                                    @click="quickCopy(String(os.id), `os_id ${os.id}`)"
                                    class="px-2 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-[#1D6AE5] font-mono-num font-bold cursor-pointer shrink-0"
                                >
                                    {{ os.id }}
                                </button>
                                <span class="font-medium text-slate-800 truncate">{{ os.name }}</span>
                            </div>
                            <span class="text-slate-500 text-[11px] shrink-0">{{ os.group }}</span>
                        </div>
                    </div>
                    <table class="hidden sm:table w-full text-left border-collapse text-[13.5px]">
                        <thead class="sticky top-0 bg-slate-50 border-b border-slate-200/80 text-xs uppercase text-slate-400">
                            <tr>
                                <th class="py-2.5 px-4 whitespace-nowrap">os_id</th>
                                <th class="py-2.5 px-4">Tên Hệ điều hành</th>
                                <th class="py-2.5 px-4 text-right whitespace-nowrap">Nhóm</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="os in osList" :key="os.id" class="hover:bg-slate-50/70">
                                <td class="py-2.5 px-4 whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="quickCopy(String(os.id), `os_id ${os.id}`)"
                                        class="px-2 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-[#1D6AE5] font-mono-num font-bold text-[13px] cursor-pointer"
                                    >
                                        {{ os.id }}
                                    </button>
                                </td>
                                <td class="py-2.5 px-4 font-medium text-slate-800">{{ os.name }}</td>
                                <td class="py-2.5 px-4 text-right text-slate-500 whitespace-nowrap">{{ os.group }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Danh sách Tham số chuẩn Stripe Docs (Không bị bóp cột hay gãy chữ) -->
        <div v-if="doc.params && doc.params.length > 0" class="space-y-3">
            <h3 class="text-[13px] font-extrabold uppercase tracking-wider text-slate-600">
                Tham số truyền vào (Parameters)
            </h3>
            <div class="rounded-xl border border-slate-200 divide-y divide-slate-200/80 bg-slate-50/40">
                <div v-for="param in doc.params" :key="param.name" class="p-4 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <code class="text-[14.5px] font-mono font-bold text-[#0B1437]">{{ param.name }}</code>
                        <span class="text-[13px] font-mono text-slate-500">{{ param.type }}</span>
                        <span class="px-2 py-0.5 rounded bg-slate-200/70 text-slate-600 font-mono text-[11px] font-semibold whitespace-nowrap">
                            {{ param.location === 'path' ? 'URL Path' : 'JSON Body' }}
                        </span>
                        <span
                            class="px-2 py-0.5 rounded-md text-xs font-bold whitespace-nowrap"
                            :class="param.required ? 'bg-rose-50 text-rose-700 border border-rose-200/80' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ param.required ? 'Bắt buộc' : 'Tùy chọn' }}
                        </span>
                    </div>
                    <p class="text-[14px] text-slate-700 leading-relaxed">
                        {{ param.desc }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Đoạn mã gọi mẫu đa ngôn ngữ -->
        <CodeBlockCard
            title="Đoạn mã gọi mẫu (Request Example)"
            :code="generateCodeSnippet(doc, baseUrl, sampleToken, codeLang)"
            :copy-label="`code ${codeLang.toUpperCase()}`"
            :lang="codeLang"
            show-lang-tabs
            @update:lang="emit('update:codeLang', $event)"
        />

        <!-- Cặp JSON Request & JSON Response đặt cạnh nhau -->
        <div class="grid grid-cols-1 gap-4" :class="doc.requestJson ? 'xl:grid-cols-2' : ''">
            <CodeBlockCard
                v-if="doc.requestJson"
                title="Mẫu JSON gửi đi (Request Body)"
                :code="doc.requestJson"
                copy-label="JSON Request"
                tone="request"
            />
            <CodeBlockCard
                title="Mẫu JSON trả về (Success Response)"
                :code="doc.responseJson"
                copy-label="JSON Response"
                tone="success"
                :badge="doc.httpSuccess"
            />
        </div>

        <div
            v-if="doc.note"
            class="p-4 rounded-xl bg-amber-50/90 border border-amber-200 flex items-start gap-3 text-[14px] text-amber-950 leading-relaxed"
        >
            <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <span>{{ doc.note }}</span>
        </div>
    </section>
</template>
