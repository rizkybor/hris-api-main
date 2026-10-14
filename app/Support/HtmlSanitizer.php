<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for HTML written in the RichTextEditor. Rich-text
 * bodies are rendered back with innerHTML/v-html for whoever opens the
 * document next, so anything beyond formatting (scripts, event handlers,
 * javascript: links, iframes...) is stripped before it's stored.
 */
class HtmlSanitizer
{
    private const TAGS = [
        'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup', 'font',
        'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'hr',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'a', 'img',
    ];

    // Elements removed together with everything inside them.
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'input', 'button', 'select', 'textarea'];

    private const ATTRIBUTES = [
        '*' => ['style'],
        'a' => ['href', 'target'],
        // data-flowchart-id: an image inserted from the Flowchart module,
        // swapped for the flowchart's latest snapshot when a PDF is made.
        'img' => ['src', 'alt', 'width', 'height', 'data-flowchart-id'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'font' => ['color'],
    ];

    private const STYLE_PROPERTIES = [
        'color', 'background-color', 'text-align', 'font-weight', 'font-style', 'text-decoration',
        'margin-left', 'padding-left', 'text-indent', 'width', 'height', 'max-width', 'vertical-align',
    ];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return '';
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        // Copy first: the list changes as children are removed/unwrapped.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! in_array($tag, self::TAGS, true)) {
                // Unknown wrapper: keep its (already cleaned) content.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::ATTRIBUTES['*'], self::ATTRIBUTES[$tag] ?? []);

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);

                continue;
            }

            $value = trim($attr->value);

            if ($name === 'style') {
                $style = self::cleanStyle($value);
                $style === '' ? $el->removeAttribute($attr->name) : $el->setAttribute('style', $style);
            } elseif ($name === 'href' && ! preg_match('#^(https?:|mailto:|tel:|\#)#i', $value)) {
                $el->removeAttribute($attr->name);
            } elseif ($name === 'data-flowchart-id' && ! ctype_digit($value)) {
                $el->removeAttribute($attr->name);
            } elseif ($name === 'src' && ! preg_match('#^https://#i', $value)) {
                $el->removeAttribute($attr->name);
            } elseif ($name === 'target') {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private static function cleanStyle(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim($value);

            if ($value === '' || ! in_array($property, self::STYLE_PROPERTIES, true)) {
                continue;
            }
            // No url(), expression() or escapes sneaking through a value.
            if (preg_match('/url\s*\(|expression\s*\(|javascript:|\\\\|[<>]/i', $value)) {
                continue;
            }

            $kept[] = "{$property}: {$value}";
        }

        return implode('; ', $kept);
    }
}
