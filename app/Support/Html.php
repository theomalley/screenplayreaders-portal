<?php

// v1.0 — 2026-06-12 | Bio HTML sanitization: allowlist for admin-authored HTML,
// strip_tags for reader/editor plain-text bios.

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class Html
{
    /**
     * Tags admins may use when formatting a bio (matches the inline-formatting
     * subset of WordPress's wp_kses_post, since bios are rendered through
     * wp_kses_post() on the public site).
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike',
        'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'code', 'pre', 'span',
    ];

    /**
     * Tags whose content is never safe to surface as text and must be
     * dropped entirely (script bodies, form controls, embeds, etc.).
     */
    private const STRIP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet', 'form',
        'button', 'input', 'textarea', 'select', 'option', 'svg', 'math',
        'link', 'meta', 'base', 'noscript', 'frame', 'frameset', 'video',
        'audio', 'source', 'track', 'canvas', 'template',
    ];

    /** Attributes allowed per allowed tag. */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    /**
     * Sanitize admin-authored bio HTML: drop any tag/attribute not on the
     * allowlist (unwrapping disallowed-but-harmless tags while preserving
     * their text), strip script/style/etc. entirely, and block
     * javascript:/data: links.
     */
    public static function sanitizeBioHtml(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        $prevErrors = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
                . '<sr-bio-root>' . $html . '</sr-bio-root>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        $root = $dom->getElementsByTagName('sr-bio-root')->item(0);
        if (! $root) {
            return null;
        }

        self::sanitizeChildren($dom, $root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        $output = trim($output);

        return $output !== '' ? $output : null;
    }

    /**
     * Sanitize a plain-text bio: strip all HTML tags entirely.
     */
    public static function sanitizeBioPlainText(?string $text): ?string
    {
        $text = trim(strip_tags((string) $text));

        return $text !== '' ? $text : null;
    }

    private static function sanitizeChildren(DOMDocument $dom, DOMNode $node): void
    {
        $child = $node->firstChild;

        while ($child !== null) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, self::STRIP_WITH_CONTENT, true)) {
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }

                // Recurse first so nested disallowed tags are cleaned up
                // before we decide whether to keep or unwrap this element.
                self::sanitizeChildren($dom, $child);

                if (in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::sanitizeAttributes($child, $tag);
                } else {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            } elseif (! ($child instanceof DOMText)) {
                // Drop comments, processing instructions, CDATA, etc.
                $node->removeChild($child);
            }

            $child = $next;
        }
    }

    private static function sanitizeAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
                continue;
            }

            if ($name === 'href' && self::isUnsafeUrl($attr->value)) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * True if a URL's scheme isn't http/https/mailto/tel — blocks
     * javascript:, data:, vbscript:, etc. (including whitespace-obfuscated
     * variants such as "java\tscript:").
     */
    private static function isUnsafeUrl(string $url): bool
    {
        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
        $stripped = preg_replace('/[\x00-\x20]+/', '', $decoded);

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $stripped, $m)) {
            return ! in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true);
        }

        return false;
    }
}
