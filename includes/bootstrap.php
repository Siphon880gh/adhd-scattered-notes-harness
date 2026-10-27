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

/** Cache-bust local asset URLs with ?t=filemtime. */
function asset_url(string $relativePath): string
{
    $relativePath = ltrim($relativePath, '/');
    $fullPath = app_root() . '/' . $relativePath;
    $t = is_file($fullPath) ? (string) filemtime($fullPath) : (string) time();

    return $relativePath . '?t=' . rawurlencode($t);
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
    if ($phase !== 2 && $phase !== 3) {
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

    $skip = [
        'shorthands.md' => true,
        'phase2-suggestions.md' => true,
        'phase1-suggestions.md' => true,
    ];
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
 * @param array<string, mixed> $organized
 * @return list<string>
 */
function collect_organized_tags(array $organized): array
{
    $out = [];
    $seen = [];
    foreach (phase2_bucket_keys() as $bucket) {
        $items = $organized[$bucket] ?? [];
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (normalize_tags($item['tags'] ?? []) as $tagName) {
                $key = strtolower($tagName);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $tagName;
            }
        }
    }
    return $out;
}

/**
 * Merge two filter group headings when a divider between them is removed.
 */
function merge_filter_group_headings(string $left, string $right): string
{
    $left = trim($left);
    $right = trim($right);
    if ($left === '') {
        return $right;
    }
    if ($right === '') {
        return $left;
    }
    if (strcasecmp($left, $right) === 0) {
        return $left;
    }

    return $left . ' + ' . $right;
}

/**
 * @param mixed $layout
 * @param list<string> $availableTags
 * @return list<array{type: string, name?: string}>
 */
function normalize_filter_layout($layout, array $availableTags): array
{
    $available = [];
    foreach ($availableTags as $tagName) {
        $name = trim((string) $tagName);
        if ($name === '') {
            continue;
        }
        $available[strtolower($name)] = $name;
    }

    /** @var list<array{heading: string, tags: list<string>}> $groups */
    $groups = [];
    $heading = '';
    $tags = [];
    $used = [];

    $flushGroup = static function () use (&$groups, &$heading, &$tags): void {
        if ($tags === []) {
            $heading = '';

            return;
        }
        $groups[] = [
            'heading' => $heading,
            'tags' => $tags,
        ];
        $heading = '';
        $tags = [];
    };

    if (is_array($layout)) {
        foreach ($layout as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            if ($type === 'heading') {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                if ($tags === []) {
                    $heading = $name;
                } elseif ($heading === '') {
                    $heading = $name;
                }
                continue;
            }
            if ($type === 'divider') {
                if ($tags !== []) {
                    $flushGroup();
                }
                // Optional heading on divider names the following group (compat).
                $after = trim((string) ($item['heading'] ?? ''));
                if ($after !== '') {
                    $heading = $after;
                }
                continue;
            }
            if ($type !== 'tag') {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            if (!isset($available[$key]) || isset($used[$key])) {
                continue;
            }
            $used[$key] = true;
            $tags[] = $available[$key];
        }
    }
    $flushGroup();

    $missing = [];
    foreach ($available as $key => $name) {
        if (!isset($used[$key])) {
            $missing[] = $name;
        }
    }
    natcasesort($missing);
    $missing = array_values($missing);
    if ($missing !== []) {
        if ($groups === []) {
            $groups[] = ['heading' => '', 'tags' => $missing];
        } else {
            $last = count($groups) - 1;
            foreach ($missing as $name) {
                $groups[$last]['tags'][] = $name;
            }
        }
    }

    $out = [];
    foreach ($groups as $index => $group) {
        if ($group['tags'] === []) {
            continue;
        }
        if ($out !== []) {
            $out[] = ['type' => 'divider'];
        }
        if ($group['heading'] !== '') {
            $out[] = ['type' => 'heading', 'name' => $group['heading']];
        }
        foreach ($group['tags'] as $tagName) {
            $out[] = ['type' => 'tag', 'name' => $tagName];
        }
    }

    return $out;
}

/**
 * Per-notebook (tag) note id order for Notebook mode.
 *
 * @param mixed $order
 * @param array{tasks: list<array<string, mixed>>, reference: list<array<string, mixed>>, articleCandidates: list<array<string, mixed>>} $organized
 * @return array<string, list<string>>
 */
function normalize_notebook_order($order, array $organized): array
{
    $validIds = [];
    foreach (['tasks', 'reference', 'articleCandidates'] as $bucket) {
        $items = $organized[$bucket] ?? [];
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? ''));
            if ($id !== '') {
                $validIds[$id] = true;
            }
        }
    }

    $out = [];
    if (!is_array($order)) {
        return $out;
    }

    foreach ($order as $tagKey => $ids) {
        if (!is_string($tagKey) && !is_int($tagKey)) {
            continue;
        }
        $tag = trim((string) $tagKey);
        if ($tag === '' || !is_array($ids)) {
            continue;
        }
        $clean = [];
        $seen = [];
        foreach ($ids as $idRaw) {
            $id = trim((string) $idRaw);
            if ($id === '' || !isset($validIds[$id]) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $clean[] = $id;
        }
        if ($clean !== []) {
            $out[$tag] = $clean;
        }
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
    $checked = !empty($item['checked']);

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
            'checked' => $checked,
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
            'checked' => $checked,
        ];
    }

    return $normalized;
}

