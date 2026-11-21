export interface VpsCredentialItem {
    id: number;
    label: string;
    public_ip?: string | null;
    login_username?: string | null;
    root_password?: string | null;
    os_distro?: string;
    expires_at?: string | null;
}

export function formatFullVpsCredential(v: VpsCredentialItem): string {
    const ip = v.public_ip || 'pending-ip';
    const user = v.login_username || (v.os_distro === 'windows' ? 'Administrator' : 'root');
    const pass = v.root_password || '';
    return `${ip}|${user}|${pass}`;
}

export function downloadRdpFile(ip: string, username = 'Administrator', label = 'lugiacloud-vps'): void {
    const rdpContent = [
        'screen mode id:i:2',
        'use multimon:i:0',
        `full address:s:${ip}`,
        'audiomode:i:0',
        'redirectprinters:i:0',
        'redirectclipboard:i:1',
        'autoreconnection enabled:i:1',
        'authentication level:i:2',
        'prompt for credentials:i:1',
        'negotiate security layer:i:1',
        `username:s:${username}`,
    ].join('\r\n');

    const safeName = label.replace(/[^a-zA-Z0-9_-]/g, '_') || ip.replace(/\./g, '_');
    const blob = new Blob([rdpContent], { type: 'application/x-rdp;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${safeName}-${ip}.rdp`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

export function downloadVpsListTxt(items: VpsCredentialItem[]): void {
    const lines = items.map((v) => `${formatFullVpsCredential(v)} | ${v.label} | HSD: ${v.expires_at || '—'}`);
    const header = `# LugiaCloud VPS Export (${items.length} máy chủ) — Định dạng: IP|Username|Password | Tên | HSD\r\n`;
    const blob = new Blob([header + lines.join('\r\n')], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const dateStr = new Date().toISOString().slice(0, 10);
    link.href = url;
    link.download = `lugiacloud-vps-${dateStr}.txt`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
