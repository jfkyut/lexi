<?php

namespace App\Http\Services;

use App\Models\Attachment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser;
use ZipArchive;

class AttachmentPromptService
{
    private const MAX_TEXT_CHARACTERS = 100_000;

    private const MAX_TEXT_BYTES = 400_000;

    private const MAX_PDF_BYTES = 20 * 1024 * 1024;

    private const MAX_DOCX_BYTES = 20 * 1024 * 1024;

    private const MAX_DOCX_XML_BYTES = 20 * 1024 * 1024;

    /**
     * @return array<string, mixed>
     */
    public function promptPart(Attachment $attachment): array
    {
        $disk = Storage::disk('local');
        $mimeType = $disk->mimeType($attachment->path) ?: 'application/octet-stream';
        $size = (int) $attachment->size;
        $extension = Str::lower(pathinfo($attachment->filename, PATHINFO_EXTENSION));

        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            if ($size > 10 * 1024 * 1024) {
                return $this->unavailablePart($attachment->filename, $mimeType, 'Images over 10 MB are stored but not sent to the AI.');
            }

            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:'.$mimeType.';base64,'.base64_encode($disk->get($attachment->path)),
                ],
            ];
        }

        if ($mimeType === 'application/pdf' && $size > self::MAX_PDF_BYTES) {
            return $this->unavailablePart($attachment->filename, $mimeType, 'PDF text extraction is limited to 20 MB per file.');
        }

        if ($extension === 'docx' && $size > self::MAX_DOCX_BYTES) {
            return $this->unavailablePart($attachment->filename, $mimeType, 'DOCX text extraction is limited to 20 MB per file.');
        }

        $isTextFile = Str::startsWith($mimeType, 'text/')
            || ($extension === 'txt' && $mimeType === 'application/octet-stream')
            || in_array($mimeType, [
                'application/javascript',
                'application/json',
                'application/sql',
                'application/xml',
            ], true);

        $text = match (true) {
            $mimeType === 'application/pdf' => $this->pdfText($disk->get($attachment->path)),
            $extension === 'docx' => $this->docxText($disk->path($attachment->path)),
            $isTextFile => $this->textContent($attachment->path),
            default => null,
        };

        if ($text === null || trim($text) === '') {
            return $this->unavailablePart($attachment->filename, $mimeType, 'Only text-based PDF, DOCX, and UTF-8 text documents are extracted. Scanned PDFs and other formats need OCR or additional processing.');
        }

        if (! mb_check_encoding($text, 'UTF-8')) {
            return [
                'type' => 'text',
                'text' => "The attachment {$attachment->filename} could not be analyzed because its extracted text is not valid UTF-8.",
            ];
        }

        $truncated = mb_strlen($text) > self::MAX_TEXT_CHARACTERS
            || ($isTextFile && $size > self::MAX_TEXT_BYTES);
        $text = mb_substr($text, 0, self::MAX_TEXT_CHARACTERS);

        return [
            'type' => 'text',
            'text' => "Document attachment {$attachment->filename} ({$mimeType}):\n".$text.($truncated ? "\n[Document text truncated after 100,000 characters or 400 KB.]" : ''),
        ];
    }

    /**
     * @return array{type: string, text: string}
     */
    private function unavailablePart(string $filename, string $mimeType, string $reason): array
    {
        return [
            'type' => 'text',
            'text' => "The attachment {$filename} ({$mimeType}) is stored but could not be analyzed. {$reason}",
        ];
    }

    private function textContent(string $path): string
    {
        $stream = Storage::disk('local')->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The text attachment could not be opened for analysis.');
        }

        $contents = '';

        try {
            while (! feof($stream) && strlen($contents) <= self::MAX_TEXT_BYTES) {
                $chunk = fread($stream, min(64 * 1024, self::MAX_TEXT_BYTES + 1 - strlen($contents)));

                if (! is_string($chunk)) {
                    throw new RuntimeException('The text attachment could not be read for analysis.');
                }

                if ($chunk === '') {
                    break;
                }

                $contents .= $chunk;
            }
        } finally {
            fclose($stream);
        }

        return mb_strcut($contents, 0, self::MAX_TEXT_BYTES, 'UTF-8');
    }

    private function pdfText(string $contents): string
    {
        return (new Parser)->parseContent($contents)->getText();
    }

    private function docxText(string $path): string
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException('The DOCX attachment could not be opened.');
        }

        $paragraphs = [];
        $foundMainDocument = false;
        $totalXmlBytes = 0;

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);

                if (! is_string($name) || ! preg_match('#^word/(document|header\d+|footer\d+|footnotes|endnotes|comments)\.xml$#', $name)) {
                    continue;
                }

                if ($name === 'word/document.xml') {
                    $foundMainDocument = true;
                }

                $stat = $archive->statIndex($index);

                if (! is_array($stat) || $stat['size'] > self::MAX_DOCX_XML_BYTES) {
                    throw new RuntimeException('The DOCX attachment contains an XML part that is too large to analyze.');
                }

                $totalXmlBytes += $stat['size'];

                if ($totalXmlBytes > self::MAX_DOCX_XML_BYTES) {
                    throw new RuntimeException('The DOCX attachment contains too much XML to analyze.');
                }

                $xml = $archive->getFromIndex($index);

                if (! is_string($xml)) {
                    throw new RuntimeException('The DOCX attachment text could not be read.');
                }

                $document = new DOMDocument;

                $previousErrorMode = libxml_use_internal_errors(true);
                $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
                libxml_clear_errors();
                libxml_use_internal_errors($previousErrorMode);

                if (! $loaded) {
                    throw new RuntimeException('The DOCX attachment contains invalid document XML.');
                }

                $xpath = new DOMXPath($document);

                foreach ($xpath->query('//*[local-name()="p"]') ?: [] as $paragraph) {
                    if (! $paragraph instanceof DOMElement) {
                        continue;
                    }

                    $words = [];

                    foreach ($xpath->query('.//*[local-name()="t"]', $paragraph) ?: [] as $textNode) {
                        if ($textNode instanceof DOMNode) {
                            $words[] = $textNode->textContent ?? '';
                        }
                    }

                    $paragraphs[] = implode('', $words);
                }
            }
        } finally {
            $archive->close();
        }

        if (! $foundMainDocument) {
            throw new RuntimeException('The DOCX attachment does not contain a readable Word document.');
        }

        return implode("\n", $paragraphs);
    }
}
