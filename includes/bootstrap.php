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

function normalize_phase(int $phase): int
{
    return ($phase === 2 || $phase === 3) ? $phase : 1;
}

function app_config_path(): string
{
    return app_root() . '/app.config.json';
}

/**
 * Folder names directly under inputs/. These are separate projects.
 *
 * @return list<string>
 */
function list_input_projects(): array
{
    $root = app_root() . '/inputs';
    if (!is_dir($root)) {
        return [];
    }

    $names = [];
    foreach (scandir($root) ?: [] as $name) {
        if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
            continue;
        }
        if (is_dir($root . '/' . $name)) {
            $names[] = $name;
        }
    }

    natcasesort($names);

    return array_values($names);
}

/**
 * Phase to use the first time a project is opened and nothing has remembered it.
 * Phase 2 once phase2.json exists. Phase 3 once that file has a filter layout.
 */
function infer_project_phase(string $folder): int
{
    $dir = input_dir($folder);
    if (!is_dir($dir)) {
        return 1;
    }
    $phase2 = load_json_file($dir . '/phase2.json');
    if (!is_array($phase2)) {
        return 1;
    }
    $layout = $phase2['filterLayout'] ?? null;
    if (is_array($layout) && $layout !== []) {
        return 3;
    }

    return 2;
}

/**
 * @param mixed $raw
 * @return array<string, array{phase: int}>
 */
function normalize_project_registry($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $name => $row) {
        $name = basename(str_replace('\\', '/', trim((string) $name)));
        if ($name === '' || $name === '.' || $name === '..') {
            continue;
        }
        $phase = 1;
        if (is_array($row) && isset($row['phase'])) {
            $phase = (int) $row['phase'];
        } elseif (is_numeric($row)) {
            $phase = (int) $row;
        }
        $out[$name] = ['phase' => normalize_phase($phase)];
    }
    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

    return $out;
}

/**
 * @return array{inputFolder: string, phase: int, projects: array<string, array{phase: int}>}
 */
function load_config(): array
{
    $path = app_config_path();
    $defaults = ['inputFolder' => '', 'phase' => 1, 'projects' => []];

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
    $folder = basename(str_replace('\\', '/', $folder));
    if ($folder === '.' || $folder === '..') {
        $folder = '';
    }
    $phase = isset($data['phase']) ? normalize_phase((int) $data['phase']) : 1;

    return [
        'inputFolder' => $folder,
        'phase' => $phase,
        'projects' => normalize_project_registry($data['projects'] ?? null),
    ];
}

/**
 * Phase shown for a project. The live folder uses the top-level phase.
 * A remembered phase wins over inference. Otherwise infer from artifacts.
 *
 * @param array{inputFolder: string, phase: int, projects: array<string, array{phase: int}>} $config
 */
function project_phase_for(array $config, string $folder): int
{
    if ($folder !== '' && $folder === ($config['inputFolder'] ?? '')) {
        return normalize_phase((int) ($config['phase'] ?? 1));
    }
    if (isset($config['projects'][$folder]['phase'])) {
        return normalize_phase((int) $config['projects'][$folder]['phase']);
    }

    return infer_project_phase($folder);
}

/**
 * @param array<string, array{phase: int}> $projects
 */
