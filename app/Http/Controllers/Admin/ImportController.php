<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Http\Refusal;
use App\Admin\Import\HeaderMapper;
use App\Admin\Import\ImportDefinition;
use App\Admin\Import\ImportRegistry;
use App\Admin\Import\ImportSession;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Four-endpoint import pipeline: preview -> validate -> commit (resumable) -> failures.
 *
 * The file is staged once by preview() and re-read from the token-derived path,
 * so the committed bytes are always the previewed bytes. commit() processes one
 * chunk per request inside a transaction and returns the byte offset to resume
 * from; memory is O(chunk) and a killed request never leaves a half-written row.
 */
class ImportController extends Controller
{
    private const TOKEN_RULES = ['required', 'string', 'size:26', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/i'];

    public function preview(Request $request, string $resource): JsonResponse
    {
        $definition = ImportRegistry::resolve($resource);
        $this->authorizeImport($request, $definition);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');

        // `mimes:` trusts the client-supplied extension for text types; re-check it.
        abort_unless(
            in_array(strtolower((string) $file->getClientOriginalExtension()), ['csv', 'txt'], true),
            422,
            __('transfer.import.badFile'),
        );

        $handle = fopen($file->getRealPath(), 'r');
        $headers = $this->readHeaders($handle);

        abort_if($headers === [], 422, __('transfer.import.noHeaders'));
        abort_if(
            count($headers) > $definition->getMaxColumns(),
            422,
            __('transfer.import.tooManyColumns', ['max' => $definition->getMaxColumns()]),
        );

        $headerBytes = ftell($handle);

        // Read only a sample; the row count is estimated from filesize / average
        // row bytes rather than scanning to EOF just to produce a number.
        $sample = [];
        while (count($sample) < 20 && ($row = fgetcsv($handle)) !== false) {
            $sample[] = $this->combine($headers, $row);
        }

        $sampleBytes = ftell($handle) - $headerBytes;
        $fileBytes = max(0, filesize($file->getRealPath()) - $headerBytes);
        $totalRows = ($sample === [] || $sampleBytes <= 0)
            ? count($sample)
            : (int) max(count($sample), round($fileBytes / ($sampleBytes / count($sample))));

        fclose($handle);

        // A clean refusal, not abort(): both row-cap call sites answer an XHR
        // caller, and a rendered HTML error page is unparseable to it.
        if ($totalRows > $definition->getMaxRows()) {
            Refusal::throw(
                $request,
                __('transfer.import.tooManyRows', ['max' => $definition->getMaxRows()]),
                ['max' => $definition->getMaxRows()],
            );
        }

        $session = ImportSession::stage($file, $resource, (int) $request->user()->id, $headers);

        return response()->json([
            'token' => $session->token,
            'headers' => $headers,
            'columns' => $definition->toClientSchema(),
            'mapping' => HeaderMapper::guess($definition, $headers),
            'rows' => $sample,
            'totalRows' => $totalRows,
            'chunkSize' => $definition->getChunkSize(),
        ]);
    }

    /** Dry run: reports per-cell errors and the exact row count, writing nothing. */
    public function validateRows(Request $request, string $resource): JsonResponse
    {
        $definition = ImportRegistry::resolve($resource);
        $this->authorizeImport($request, $definition);

        $validated = $request->validate([
            'token' => self::TOKEN_RULES,
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $session = ImportSession::resolve($validated['token'], $resource, (int) $request->user()->id);
        $mapping = $this->validatedMapping($request, $definition, $session);
        $limit = (int) ($validated['limit'] ?? 50);

        $handle = fopen($session->fullPath(), 'r');
        $this->readHeaders($handle);

        $rows = [];
        $invalid = 0;
        $total = 0;
        $line = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $line++;
            $total++;

            if (count($rows) >= $limit) {
                continue;
            }

            $data = $this->castRow($definition, $mapping, $this->combine($session->headers, $raw));
            $errors = $this->rowErrors($definition, $data);

            if ($errors !== []) {
                $invalid++;
            }

            $rows[] = [
                'line' => $line,
                'values' => $this->maskSensitive($definition, $data),
                'errors' => $errors,
            ];
        }

        fclose($handle);

        if ($total > $definition->getMaxRows()) {
            Refusal::throw(
                $request,
                __('transfer.import.tooManyRows', ['max' => $definition->getMaxRows()]),
                ['max' => $definition->getMaxRows()],
            );
        }

        return response()->json([
            'columns' => $definition->toClientSchema(),
            'headers' => $session->headers,
            'mapping' => $mapping,
            'rows' => $rows,
            'totalRows' => $total,
            'invalidRows' => $invalid,
            'token' => $session->token,
        ]);
    }

    /** One resumable chunk. The client loops on next_offset until done. */
    public function commit(Request $request, string $resource): JsonResponse
    {
        $definition = ImportRegistry::resolve($resource);
        $this->authorizeImport($request, $definition);

        $validated = $request->validate([
            'token' => self::TOKEN_RULES,
            'offset' => ['nullable', 'integer', 'min:0'],
            'line' => ['nullable', 'integer', 'min:1'],
            'total_rows' => ['nullable', 'integer', 'min:0'],
        ]);

        $session = ImportSession::resolve($validated['token'], $resource, (int) $request->user()->id);
        $mapping = $this->validatedMapping($request, $definition, $session);

        $offset = (int) ($validated['offset'] ?? 0);
        $line = (int) ($validated['line'] ?? 1);
        $user = $request->user();

        $handle = fopen($session->fullPath(), 'r');

        if ($offset === 0) {
            $this->readHeaders($handle);
        } else {
            abort_if(fseek($handle, $offset) !== 0, 422, __('transfer.import.tokenExpired'));
        }

        $imported = 0;
        $updated = 0;
        $failures = [];
        $processed = 0;
        $reachedEnd = false;

        DB::transaction(function () use (
            $handle, $definition, $mapping, $session, $user,
            &$imported, &$updated, &$failures, &$processed, &$line, &$reachedEnd
        ) {
            while ($processed < $definition->getChunkSize()) {
                $raw = fgetcsv($handle);

                if ($raw === false) {
                    $reachedEnd = true;
                    break;
                }

                $line++;
                $processed++;

                $data = $this->castRow($definition, $mapping, $this->combine($session->headers, $raw));
                $errors = $this->rowErrors($definition, $data);

                if ($errors !== []) {
                    $failures[] = [$line, $this->flatten($errors), $data];

                    continue;
                }

                if (! $definition->passesAuthorization($data, $user)) {
                    $failures[] = [$line, __('transfer.import.rowForbidden'), $data];

                    continue;
                }

                if ($definition->isDryRunOnly()) {
                    $imported++;

                    continue;
                }

                try {
                    $record = $definition->makeRecord($data, $user);
                    $existed = $record->exists;
                    $definition->runBeforeSave($record, $data, $user);
                    $record->save();
                    $definition->runAfterSave($record, $data, $user);
                    $existed ? $updated++ : $imported++;
                } catch (Throwable $e) {
                    $failures[] = [$line, $e->getMessage(), $data];
                }
            }
        });

        $nextOffset = ftell($handle);
        fclose($handle);

        $failed = count($failures);
        $totalFailures = $failed > 0
            ? $this->appendFailures($definition, $session, $failures)
            : $session->failureCount();

        $maxFailures = (int) config('myra.imports.max_failures', 1000);
        $aborted = $totalFailures >= $maxFailures;
        $done = $reachedEnd || $aborted;

        return response()->json([
            'imported' => $imported,
            'updated' => $updated,
            'failed' => $failed,
            'next_offset' => $nextOffset,
            'next_line' => $line,
            'total_rows' => (int) ($validated['total_rows'] ?? 0),
            'done' => $done,
            'status' => $aborted ? 'aborted' : ($done ? 'done' : 'running'),
            'failuresUrl' => $totalFailures > 0
                ? route('admin.import.failures', ['resource' => $resource, 'token' => $session->token])
                : null,
        ]);
    }

    public function failures(Request $request, string $resource, string $token): StreamedResponse
    {
        $definition = ImportRegistry::resolve($resource);
        $this->authorizeImport($request, $definition);

        Validator::make(['token' => $token], ['token' => self::TOKEN_RULES])->validate();

        $session = ImportSession::resolve($token, $resource, (int) $request->user()->id);
        $path = $session->failuresRelPath();

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, Csv::filename("{$resource}-failures"), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    // -------------------------------------------------------------------------

    private function authorizeImport(Request $request, ImportDefinition $definition): void
    {
        abort_unless($request->user()?->can($definition->getPermission()), 403);
    }

    /**
     * Mapping keys are whitelisted against the declared column names and mapping
     * values against the staged headers. Without this, any client key reaches the
     * model and mass assignment is the only thing standing in the way.
     *
     * @return array<string,string>
     */
    private function validatedMapping(Request $request, ImportDefinition $definition, ImportSession $session): array
    {
        $request->validate([
            'mapping' => ['required', 'array', 'max:128'],
            // '' is the explicit "skip this column" value; anything else must be a staged header.
            'mapping.*' => ['nullable', 'string', 'max:191', Rule::in(array_merge($session->headers, ['']))],
        ]);

        $mapping = (array) $request->input('mapping');

        abort_if(
            array_diff(array_keys($mapping), $definition->columnNames()) !== [],
            422,
            __('transfer.import.unknownColumn'),
        );

        foreach ($definition->requiredMappingNames() as $name) {
            abort_if(blank($mapping[$name] ?? null), 422, __('transfer.import.mappingRequired'));
        }

        return array_map(fn ($v) => (string) ($v ?? ''), $mapping);
    }

    /** @return string[] */
    private function readHeaders($handle): array
    {
        $headers = fgetcsv($handle);

        if (! is_array($headers) || $headers === [null]) {
            return [];
        }

        $headers[0] = preg_replace('/^\x{FEFF}/u', '', (string) $headers[0]);

        return array_map(fn ($h) => trim((string) $h), $headers);
    }

    private function combine(array $headers, array $row): array
    {
        return array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), ''));
    }

