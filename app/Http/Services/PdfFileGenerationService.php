<?php

namespace App\Http\Services;

use Dompdf\Dompdf;

class PdfFileGenerationService
{
    public function generate(string $title, string $content): string
    {
        $document = new Dompdf;
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $formattedContent = $this->formatContent($content);

        $document->loadHtml(
            '<!doctype html><html><head><meta charset="UTF-8"><style>'.
            '@page{margin:60pt 54pt 54pt}'.
            'body{font-family:DejaVu Sans,sans-serif;font-size:10.5pt;line-height:1.45;color:#202124}'.
            'h1{font-size:19pt;line-height:1.25;margin:0 0 22pt;color:#17365d}'.
            'h2{font-size:14pt;margin:20pt 0 8pt;color:#1f4e79;page-break-after:avoid}'.
            'h3{font-size:11.5pt;margin:14pt 0 6pt;color:#1f4e79;page-break-after:avoid}'.
            'p{margin:0 0 8pt}'.
            '.list-item{margin:0 0 4pt;display:block}'.
            '.depth-1{margin-left:20pt}.depth-2{margin-left:40pt}.depth-3{margin-left:60pt}'.
            '.marker{display:inline-block;width:22pt;font-weight:bold}'.
            '.rule{border:0;border-top:1px solid #a6a6a6;margin:14pt 0}'.
            '</style></head><body><h1>'.$safeTitle.'</h1>'.$formattedContent.'</body></html>',
            'UTF-8',
        );
        $document->render();

        return $document->output();
    }

    private function formatContent(string $content): string
    {
        $html = [];

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $line = rtrim($line);
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/u', $trimmedLine) === 1) {
                $html[] = '<hr class="rule">';

                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/u', $trimmedLine, $heading) === 1) {
                $level = min(3, strlen($heading[1]) + 1);
                $html[] = '<h'.$level.'>'.$this->formatInline($heading[2]).'</h'.$level.'>';

                continue;
            }

            if (preg_match('/^(?:[IVXLCDM]+\.|[A-Z]\.)\s+(.+)$/u', $trimmedLine) === 1) {
                $html[] = '<h3>'.$this->formatInline($trimmedLine).'</h3>';

                continue;
            }

            if (preg_match('/^(\s*)([-*+]|\d+[.)])\s+(.+)$/u', $line, $listItem) === 1) {
                $depth = min(3, intdiv(strlen($listItem[1]), 2));
                $isNumbered = ctype_digit($listItem[2][0]);
                $marker = $isNumbered ? $listItem[2] : '&bull;';
                $listClass = $isNumbered ? 'numbered' : 'bullet';

                $html[] = '<div class="list-item '.$listClass.' depth-'.$depth.'"><span class="marker">'.$marker.'</span>'.
                    $this->formatInline($listItem[3]).'</div>';

                continue;
            }

            $html[] = '<p>'.$this->formatInline($trimmedLine).'</p>';
        }

        return implode("\n", $html);
    }

    private function formatInline(string $text): string
    {
        $escapedText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $formattedText = preg_replace('/(?:\*\*|__)(.+?)(?:\*\*|__)/u', '<strong>$1</strong>', $escapedText);
        $formattedText = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $formattedText ?? $escapedText);

        return preg_replace('/`([^`]+)`/u', '<code>$1</code>', $formattedText ?? $escapedText) ?? $escapedText;
    }
}