function save_app_config(string $folder, int $phase, array $projects): bool
{
    $folder = basename(str_replace('\\', '/', trim($folder)));
    if ($folder === '.' || $folder === '..') {
        $folder = '';
    }
    $phase = normalize_phase($phase);
    $projects = normalize_project_registry($projects);
    if ($folder !== '') {
        $projects[$folder] = ['phase' => $phase];
        ksort($projects, SORT_NATURAL | SORT_FLAG_CASE);
    }

    $json = json_encode(
        [
            'inputFolder' => $folder,
            'phase' => $phase,
            'projects' => $projects === [] ? new stdClass() : $projects,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($json === false) {
        return false;
    }

    return file_put_contents(app_config_path(), $json . "\n") !== false;
}

/**
 * Remember the live phase and fill in projects that have never been opened.
 * Returns the config after the registry is current.
 *
 * @return array{inputFolder: string, phase: int, projects: array<string, array{phase: int}>}
 */
function ensure_project_registry(): array
{
    $config = load_config();
    $projects = $config['projects'];
    $known = list_input_projects();
    $knownSet = array_fill_keys($known, true);
    foreach (array_keys($projects) as $name) {
        if (!isset($knownSet[$name])) {
            unset($projects[$name]);
        }
    }
    foreach ($known as $name) {
        if (!isset($projects[$name])) {
            $projects[$name] = ['phase' => infer_project_phase($name)];
        }
    }
    $folder = $config['inputFolder'];
    $phase = $config['phase'];
    if ($folder !== '' && isset($knownSet[$folder])) {
        $projects[$folder] = ['phase' => $phase];
    }

    $before = json_encode($config['projects']);
    $after = json_encode(normalize_project_registry($projects));
    if ($before !== $after) {
        save_app_config($folder, $phase, $projects);
        $config = load_config();
    }

    return $config;
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
        'phase1-regions.md' => true,
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
 * Dash-only fence length, or 0 when the line is not a note break.
 * Three or more dashes (`---`, `------`) match the harness divider rules.
 */
function phase1_dash_length(string $text): int
{
    $trim = trim($text);
    if ($trim === '' || preg_match('/^-{3,}$/', $trim) !== 1) {
        return 0;
    }

    return strlen($trim);
}

/**
 * @return list<int>
 */
function phase1_thought_palette(): array
{
    return [199, 152, 42, 270, 330, 12, 88, 230];
}

/**
 * Hue for note $id (1-based). Skips the previous note's hue so neighbors differ.
 */
function phase1_thought_hue(int $id, ?int $previous = null): int
{
    $hues = phase1_thought_palette();
    $count = count($hues);
    $hue = $hues[($id - 1) % $count];
    if ($previous !== null && $hue === $previous) {
        $hue = $hues[$id % $count];
    }

    return $hue;
}

function phase1_thought_preview(string $text, int $max = 84): string
{
    $text = trim((string) (preg_replace('/\s+/u', ' ', $text) ?? ''));
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text) > $max) {
        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
    if (strlen($text) > $max) {
        return rtrim(substr($text, 0, $max - 1)) . '…';
    }

    return $text;
}

/**
 * Indexes of short dash lines kept as content because a longer fence wraps them
 * (`---` inside a `------` article wrapper).
 *
 * @param list<array<string, mixed>> $lines
 * @return array<int, true>
 */
function phase1_protected_dash_indexes(array $lines): array
{
    $byLength = [];
    foreach ($lines as $i => $line) {
        if (!is_array($line)) {
            continue;
        }
        $len = phase1_dash_length((string) ($line['text'] ?? ''));
        if ($len >= 6) {
            $byLength[$len][] = $i;
        }
    }

    $protected = [];
    foreach ($byLength as $len => $positions) {
        $pairs = intdiv(count($positions), 2);
        for ($pair = 0; $pair < $pairs; $pair++) {
            $start = $positions[$pair * 2];
            $end = $positions[$pair * 2 + 1];
            for ($j = $start + 1; $j < $end; $j++) {
                $inner = $lines[$j] ?? null;
                if (!is_array($inner)) {
                    continue;
                }
                $innerLen = phase1_dash_length((string) ($inner['text'] ?? ''));
                if ($innerLen >= 3 && $innerLen < $len) {
                    $protected[$j] = true;
                }
            }
        }
    }

    return $protected;
}

/**
 * Group source lines into distinct notes.
 * A line of only `---` (or longer) is a hard break. A longer matching fence
 * keeps shorter dash lines inside that note. Files with no dash break split
 * on blank lines instead.
 *
 * User overrides: `splits` are source line numbers that start a new note even
 * when the file has no break there. `joins` are source line numbers that stay
 * with the previous note even when a dash or blank line would split them.
 *
 * @param list<array<string, mixed>> $lines
 * @param array{splits?: list<int>, joins?: list<int>} $overrides
 * @return array{mode: string, thoughts: list<array{id: int, start: int, end: int, preview: string, lines: int, hue: int, uncertain: bool, added: bool, blocked: bool, indexes: list<int>}>}
 */
function phase1_thoughts(array $lines, array $overrides = []): array
{
    $lines = array_values($lines);
    $protected = phase1_protected_dash_indexes($lines);
    $hasHardBreak = false;
    foreach ($lines as $i => $line) {
        if (!is_array($line)) {
            continue;
        }
        $len = phase1_dash_length((string) ($line['text'] ?? ''));
        if ($len >= 3 && !isset($protected[$i])) {
            $hasHardBreak = true;
            break;
        }
    }

    $splits = [];
    foreach ($overrides['splits'] ?? [] as $n) {
        $n = (int) $n;
        if ($n > 0) {
            $splits[$n] = true;
        }
    }
    $joins = [];
    foreach ($overrides['joins'] ?? [] as $n) {
        $n = (int) $n;
        if ($n > 0) {
            $joins[$n] = true;
        }
    }

    $thoughts = [];
    $current = [];
    $flush = function () use (&$thoughts, &$current, $lines): void {
        $content = [];
        foreach ($current as $i) {
            $text = trim((string) ($lines[$i]['text'] ?? ''));
            if ($text !== '') {
                $content[] = $i;
            }
        }
        if ($content === []) {
            $current = [];
            return;
        }
        while ($current !== [] && trim((string) ($lines[$current[0]]['text'] ?? '')) === '') {
            array_shift($current);
        }
        while ($current !== [] && trim((string) ($lines[$current[count($current) - 1]]['text'] ?? '')) === '') {
            array_pop($current);
        }
        $firstText = (string) ($lines[$content[0]]['text'] ?? '');
        $startN = (int) ($lines[$content[0]]['n'] ?? ($content[0] + 1));
        $last = $content[count($content) - 1];
        $endN = (int) ($lines[$last]['n'] ?? ($last + 1));
        $uncertain = false;
        $added = false;
        $blocked = false;
        foreach ($content as $i) {
            $row = $lines[$i];
            $marks = $row['marks'] ?? [];
            if (is_array($marks)) {
                foreach ($marks as $mark) {
                    if (is_array($mark) && ($mark['type'] ?? '') === 'uncertain') {
                        $uncertain = true;
                        break;
                    }
                }
            }
            $yt = $row['youtube'] ?? null;
            if (is_array($yt) && !empty($yt['added'])) {
                $added = true;
            }
            $link = $row['link'] ?? null;
            if (is_array($link)) {
                if (!empty($link['added'])) {
                    $added = true;
                }
                if (!empty($link['blocked'])) {
                    $blocked = true;
                }
            }
        }
        $id = count($thoughts) + 1;
        $thoughts[] = [
            'id' => $id,
            'start' => $startN,
            'end' => $endN,
            'preview' => phase1_thought_preview($firstText),
            'lines' => count($current),
            'hue' => 0,
            'uncertain' => $uncertain,
            'added' => $added,
            'blocked' => $blocked,
            'indexes' => $current,
        ];
        $current = [];
    };

    $pendingBoundary = false;
    foreach ($lines as $i => $line) {
        if (!is_array($line)) {
            continue;
        }
        $text = (string) ($line['text'] ?? '');
        $len = phase1_dash_length($text);
        $n = (int) ($line['n'] ?? ($i + 1));
        $isSeparator = $hasHardBreak
            ? ($len >= 3 && !isset($protected[$i]))
            : (trim($text) === '');
        if ($isSeparator) {
            if ($current !== []) {
                $pendingBoundary = true;
            }
            continue;
        }
        if ($pendingBoundary && trim($text) === '') {
            continue;
        }
        $forceSplit = isset($splits[$n]);
        $forceJoin = isset($joins[$n]);
        $break = false;
        if ($current !== []) {
            if ($forceSplit) {
                $break = true;
            } elseif ($pendingBoundary && !$forceJoin) {
                $break = true;
            }
        }
        if ($break) {
            $flush();
        }
        $pendingBoundary = false;
        $current[] = $i;
    }
    $flush();

    $previousHue = null;
    foreach ($thoughts as $i => $thought) {
        $hue = phase1_thought_hue($i + 1, $previousHue);
        $thoughts[$i]['hue'] = $hue;
        $previousHue = $hue;
    }

    $mode = $hasHardBreak ? 'dashes' : 'blanks';

    return ['mode' => $mode, 'thoughts' => $thoughts];
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

function phase1_anchor(string $kind, string $file, int $line): string
{
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $file));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'file';
    }

    return 'p1-' . $kind . '-' . $slug . '-' . $line;
}

