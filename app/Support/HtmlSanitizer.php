<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist-based server-side HTML sanitizer for rich-text content
 * (article/page bodies, email templates). Defense in depth alongside the
 * client-side DOMPurify pass — unsafe HTML never reaches the database, so it
 * stays safe across emails, exports, APIs, and future renderers.
 */
class HtmlSanitizer
{
    /** Tags permitted in stored content. Everything else is unwrapped or dropped. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'span', 'div',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'mark', 'small',
        'a', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];

    /** Allowed attributes per tag (plus a global set applied to all). */
    private const ALLOWED_ATTRS = [
        '*' => ['class', 'title'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    /** Tags whose entire subtree is removed (never just unwrapped). */
    private const FORBIDDEN_SUBTREE = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript'];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        // Wrap so multiple roots are preserved; force UTF-8.
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__root__');
        if (! $root) {
            return '';
        }

        self::sanitizeNode($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        // DOMDocument percent-encodes braces inside URL attributes (href/src),
        // which would corrupt templating placeholders like {{ reset_link }}.
        // Restore them — braces are harmless in HTML/URLs.
        $output = str_replace(['%7B', '%7b', '%7D', '%7d'], ['{', '{', '}', '}'], $output);

        return trim($output);
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        // Iterate over a snapshot since we mutate the tree.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue; // text/comment handled below
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::FORBIDDEN_SUBTREE, true)) {
                $child->parentNode->removeChild($child);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unknown but not dangerous: unwrap (keep children, drop the tag).
                self::sanitizeNode($child);
                self::unwrap($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
            self::sanitizeNode($child);
        }

        // Strip comments (can carry conditional/script payloads).
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::ALLOWED_ATTRS['*'], self::ALLOWED_ATTRS[$tag] ?? []);

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! self::isSafeUrl($attr->value, $tag)) {
                $el->removeAttribute($attr->name);
            }
        }

        // Harden links opened in a new tab.
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    private static function isSafeUrl(string $url, string $tag): bool
    {
        $trimmed = ltrim($url);

        // Allow only data: URIs for image sources, and only image payloads.
        if (stripos($trimmed, 'data:') === 0) {
            return $tag === 'img' && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $trimmed) === 1;
        }

        // Block scheme-bearing dangerous protocols; allow http/https/mailto/tel and relative URLs.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $trimmed, $m)) {
            $scheme = strtolower(rtrim($m[0], ':'));

            return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
        }

        return true; // relative URL / anchor
    }

    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }
}