/**
 * @param array<string, mixed>|null $phase1
 * @param list<string> $noteFiles
 * @return array{files: int, uncertain: int, youtube: int, links: int, blocked: int}
 */
function phase1_review_counts(?array $phase1, array $noteFiles): array
{
    $uncertain = 0;
    $youtube = 0;
    $links = 0;
    $blocked = 0;
    if (is_array($phase1) && isset($phase1['files']) && is_array($phase1['files'])) {
        foreach ($phase1['files'] as $fileData) {
            if (!is_array($fileData)) {
                continue;
            }
            $lines = $fileData['lines'] ?? [];
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $marks = $line['marks'] ?? [];
                if (is_array($marks)) {
                    foreach ($marks as $mark) {
                        if (is_array($mark) && ($mark['type'] ?? '') === 'uncertain') {
                            $uncertain++;
                            break;
                        }
                    }
                }
                $yt = $line['youtube'] ?? null;
                if (is_array($yt) && !empty($yt['added'])) {
                    $youtube++;
                }
                $link = $line['link'] ?? null;
                if (is_array($link)) {
                    if (!empty($link['blocked'])) {
                        $blocked++;
                    }
                    if (!empty($link['added'])) {
                        $links++;
                    }
                }
            }
        }
    }

    return [
        'files' => count($noteFiles),
        'uncertain' => $uncertain,
        'youtube' => $youtube,
        'links' => $links,
        'blocked' => $blocked,
    ];
}

/**
 * Per-file Phase 1 nav badges: attention (shorthands + blocked) vs quiet added enrichments.
 *
 * @param array<string, mixed>|null $phase1
 * @param list<string> $noteFiles
 * @return array<string, array{uncertain: int, blocked: int, attention: int, added: int}>
 */
function phase1_file_nav_stats(?array $phase1, array $noteFiles): array
{
    $out = [];
    foreach ($noteFiles as $name) {
        $out[$name] = [
            'uncertain' => 0,
            'blocked' => 0,
            'attention' => 0,
            'added' => 0,
        ];
    }

    if (!is_array($phase1) || !isset($phase1['files']) || !is_array($phase1['files'])) {
        return $out;
    }

    foreach ($phase1['files'] as $fileName => $fileData) {
        $fileName = (string) $fileName;
        if (!isset($out[$fileName]) || !is_array($fileData)) {
            continue;
        }
        $lines = $fileData['lines'] ?? [];
        if (!is_array($lines)) {
            continue;
        }
        $uncertain = 0;
        $blocked = 0;
        $added = 0;
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $marks = $line['marks'] ?? [];
            if (is_array($marks)) {
                foreach ($marks as $mark) {
                    if (is_array($mark) && ($mark['type'] ?? '') === 'uncertain') {
                        $uncertain++;
                        break;
                    }
                }
            }
            $yt = $line['youtube'] ?? null;
            if (is_array($yt) && !empty($yt['added'])) {
                $added++;
            }
            $link = $line['link'] ?? null;
            if (is_array($link)) {
                if (!empty($link['blocked'])) {
                    $blocked++;
                }
                if (!empty($link['added'])) {
                    $added++;
                }
            }
        }
        $out[$fileName] = [
            'uncertain' => $uncertain,
            'blocked' => $blocked,
            'attention' => $uncertain + $blocked,
            'added' => $added,
        ];
    }

    return $out;
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