function phase1_jump_href(string $file, string $activeFile, string $anchor): string
{
    if ($file === $activeFile) {
        return '#' . $anchor;
    }

    return '?file=' . rawurlencode($file) . '#' . $anchor;
}

/**
 * One row per counted Phase 1 item, so the sticky totals can jump to each one.
 *
 * @param array<string, mixed>|null $phase1
 * @param list<string> $noteFiles
 * @return array<string, list<array{text: string, title: string, href: string, id: string}>>
 */
function phase1_review_jumps(?array $phase1, array $noteFiles, string $activeFile): array
{
    $many = count($noteFiles) > 1;
    $jumps = [
        'files' => [],
        'uncertain' => [],
        'youtube' => [],
        'links' => [],
        'blocked' => [],
    ];

    foreach ($noteFiles as $file) {
        $jumps['files'][] = [
            'text' => $file,
            'title' => $file,
            'href' => phase1_jump_href($file, $activeFile, 'p1-file'),
            'id' => $file === $activeFile ? 'p1-file' : '',
        ];

        $lines = [];
        if (is_array($phase1) && isset($phase1['files'][$file]['lines']) && is_array($phase1['files'][$file]['lines'])) {
            $lines = $phase1['files'][$file]['lines'];
        }
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $n = (int) ($line['n'] ?? 0);
            $text = (string) ($line['text'] ?? '');
            $where = ($many ? $file . ' · ' : '') . 'line ' . $n;

            $tokens = [];
            $marks = $line['marks'] ?? [];
            if (is_array($marks)) {
                foreach ($marks as $mark) {
                    if (!is_array($mark) || ($mark['type'] ?? '') !== 'uncertain') {
                        continue;
                    }
                    $token = trim((string) ($mark['token'] ?? ''));
                    $tokens[] = $token !== '' ? $token : 'uncertain';
                }
            }
            if ($tokens !== []) {
                $snippet = phase1_thought_preview($text, 120);
                $label = $where . ' · ' . implode(', ', $tokens);
                if ($snippet !== '') {
                    $label .= ' — ' . $snippet;
                }
                $anchor = phase1_anchor('line', $file, $n);
                $jumps['uncertain'][] = [
                    'text' => $label,
                    'title' => $label,
                    'href' => phase1_jump_href($file, $activeFile, $anchor),
                    'id' => $file === $activeFile ? $anchor : '',
                ];
            }

            $yt = $line['youtube'] ?? null;
            if (is_array($yt) && !empty($yt['added'])) {
                $title = trim((string) ($yt['title'] ?? ''));
                if ($title === '') {
                    $title = phase1_thought_preview($text, 120);
                }
                $label = $where . ' · ' . ($title !== '' ? $title : 'YouTube');
                $anchor = phase1_anchor('yt', $file, $n);
                $jumps['youtube'][] = [
                    'text' => $label,
                    'title' => $label,
                    'href' => phase1_jump_href($file, $activeFile, $anchor),
                    'id' => $file === $activeFile ? $anchor : '',
                ];
            }

            $link = $line['link'] ?? null;
            if (!is_array($link)) {
                continue;
            }
            $url = trim((string) ($link['url'] ?? $text));
            $title = trim((string) ($link['title'] ?? ''));
            $shown = $title !== '' ? $title : $url;
            if (!empty($link['added'])) {
                $anchor = phase1_anchor('link', $file, $n);
                $label = $where . ' · ' . ($shown !== '' ? $shown : 'Link');
                $jumps['links'][] = [
                    'text' => $label,
                    'title' => $label . ($url !== '' && $url !== $shown ? ' — ' . $url : ''),
                    'href' => phase1_jump_href($file, $activeFile, $anchor),
                    'id' => $file === $activeFile ? $anchor : '',
                ];
            }
            if (!empty($link['blocked'])) {
                $intent = trim((string) ($link['intent'] ?? ''));
                $label = $where . ' · ' . ($url !== '' ? $url : 'Blocked URL');
                if ($intent !== '') {
                    $label .= ' — ' . phase1_thought_preview($intent, 80);
                }
                $anchor = phase1_anchor('blocked', $file, $n);
                $jumps['blocked'][] = [
                    'text' => $label,
                    'title' => $label,
                    'href' => phase1_jump_href($file, $activeFile, $anchor),
                    'id' => $file === $activeFile ? $anchor : '',
                ];
            }
        }
    }

    return $jumps;
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

