<?php

namespace App\Http\Services;

use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GeneratedFileService
{
    public function __construct(
        private PdfFileGenerationService $pdfFileGenerationService,
        private DocxFileGenerationService $docxFileGenerationService,
        private ExcelFileGenerationService $excelFileGenerationService,
    ) {}

    public function generate(Message $message, LLMToolCall $toolCall): Attachment
    {
        if ($toolCall->name !== 'generate_file') {
            throw new RuntimeException('The LLM requested an unsupported tool.');
        }

        $data = Validator::make($toolCall->arguments, [
            'format' => ['required', 'string', Rule::in(['pdf', 'docx', 'xlsx'])],
            'filename' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9 _-]*\.(pdf|docx|xlsx)\z/'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required_if:format,pdf,docx', 'nullable', 'string', 'max:50000'],
            'headers' => ['required_if:format,xlsx', 'nullable', 'array', 'min:1', 'max:50'],
            'headers.*' => ['required', 'string', 'max:255'],
            'rows' => ['required_if:format,xlsx', 'nullable', 'array', 'max:1000'],
            'rows.*' => ['array', 'max:50'],
        ])->validate();

        if (pathinfo($data['filename'], PATHINFO_EXTENSION) !== $data['format']) {
            throw ValidationException::withMessages([
                'filename' => 'The filename extension must match the selected file format.',
            ]);
        }

        $content = match ($data['format']) {
            'pdf' => $this->pdfFileGenerationService->generate($data['title'], $data['content']),
            'docx' => $this->docxFileGenerationService->generate($data['title'], $data['content']),
            'xlsx' => $this->excelFileGenerationService->generate(
                $data['title'],
                $data['headers'],
                $this->validatedRows($data['headers'], $data['rows']),
            ),
            default => throw new RuntimeException('The LLM requested an unsupported file format.'),
        };

        $path = 'attachments/generated/'.Str::uuid().'.'.$data['format'];

        if (! Storage::disk('local')->put($path, $content)) {
            throw new RuntimeException('The generated attachment could not be stored.');
        }

        try {
            return $message->attachments()->create([
                'type' => 'document',
                'filename' => $data['filename'],
                'size' => (string) strlen($content),
                'path' => $path,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, string|int|float|bool|null>>
     */
    private function validatedRows(array $headers, array $rows): array
    {
        if (! array_is_list($headers) || ! array_is_list($rows)) {
            throw ValidationException::withMessages([
                'headers' => 'Spreadsheet headers and rows must be ordered lists.',
            ]);
        }

        $totalCharacters = 0;

        foreach ($headers as $header) {
            $totalCharacters += mb_strlen($header);
        }

        foreach ($rows as $rowIndex => $row) {
            if (! array_is_list($row) || count($row) !== count($headers)) {
                throw ValidationException::withMessages([
                    "rows.{$rowIndex}" => 'Each spreadsheet row must have the same number of values as headers.',
                ]);
            }

            foreach ($row as $columnIndex => $value) {
                if (! is_scalar($value) && $value !== null) {
                    throw ValidationException::withMessages([
                        "rows.{$rowIndex}.{$columnIndex}" => 'Spreadsheet values must be text, numbers, or booleans.',
                    ]);
                }

                $valueLength = is_string($value) ? mb_strlen($value) : strlen((string) $value);

                if ($valueLength > 5_000 || $totalCharacters + $valueLength > 200_000) {
                    throw ValidationException::withMessages([
                        "rows.{$rowIndex}.{$columnIndex}" => 'The spreadsheet content is too large to generate.',
                    ]);
                }

                $totalCharacters += $valueLength;
            }
        }

        return $rows;
    }
}