/**
 * Write userPaste / userNote onto a blocked (or any) link line in phase1.json.
 *
 * @param array<string, mixed> $phase1
 * @return array{ok: bool, error?: string, phase1?: array<string, mixed>}
 */
function apply_phase1_url_paste(array $phase1, string $file, int $lineN, string $userPaste, string $userNote): array
{
    $file = trim($file);
    if ($file === '' || $lineN < 1) {
        return ['ok' => false, 'error' => 'Expected file and positive line number.'];
    }
    if (!isset($phase1['files']) || !is_array($phase1['files'])) {
        return ['ok' => false, 'error' => 'phase1.json has no files map.'];
    }
    if (!isset($phase1['files'][$file]) || !is_array($phase1['files'][$file])) {
        return ['ok' => false, 'error' => 'Unknown file in phase1.json.'];
    }
    $lines = $phase1['files'][$file]['lines'] ?? null;
    if (!is_array($lines)) {
        return ['ok' => false, 'error' => 'File has no lines in phase1.json.'];
    }

    $found = false;
    foreach ($lines as $idx => $line) {
        if (!is_array($line)) {
            continue;
        }
        if ((int) ($line['n'] ?? 0) !== $lineN) {
            continue;
        }
        $link = $line['link'] ?? null;
        if (!is_array($link)) {
            $link = [];
        }
        $link['userPaste'] = $userPaste;
        $link['userNote'] = $userNote;
        if (!isset($link['url']) || trim((string) $link['url']) === '') {
            $link['url'] = trim((string) ($line['text'] ?? ''));
        }
        if (!isset($link['intent'])) {
            $link['intent'] = '';
        }
        if (!isset($link['title'])) {
            $link['title'] = '';
        }
        if (!isset($link['about'])) {
            $link['about'] = '';
        }
        if (!array_key_exists('added', $link)) {
            $link['added'] = false;
        }
        if (!array_key_exists('blocked', $link)) {
            $link['blocked'] = true;
        }
        $line['link'] = $link;
        $lines[$idx] = $line;
        $found = true;
        break;
    }

    if (!$found) {
        return ['ok' => false, 'error' => 'Line not found in phase1.json.'];
    }

    $phase1['files'][$file]['lines'] = $lines;
    return ['ok' => true, 'phase1' => $phase1];
}

function phase2_suggestions_path(string $dir): string
{
    return $dir . '/phase2-suggestions.md';
}

/**
 * Panel keys that accept whole-section Suggest notes (match phase2 buckets).
 *
 * @return list<string>
 */
function phase2_suggestion_panel_keys(): array
{
    return ['tasks', 'reference', 'articleCandidates'];
}

/**
 * @param mixed $data
 * @return array{panel: array{tasks: string, reference: string, articleCandidates: string}, items: array<string, string>}
 */
function normalize_phase2_suggestions($data): array
{
    $panelKeys = phase2_suggestion_panel_keys();
    $out = [
        'panel' => [
            'tasks' => '',
            'reference' => '',
            'articleCandidates' => '',
        ],
        'items' => [],
    ];
    if (!is_array($data)) {
        return $out;
    }

    $panel = $data['panel'] ?? null;
    if (is_array($panel)) {
        foreach ($panelKeys as $key) {
            $out['panel'][$key] = trim((string) ($panel[$key] ?? ''));
        }
    }

    $items = $data['items'] ?? null;
    if (is_array($items)) {
        foreach ($items as $id => $text) {
            $itemId = trim((string) $id);
            if ($itemId === '') {
                continue;
            }
            $body = trim((string) $text);
            if ($body === '') {
                continue;
            }
            $out['items'][$itemId] = $body;
        }
    }

    return $out;
}

/**
 * @return array{panel: array{tasks: string, reference: string, articleCandidates: string}, items: array<string, string>}
 */