    /** @return array<string,mixed> every declared column, cast, keyed by column name */
    private function castRow(ImportDefinition $definition, array $mapping, array $sourceRow): array
    {
        $out = [];

        foreach ($definition->getColumns() as $column) {
            $header = $mapping[$column->name()] ?? '';
            $raw = $header !== '' ? ($sourceRow[$header] ?? null) : null;
            $out[$column->name()] = $column->cast($raw, $sourceRow);
        }

        return $out;
    }

    /** @return array<string,string[]> column name => messages */
    private function rowErrors(ImportDefinition $definition, array $data): array
    {
        $rules = [];
        $attributes = [];

        foreach ($definition->getColumns() as $column) {
            if ($column->getRules() !== []) {
                $rules[$column->name()] = $column->getRules();
                $attributes[$column->name()] = $column->getLabel();
            }
        }

        if ($rules === []) {
            return [];
        }

        $validator = Validator::make($data, $rules, [], $attributes);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    private function maskSensitive(ImportDefinition $definition, array $data): array
    {
        foreach ($definition->getColumns() as $column) {
            if ($column->isSensitive() && array_key_exists($column->name(), $data)) {
                $data[$column->name()] = $data[$column->name()] === null || $data[$column->name()] === ''
                    ? ''
                    : '***';
            }
        }

        return array_map(fn ($v) => is_array($v) ? implode(', ', $v) : $v, $data);
    }

    private function flatten(array $errors): string
    {
        return implode('; ', array_merge(...array_values($errors)));
    }

    /** Appends to imports/{userId}/{token}-failures.csv and returns the running total. */
    private function appendFailures(ImportDefinition $definition, ImportSession $session, array $failures): int
    {
        $path = Storage::disk('local')->path($session->failuresRelPath());
        Storage::disk('local')->makeDirectory(dirname($session->failuresRelPath()));

        $fresh = ! file_exists($path);
        $handle = fopen($path, 'a');

        if ($fresh) {
            fputcsv($handle, array_merge(['Line', 'Error'], $definition->columnNames()));
        }

        foreach ($failures as [$line, $message, $data]) {
            $masked = $this->maskSensitive($definition, $data);
            fputcsv($handle, Csv::row(array_merge(
                [$line, $message],
                array_map(fn ($name) => $masked[$name] ?? '', $definition->columnNames()),
            )));
        }

        fclose($handle);

        return $session->addFailures(count($failures));
    }
}
