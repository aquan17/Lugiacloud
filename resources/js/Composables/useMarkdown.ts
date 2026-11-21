export interface TocItem {
    id: string;
    text: string;
    level: 2 | 3;
}

export interface RenderedMarkdown {
    html: string;
    toc: TocItem[];
}

function escapeHtml(raw: string): string {
    return raw
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function slugifyHeading(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'muc';
}

function isSafeUrl(url: string, allowBlob = false): boolean {
    const trimmed = url.trim();
    return (
        trimmed.startsWith('https://') ||
        trimmed.startsWith('http://') ||
        trimmed.startsWith('/') ||
        trimmed.startsWith('#') ||
        (allowBlob && trimmed.startsWith('blob:'))
    );
}

function renderInline(escaped: string, allowBlob = false): string {
    // Inline code first (protect from further formatting)
    const codeTokens: string[] = [];
    let out = escaped.replace(/`([^`]+)`/g, (_, code) => {
        const idx = codeTokens.length;
        codeTokens.push(
            `<code class="px-1.5 py-0.5 rounded-md bg-slate-100 border border-slate-200/80 font-mono text-[12.5px] font-semibold text-[#0B1437]">${code}</code>`
        );
        return `@@CODE_${idx}@@`;
    });

    // Images: ![alt](url)
    out = out.replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, (_, alt, rawUrl) => {
        if (!isSafeUrl(rawUrl, allowBlob)) return '';
        return `<figure class="my-5"><img src="${rawUrl}" alt="${alt}" loading="lazy" decoding="async" class="w-full rounded-xl border border-slate-200/90 shadow-xs object-cover" />${
            alt ? `<figcaption class="text-center text-xs text-slate-500 mt-2">${alt}</figcaption>` : ''
        }</figure>`;
    });

    // Self-hosted MP4/WebM clip: [video](https://cdn.lugiacloud.com/...mp4)
    out = out.replace(/\[video\]\(([^)\s]+)\)/gi, (_, rawUrl) => {
        if (!isSafeUrl(rawUrl, allowBlob)) return '';
        return `<div class="my-5 rounded-xl overflow-hidden border border-slate-200 bg-slate-950"><video controls preload="metadata" playsinline class="w-full max-h-[460px]"><source src="${rawUrl}" /></video></div>`;
    });

    // Links: [label](url)
    out = out.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_, label, rawUrl) => {
        if (!isSafeUrl(rawUrl, false)) return label;
        const isExternal = rawUrl.startsWith('http://') || rawUrl.startsWith('https://');
        const attrs = isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
        return `<a href="${rawUrl}"${attrs} class="font-semibold text-[#1D6AE5] hover:text-[#1557C0] transition-colors">${label}</a>`;
    });

    // Bold & Italic
    out = out.replace(/\*\*([^*]+)\*\*/g, '<strong class="font-bold text-[#0B1437]">$1</strong>');
    out = out.replace(/\*([^*]+)\*/g, '<em class="italic">$1</em>');

    // Restore inline code tokens
    out = out.replace(/@@CODE_(\d+)@@/g, (_, idx) => codeTokens[Number(idx)] || '');
    return out;
}

export function renderMarkdown(markdown: string, options: { allowBlobUrls?: boolean } = {}): RenderedMarkdown {
    const allowBlob = Boolean(options.allowBlobUrls);
    const lines = (markdown || '').replace(/\r\n/g, '\n').split('\n');
    const htmlParts: string[] = [];
    const toc: TocItem[] = [];
    const usedIds = new Set<string>();

    let i = 0;
    while (i < lines.length) {
        const line = lines[i];
        const trimmed = line.trim();

        // Empty line
        if (trimmed === '') {
            i++;
            continue;
        }

        // Fenced code block ```
        if (trimmed.startsWith('```')) {
            const lang = escapeHtml(trimmed.slice(3).trim());
            const codeLines: string[] = [];
            i++;
            while (i < lines.length && !lines[i].trim().startsWith('```')) {
                codeLines.push(lines[i]);
                i++;
            }
            i++; // skip closing ```
            htmlParts.push(
                `<div class="my-4 rounded-xl overflow-hidden bg-[#0B1437] border border-slate-800 shadow-xs">` +
                    (lang
                        ? `<div class="px-4 py-1.5 bg-slate-950/60 border-b border-white/10 text-[10.5px] font-mono uppercase tracking-wider text-slate-400">${lang}</div>`
                        : '') +
                    `<pre class="p-4 overflow-x-auto text-xs sm:text-[13px] font-mono text-sky-200 leading-relaxed"><code>${escapeHtml(codeLines.join('\n'))}</code></pre></div>`
            );
            continue;
        }

        // Headings ## and ###
        const headingMatch = trimmed.match(/^(#{2,3})\s+(.+)$/);
        if (headingMatch) {
            const level = headingMatch[1].length as 2 | 3;
            const rawText = headingMatch[2].trim();
            const plainText = rawText.replace(/[*`[\]]/g, '');
            let id = slugifyHeading(plainText);
            let suffix = 2;
            while (usedIds.has(id)) {
                id = `${slugifyHeading(plainText)}-${suffix++}`;
            }
            usedIds.add(id);
            toc.push({ id, text: plainText, level });

            if (level === 2) {
                htmlParts.push(
                    `<h2 id="${id}" class="scroll-mt-24 text-lg sm:text-xl font-extrabold text-[#0B1437] tracking-tight mt-8 mb-3 pb-2 border-b border-slate-200/80">${renderInline(escapeHtml(rawText), allowBlob)}</h2>`
                );
            } else {
                htmlParts.push(
                    `<h3 id="${id}" class="scroll-mt-24 text-base sm:text-[17px] font-bold text-[#0B1437] mt-6 mb-2.5">${renderInline(escapeHtml(rawText), allowBlob)}</h3>`
                );
            }
            i++;
            continue;
        }

        // Blockquote >
        if (trimmed.startsWith('>')) {
            const quoteLines: string[] = [];
            while (i < lines.length && lines[i].trim().startsWith('>')) {
                quoteLines.push(lines[i].trim().replace(/^>\s?/, ''));
                i++;
            }
            htmlParts.push(
                `<blockquote class="my-4 p-4 rounded-xl bg-blue-50/70 border-l-4 border-[#1D6AE5] text-[13.5px] text-slate-700 leading-relaxed space-y-1.5">${quoteLines
                    .map((q) => `<p>${renderInline(escapeHtml(q), allowBlob)}</p>`)
                    .join('')}</blockquote>`
            );
            continue;
        }

        // Unordered list - or *
        if (/^[-*]\s+/.test(trimmed)) {
            const items: string[] = [];
            while (i < lines.length && /^[-*]\s+/.test(lines[i].trim())) {
                items.push(lines[i].trim().replace(/^[-*]\s+/, ''));
                i++;
            }
            htmlParts.push(
                `<ul class="my-3.5 pl-5 list-disc space-y-2 text-[14.5px] text-slate-700 leading-relaxed">${items
                    .map((item) => `<li>${renderInline(escapeHtml(item), allowBlob)}</li>`)
                    .join('')}</ul>`
            );
            continue;
        }

        // Ordered list 1. 2.
        if (/^\d+\.\s+/.test(trimmed)) {
            const items: string[] = [];
            while (i < lines.length && /^\d+\.\s+/.test(lines[i].trim())) {
                items.push(lines[i].trim().replace(/^\d+\.\s+/, ''));
                i++;
            }
            htmlParts.push(
                `<ol class="my-3.5 pl-5 list-decimal space-y-2 text-[14.5px] text-slate-700 leading-relaxed">${items
                    .map((item) => `<li>${renderInline(escapeHtml(item), allowBlob)}</li>`)
                    .join('')}</ol>`
            );
            continue;
        }

        // Markdown Table (| col1 | col2 | followed by | --- | --- |)
        if (
            trimmed.startsWith('|') &&
            i + 1 < lines.length &&
            /^\|[\s:|-]+\|$/.test(lines[i + 1].trim())
        ) {
            const parseRow = (r: string) =>
                r
                    .trim()
                    .replace(/^\||\|$/g, '')
                    .split('|')
                    .map((cell) => renderInline(escapeHtml(cell.trim()), allowBlob));
            const headers = parseRow(trimmed);
            i += 2; // skip header + separator
            const bodyRows: string[][] = [];
            while (i < lines.length && lines[i].trim().startsWith('|')) {
                bodyRows.push(parseRow(lines[i]));
                i++;
            }
            const theadHtml = `<thead class="bg-slate-50 border-b border-slate-200"><tr>${headers
                .map((h) => `<th class="py-2.5 px-3.5 text-left text-xs font-extrabold text-[#0B1437]">${h}</th>`)
                .join('')}</tr></thead>`;
            const tbodyHtml = `<tbody class="divide-y divide-slate-200/80">${bodyRows
                .map(
                    (row) =>
                        `<tr class="hover:bg-slate-50/60">${row
                            .map((c) => `<td class="py-2.5 px-3.5 text-xs sm:text-[13px] text-slate-700">${c}</td>`)
                            .join('')}</tr>`
                )
                .join('')}</tbody>`;
            htmlParts.push(
                `<div class="my-5 overflow-x-auto rounded-xl border border-slate-200"><table class="w-full border-collapse">${theadHtml}${tbodyHtml}</table></div>`
            );
            continue;
        }

        // Standalone image or video line
        if (/^!\[[^\]]*\]\([^)\s]+\)$/.test(trimmed) || /^\[video\]\([^)\s]+\)$/i.test(trimmed)) {
            htmlParts.push(renderInline(escapeHtml(trimmed), allowBlob));
            i++;
            continue;
        }

        // Normal paragraph (consume consecutive plain lines)
        const paraLines: string[] = [];
        while (
            i < lines.length &&
            lines[i].trim() !== '' &&
            !lines[i].trim().startsWith('```') &&
            !/^#{2,3}\s+/.test(lines[i].trim()) &&
            !lines[i].trim().startsWith('>') &&
            !/^[-*]\s+/.test(lines[i].trim()) &&
            !/^\d+\.\s+/.test(lines[i].trim())
        ) {
            paraLines.push(lines[i].trim());
            i++;
        }
        htmlParts.push(
            `<p class="my-3.5 text-[14.5px] sm:text-[15px] text-slate-700 leading-[1.8]">${renderInline(escapeHtml(paraLines.join(' ')), allowBlob)}</p>`
        );
    }

    return {
        html: htmlParts.join('\n'),
        toc,
    };
}