function parse_phase2_suggestions_md(string $raw): array
{
    $panelKeys = phase2_suggestion_panel_keys();
    $out = [
        'panel' => [
            'tasks' => '',
            'reference' => '',
            'articleCandidates' => '',
        ],
        'items' => [],
    ];

    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);
    $section = null; // 'panel:{key}' | 'item:{id}'
    $buf = [];

    $flush = static function () use (&$section, &$buf, &$out, $panelKeys): void {
        if ($section === null) {
            $buf = [];
            return;
        }
        $text = trim(implode("\n", $buf));
        $buf = [];
        if ($text === '') {
            return;
        }
        if (strpos($section, 'panel:') === 0) {
            $key = substr($section, 6);
            if (in_array($key, $panelKeys, true)) {
                $out['panel'][$key] = $text;
            }
            return;
        }
        if (strpos($section, 'item:') === 0) {
            $id = substr($section, 5);
            if ($id !== '') {
                $out['items'][$id] = $text;
            }
        }
    };

    foreach ($lines as $line) {
        if (preg_match('/^##\s+Panel:\s*(tasks|reference|articleCandidates)\s*$/i', $line, $m)) {
            $flush();
            $keyMap = [
                'tasks' => 'tasks',
                'reference' => 'reference',
                'articlecandidates' => 'articleCandidates',
            ];
            $key = $keyMap[strtolower($m[1])] ?? null;
            if ($key !== null) {
                $section = 'panel:' . $key;
            }
            continue;
        }
        if (preg_match('/^##\s+Item:\s*(\S+)\s*$/i', $line, $m)) {
            $flush();
            $section = 'item:' . trim($m[1]);
            continue;
        }
        if (preg_match('/^#\s+/', $line) && $section === null) {
            continue;
        }
        if ($section !== null) {
            $buf[] = $line;
        }
    }
    $flush();

    return normalize_phase2_suggestions($out);
}

/**
 * @param array{panel: array{tasks: string, reference: string, articleCandidates: string}, items: array<string, string>} $data
 */
function format_phase2_suggestions_md(array $data): string
{
    $data = normalize_phase2_suggestions($data);
    $parts = ["# Phase 2 suggestions", ''];

    foreach (phase2_suggestion_panel_keys() as $key) {
        $panelText = $data['panel'][$key];
        if ($panelText === '') {
            continue;
        }
        $parts[] = '## Panel: ' . $key;
        $parts[] = '';
        $parts[] = $panelText;
        $parts[] = '';
    }

    foreach ($data['items'] as $id => $text) {
        $parts[] = '## Item: ' . $id;
        $parts[] = '';
        $parts[] = $text;
        $parts[] = '';
    }

    $md = rtrim(implode("\n", $parts));
    return $md === '# Phase 2 suggestions' ? "# Phase 2 suggestions\n" : $md . "\n";
}

/**
 * @return array{panel: array{tasks: string, reference: string, articleCandidates: string}, items: array<string, string>}
 */
function load_phase2_suggestions(string $dir): array
{
    $path = phase2_suggestions_path($dir);
    if (!is_file($path)) {
        return normalize_phase2_suggestions(null);
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return normalize_phase2_suggestions(null);
    }
    return parse_phase2_suggestions_md($raw);
}

/**
 * @param array<string, mixed> $data
 */
function save_phase2_suggestions(string $dir, array $data): bool
{
    $normalized = normalize_phase2_suggestions($data);
    $md = format_phase2_suggestions_md($normalized);
    $path = phase2_suggestions_path($dir);

    $panelEmpty = true;
    foreach (phase2_suggestion_panel_keys() as $key) {
        if ($normalized['panel'][$key] !== '') {
            $panelEmpty = false;
            break;
        }
    }

    if ($panelEmpty && $normalized['items'] === []) {
        if (is_file($path)) {
            return unlink($path);
        }
        return true;
    }

    return file_put_contents($path, $md) !== false;
}

function phase1_suggestions_path(string $dir): string
{
    return $dir . '/phase1-suggestions.md';
}

/**
 * Normalize shorthand token keys (trim; preserve display casing from first write).
 */
function phase1_shorthand_key(string $token): string
{
    return trim($token);
}

/**
 * @param mixed $data
 * @return array{shorthands: array<string, string>, enrichments: array<string, string>}
 */
