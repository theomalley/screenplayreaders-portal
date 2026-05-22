<?php

// v1.1 — 2026-05-21 | Full page source parsing; iframe rendering with WP styles

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class ManualController extends Controller
{
    public function show()
    {
        $hasContent = (bool) Setting::getValue('reader_manual_content');
        return view('manual.show', compact('hasContent'));
    }

    public function frame()
    {
        $content      = Setting::getValue('reader_manual_content', '');
        $stylesheets  = json_decode(Setting::getValue('reader_manual_stylesheets', '[]'), true) ?: [];
        $inlineStyles = Setting::getValue('reader_manual_inline_styles', '');
        $bodyClass    = Setting::getValue('reader_manual_body_class', '');

        return view('manual.frame', compact('content', 'stylesheets', 'inlineStyles', 'bodyClass'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['source_html' => ['nullable', 'string']]);

        $source = trim($request->input('source_html', ''));

        if ($this->isFullPage($source)) {
            $parsed = $this->parseSourceHtml($source);
            Setting::setValue('reader_manual_content',      $parsed['content']);
            Setting::setValue('reader_manual_stylesheets',  json_encode($parsed['stylesheets']));
            Setting::setValue('reader_manual_inline_styles', $parsed['inlineStyles']);
            Setting::setValue('reader_manual_body_class',   $parsed['bodyClass']);
        } else {
            Setting::setValue('reader_manual_content', $source);
        }

        return back()->with('success', 'Reader Manual updated.');
    }

    private function isFullPage(string $html): bool
    {
        return stripos($html, '<html') !== false || stripos($html, '<!doctype') !== false;
    }

    private function parseSourceHtml(string $source): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($source);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // All <link rel="stylesheet"> hrefs from <head>
        $stylesheets = [];
        foreach ($xpath->query('//head/link[@rel="stylesheet"]') as $link) {
            $href = $link->getAttribute('href');
            if ($href) {
                $stylesheets[] = $href;
            }
        }

        // All <style> text content from <head>
        $inlineStyles = '';
        foreach ($xpath->query('//head/style') as $style) {
            $inlineStyles .= $style->textContent . "\n";
        }

        // Body class for CSS selector compatibility
        $bodyNode  = $xpath->query('//body')->item(0);
        $bodyClass = $bodyNode ? $bodyNode->getAttribute('class') : '';

        // Content: prefer .entry-content, then <main>, then <body>
        $contentNode = null;
        foreach ($xpath->query('//*[contains(@class,"entry-content")]') as $node) {
            $contentNode = $node;
            break;
        }
        if (!$contentNode) {
            $contentNode = $xpath->query('//main')->item(0);
        }
        if (!$contentNode) {
            $contentNode = $bodyNode;
        }

        $content = $contentNode ? $dom->saveHTML($contentNode) : '';

        return compact('stylesheets', 'inlineStyles', 'bodyClass', 'content');
    }
}
