<?php

namespace App\Support;

class HtmlSanitizer
{
    /**
     * Lista de tags permitidas para conteúdo de aula/seção (evita XSS).
     */
    private const ALLOWED_TAGS = '<p><br><strong><em><b><i><u><s><a><ul><ol><li><h1><h2><h3><h4><blockquote><pre><code><span><div>';

    /**
     * Sanitiza HTML para exibição segura (remove script, eventos, javascript:).
     */
    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        $html = strip_tags($html, self::ALLOWED_TAGS);
        $html = self::removeEventHandlers($html);
        $html = self::removeJavascriptUrls($html);

        return $html;
    }

    private static function removeEventHandlers(string $html): string
    {
        return (string) preg_replace_callback(
            '/<(\w+)([^>]*)>/i',
            function (array $m): string {
                $attrs = (string) preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $m[2]);
                return '<' . $m[1] . $attrs . '>';
            },
            $html
        );
    }

    private static function removeJavascriptUrls(string $html): string
    {
        return (string) preg_replace(
            '/href\s*=\s*["\']\s*javascript\s*:[^"\']*["\']/i',
            'href="#"',
            $html
        );
    }
}