function normalize_phase1_suggestions($data): array
{
    $out = [
        'shorthands' => [],
        'enrichments' => [],
    ];
    if (!is_array($data)) {
        return $out;
    }

    $shorthands = $data['shorthands'] ?? null;
    if (is_array($shorthands)) {
        $seen = [];
        foreach ($shorthands as $token => $text) {
            $key = phase1_shorthand_key((string) $token);
            if ($key === '') {
                continue;
            }
            $body = trim((string) $text);
            if ($body === '') {
                continue;
            }
            $fold = strtolower($key);
            if (isset($seen[$fold])) {
                continue;
            }
            $seen[$fold] = true;
            $out['shorthands'][$key] = $body;
        }
    }

    $enrichments = $data['enrichments'] ?? null;
    if (is_array($enrichments)) {
        foreach ($enrichments as $id => $text) {
            $itemId = trim((string) $id);
            if ($itemId === '' || !preg_match('/^.+:\d+$/', $itemId)) {
                continue;
            }
            $body = trim((string) $text);
            if ($body === '') {
                continue;
            }
            $out['enrichments'][$itemId] = $body;
        }
    }

    return $out;
}

/**
 * Case-insensitive shorthand lookup.
 *
 * @param array{shorthands: array<string, string>, enrichments: array<string, string>} $suggestions
 */
function phase1_shorthand_suggestion(array $suggestions, string $token): string
{
    $key = phase1_shorthand_key($token);
    if ($key === '') {
        return '';
    }
    if (isset($suggestions['shorthands'][$key])) {
        return (string) $suggestions['shorthands'][$key];
    }
    $fold = strtolower($key);
    foreach ($suggestions['shorthands'] as $stored => $text) {
        if (strtolower((string) $stored) === $fold) {
            return (string) $text;
        }
    }
    return '';
}

/**
 * @return array{shorthands: array<string, string>, enrichments: array<string, string>}
 */
function parse_phase1_suggestions_md(string $raw): array
{
    $out = [
        'shorthands' => [],
        'enrichments' => [],
    ];

    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);
    $section = null; // 'shorthand:{token}' | 'enrichment:{file:line}'
    $buf = [];

    $flush = static function () use (&$section, &$buf, &$out): void {
        if ($section === null) {
            $buf = [];
            return;
        }
        $text = trim(implode("\n", $buf));
        $buf = [];
        if ($text === '') {
            return;
        }
        if (strpos($section, 'shorthand:') === 0) {
            $token = phase1_shorthand_key(substr($section, 10));
            if ($token !== '') {
                $out['shorthands'][$token] = $text;
            }
            return;
        }
        if (strpos($section, 'enrichment:') === 0) {
            $id = trim(substr($section, 11));
            if ($id !== '') {
                $out['enrichments'][$id] = $text;
            }
        }
    };

    foreach ($lines as $line) {
        if (preg_match('/^##\s+Shorthand:\s*(.+?)\s*$/i', $line, $m)) {
            $flush();
            $section = 'shorthand:' . phase1_shorthand_key($m[1]);
            continue;
        }
        if (preg_match('/^##\s+Enrichment:\s*(\S+)\s*$/i', $line, $m)) {
            $flush();
            $section = 'enrichment:' . trim($m[1]);
            continue;
        }
        if (preg_match('/^#\s+/', $line) && $section === null) {
            continue;
        }
        if ($section !== null) {
            $buf[] = $line;
        }
    }
    $flush();

    return normalize_phase1_suggestions($out);
}

/**
 * @param array{shorthands: array<string, string>, enrichments: array<string, string>} $data
 */
function format_phase1_suggestions_md(array $data): string
{
    $data = normalize_phase1_suggestions($data);
    $parts = ['# Phase 1 suggestions', ''];

    foreach ($data['shorthands'] as $token => $text) {
        $parts[] = '## Shorthand: ' . $token;
        $parts[] = '';
        $parts[] = $text;
        $parts[] = '';
    }

    foreach ($data['enrichments'] as $id => $text) {
        $parts[] = '## Enrichment: ' . $id;
        $parts[] = '';
        $parts[] = $text;
        $parts[] = '';
    }

    $md = rtrim(implode("\n", $parts));
    return $md === '# Phase 1 suggestions' ? "# Phase 1 suggestions\n" : $md . "\n";
}

