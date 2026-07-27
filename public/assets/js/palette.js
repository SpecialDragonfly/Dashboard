const SAMPLE_WIDTH = 200;
const SAMPLE_HEIGHT = 112;

const TOKENS = ['--bg-start', '--bg-end', '--header-bg', '--accent', '--accent-2'];

document.getElementById('palette-file').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const img = document.getElementById('palette-source-img');
    img.onload = () => {
        img.style.display = '';
        const palette = extractPalette(img);
        applyPalette(palette);
        renderSwatches(palette);
        renderOutput(palette);
        URL.revokeObjectURL(img.src);
    };
    img.src = URL.createObjectURL(file);
});

document.getElementById('palette-copy-btn').addEventListener('click', async () => {
    const text = document.getElementById('palette-output-text').value;
    const btn = document.getElementById('palette-copy-btn');
    try {
        await navigator.clipboard.writeText(text);
        btn.textContent = 'Copied';
    } catch {
        btn.textContent = 'Copy failed';
    }
    setTimeout(() => { btn.textContent = 'Copy'; }, 1500);
});

const QUANTIZE_COLOURS = 24;

function extractPalette(img) {
    const canvas = document.getElementById('palette-canvas');
    const scale = Math.min(SAMPLE_WIDTH / img.naturalWidth, SAMPLE_HEIGHT / img.naturalHeight, 1);
    const w = Math.max(1, Math.round(img.naturalWidth * scale));
    const h = Math.max(1, Math.round(img.naturalHeight * scale));
    canvas.width = w;
    canvas.height = h;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, w, h);
    const data = ctx.getImageData(0, 0, w, h).data;

    const pixels = [];
    for (let i = 0; i < data.length; i += 4) {
        pixels.push({ r: data[i], g: data[i + 1], b: data[i + 2] });
    }

    // Median-cut quantization: repeatedly split the most-populous colour
    // bucket along its widest channel, so each resulting cluster's average
    // colour genuinely represents a chunk of the image rather than one
    // arbitrary pixel.
    const clusters = quantize(pixels, QUANTIZE_COLOURS).map((c) => {
        const [hue, sat, light] = rgbToHsl(c.r, c.g, c.b);
        return { ...c, h: hue, s: sat, l: light };
    });
    clusters.sort((a, b) => a.l - b.l);

    const bgStart = weightedPercentile(clusters, 0.02);
    const bgEnd = weightedPercentile(clusters, 0.18);

    const darkerHalf = weightedHead(clusters, 0.5);
    const headerBg = mostSaturated(darkerHalf) ?? bgEnd;

    const midRange = clusters.filter((c) => c.l >= 0.25 && c.l <= 0.75);
    const accent = mostSaturated(midRange) ?? mostSaturated(clusters) ?? bgEnd;

    const accent2Rgb = hslToRgb(accent.h, accent.s, Math.max(0.05, accent.l - 0.14));

    return {
        '--bg-start': toHex(bgStart.r, bgStart.g, bgStart.b),
        '--bg-end': toHex(bgEnd.r, bgEnd.g, bgEnd.b),
        '--header-bg': toHex(headerBg.r, headerBg.g, headerBg.b),
        '--accent': toHex(accent.r, accent.g, accent.b),
        '--accent-2': toHex(accent2Rgb[0], accent2Rgb[1], accent2Rgb[2]),
    };
}

/**
 * Median-cut colour quantization. Returns up to `maxColours` clusters,
 * each `{ r, g, b, count }` where r/g/b is the population-average colour
 * of every pixel assigned to that cluster and `count` is how many pixels
 * that is — bigger count means more dominant in the source image.
 */
