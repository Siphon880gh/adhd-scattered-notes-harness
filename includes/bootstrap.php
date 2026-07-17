<?php
declare(strict_types=1);

function app_root(): string
{
    return dirname(__DIR__);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @return array{inputFolder: string, phase: int}
 */
function load_config(): array
{
    $path = app_root() . '/app.config.json';
    $defaults = ['inputFolder' => '', 'phase' => 1];

    if (!is_file($path)) {
        return $defaults;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return $defaults;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $defaults;
    }

    $folder = isset($data['inputFolder']) ? trim((string) $data['inputFolder']) : '';
    $phase = isset($data['phase']) ? (int) $data['phase'] : 1;
    if ($phase !== 2) {
        $phase = 1;
    }

    return [
        'inputFolder' => $folder,
        'phase' => $phase,
    ];
}

function input_dir(string $folder): string
{
    return app_root() . '/inputs/' . $folder;
}

/**
 * @return list<string>
 */
function list_note_files(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $skip = ['shorthands.md' => true];
    $files = [];

    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (isset($skip[$name])) {
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'md' && $ext !== 'txt') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_file($path)) {
            $files[] = $name;
        }
    }

    natcasesort($files);
    return array_values($files);
}

/**
 * @return array<string, mixed>|null
 */
function load_json_file(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Build a fallback line list from the raw file when phase1.json is missing a file.
 *
 * @return list<array{n: int, text: string, marks: list<array<string, mixed>>}>
 */
function raw_file_lines(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $content = file_get_contents($path);
    if ($content === false) {
        return [];
    }
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $parts = explode("\n", $content);
    // Drop trailing empty line from final newline
    if ($parts !== [] && end($parts) === '') {
        array_pop($parts);
    }

    $lines = [];
    $n = 1;
    foreach ($parts as $text) {
        $lines[] = [
            'n' => $n,
            'text' => $text,
            'marks' => [],
        ];
        $n++;
    }
    return $lines;
}

/**
 * @return list<string>
 */
function phase2_bucket_keys(): array
{
    return ['tasks', 'reference', 'articleCandidates'];
}

/**
 * @param mixed $tags
 * @return list<string>
 */
function normalize_tags($tags): array
{
    if (!is_array($tags)) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach ($tags as $tag) {
        $name = trim((string) $tag);
        if ($name === '') {
            continue;
        }
        $key = strtolower($name);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $name;
    }
    return $out;
}

/**
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function normalize_organized_item(array $item, string $bucket): array
{
    $id = trim((string) ($item['id'] ?? ''));
    $title = (string) ($item['title'] ?? ($id !== '' ? $id : 'Untitled'));
    $sources = $item['sources'] ?? [];
    if (!is_array($sources)) {
        $sources = [];
    }
    $sourcesOut = [];
    foreach ($sources as $src) {
        $sourcesOut[] = (string) $src;
    }

    $body = (string) ($item['body'] ?? '');
    $why = (string) ($item['why'] ?? '');
    if ($bucket === 'articleCandidates') {
        if ($why === '' && $body !== '') {
            $why = $body;
        }
        $normalized = [
            'id' => $id,
            'title' => $title,
            'why' => $why,
            'sources' => $sourcesOut,
            'tags' => normalize_tags($item['tags'] ?? []),
        ];
    } else {
        if ($body === '' && $why !== '') {
            $body = $why;
        }
        $normalized = [
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'sources' => $sourcesOut,
            'tags' => normalize_tags($item['tags'] ?? []),
        ];
    }

    return $normalized;
}

/**
 * @param array<string, mixed>|null $organized
 * @return array{tasks: list<array<string, mixed>>, reference: list<array<string, mixed>>, articleCandidates: list<array<string, mixed>>}
 */
function normalize_organized(?array $organized): array
{
    $out = [
        'tasks' => [],
        'reference' => [],
        'articleCandidates' => [],
    ];
    if (!is_array($organized)) {
        return $out;
    }

    foreach (phase2_bucket_keys() as $bucket) {
        $items = $organized[$bucket] ?? [];
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = normalize_organized_item($item, $bucket);
            if ($normalized['id'] === '') {
                continue;
            }
            $out[$bucket][] = $normalized;
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $data
 */
function save_json_file(string $path, array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return file_put_contents($path, $json . "\n") !== false;
}