/**
 * @return array{shorthands: array<string, string>, enrichments: array<string, string>}
 */
function load_phase1_suggestions(string $dir): array
{
    $path = phase1_suggestions_path($dir);
    if (!is_file($path)) {
        return normalize_phase1_suggestions(null);
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return normalize_phase1_suggestions(null);
    }
    return parse_phase1_suggestions_md($raw);
}

/**
 * @param array<string, mixed> $data
 */
function save_phase1_suggestions(string $dir, array $data): bool
{
    $normalized = normalize_phase1_suggestions($data);
    $md = format_phase1_suggestions_md($normalized);
    $path = phase1_suggestions_path($dir);

    if ($normalized['shorthands'] === [] && $normalized['enrichments'] === []) {
        if (is_file($path)) {
            return unlink($path);
        }
        return true;
    }

    return file_put_contents($path, $md) !== false;
}

function snapshots_dir(): string
{
    return app_root() . '/snapshots';
}

function ensure_snapshots_dir(): bool
{
    $dir = snapshots_dir();
    if (is_dir($dir)) {
        return true;
    }
    return mkdir($dir, 0775, true);
}

/**
 * Safe snapshot file stem (no path segments).
 */
function sanitize_snapshot_id(string $id): string
{
    $id = trim($id);
    if ($id === '') {
        return '';
    }
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,120}$/', $id)) {
        return '';
    }
    return $id;
}

function snapshot_path(string $id): string
{
    $safe = sanitize_snapshot_id($id);
    if ($safe === '') {
        return '';
    }
    return snapshots_dir() . '/' . $safe . '.json';
}

/**
 * @return list<array{id: string, name: string, sourceFolder: string, createdAt: string}>
 */
function list_snapshots(): array
{
    $dir = snapshots_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $out = [];
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
            continue;
        }
        $id = pathinfo($name, PATHINFO_FILENAME);
        $safe = sanitize_snapshot_id($id);
        if ($safe === '' || $safe !== $id) {
            continue;
        }
        $data = load_json_file($dir . '/' . $name);
        if ($data === null) {
            continue;
        }
        $createdAt = trim((string) ($data['createdAt'] ?? ''));
        $mtime = @filemtime($dir . '/' . $name);
        if ($createdAt === '' && $mtime !== false) {
            $createdAt = gmdate('c', $mtime);
        }
        $sourceFolder = trim((string) ($data['sourceFolder'] ?? ''));
        $label = trim((string) ($data['name'] ?? ''));
        if ($label === '') {
            $label = $sourceFolder !== '' ? $sourceFolder : $safe;
        }
        $out[] = [
            'id' => $safe,
            'name' => $label,
            'sourceFolder' => $sourceFolder,
            'createdAt' => $createdAt,
        ];
    }

    usort($out, static function (array $a, array $b): int {
        return strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
    });

    return $out;
}

/**
 * @return array<string, mixed>|null
 */
function load_snapshot(string $id): ?array
{
    $path = snapshot_path($id);
    if ($path === '' || !is_file($path)) {
        return null;
    }
    $data = load_json_file($path);
    if ($data === null) {
        return null;
    }

    $safe = sanitize_snapshot_id($id);
    $organized = normalize_organized(
        is_array($data['organized'] ?? null) ? $data['organized'] : []
    );
    $availableTags = collect_organized_tags($organized);
    $filterLayout = normalize_filter_layout(
        is_array($data['filterLayout'] ?? null) ? $data['filterLayout'] : [],
        $availableTags
    );
    $notebookOrder = normalize_notebook_order(
        $data['notebookOrder'] ?? [],
        $organized
    );

    $name = trim((string) ($data['name'] ?? ''));
    $sourceFolder = trim((string) ($data['sourceFolder'] ?? ''));
    $createdAt = trim((string) ($data['createdAt'] ?? ''));
    if ($name === '') {
        $name = $sourceFolder !== '' ? $sourceFolder : $safe;
    }

    return [
        'id' => $safe,
        'name' => $name,
        'sourceFolder' => $sourceFolder,
        'createdAt' => $createdAt,
        'organized' => $organized,
        'filterLayout' => $filterLayout,
        'notebookOrder' => $notebookOrder,
        'blame' => is_array($data['blame'] ?? null) ? $data['blame'] : [],
    ];
}