function phase1_regions_path(string $dir): string
{
    return $dir . '/phase1-regions.md';
}

/**
 * @param mixed $value
 * @return list<int>
 */
function phase1_region_line_list($value): array
{
    if (is_string($value)) {
        $value = preg_split('/\R/', $value) ?: [];
    }
    if (!is_array($value)) {
        return [];
    }
    $nums = [];
    foreach ($value as $n) {
        if (is_string($n)) {
            $n = trim($n);
        }
        if (!is_numeric($n)) {
            continue;
        }
        $n = (int) $n;
        if ($n > 0) {
            $nums[$n] = $n;
        }
    }
    $list = array_values($nums);
    sort($list, SORT_NUMERIC);

    return $list;
}

/**
 * @param mixed $data
 * @return array<string, array{splits: list<int>, joins: list<int>}>
 */
function normalize_phase1_regions($data): array
{
    $out = [];
    if (!is_array($data)) {
        return $out;
    }
    $files = $data['files'] ?? null;
    if (!is_array($files)) {
        return $out;
    }
    foreach ($files as $name => $row) {
        if (!is_string($name) || !is_array($row)) {
            continue;
        }
        $name = basename(str_replace('\\', '/', trim($name)));
        if ($name === '' || $name === '.' || $name === '..') {
            continue;
        }
        $splits = phase1_region_line_list($row['splits'] ?? []);
        $joins = phase1_region_line_list($row['joins'] ?? []);
        $splits = array_values(array_diff($splits, $joins));
        if ($splits === [] && $joins === []) {
            continue;
        }
        $out[$name] = [
            'splits' => $splits,
            'joins' => $joins,
        ];
    }
    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

    return $out;
}

