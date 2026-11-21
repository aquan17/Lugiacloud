export function formatVnd(amount: number | string | null | undefined): string {
    const n = Number(amount ?? 0);
    if (Number.isNaN(n)) return '0đ';
    return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ';
}

export function formatCompactVnd(amount: number | string | null | undefined): string {
    const n = Number(amount ?? 0);
    if (Number.isNaN(n) || n === 0) return '0đ';
    if (Math.abs(n) >= 1_000_000 && n % 100_000 === 0) {
        return (n / 1_000_000).toFixed(n % 1_000_000 === 0 ? 0 : 1) + 'tr';
    }
    if (Math.abs(n) >= 1000 && n % 1000 === 0) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(n / 1000)) + 'K';
    }
    return formatVnd(n);
}

export async function copyToClipboard(text: string): Promise<boolean> {
    if (!text) return false;
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const el = document.createElement('textarea');
        el.value = text;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(el);
        return ok;
    }
}

export function downloadTextFile(filename: string, content: string, mime = 'text/plain;charset=utf-8'): void {
    const blob = new Blob([content], { type: mime });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