/**
 * Persist a Phase 3 snapshot from the current phase2 payload. Does not touch app.config.json.
 *
 * @param array<string, mixed> $phase2
 * @return array{ok: bool, error?: string, snapshot?: array{id: string, name: string, sourceFolder: string, createdAt: string, path: string}}
 */
function save_phase3_snapshot(string $sourceFolder, array $phase2, string $name = ''): array
{
    $sourceFolder = trim($sourceFolder);
    if ($sourceFolder === '') {
        return ['ok' => false, 'error' => 'No input folder to snapshot.'];
    }
    if (!ensure_snapshots_dir()) {
        return ['ok' => false, 'error' => 'Could not create snapshots/ folder.'];
    }

    $organized = normalize_organized(
        is_array($phase2['organized'] ?? null) ? $phase2['organized'] : []
    );
    $availableTags = collect_organized_tags($organized);
    $filterLayout = normalize_filter_layout(
        is_array($phase2['filterLayout'] ?? null) ? $phase2['filterLayout'] : [],
        $availableTags
    );
    $notebookOrder = normalize_notebook_order(
        $phase2['notebookOrder'] ?? [],
        $organized
    );
    $blame = is_array($phase2['blame'] ?? null) ? $phase2['blame'] : [];

    $label = trim($name);
    if ($label === '') {
        return ['ok' => false, 'error' => 'Enter a snapshot name.'];
    }
    $label = mb_substr($label, 0, 80);
    $labelKey = strtolower($label);
    foreach (list_snapshots() as $existingSnap) {
        if (strtolower((string) ($existingSnap['name'] ?? '')) === $labelKey) {
            return [
                'ok' => false,
                'error' => 'A snapshot named "' . $label . '" already exists. Choose a different name.',
            ];
        }
    }

    $stamp = gmdate('Ymd-His');
    $folderSlug = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $sourceFolder) ?? 'folder';
    $folderSlug = trim($folderSlug, '-._');
    if ($folderSlug === '') {
        $folderSlug = 'folder';
    }
    $baseId = $folderSlug . '-' . $stamp;
    $id = sanitize_snapshot_id($baseId);
    if ($id === '') {
        return ['ok' => false, 'error' => 'Could not build a safe snapshot id.'];
    }
    $path = snapshot_path($id);
    $n = 1;
    while ($path !== '' && is_file($path) && $n < 50) {
        $id = sanitize_snapshot_id($baseId . '-' . $n);
        $path = snapshot_path($id);
        $n++;
    }
    if ($id === '' || $path === '' || is_file($path)) {
        return ['ok' => false, 'error' => 'Snapshot id collision — try again.'];
    }

    $createdAt = gmdate('c');
    $payload = [
        'id' => $id,
        'name' => $label,
        'sourceFolder' => $sourceFolder,
        'createdAt' => $createdAt,
        'organized' => $organized,
        'filterLayout' => $filterLayout,
        'notebookOrder' => $notebookOrder === [] ? new \stdClass() : $notebookOrder,
        'blame' => $blame,
    ];

    if (!save_json_file($path, $payload)) {
        return ['ok' => false, 'error' => 'Could not write snapshot file.'];
    }

    return [
        'ok' => true,
        'snapshot' => [
            'id' => $id,
            'name' => $label,
            'sourceFolder' => $sourceFolder,
            'createdAt' => $createdAt,
            'path' => 'snapshots/' . $id . '.json',
        ],
    ];
}

/**
 * Update an existing snapshot's organized / filterLayout / notebookOrder.
 * Does not touch app.config.json or live phase2.json.
 *
 * @param array<string, mixed> $phase2
 * @return array{ok: bool, error?: string, snapshot?: array{id: string, name: string, sourceFolder: string, createdAt: string, path: string}}
 */
