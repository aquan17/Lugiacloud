<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCw, Maximize2, Keyboard, Send, Terminal } from 'lucide-vue-next';

const props = defineProps<{
    vps: { id: number; label: string; public_ip?: string };
    ticket: string;
    host: string;
    backUrl?: string;
}>();

const status = ref<'connecting' | 'connected' | 'disconnected' | 'error'>('connecting');
const errorMessage = ref('');
const pasteText = ref('');
const containerWrap = ref<HTMLElement | null>(null);

let wmksInstance: any = null;
let connectTimer: ReturnType<typeof setTimeout> | null = null;

function loadStylesheet(href: string): void {
    if (document.querySelector(`link[href="${href}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
}

function loadScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`) as HTMLScriptElement | null;
        if (existing) {
            if (existing.dataset.loaded === '1') {
                resolve();
                return;
            }
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = false;
        script.onload = () => {
            script.dataset.loaded = '1';
            resolve();
        };
        script.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(script);
    });
}

async function initWmks() {
    status.value = 'connecting';
    errorMessage.value = '';

    try {
        loadStylesheet('https://cdn.jsdelivr.net/npm/vmware-wmks@1.0.0/css/wmks-all.css');
        await loadScript('https://code.jquery.com/jquery-3.6.0.min.js');
        await loadScript('https://code.jquery.com/ui/1.13.2/jquery-ui.min.js');
        await loadScript('https://cdn.jsdelivr.net/npm/vmware-wmks@1.0.0/wmks.min.js');

        const WMKS = (window as any).WMKS;
        if (!WMKS) {
            throw new Error('Không thể khởi tạo thư viện VMware WMKS.');
        }

        const wsUrl = `wss://cusproxy.cloudzone.vn:443/ticket/${props.ticket}?host=${props.host}`;

        wmksInstance = WMKS.createWMKS('wmks-container', {
            rescale: true,
            changeResolution: false,
            useUnicodeKeyboardInput: true,
        });

        wmksInstance.register(WMKS.CONST.Events.CONNECTION_STATE_CHANGE, (_evt: any, data: any) => {
            if (data.state === WMKS.CONST.ConnectionState.CONNECTED) {
                status.value = 'connected';
            } else if (data.state === WMKS.CONST.ConnectionState.DISCONNECTED) {
                status.value = 'disconnected';
            }
        });

        connectTimer = setTimeout(() => {
            try {
                wmksInstance?.connect(wsUrl);
            } catch (e: any) {
                status.value = 'error';
                errorMessage.value = e?.message || 'Kết nối WebSocket Console thất bại.';
            }
        }, 350);
    } catch (e: any) {
        status.value = 'error';
        errorMessage.value = e?.message || 'Không tải được trình điều khiển VNC Console.';
    }
}

function sendCtrlAltDel() {
    wmksInstance?.sendCAD?.();
}

function sendPasteText() {
    if (!pasteText.value || !wmksInstance?.sendInputString) return;
    wmksInstance.sendInputString(pasteText.value);
    pasteText.value = '';
}

function toggleFullscreen() {
    if (wmksInstance?.enterFullScreen) {
        wmksInstance.enterFullScreen();
        return;
    }
    const el = containerWrap.value;
    if (!el) return;
    if (!document.fullscreenElement) {
        el.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
}

function reloadConsole() {
    window.location.reload();
}

onMounted(() => {
    initWmks();
});

onUnmounted(() => {
    if (connectTimer) clearTimeout(connectTimer);
    try {
        wmksInstance?.disconnect?.();
        wmksInstance?.destroy?.();
    } catch {
    }
    wmksInstance = null;
});
</script>

<template>
    <div class="min-h-screen bg-[#0B1120] text-white flex flex-col">
        <Head :title="`Console — ${vps.label}`" />

        <!-- Top Console Control Bar -->
        <header class="min-h-13 px-4 py-2 bg-[#0B1437] border-b border-white/10 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="backUrl || `/vps/${vps.id}`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-200 hover:text-white font-semibold transition"
                >
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Quay lại VPS</span>
                </Link>

                <div class="flex items-center gap-2">
                    <Terminal class="w-4 h-4 text-sky-400" />
                    <span class="font-bold text-white">{{ vps.label }}</span>
                    <span v-if="vps.public_ip" class="font-mono text-sky-400 font-semibold">{{ vps.public_ip }}</span>
                </div>

                <!-- Live status pill -->
                <span
                    v-if="status === 'connected'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                    <span>Đã kết nối</span>
                </span>
                <span
                    v-else-if="status === 'connecting'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" />
                    <span>Đang kết nối VNC...</span>
                </span>
                <span
                    v-else
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400" />
                    <span>{{ status === 'error' ? 'Lỗi kết nối' : 'Mất kết nối' }}</span>
                </span>
            </div>

            <!-- Console Tools -->
            <div class="flex flex-wrap items-center gap-2">
                <template v-if="status === 'connected'">
                    <button
                        type="button"
                        @click="sendCtrlAltDel"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-100 font-semibold cursor-pointer transition"
                        title="Gửi tổ hợp phím Ctrl + Alt + Delete"
                    >
                        <Keyboard class="w-3.5 h-3.5 text-sky-400" />
                        <span>Ctrl+Alt+Del</span>
                    </button>

                    <form @submit.prevent="sendPasteText" class="flex items-center bg-slate-900 border border-slate-700 rounded-lg overflow-hidden h-8">
                        <input
                            v-model="pasteText"
                            type="password"
                            placeholder="Dán mật khẩu / văn bản..."
                            class="bg-transparent border-0 text-xs text-white px-2.5 w-40 sm:w-48 focus:outline-none placeholder-slate-500 h-full font-mono"
                        />
                        <button
                            type="submit"
                            :disabled="!pasteText"
                            class="inline-flex items-center gap-1 px-2.5 h-full bg-slate-800 hover:bg-slate-700 border-l border-slate-700 text-slate-100 font-semibold cursor-pointer disabled:opacity-40 transition"
                        >
                            <Send class="w-3 h-3" />
                            <span>Paste</span>
                        </button>
                    </form>

                    <button
                        type="button"
                        @click="toggleFullscreen"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-100 font-semibold cursor-pointer transition"
                        title="Toàn màn hình"
                    >
                        <Maximize2 class="w-3.5 h-3.5" />
                        <span>Full</span>
                    </button>
                </template>

                <button
                    type="button"
                    @click="reloadConsole"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer transition"
                >
                    <RefreshCw class="w-3.5 h-3.5" />
                    <span>Kết nối lại</span>
                </button>
            </div>
        </header>

        <!-- Error Banner -->
        <div
            v-if="status === 'error' || status === 'disconnected'"
            class="px-4 py-2.5 bg-rose-950/80 border-b border-rose-800/60 text-xs text-rose-200 flex items-center justify-between gap-3"
        >
            <span>
                {{ errorMessage || 'Phiên VNC Console đã ngắt hoặc hết hạn ticket. Nhấn "Kết nối lại" để cấp phiên mới.' }}
            </span>
            <button
                type="button"
                @click="reloadConsole"
                class="px-3 py-1 rounded-md bg-rose-600 hover:bg-rose-500 text-white font-bold cursor-pointer shrink-0"
            >
                Tải lại phiên mới
            </button>
        </div>

        <!-- WMKS Console Viewport -->
        <div ref="containerWrap" class="relative flex-1 w-full bg-black min-h-[calc(100vh-56px)] overflow-hidden">
            <div id="wmks-container" class="absolute inset-0 w-full h-full" />
        </div>
    </div>
</template>

<style scoped>
#wmks-container :deep(canvas) {
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
}
</style>