function parse_phase1_regions_md(string $raw): array
{
    $files = [];
    $file = '';
    $section = '';
    $lines = preg_split('/\R/', $raw) ?: [];
    foreach ($lines as $line) {
        if (preg_match('/^## File:\s*(.+?)\s*$/', $line, $m) === 1) {
            $file = basename(str_replace('\\', '/', trim($m[1])));
            $section = '';
            if ($file !== '' && $file !== '.' && $file !== '..' && !isset($files[$file])) {
                $files[$file] = ['splits' => [], 'joins' => []];
            }
            continue;
        }
        if ($file === '' || !isset($files[$file])) {
            continue;
        }
        if (preg_match('/^### Splits\s*$/', $line) === 1) {
            $section = 'splits';
            continue;
        }
        if (preg_match('/^### Joins\s*$/', $line) === 1) {
            $section = 'joins';
            continue;
        }
        if ($section === '' || preg_match('/^\d+$/', trim($line)) !== 1) {
            continue;
        }
        $files[$file][$section][] = (int) trim($line);
    }

    return normalize_phase1_regions(['files' => $files]);
}

/**
 * @param array<string, array{splits: list<int>, joins: list<int>}> $data
 */
function format_phase1_regions_md(array $data): string
{
    $data = normalize_phase1_regions(['files' => $data]);
    $parts = [
        '# Phase 1 regions',
        '',
        'Corrections when a note boundary is wrong. A split starts a new note at that source line. A join keeps that source line in the previous note.',
        '',
    ];
    foreach ($data as $name => $row) {
        $parts[] = '## File: ' . $name;
        $parts[] = '';
        $parts[] = '### Splits';
        $parts[] = '';
        foreach ($row['splits'] as $n) {
            $parts[] = (string) $n;
        }
        if ($row['splits'] === []) {
            $parts[] = '';
        }
        $parts[] = '';
        $parts[] = '### Joins';
        $parts[] = '';
        foreach ($row['joins'] as $n) {
            $parts[] = (string) $n;
        }
        $parts[] = '';
    }

    return rtrim(implode("\n", $parts)) . "\n";
}