function update_snapshot(string $id, array $phase2): array
{
    $safe = sanitize_snapshot_id($id);
    if ($safe === '') {
        return ['ok' => false, 'error' => 'Invalid snapshot id.'];
    }
    $path = snapshot_path($safe);
    if ($path === '' || !is_file($path)) {
        return ['ok' => false, 'error' => 'Snapshot not found.'];
    }

    $existing = load_json_file($path);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Could not read snapshot file.'];
    }

    $organized = normalize_organized(
        is_array($phase2['organized'] ?? null) ? $phase2['organized'] : []
    );
    $availableTags = collect_organized_tags($organized);
    $filterLayout = normalize_filter_layout(
        is_array($phase2['filterLayout'] ?? null) ? $phase2['filterLayout'] : [],
        $availableTags
    );
    $notebookOrder = normalize_notebook_order(
        $phase2['notebookOrder'] ?? [],
        $organized
    );

    $name = trim((string) ($existing['name'] ?? ''));
    $sourceFolder = trim((string) ($existing['sourceFolder'] ?? ''));
    $createdAt = trim((string) ($existing['createdAt'] ?? ''));
    if ($name === '') {
        $name = $sourceFolder !== '' ? $sourceFolder : $safe;
    }
    if ($createdAt === '') {
        $createdAt = gmdate('c');
    }
    $blame = is_array($existing['blame'] ?? null) ? $existing['blame'] : [];
    if (array_key_exists('blame', $phase2) && is_array($phase2['blame'])) {
        $blame = $phase2['blame'];
    }

    $payload = [
        'id' => $safe,
        'name' => $name,
        'sourceFolder' => $sourceFolder,
        'createdAt' => $createdAt,
        'organized' => $organized,
        'filterLayout' => $filterLayout,
        'notebookOrder' => $notebookOrder === [] ? new \stdClass() : $notebookOrder,
        'blame' => $blame,
    ];

    if (!save_json_file($path, $payload)) {
        return ['ok' => false, 'error' => 'Could not write snapshot file.'];
    }

    return [
        'ok' => true,
        'snapshot' => [
            'id' => $safe,
            'name' => $name,
            'sourceFolder' => $sourceFolder,
            'createdAt' => $createdAt,
            'path' => 'snapshots/' . $safe . '.json',
        ],
    ];
}

/**
 * Rename a snapshot while preserving its stable file id and contents.
 *
 * @return array{ok: bool, error?: string, snapshot?: array{id: string, name: string, sourceFolder: string, createdAt: string, path: string}}
 */
function rename_snapshot(string $id, string $name): array
{
    $safe = sanitize_snapshot_id($id);
    $path = snapshot_path($safe);
    if ($safe === '' || $path === '' || !is_file($path)) {
        return ['ok' => false, 'error' => 'Snapshot not found.'];
    }

    $label = mb_substr(trim($name), 0, 80);
    if ($label === '') {
        return ['ok' => false, 'error' => 'Enter a snapshot name.'];
    }
    foreach (list_snapshots() as $existingSnap) {
        if ((string) ($existingSnap['id'] ?? '') !== $safe
            && strtolower((string) ($existingSnap['name'] ?? '')) === strtolower($label)) {
            return ['ok' => false, 'error' => 'A snapshot named "' . $label . '" already exists. Choose a different name.'];
        }
    }

    $payload = load_json_file($path);
    if ($payload === null) {
        return ['ok' => false, 'error' => 'Could not read snapshot file.'];
    }
    $payload['id'] = $safe;
    $payload['name'] = $label;
    if (!save_json_file($path, $payload)) {
        return ['ok' => false, 'error' => 'Could not write snapshot file.'];
    }

    $snapshot = load_snapshot($safe);
    if ($snapshot === null) {
        return ['ok' => false, 'error' => 'Could not reload renamed snapshot.'];
    }
    $snapshot['path'] = 'snapshots/' . $safe . '.json';
    return ['ok' => true, 'snapshot' => $snapshot];
}

/**
 * Delete one safely resolved snapshot file.
 *
 * @return array{ok: bool, error?: string}
 */
function delete_snapshot(string $id): array
{
    $safe = sanitize_snapshot_id($id);
    $path = snapshot_path($safe);
    if ($safe === '' || $path === '' || !is_file($path)) {
        return ['ok' => false, 'error' => 'Snapshot not found.'];
    }
    if (!unlink($path)) {
        return ['ok' => false, 'error' => 'Could not delete snapshot file.'];
    }
    return ['ok' => true];
}