function quantize(pixels, maxColours) {
    const boxes = [pixels];

    while (boxes.length < maxColours) {
        let splitIdx = -1, splitSize = 1;
        for (let i = 0; i < boxes.length; i++) {
            if (boxes[i].length > splitSize) {
                splitSize = boxes[i].length;
                splitIdx = i;
            }
        }
        if (splitIdx === -1) break; // every box down to a single pixel

        const box = boxes[splitIdx];
        const channel = widestChannel(box);
        box.sort((a, b) => a[channel] - b[channel]);
        const mid = Math.floor(box.length / 2);
        boxes.splice(splitIdx, 1, box.slice(0, mid), box.slice(mid));
    }

    return boxes.map(averageColour);
}

function widestChannel(pixels) {
    let rMin = 255, rMax = 0, gMin = 255, gMax = 0, bMin = 255, bMax = 0;
    for (const p of pixels) {
        if (p.r < rMin) rMin = p.r; if (p.r > rMax) rMax = p.r;
        if (p.g < gMin) gMin = p.g; if (p.g > gMax) gMax = p.g;
        if (p.b < bMin) bMin = p.b; if (p.b > bMax) bMax = p.b;
    }
    const rRange = rMax - rMin, gRange = gMax - gMin, bRange = bMax - bMin;
    if (rRange >= gRange && rRange >= bRange) return 'r';
    if (gRange >= rRange && gRange >= bRange) return 'g';
    return 'b';
}

function averageColour(pixels) {
    let r = 0, g = 0, b = 0;
    for (const p of pixels) { r += p.r; g += p.g; b += p.b; }
    const n = pixels.length;
    return { r: Math.round(r / n), g: Math.round(g / n), b: Math.round(b / n), count: n };
}

/** The cluster whose cumulative population share (from the darkest end of a lightness-sorted list) first reaches `p`. */
function weightedPercentile(sortedClusters, p) {
    const total = sortedClusters.reduce((sum, c) => sum + c.count, 0);
    const target = total * p;
    let cum = 0;
    for (const c of sortedClusters) {
        cum += c.count;
        if (cum >= target) return c;
    }
    return sortedClusters[sortedClusters.length - 1];
}

/** Clusters from the darkest end of a lightness-sorted list, up to a cumulative population share of `fraction`. */
function weightedHead(sortedClusters, fraction) {
    const total = sortedClusters.reduce((sum, c) => sum + c.count, 0);
    const target = total * fraction;
    const result = [];
    let cum = 0;
    for (const c of sortedClusters) {
        result.push(c);
        cum += c.count;
        if (cum >= target) break;
    }
    return result;
}

function mostSaturated(clusters) {
    if (clusters.length === 0) return null;
    return clusters.reduce((best, c) => (c.s > best.s ? c : best), clusters[0]);
}

function applyPalette(palette) {
    for (const token of TOKENS) {
        document.documentElement.style.setProperty(token, palette[token]);
    }
}

function renderSwatches(palette) {
    const container = document.getElementById('palette-swatches');
    container.style.display = '';
    container.innerHTML = TOKENS.map((token) => `
        <div class="palette-swatch">
            <div class="palette-swatch-colour" style="background:${escHtml(palette[token])}"></div>
            <span class="palette-swatch-name">${escHtml(token)}</span>
            <span class="palette-swatch-value">${escHtml(palette[token])}</span>
        </div>
    `).join('');

    document.getElementById('palette-samples').style.display = '';
}

function renderOutput(palette) {
    const output = document.getElementById('palette-output');
    output.style.display = '';
    document.getElementById('palette-output-text').value =
        TOKENS.map((token) => `    ${token}: ${palette[token]};`).join('\n');
}

function rgbToHsl(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h = 0, s = 0;
    const l = (max + min) / 2;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
    }

    return [h, s, l];
}

function hslToRgb(h, s, l) {
    if (s === 0) {
        const v = Math.round(l * 255);
        return [v, v, v];
    }

    const hue2rgb = (p, q, t) => {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
    };

    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;
    const r = hue2rgb(p, q, h + 1 / 3);
    const g = hue2rgb(p, q, h);
    const b = hue2rgb(p, q, h - 1 / 3);

    return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
}

function toHex(r, g, b) {
    return '#' + [r, g, b].map((c) => c.toString(16).padStart(2, '0')).join('');
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
