<?php

namespace App\Http\Services;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\ListItem;
use RuntimeException;

class DocxFileGenerationService
{
    public function generate(string $title, string $content): string
    {
        $outputEscapingWasEnabled = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $document = new PhpWord;
            $document->getDocInfo()->setTitle($title);
            $document->setDefaultFontName('Arial');
            $document->setDefaultFontSize(10);
            $document->setDefaultParagraphStyle([
                'spaceAfter' => 120,
                'lineHeight' => 1.15,
            ]);

            $section = $document->addSection([
                'marginTop' => 900,
                'marginBottom' => 900,
                'marginLeft' => 1100,
                'marginRight' => 1100,
            ]);
            $section->addText($title, [
                'name' => 'Arial',
                'size' => 16,
                'bold' => true,
            ], [
                'alignment' => 'center',
                'spaceAfter' => 360,
                'keepNext' => true,
            ]);

            foreach (preg_split('/\R/u', $content) ?: [] as $line) {
                $line = rtrim($line);
                $trimmedLine = trim($line);

                if ($trimmedLine === '') {
                    continue;
                }

                if (preg_match('/^(#{1,3})\s+(.+)$/u', $trimmedLine, $heading) === 1) {
                    $level = strlen($heading[1]);
                    $section->addText(
                        $this->plainText($heading[2]),
                        [
                            'name' => 'Arial',
                            'size' => $level === 1 ? 14 : 12,
                            'bold' => true,
                            'color' => '1F4E79',
                        ],
                        [
                            'spaceBefore' => $level === 1 ? 280 : 180,
                            'spaceAfter' => 100,
                            'keepNext' => true,
                        ],
                    );

                    continue;
                }

                if (preg_match('/^(?:[IVXLCDM]+\.|[A-Z]\.)\s+(.+)$/u', $trimmedLine) === 1) {
                    $section->addText(
                        $this->plainText($trimmedLine),
                        [
                            'name' => 'Arial',
                            'size' => 11,
                            'bold' => true,
                            'color' => '1F4E79',
                        ],
                        [
                            'spaceBefore' => 200,
                            'spaceAfter' => 100,
                            'keepNext' => true,
                        ],
                    );

                    continue;
                }

                if (preg_match('/^(\s*)([-*+]|\d+[.)])\s+(.+)$/u', $line, $listItem) === 1) {
                    $depth = min(3, intdiv(strlen($listItem[1]), 2));
                    $listType = ctype_digit($listItem[2][0])
                        ? ListItem::TYPE_NUMBER
                        : ($depth === 0 ? ListItem::TYPE_BULLET_FILLED : ListItem::TYPE_BULLET_EMPTY);

                    $listItemRun = $section->addListItemRun(
                        $depth,
                        ['listType' => $listType],
                        ['spaceAfter' => 60, 'lineHeight' => 1.15],
                    );
                    $this->addFormattedText($listItemRun, $listItem[3]);

                    continue;
                }

                $this->addFormattedParagraph($section, $trimmedLine);
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'lexi-docx-');

            if (! is_string($temporaryPath)) {
                throw new RuntimeException('A temporary DOCX file could not be created.');
            }

            try {
                $document->save($temporaryPath, 'Word2007');
                $contents = file_get_contents($temporaryPath);

                if (! is_string($contents)) {
                    throw new RuntimeException('The generated DOCX file could not be read.');
                }

                return $contents;
            } finally {
                unlink($temporaryPath);
            }
        } finally {
            Settings::setOutputEscapingEnabled($outputEscapingWasEnabled);
        }
    }

    private function addFormattedParagraph(Section $section, string $text): void
    {
        $textRun = $section->addTextRun([
            'spaceAfter' => 120,
            'lineHeight' => 1.15,
        ]);

        $this->addFormattedText($textRun, $text);
    }

    private function addFormattedText(TextRun $textRun, string $text): void
    {
        $parts = preg_split('/(\*\*.+?\*\*|__.+?__)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts ?: [$text] as $part) {
            $isBold = preg_match('/^(?:\*\*|__)(.+)(?:\*\*|__)$/u', $part, $boldText) === 1;
            $textRun->addText(
                $isBold ? $boldText[1] : $part,
                $isBold ? ['bold' => true] : [],
            );
        }
    }

    private function plainText(string $text): string
    {
        return preg_replace('/(?:\*\*|__)(.+?)(?:\*\*|__)/u', '$1', $text) ?? $text;
    }
}
