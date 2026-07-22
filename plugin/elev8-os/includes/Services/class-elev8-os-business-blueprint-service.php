<?php
if (!defined('ABSPATH')) { exit; }

/** Repository-backed architectural memory for Elev8 OS. */
final class Elev8_OS_Business_Blueprint_Service {
    public static function init(): void {}

    public static function canonical_path(): string {
        $repo = wp_normalize_path(dirname(ELEV8_OS_DIR, 3) . '/BUSINESS_BLUEPRINT.md');
        if (is_readable($repo)) { return $repo; }
        return wp_normalize_path(ELEV8_OS_DIR . 'assets/docs/BUSINESS_BLUEPRINT.md');
    }

    public static function contents(): string {
        $path = self::canonical_path();
        if (!is_readable($path)) { return ''; }
        $contents = file_get_contents($path);
        return is_string($contents) ? $contents : '';
    }

    public static function modified_at(): int {
        $path = self::canonical_path();
        return is_readable($path) ? (int) filemtime($path) : 0;
    }

    /** @return array<string,string> */
    public static function sections(): array {
        $markdown = self::contents();
        if ($markdown === '') { return []; }
        $sections = [];
        $title = 'Overview';
        $buffer = [];
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $match)) {
                if ($buffer) { $sections[$title] = trim(implode("\n", $buffer)); }
                $title = trim($match[1]);
                $buffer = [];
                continue;
            }
            if (!preg_match('/^#\s+/', $line)) { $buffer[] = $line; }
        }
        if ($buffer) { $sections[$title] = trim(implode("\n", $buffer)); }
        return $sections;
    }

    public static function render_markdown(string $markdown): string {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $html = '';
        $in_ul = false;
        $in_ol = false;
        $in_code = false;
        $code = [];
        $paragraph = [];

        $flush_paragraph = static function () use (&$html, &$paragraph): void {
            if (!$paragraph) { return; }
            $text = trim(implode(' ', $paragraph));
            if ($text !== '') { $html .= '<p>' . Elev8_OS_Business_Blueprint_Service::inline($text) . '</p>'; }
            $paragraph = [];
        };
        $close_lists = static function () use (&$html, &$in_ul, &$in_ol): void {
            if ($in_ul) { $html .= '</ul>'; $in_ul = false; }
            if ($in_ol) { $html .= '</ol>'; $in_ol = false; }
        };

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                $flush_paragraph(); $close_lists();
                if ($in_code) { $html .= '<pre><code>' . esc_html(implode("\n", $code)) . '</code></pre>'; $code = []; }
                $in_code = !$in_code;
                continue;
            }
            if ($in_code) { $code[] = $line; continue; }
            if (trim($line) === '') { $flush_paragraph(); $close_lists(); continue; }
            if (preg_match('/^(#{1,4})\s+(.+)$/', $line, $m)) {
                $flush_paragraph(); $close_lists(); $level = strlen($m[1]) + 1;
                $html .= '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
                continue;
            }
            if (preg_match('/^>\s*(.+)$/', $line, $m)) {
                $flush_paragraph(); $close_lists(); $html .= '<blockquote>' . self::inline($m[1]) . '</blockquote>'; continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                $flush_paragraph(); if ($in_ol) { $html .= '</ol>'; $in_ol = false; }
                if (!$in_ul) { $html .= '<ul>'; $in_ul = true; }
                $html .= '<li>' . self::inline($m[1]) . '</li>'; continue;
            }
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                $flush_paragraph(); if ($in_ul) { $html .= '</ul>'; $in_ul = false; }
                if (!$in_ol) { $html .= '<ol>'; $in_ol = true; }
                $html .= '<li>' . self::inline($m[1]) . '</li>'; continue;
            }
            if (str_contains($line, '|') && preg_match('/^\s*\|/', $line)) {
                $flush_paragraph(); $close_lists();
                $html .= '<pre class="elev8-blueprint__table-source">' . esc_html($line) . '</pre>'; continue;
            }
            $paragraph[] = $line;
        }
        $flush_paragraph(); $close_lists();
        if ($in_code && $code) { $html .= '<pre><code>' . esc_html(implode("\n", $code)) . '</code></pre>'; }
        return wp_kses_post($html);
    }

    private static function inline(string $text): string {
        $safe = esc_html($text);
        $safe = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
        $safe = preg_replace('/`(.+?)`/', '<code>$1</code>', $safe) ?? $safe;
        return $safe;
    }

    public static function summary(): array {
        return [
            'major_engines' => 9,
            'supporting_engines' => 11,
            'decisions' => substr_count(self::contents(), '### ADR-'),
            'open_questions' => 5,
        ];
    }
}
