/**
 * Scheme allowlist for authored URLs, the client-side twin of UrlGuard::safe().
 *
 * Vue does not sanitise `:href` or `:src`. Anything an admin can type reaches
 * anonymous visitors verbatim, so every authored URL on a publicly rendered
 * section MUST pass through here before it is bound to an attribute.
 */

/** Control characters are ignored by URL parsers: `java\tscript:` still runs. */
// eslint-disable-next-line no-control-regex
const CONTROL = /[\u0000-\u001f\u007f-\u009f]/g;

const SAFE_SCHEME = /^(?:https?|mailto|tel):/i;

/** `/path` but never `//host`, plus `#anchor`, `?query`, `./` and `../`. */
const SITE_RELATIVE = /^(?:\/(?!\/)|[#?]|\.{1,2}\/)/;

/** Only data a browser paints as an image, never text/html or svg+xml. */
const SAFE_DATA_IMAGE = /^data:image\/(?:png|jpe?g|gif|webp|avif);base64,[a-z0-9+/=]+$/i;

function clean(value: unknown): string {
    return typeof value === 'string' ? value.replace(CONTROL, '').trim() : '';
}

/**
 * The authored value when it is safe in an `href`, otherwise ''.
 * An empty result means "render no link at all" — never a live javascript: URL.
 */
export function safeUrl(value: unknown): string {
    const raw = clean(value);

    if (raw === '') return '';

    return SITE_RELATIVE.test(raw) || SAFE_SCHEME.test(raw) ? raw : '';
}

/** The same allowlist for `src`, plus inline image data URIs. */
export function safeSrc(value: unknown): string {
    const raw = clean(value);

    if (raw === '') return '';
    if (SAFE_DATA_IMAGE.test(raw)) return raw;

    return safeUrl(raw);
}

/** True for an absolute http(s) target, which is what earns rel="noopener". */
export function isExternalUrl(value: unknown): boolean {
    return /^https?:/i.test(clean(value));
}
