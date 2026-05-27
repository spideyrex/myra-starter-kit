import DOMPurify from 'dompurify';

/**
 * Sanitize HTML to prevent XSS attacks.
 * Uses DOMPurify with a safe default configuration.
 */
export function sanitizeHtml(dirty: string): string {
    return DOMPurify.sanitize(dirty, {
        ALLOWED_TAGS: [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'br', 'hr', 'div', 'span',
            'strong', 'em', 'b', 'i', 'u', 's', 'del', 'ins', 'sub', 'sup', 'mark',
            'a', 'img',
            'ul', 'ol', 'li',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
            'blockquote', 'pre', 'code',
            'figure', 'figcaption', 'picture', 'source',
            'details', 'summary',
            'abbr', 'cite', 'dfn', 'kbd', 'samp', 'var', 'time',
            'dl', 'dt', 'dd',
            'section', 'article', 'aside', 'header', 'footer', 'nav', 'main',
            'video', 'audio',
        ],
        ALLOWED_ATTR: [
            'href', 'src', 'alt', 'title', 'class', 'id', 'style',
            'target', 'rel', 'width', 'height',
            'colspan', 'rowspan', 'scope',
            'start', 'type', 'reversed',
            'datetime', 'open', 'loading', 'decoding',
            'controls', 'autoplay', 'loop', 'muted', 'poster', 'preload',
        ],
        ALLOW_DATA_ATTR: false,
        ADD_ATTR: ['target'],
        FORBID_TAGS: ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'button'],
        FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover', 'onfocus', 'onblur'],
    });
}

/**
 * Sanitize SVG content (more restrictive than HTML).
 */
export function sanitizeSvg(dirty: string): string {
    return DOMPurify.sanitize(dirty, {
        USE_PROFILES: { svg: true, svgFilters: true },
        ADD_TAGS: ['use'],
    });
}
