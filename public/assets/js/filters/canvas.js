/**
 * public/assets/js/filters/canvas.js — Atomic filter implementations
 *
 * Each function takes an ImageData object and a params object, applies the
 * filter in-place, and returns the mutated ImageData. No function creates a
 * new ImageData — they all operate on the pixel buffer directly.
 *
 * Filter IDs match the database rows in pipeline_steps.filter_id:
 *
 *   1  — gaussianBlur   { intensity: 1–50 }
 *   2  — grayscale      {}
 *   3  — sepia          {}
 *   4  — brightness     { level: -255–255 }
 *   5  — contrast       { level: -255–255 }
 *   6  — saturation     { level: -100–0 }
 *   7  — hueRotate      { angle: 0–360 }
 *   8  — vignette       {}
 *   9  — digitalNoise   { intensity: 1–100 }
 *   10 — edgeDetect     {}
 *
 * All pixel loops use a flat Uint8ClampedArray where each pixel occupies
 * four consecutive bytes: [R, G, B, A]. Alpha is always preserved unchanged
 * unless the filter explicitly needs to modify it.
 */

export function gaussianBlur(imageData, params = {}) {
    const radius = Math.max(1, Math.min(50, params.intensity ?? 5));
    const passes = 3;

    for (let i = 0; i < passes; i++) {
        _boxBlurH(imageData, radius);
        _boxBlurV(imageData, radius);
    }

    return imageData;
}

function _boxBlurH(imageData, radius) {
    const { data, width, height } = imageData;
    const tmp = new Uint8ClampedArray(data);
    const diameter = radius * 2 + 1;

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            let r = 0, g = 0, b = 0, count = 0;

            for (let kx = -radius; kx <= radius; kx++) {
                const px = Math.min(width - 1, Math.max(0, x + kx));
                const idx = (y * width + px) * 4;
                r += tmp[idx];
                g += tmp[idx + 1];
                b += tmp[idx + 2];
                count++;
            }

            const idx = (y * width + x) * 4;
            data[idx] = r / count;
            data[idx + 1] = g / count;
            data[idx + 2] = b / count;
        }
    }
}

function _boxBlurV(imageData, radius) {
    const { data, width, height } = imageData;
    const tmp = new Uint8ClampedArray(data);

    for (let x = 0; x < width; x++) {
        for (let y = 0; y < height; y++) {
            let r = 0, g = 0, b = 0, count = 0;

            for (let ky = -radius; ky <= radius; ky++) {
                const py = Math.min(height - 1, Math.max(0, y + ky));
                const idx = (py * width + x) * 4;
                r += tmp[idx];
                g += tmp[idx + 1];
                b += tmp[idx + 2];
                count++;
            }

            const idx = (y * width + x) * 4;
            data[idx] = r / count;
            data[idx + 1] = g / count;
            data[idx + 2] = b / count;
        }
    }
}

export function grayscale(imageData, params = {}) {
    const { data } = imageData;

    for (let i = 0; i < data.length; i += 4) {
        const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];

        data[i] = lum;
        data[i + 1] = lum;
        data[i + 2] = lum;
    }

    return imageData;
}

export function sepia(imageData, params = {}) {
    const { data } = imageData;

    for (let i = 0; i < data.length; i += 4) {
        const r = data[i], g = data[i + 1], b = data[i + 2];

        data[i] = Math.min(255, r * 0.393 + g * 0.769 + b * 0.189);
        data[i + 1] = Math.min(255, r * 0.349 + g * 0.686 + b * 0.168);
        data[i + 2] = Math.min(255, r * 0.272 + g * 0.534 + b * 0.131);
    }

    return imageData;
}

export function brightness(imageData, params = {}) {
    const level = Math.max(-255, Math.min(255, params.level ?? 20));
    const { data } = imageData;

    for (let i = 0; i < data.length; i += 4) {
        data[i] += level;
        data[i + 1] += level;
        data[i + 2] += level;
    }

    return imageData;
}

export function contrast(imageData, params = {}) {
    const level = Math.max(-255, Math.min(255, params.level ?? 30));
    const factor = (259 * (level + 255)) / (255 * (259 - level));
    const { data } = imageData;

    for (let i = 0; i < data.length; i += 4) {
        data[i] = factor * (data[i] - 128) + 128;
        data[i + 1] = factor * (data[i + 1] - 128) + 128;
        data[i + 2] = factor * (data[i + 2] - 128) + 128;
    }

    return imageData;
}