/**
 * @return array<string, array{splits: list<int>, joins: list<int>}>
 */
function load_phase1_regions(string $dir): array
{
    $path = phase1_regions_path($dir);
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    return parse_phase1_regions_md($raw);
}

/**
 * @param array<string, array{splits: list<int>, joins: list<int>}> $data
 */
function save_phase1_regions(string $dir, array $data): bool
{
    $normalized = normalize_phase1_regions(['files' => $data]);
    $path = phase1_regions_path($dir);
    if ($normalized === []) {
        if (is_file($path)) {
            return unlink($path);
        }

        return true;
    }

    return file_put_contents($path, format_phase1_regions_md($normalized)) !== false;
}

/**
 * Diff the note starts the user wants against automatic boundaries.
 *
 * @param list<array<string, mixed>> $lines
 * @param list<mixed> $starts
 * @return array{splits: list<int>, joins: list<int>}
 */
function phase1_region_overrides_from_starts(array $lines, array $starts): array
{
    $auto = phase1_thoughts($lines);
    $autoStarts = [];
    foreach ($auto['thoughts'] as $thought) {
        $autoStarts[(int) $thought['start']] = true;
    }
    $valid = [];
    foreach ($lines as $i => $line) {
        if (!is_array($line)) {
            continue;
        }
        $n = (int) ($line['n'] ?? ($i + 1));
        if ($n > 0) {
            $valid[$n] = true;
        }
    }
    $want = [];
    foreach ($starts as $n) {
        $n = (int) $n;
        if ($n > 0 && isset($valid[$n])) {
            $want[$n] = true;
        }
    }
    if ($autoStarts !== []) {
        $want[(int) min(array_keys($autoStarts))] = true;
    }
    $splitList = [];
    $joinList = [];
    foreach (array_keys($want) as $n) {
        if (!isset($autoStarts[$n])) {
            $splitList[] = (int) $n;
        }
    }
    foreach (array_keys($autoStarts) as $n) {
        if (!isset($want[$n])) {
            $joinList[] = (int) $n;
        }
    }
    sort($splitList, SORT_NUMERIC);
    sort($joinList, SORT_NUMERIC);

    return [
        'splits' => $splitList,
        'joins' => $joinList,
    ];
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
