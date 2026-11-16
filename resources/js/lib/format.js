export const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

export const money = (n) => round2(n).toFixed(2);

/** Arabic-Indic (٠-٩) / Persian (۰-۹) digits and Arabic separators → ASCII. */
export function toLatinDigits(value) {
    return String(value ?? '')
        .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660))
        .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0))
        .replace(/٫/g, '.') // ٫ decimal separator
        .replace(/٬/g, ''); // ٬ thousands separator
}

/** parseFloat that accepts "١٥٫٥", "15,5" and "15.5". Returns NaN when empty/invalid. */
export function parseNum(value) {
    const s = toLatinDigits(value).trim().replace(/,/g, '.');
    return s === '' ? NaN : Number(s);
}

/** Same folding as App\Support\Arabic::normalizeSearch on the server. */
export function normalizeSearch(value) {
    return toLatinDigits(value)
        .replace(/[ً-ٰٟـ]/g, '') // tashkeel + tatweel
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي')
        .replace(/ؤ/g, 'و')
        .replace(/ئ/g, 'ي')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/** Read Laravel's XSRF-TOKEN cookie (always current, even after login regenerates the session). */
export function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