export function saturation(imageData, params = {}) {
    const level = Math.max(-100, Math.min(0 , params.level ?? -50));
    const factor = -level / 100;
    const { data } = imageData;

    for (let i = 0; i < data.length; i += 4) {
        const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
        data[i] = data[i] + factor * (lum - data[i]);
        data[i + 1] = data[i + 1] + factor * (lum - data[i + 1]);
        data[i + 2] = data[i + 2] + factor * (lum - data[i + 2]);
    }

    return imageData;
}

export function hueRotate(imageData, params = {}) {
    const angle = ((params.angle ?? 180) % 360 + 360) % 360;
    if (angle === 0) {
        return imageData;
    }

    const shift = angle / 360;
    const { data } = imageData;

    for (let i = 0; i < data.length; i += 4) {
        const [h, s, l] = _rgbToHsl(data[i], data[i + 1], data[i + 2]);
        const [r, g, b] = _hslToRgb((h + shift) % 1, s, l);
        data[i] = r;
        data[i + 1] = g;
        data[i + 2] = b;
    }

    return imageData;
}

function _rgbToHsl(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;
    
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h, s;
    const l = (max + min) / 2;
    
    if (max === min) {
        h = s = 0;
    } else {
        const d = max - min ;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
            break;
            case g: h = ((b - r) / d + 2) / 6;
            break;
            case b: h = ((r - g) / d + 4) / 6;
            break;
        }
    }

    return [h, s, l];
}

function _hslToRgb(h, s, l) {
    if (s === 0) {
        const v = Math.round(l * 255);
        return [v, v, v];
    }

    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;

    return [Math.round(_hue2rgb(p, q, h + 1 / 3) * 255), Math.round(_hue2rgb(p, q, h) * 255), Math.round(_hue2rgb(p, q, h - 1 / 3) * 255)];
}

function _hue2rgb(p, q, t) {
    if (t < 0) {
        t += 1;
    }
    if (t > 1) {
        t -= 1;
    }
    if (t < 1 / 6) {
        return p + (q - p) * 6 * t;
    }
    if (t < 1 / 2) {
        return q;
    }
    if (t < 2 / 3) {
        return p + (q - p) * (2 / 3 - t) * 6;
    }
    return p;
}

export function vignette(imageData, params = {}) {
    const { data, width, height } = imageData;
    const cx = width / 2;
    const cy = height / 2;
    const maxDist = Math.sqrt(cx * cx + cy * cy);
    
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const dx = x - cx;
            const dy = y - cy;
            const dist = Math.sqrt(dx * dx + dy * dy) / maxDist;

            const factor = Math.max(0, 1 - dist * dist * 1.5);
            const idx = (y * width + x) * 4;
            data[idx] *= factor;
            data[idx + 1] *= factor;
            data[idx + 2] *= factor;
        }
    }

    return imageData;
}

export function digitalNoise(imageData, params = {}) {
    const intensity = Math.max(1, Math.min(100, params.intensity ?? 15));
    const { data } = imageData;
    const half = intensity / 2;

    for (let i = 0; i < data.length; i += 4) {
        const noise = (Math.random * intensity) - half;
        data[i] += noise;
        data[i + 1] += noise;
        data[i + 2] += noise;
    }

    return imageData;
}

export function edgeDetect(imageData, params = {}) {
    const { data, width, height } = imageData;
    const tmp = new Uint8ClampedArray(data);
 
    const sobelX = [-1, 0, 1, -2, 0, 2, -1, 0, 1];
    const sobelY = [-1, -2, -1, 0, 0, 0, 1, 2, 1];
 
    for (let y = 1; y < height - 1; y++) {
        for (let x = 1; x < width - 1; x++) {
            let gx = 0, gy = 0;
 
            for (let ky = -1; ky <= 1; ky++) {
                for (let kx = -1; kx <= 1; kx++) {
                    const idx = ((y + ky) * width + (x + kx)) * 4;
                    const lum = 0.299 * tmp[idx] + 0.587 * tmp[idx + 1] + 0.114 * tmp[idx + 2];
                    const ki  = (ky + 1) * 3 + (kx + 1);
                    gx += lum * sobelX[ki];
                    gy += lum * sobelY[ki];
                }
            }
 
            const mag = Math.min(255, Math.sqrt(gx * gx + gy * gy));
            const idx = (y * width + x) * 4;
            data[idx]     = mag;
            data[idx + 1] = mag;
            data[idx + 2] = mag;
        }
    }
 
    return imageData;
}

export const FILTER_MAP = {
    1: gaussianBlur,
    2: grayscale,
    3: sepia,
    4: brightness,
    5: contrast,
    6: saturation,
    7: hueRotate,
    8: vignette,
    9: digitalNoise,
    10: edgeDetect,
};