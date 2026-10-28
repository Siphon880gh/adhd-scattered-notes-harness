<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$config = ensure_project_registry();
$folder = $config['inputFolder'];
$phase = $config['phase'];
$dir = $folder !== '' ? input_dir($folder) : '';
$folderExists = $folder !== '' && is_dir($dir);

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'switch-project'
) {
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    $name = basename(str_replace('\\', '/', trim((string) (is_array($payload) ? ($payload['folder'] ?? '') : ''))));
    $known = list_input_projects();
    if ($name === '' || !in_array($name, $known, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown project folder.']);
        exit;
    }

    $projects = $config['projects'];
    if ($folder !== '' && in_array($folder, $known, true)) {
        $projects[$folder] = ['phase' => $phase];
    }
    $nextPhase = isset($projects[$name]['phase'])
        ? normalize_phase((int) $projects[$name]['phase'])
        : infer_project_phase($name);
    if (!save_app_config($name, $nextPhase, $projects)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write app.config.json.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'folder' => $name,
        'phase' => $nextPhase,
    ]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'save-phase2'
) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$folderExists || ($phase !== 2 && $phase !== 3)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Organized editing is only available in Phase 2 or 3.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($payload) || !isset($payload['organized']) || !is_array($payload['organized'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Expected JSON body with organized.']);
        exit;
    }

    $phase2Path = $dir . '/phase2.json';
    $existing = load_json_file($phase2Path) ?? [];
    $existing['organized'] = normalize_organized($payload['organized']);
    $availableTags = collect_organized_tags($existing['organized']);
    if (array_key_exists('filterLayout', $payload)) {
        $existing['filterLayout'] = normalize_filter_layout($payload['filterLayout'], $availableTags);
    } elseif (!isset($existing['filterLayout']) || !is_array($existing['filterLayout'])) {
        $existing['filterLayout'] = normalize_filter_layout([], $availableTags);
    } else {
        $existing['filterLayout'] = normalize_filter_layout($existing['filterLayout'], $availableTags);
    }

    if (array_key_exists('notebookOrder', $payload)) {
        $existing['notebookOrder'] = normalize_notebook_order($payload['notebookOrder'], $existing['organized']);
    } elseif (!isset($existing['notebookOrder']) || !is_array($existing['notebookOrder'])) {
        $existing['notebookOrder'] = [];
    } else {
        $existing['notebookOrder'] = normalize_notebook_order($existing['notebookOrder'], $existing['organized']);
    }

    if (!save_json_file($phase2Path, $existing)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write phase2.json.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'organized' => $existing['organized'],
        'filterLayout' => $existing['filterLayout'],
        'notebookOrder' => $existing['notebookOrder'],
    ]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'save-suggestions'
) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$folderExists || $phase !== 2) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Suggestions are only available in Phase 2.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Expected JSON body with panel and/or items.']);
        exit;
    }

    $suggestions = normalize_phase2_suggestions($payload);
    if (!save_phase2_suggestions($dir, $suggestions)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write phase2-suggestions.md.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'suggestions' => $suggestions,
    ]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'save-url-paste'
) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$folderExists || $phase !== 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'URL pastes are only available in Phase 1.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Expected JSON body with file, line, userPaste.']);
        exit;
    }

    $file = isset($payload['file']) ? (string) $payload['file'] : '';
    $lineN = isset($payload['line']) ? (int) $payload['line'] : 0;
    $userPaste = isset($payload['userPaste']) ? (string) $payload['userPaste'] : '';
    $userNote = isset($payload['userNote']) ? (string) $payload['userNote'] : '';

    $phase1Path = $dir . '/phase1.json';
    $existing = load_json_file($phase1Path);
    if ($existing === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'phase1.json is missing.']);
        exit;
    }

    $applied = apply_phase1_url_paste($existing, $file, $lineN, $userPaste, $userNote);
    if (empty($applied['ok']) || !isset($applied['phase1']) || !is_array($applied['phase1'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => (string) ($applied['error'] ?? 'Could not apply paste.')]);
        exit;
    }

    if (!save_json_file($phase1Path, $applied['phase1'])) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write phase1.json.']);
        exit;
    }

    $linkOut = null;
    $filesMap = $applied['phase1']['files'] ?? [];
    if (is_array($filesMap) && isset($filesMap[$file]) && is_array($filesMap[$file])) {
        $linesOut = $filesMap[$file]['lines'] ?? [];
        if (is_array($linesOut)) {
            foreach ($linesOut as $lineRow) {
                if (is_array($lineRow) && (int) ($lineRow['n'] ?? 0) === $lineN) {
                    $linkOut = is_array($lineRow['link'] ?? null) ? $lineRow['link'] : null;
                    break;
                }
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'file' => $file,
        'line' => $lineN,
        'link' => $linkOut,
    ]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'save-phase1-suggestions'
) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$folderExists || $phase !== 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Phase 1 suggestions are only available in Phase 1.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Expected JSON body with suggestions.']);
        exit;
    }

    $suggestions = normalize_phase1_suggestions($payload);
    if (!save_phase1_suggestions($dir, $suggestions)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write phase1-suggestions.md.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'suggestions' => $suggestions,
    ]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'save-phase1-regions'
) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$folderExists || $phase !== 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Phase 1 region edits are only available in Phase 1.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Expected JSON body with file and starts.']);
        exit;
    }

    $file = basename(str_replace('\\', '/', (string) ($payload['file'] ?? '')));
    $noteFilesForSave = list_note_files($dir);
    if ($file === '' || !in_array($file, $noteFilesForSave, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown note file.']);
        exit;
    }

    $starts = $payload['starts'] ?? [];
    if (!is_array($starts)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'starts must be a list of line numbers.']);
        exit;
    }

    $lines = lines_for_file($dir, $file, load_json_file($dir . '/phase1.json'));
    $overrides = phase1_region_overrides_from_starts($lines, $starts);
    $regions = load_phase1_regions($dir);
    if ($overrides['splits'] === [] && $overrides['joins'] === []) {
        unset($regions[$file]);
    } else {
        $regions[$file] = $overrides;
    }
    if (!save_phase1_regions($dir, $regions)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write phase1-regions.md.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'file' => $file,
        'edited' => $overrides['splits'] !== [] || $overrides['joins'] !== [],
        'splits' => $overrides['splits'],
        'joins' => $overrides['joins'],
    ]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'save-snapshot'
) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$folderExists || $phase !== 3) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Snapshots can only be saved from Phase 3.']);
        exit;
    }

    $phase2Path = $dir . '/phase2.json';
    $existing = load_json_file($phase2Path);
    if ($existing === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'phase2.json is missing.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    $name = '';
    if (is_array($payload) && isset($payload['name'])) {
        $name = trim((string) $payload['name']);
    }

    $result = save_phase3_snapshot($folder, $existing, $name);
    if (empty($result['ok'])) {
        $error = (string) ($result['error'] ?? 'Could not save snapshot.');
        $isConflict = stripos($error, 'already exists') !== false
            || stripos($error, 'Enter a snapshot name') !== false;
        http_response_code($isConflict ? 400 : 500);
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'snapshot' => $result['snapshot'],
    ]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'update-snapshot'
) {
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($payload) || !isset($payload['organized']) || !is_array($payload['organized'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Expected JSON body with organized.']);
        exit;
    }

    $snapshotId = '';
    if (isset($payload['id'])) {
        $snapshotId = sanitize_snapshot_id((string) $payload['id']);
    }
    if ($snapshotId === '' && isset($_GET['id'])) {
        $snapshotId = sanitize_snapshot_id((string) $_GET['id']);
    }
    if ($snapshotId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing snapshot id.']);
        exit;
    }

    $result = update_snapshot($snapshotId, $payload);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'Could not update snapshot.')]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'snapshot' => $result['snapshot'],
    ]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'rename-snapshot'
) {
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    $snapshotId = is_array($payload) ? sanitize_snapshot_id((string) ($payload['id'] ?? '')) : '';
    $name = is_array($payload) ? trim((string) ($payload['name'] ?? '')) : '';
    if ($snapshotId === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Snapshot id and name are required.']);
        exit;
    }

    $result = rename_snapshot($snapshotId, $name);
    if (empty($result['ok'])) {
        $error = (string) ($result['error'] ?? 'Could not rename snapshot.');
        $isConflict = stripos($error, 'already exists') !== false;
        http_response_code($isConflict ? 400 : 404);
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }

    echo json_encode(['ok' => true, 'snapshot' => $result['snapshot']]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'delete-snapshot'
) {
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    $snapshotId = is_array($payload) ? sanitize_snapshot_id((string) ($payload['id'] ?? '')) : '';
    if ($snapshotId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Snapshot id is required.']);
        exit;
    }

    $result = delete_snapshot($snapshotId);
    if (empty($result['ok'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'Could not delete snapshot.')]);
        exit;
    }

    echo json_encode(['ok' => true, 'id' => $snapshotId]);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['action'])
    && (string) $_GET['action'] === 'list-snapshots'
) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'snapshots' => list_snapshots(),
    ]);
    exit;
}

$livePhase = $phase;
$liveFolder = $folder;
$viewingSnapshot = null;
$snapshotQueryId = isset($_GET['snapshot']) ? sanitize_snapshot_id((string) $_GET['snapshot']) : '';
if ($snapshotQueryId !== '') {
    $viewingSnapshot = load_snapshot($snapshotQueryId);
}

$noteFiles = $folderExists ? list_note_files($dir) : [];
$activeFile = isset($_GET['file']) ? (string) $_GET['file'] : '';
if ($activeFile === '' || !in_array($activeFile, $noteFiles, true)) {
    $activeFile = $noteFiles[0] ?? '';
}

$phase1 = $folderExists ? load_json_file($dir . '/phase1.json') : null;
$phase2 = $folderExists ? load_json_file($dir . '/phase2.json') : null;
$phase1Suggestions = $folderExists
    ? load_phase1_suggestions($dir)
    : normalize_phase1_suggestions(null);
$phase1Regions = $folderExists ? load_phase1_regions($dir) : [];
$phase2Suggestions = $folderExists
    ? load_phase2_suggestions($dir)
    : normalize_phase2_suggestions(null);

// Snapshot view overlays Phase 3 UI without changing app.config.json.
if ($viewingSnapshot !== null) {
    $phase = 3;
    $phase2 = [
        'organized' => $viewingSnapshot['organized'],
        'filterLayout' => $viewingSnapshot['filterLayout'],
        'notebookOrder' => $viewingSnapshot['notebookOrder'],
        'blame' => $viewingSnapshot['blame'],
    ];
    $phase2Suggestions = normalize_phase2_suggestions(null);
    $sourceFolder = (string) ($viewingSnapshot['sourceFolder'] ?? '');
    if ($sourceFolder !== '' && is_dir(input_dir($sourceFolder))) {
        $dir = input_dir($sourceFolder);
        $noteFiles = list_note_files($dir);
        if ($activeFile === '' || !in_array($activeFile, $noteFiles, true)) {
            $activeFile = $noteFiles[0] ?? '';
        }
    } else {
        $noteFiles = [];
        $activeFile = '';
    }
    $folderExists = true;
}

$isPhasePreview = false;
if ($viewingSnapshot === null && $folderExists && isset($_GET['preview'])) {
    $requestedPreview = (int) $_GET['preview'];
    if ($requestedPreview >= 1 && $requestedPreview <= 3 && $requestedPreview !== (int) $livePhase) {
        $phase = $requestedPreview;
        $isPhasePreview = true;
    }
}

function phase_name(int $phase): string
{
    if ($phase === 2) {
        return 'Sort notes and review tags';
    }
    if ($phase === 3) {
        return 'Filter and copy into your notes';
    }

    return 'Review marks, links, and boundaries';
}

function phase_label(int $phase): string
{
    return 'Phase ' . $phase . ' — ' . phase_name($phase);
}

/**
 * Dots for the phases before and after the live one. The live phase stays the
 * dominant label. Other phases are a preview only and do not change app.config.json.
 */
function render_phase_carousel(int $livePhase, int $viewPhase, bool $isPhasePreview): void
{
    echo '<nav class="phase-carousel" aria-label="Preview phases">';
    for ($n = 1; $n <= 3; $n++) {
        $isCurrent = $n === $livePhase;
        $isViewing = $n === $viewPhase;
        $named = phase_label($n);
        if ($isCurrent) {
            $currentAttrs = $isPhasePreview ? '' : ' aria-current="page"';
            echo '<a class="phase-carousel__current" href="index.php" title="' . e($named . ', current') . '"' . $currentAttrs . '>Phase ' . $n . '</a>';
            continue;
        }
        $class = 'phase-carousel__dot' . ($isViewing ? ' is-preview' : '');
        $label = 'Preview ' . $named;
        echo '<a class="' . $class . '" href="index.php?preview=' . $n . '" title="' . e($label) . '" aria-label="' . e($label) . '"';
        if ($isViewing) {
            echo ' aria-current="true"';
        }
        echo '></a>';
    }
    if ($isPhasePreview) {
        echo '<span class="phase-carousel__preview">Previewing Phase ' . (int) $viewPhase . '</span>';
    }
    echo '</nav>';
}

/**
 * @return array<string, string>
 */
function phase2_bucket_labels(): array
{
    return [
        'tasks' => 'Scattered',
        'reference' => 'Reference',
        'articleCandidates' => 'Articles',
    ];
}

/**
 * @param array<string, mixed>|null $phase1
 * @return list<array<string, mixed>>
 */
function lines_for_file(string $dir, string $file, ?array $phase1): array
{
    if ($phase1 !== null && isset($phase1['files'][$file]['lines']) && is_array($phase1['files'][$file]['lines'])) {
        return $phase1['files'][$file]['lines'];
    }
    return raw_file_lines($dir . '/' . $file);
}

/**
 * @param list<array<string, mixed>> $items
 */
function render_count_bar(array $items, string $ariaLabel, string $openGroup = ''): void
{
    $grouped = isset($items[0]['items']) && is_array($items[0]['items']);
    $rows = $grouped ? $items : [['label' => '', 'items' => $items]];
    $class = 'bucket-counts' . ($grouped ? ' bucket-counts--switch' : '');
    echo '<div class="' . $class . '" data-bucket-counts aria-label="' . e($ariaLabel) . '">';
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rowLabel = (string) ($row['label'] ?? '');
        $rowItems = is_array($row['items'] ?? null) ? $row['items'] : [];
        $tone = (string) ($row['tone'] ?? '');
        $groupId = (string) ($row['id'] ?? '');
        if ($grouped && $groupId !== '') {
            $open = $groupId === $openGroup;
            $toneClass = $tone !== '' ? ' bucket-counts__group--' . preg_replace('/[^a-z0-9_-]/', '', $tone) : '';
            $groupTotal = 0;
            foreach ($rowItems as $rowItem) {
                if (is_array($rowItem)) {
                    $groupTotal += (int) ($rowItem['count'] ?? 0);
                }
            }
            $openClass = $open ? ' is-open is-settled' : '';
            echo '<div class="bucket-counts__group' . $toneClass . $openClass . '" data-count-group="' . e($groupId) . '">';
            echo '<button type="button" class="bucket-counts__group-toggle" data-count-group-toggle aria-expanded="' . ($open ? 'true' : 'false') . '">';
            if ($tone === 'attention') {
                echo '<svg class="bucket-counts__warn" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.5 20h19L12 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 9v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1.1" fill="currentColor"/></svg>';
            }
            echo '<span>' . e($rowLabel) . '</span>';
            if ($tone === 'attention') {
                echo '<span class="bucket-counts__group-count" data-attention-total>(' . $groupTotal . ')</span>';
            }
            echo '<svg class="bucket-counts__chevron" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            echo '</button>';
            echo '<div class="bucket-counts__group-items"' . ($open ? '' : ' aria-hidden="true"') . '>';
            render_count_bar_items($rowItems);
            echo '</div></div>';
            continue;
        }
        render_count_bar_items($rowItems);
    }
    echo '</div>';
}

/**
 * @param list<array<string, mixed>> $items
 */
function render_count_bar_items(array $items): void
{
    $first = true;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!$first) {
            echo '<span class="bucket-counts__divider" aria-hidden="true"></span>';
        }
        $first = false;
        $label = (string) ($item['label'] ?? '');
        $count = (int) ($item['count'] ?? 0);
        $key = isset($item['key']) ? (string) $item['key'] : '';
        $jumps = $item['jumps'] ?? null;
        $countKey = isset($item['countKey']) ? (string) $item['countKey'] : '';
        $countAttr = $countKey !== '' ? ' data-review-count="' . e($countKey) . '"' : '';
        if (is_array($jumps)) {
            $align = !empty($item['alignEnd']) ? ' data-count-align="end"' : '';
            $tracksAttention = $countKey === 'uncertain' || $countKey === 'blocked';
            echo '<div class="bucket-counts__menu" data-count-menu' . $align . '>';
            echo '<button type="button" class="bucket-counts__item bucket-counts__item--link" data-count-menu-toggle aria-expanded="false" aria-haspopup="true" title="Show ' . e($label) . '">';
            echo e($label) . ' (<span' . $countAttr . '>' . $count . '</span>)';
            echo '</button>';
            echo '<div class="bucket-counts__panel" role="group" aria-label="' . e($label) . '">';
            if ($jumps === []) {
                echo '<p class="bucket-counts__empty">None</p>';
            } else {
                $openJumps = 0;
                foreach ($jumps as $jump) {
                    if (is_array($jump) && empty($jump['handled'])) {
                        $openJumps++;
                    }
                }
                if ($tracksAttention) {
                    echo '<p class="bucket-counts__empty" data-attention-empty' . ($openJumps > 0 ? ' hidden' : '') . '>None</p>';
                }
                foreach ($jumps as $jump) {
                    if (!is_array($jump)) {
                        continue;
                    }
                    $text = (string) ($jump['text'] ?? '');
                    $title = (string) ($jump['title'] ?? $text);
                    $href = (string) ($jump['href'] ?? '#');
                    $jumpId = (string) ($jump['id'] ?? '');
                    $jumpAttr = $jumpId !== '' ? ' data-count-jump="' . e($jumpId) . '"' : '';
                    $jumpClass = 'bucket-counts__jump' . (!empty($jump['handled']) ? ' is-handled' : '');
                    $attentionAttr = '';
                    if ($tracksAttention) {
                        $attentionAttr = ' data-attention-file="' . e((string) ($jump['file'] ?? '')) . '"';
                        $attentionAttr .= ' data-attention-line="' . (int) ($jump['line'] ?? 0) . '"';
                        $attentionAttr .= ' data-attention-kind="' . e($countKey) . '"';
                    }
                    echo '<a class="' . $jumpClass . '" href="' . e($href) . '" title="' . e($title) . '"' . $jumpAttr . $attentionAttr . '>' . e($text) . '</a>';
                }
            }
            echo '</div></div>';
        } elseif ($key !== '') {
            echo '<button type="button" class="bucket-counts__item bucket-counts__item--link" data-jump-bucket="' . e($key) . '" title="Jump to ' . e($label) . '">';
            echo e($label) . ' (<span data-bucket-count="' . e($key) . '">' . $count . '</span>)';
            echo '</button>';
        } else {
            echo '<span class="bucket-counts__item">';
            echo e($label) . ' (<span' . $countAttr . '>' . $count . '</span>)';
            echo '</span>';
        }
    }
}

/**
 * @param list<array<string, mixed>> $items
 * @param array<string, string> $itemSuggestions
 * @param bool $reviewLocked Phase 3: no Suggest / green-check controls
 */
function render_item_cards(array $items, string $bucket, string $emptyLabel, array $itemSuggestions = [], bool $reviewLocked = false): void
{
    $labels = phase2_bucket_labels();
    echo '<p class="muted" data-empty-bucket="' . e($bucket) . '"' . ($items === [] ? '' : ' hidden') . '>' . e($emptyLabel) . '</p>';
    echo '<div class="card-list" data-bucket-list="' . e($bucket) . '">';
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (string) ($item['id'] ?? '');
        $title = (string) ($item['title'] ?? ($id !== '' ? $id : 'Untitled'));
        $body = (string) ($item['body'] ?? $item['why'] ?? '');
        $bodyLines = $body === '' ? [] : preg_split("/\r\n|\r|\n/", $body);
        if (!is_array($bodyLines)) {
            $bodyLines = [$body];
        }
        $previewLimit = 3;
        $needsTruncate = count($bodyLines) > $previewLimit;
        $previewBody = $needsTruncate
            ? implode("\n", array_slice($bodyLines, 0, $previewLimit))
            : $body;
        $sources = $item['sources'] ?? [];
        if (!is_array($sources)) {
            $sources = [];
        }
        $tags = normalize_tags($item['tags'] ?? []);
        $checked = !empty($item['checked']);
        $suggestion = ($id !== '' && isset($itemSuggestions[$id])) ? (string) $itemSuggestions[$id] : '';
        $hasSuggestion = !$reviewLocked && $suggestion !== '';
        $tagAttr = htmlspecialchars(json_encode($tags, JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fullTextAttr = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $attrs = 'class="item-card' . ($checked ? ' is-checked' : '') . ($hasSuggestion ? ' has-suggestion' : '') . '" data-bucket="' . e($bucket) . '"';
        if ($id !== '') {
            $attrs .= ' id="org-' . e($id) . '" data-org-id="' . e($id) . '"';
        }
        $attrs .= ' data-tags="' . $tagAttr . '"';
        $attrs .= ' data-full-text="' . $fullTextAttr . '"';
        $attrs .= ' data-checked="' . ($checked ? '1' : '0') . '"';
        echo '<article ' . $attrs . '>';
        echo '<div class="item-card__main">';
        echo '<div class="item-card__title-row">';
        if (!$reviewLocked) {
            echo '<button type="button" class="item-card__check" data-check-item aria-pressed="' . ($checked ? 'true' : 'false') . '" title="' . ($checked ? 'Unmark as reviewed' : 'Mark as reviewed') . '" aria-label="' . ($checked ? 'Unmark as reviewed' : 'Mark as reviewed') . '">';
            echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>';
            echo '</button>';
        }
        echo '<h3>' . linkify_note($title) . '</h3>';
        echo '<button type="button" class="item-card__copy" data-copy-item title="Copy full note text" aria-label="Copy full note text">';
        echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
        echo '<span>Copy</span>';
        echo '</button>';
        echo '<div class="item-card__more" data-move-wrap>';
        echo '<button type="button" class="item-card__more-toggle" data-move-toggle aria-expanded="false" aria-haspopup="menu" title="More actions" aria-label="More actions">';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="12" cy="19" r="1.75"/></svg>';
        echo '</button>';
        echo '<div class="item-card__more-menu" data-move-menu hidden role="menu">';
        echo '<div class="item-card__more-label" role="presentation">Move to</div>';
        foreach ($labels as $key => $label) {
            if ($key === $bucket) {
                continue;
            }
            echo '<button type="button" class="item-card__more-option" data-move-to="' . e($key) . '" role="menuitem">' . e($label) . '</button>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        if ($body !== '') {
            echo '<div class="item-card__body">';
            echo '<p class="item-card__body-text">' . linkify_note($previewBody) . '</p>';
            if ($needsTruncate) {
                echo '<button type="button" class="item-card__read-more" data-read-more>Read more</button>';
            }
            echo '</div>';
        }
        if ($sources !== []) {
            $srcText = implode(', ', array_map('strval', $sources));
            echo '<div class="item-card__meta">' . e($srcText) . '</div>';
        }
        if (!$reviewLocked) {
            $suggestFieldId = 'suggest-' . ($id !== '' ? $id : ('tmp-' . substr(md5($title . $body), 0, 8)));
            echo '<div class="item-card__suggest" data-item-suggest>';
            echo '<button type="button" class="item-card__suggest-toggle" data-suggest-toggle aria-expanded="false" title="Suggestion for AI" aria-label="Suggestion for AI">';
            echo '<svg class="suggest-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>';
            echo '<span class="item-card__suggest-label">Suggest</span>';
            echo '</button>';
            echo '<div class="item-card__suggest-panel" data-suggest-panel hidden>';
            echo '<label class="item-card__suggest-caption" for="' . e($suggestFieldId) . '">Tell the AI how to change this note</label>';
            echo '<textarea class="item-card__suggest-input" data-suggest-input id="' . e($suggestFieldId) . '" rows="3" maxlength="4000" placeholder="e.g. rewrite title, merge with t3, move to Reference, keep the permissions detail…">' . e($suggestion) . '</textarea>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<aside class="item-card__actions" aria-label="Item actions">';
        echo '<div class="item-card__toolbar">';
        echo '<form class="item-card__tag-form" data-tag-form>';
        echo '<input type="text" class="item-card__tag-input" data-tag-input placeholder="Add tag" maxlength="40" autocomplete="off">';
        echo '<button type="submit" class="item-card__tag-add" title="Add tag">Add</button>';
        echo '</form>';
        echo '</div>';
        echo '<div class="item-card__tag-list" data-tag-list>';
        foreach ($tags as $tag) {
            echo '<span class="tag-chip" data-tag="' . e($tag) . '">';
            echo '<span class="tag-chip__label">' . e($tag) . '</span>';
            echo '<button type="button" class="tag-chip__remove" data-remove-tag="' . e($tag) . '" aria-label="Remove tag ' . e($tag) . '">×</button>';
            echo '</span>';
        }
        echo '</div>';
        echo '</aside>';
        echo '</article>';
    }
    echo '</div>';
}

$showEmpty = !$folderExists;
$snapshotMissing = $snapshotQueryId !== '' && $viewingSnapshot === null;
$snapshotList = list_snapshots();
$isSnapshotView = $viewingSnapshot !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isSnapshotView ? 'Snapshot · ' . e((string) $viewingSnapshot['name']) . ' · ' : '' ?>Scattered Notes Harness</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body class="<?= trim(($isSnapshotView ? 'is-snapshot-view' : '') . ($isPhasePreview ? ' is-phase-preview' : '')) ?>"<?php if ($isPhasePreview): ?> data-phase-preview="1" data-live-phase="<?= (int) $livePhase ?>"<?php endif; ?>>
<header class="app-header">
    <a href="index.php" class="app-header__brand">Scattered Notes</a>
    <div class="app-header__meta">
        <?php if ($isSnapshotView): ?>
            snapshot · <?= e((string) $viewingSnapshot['name']) ?>
            <span class="app-header__meta-sep" aria-hidden="true">·</span>
            live: <?php if ($liveFolder === ''): ?>no folder<?php else: ?>inputs/<?= e($liveFolder) ?> · phase <?= (int) $livePhase ?><?php endif; ?>
        <?php elseif ($showEmpty): ?>
            no folder configured
        <?php else: ?>
            <span class="app-header__folder">inputs/<?= e($liveFolder) ?></span>
        <?php endif; ?>
    </div>
    <div class="app-header__actions">
        <?php if (!$showEmpty && !$isPhasePreview && (int) $livePhase === 1): ?>
            <div class="app-header__cluster" data-phase1-history>
                <div class="app-header__segment" role="group" aria-label="Undo and redo">
                    <button type="button" class="app-header__segment-btn" data-history-undo disabled title="Undo (⌘Z)">Undo</button>
                    <button type="button" class="app-header__segment-btn" data-history-redo disabled title="Redo (⇧⌘Z)">Redo</button>
                </div>
                <button type="button" class="app-header__btn app-header__btn--primary" data-history-save title="Save suggestions and note boundaries (⌘S)">Save</button>
                <button type="button" class="app-header__btn app-header__btn--menu" data-history-toggle aria-expanded="false" aria-haspopup="true" title="Jump back to an earlier suggestion or note boundary">
                    History
                    <svg class="app-header__chevron" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div class="app-header__history-panel" data-history-panel hidden>
                    <div class="app-header__history-head">History</div>
                    <ol class="app-header__history-list" data-history-list></ol>
                </div>
            </div>
            <span class="app-header__sep" aria-hidden="true"></span>
        <?php endif; ?>
        <?php $inputProjects = list_input_projects(); ?>
        <div class="app-header__scope">
        <div class="app-header__projects" data-projects-menu data-current-project="<?= e($liveFolder) ?>">
            <button type="button"
                    class="app-header__btn app-header__btn--menu"
                    data-projects-toggle
                    aria-expanded="false"
                    aria-haspopup="true"
                    title="Switch the input folder this app is showing">
                Projects
                <?php if ($inputProjects !== []): ?><span class="app-header__count"><?= count($inputProjects) ?></span><?php endif; ?>
                <svg class="app-header__chevron" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="app-header__projects-panel" data-projects-panel hidden>
                <div class="app-header__projects-head">Projects</div>
                <?php if ($inputProjects === []): ?>
                    <p class="app-header__projects-empty muted">No folders under <code>inputs/</code> yet.</p>
                <?php else: ?>
                    <ul class="app-header__projects-list">
                        <?php foreach ($inputProjects as $projectName):
                            $projectPhase = project_phase_for($config, $projectName);
                            $projectCurrent = $projectName === $liveFolder;
                            ?>
                            <li>
                                <button type="button"
                                        class="app-header__projects-link<?= $projectCurrent ? ' is-active' : '' ?>"
                                        data-switch-project="<?= e($projectName) ?>"
                                        <?php if ($projectCurrent): ?>aria-current="true"<?php endif; ?>>
                                    <span class="app-header__projects-name">inputs/<?= e($projectName) ?></span>
                                    <span class="app-header__projects-meta muted" title="<?= e(phase_label((int) $projectPhase)) ?>">Phase <?= (int) $projectPhase ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="app-header__scope-nest">
        <?php if (!$isSnapshotView && !$isPhasePreview && $liveFolder !== '' && $livePhase === 3 && is_file(input_dir($liveFolder) . '/phase2.json')): ?>
            <button type="button" class="app-header__btn" data-save-snapshot title="Save Phase 3 as a named snapshot">
                Save snapshot as
            </button>
        <?php endif; ?>
        <div class="app-header__snapshots" data-snapshots-menu>
            <button type="button"
                    class="app-header__btn app-header__btn--menu"
                    data-snapshots-toggle
                    aria-expanded="false"
                    aria-haspopup="true"
                    title="Open a saved snapshot">
                Snapshots
                <?php if ($snapshotList !== []): ?><span class="app-header__count"><?= count($snapshotList) ?></span><?php endif; ?>
                <svg class="app-header__chevron" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="app-header__snapshots-panel" data-snapshots-panel hidden>
                <div class="app-header__snapshots-head">
                    <span>Saved snapshots</span>
                    <?php if ($snapshotList !== []): ?>
                        <button type="button" class="app-header__snapshot-manage" data-snapshots-manage aria-pressed="false" title="Show rename and delete controls">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                            </svg>
                            <span data-snapshots-manage-label>Manage</span>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($snapshotList === []): ?>
                    <p class="app-header__snapshots-empty muted">No snapshots yet. Save one from Phase 3.</p>
                <?php else: ?>
                    <ul class="app-header__snapshots-list">
                        <?php foreach ($snapshotList as $snap): ?>
                            <li class="app-header__snapshot-item">
                                <a class="app-header__snapshots-link<?= $isSnapshotView && (string) $viewingSnapshot['id'] === (string) $snap['id'] ? ' is-active' : '' ?>"
                                   href="index.php?snapshot=<?= e(rawurlencode((string) $snap['id'])) ?>">
                                    <span class="app-header__snapshots-name"><?= e((string) $snap['name']) ?></span>
                                    <span class="app-header__snapshots-meta muted">
                                        <?php if ((string) $snap['sourceFolder'] !== ''): ?>
                                            inputs/<?= e((string) $snap['sourceFolder']) ?>
                                        <?php endif; ?>
                                        <?php if ((string) $snap['createdAt'] !== ''): ?>
                                            · <?= e((string) $snap['createdAt']) ?>
                                        <?php endif; ?>
                                    </span>
                                </a>
                                <div class="app-header__snapshot-actions" aria-label="Actions for <?= e((string) $snap['name']) ?>">
                                    <button type="button" class="app-header__snapshot-action" data-rename-snapshot data-snapshot-id="<?= e((string) $snap['id']) ?>" data-snapshot-name="<?= e((string) $snap['name']) ?>">Rename</button>
                                    <button type="button" class="app-header__snapshot-action app-header__snapshot-action--delete" data-delete-snapshot data-snapshot-id="<?= e((string) $snap['id']) ?>" data-snapshot-name="<?= e((string) $snap['name']) ?>">Delete</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($isSnapshotView): ?>
            <a class="app-header__btn app-header__btn--link" href="index.php" title="Leave snapshot; keep live phase">Exit snapshot</a>
        <?php endif; ?>
        </div>
        </div>
        <?php if (!$showEmpty && ($phase === 2 || $phase === 3)): ?>
            <button type="button"
                    class="app-header__menu"
                    data-drawer-open
                    aria-label="Open original notes"
                    aria-keyshortcuts="B"
                    title="Open original notes (B)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
                <span class="shortcut-hint" data-shortcut-hint aria-hidden="true">B</span>
            </button>
        <?php endif; ?>
    </div>
</header>
<div class="snapshot-dialog" data-snapshot-dialog role="dialog" aria-modal="true" aria-labelledby="snapshot-dialog-title" hidden>
    <div class="snapshot-dialog__backdrop" data-snapshot-dialog-cancel></div>
    <form class="snapshot-dialog__card" data-snapshot-dialog-form aria-labelledby="snapshot-dialog-title">
        <h2 id="snapshot-dialog-title" data-snapshot-dialog-title>Rename snapshot</h2>
        <p class="muted" data-snapshot-dialog-message></p>
        <label class="snapshot-dialog__label" data-snapshot-dialog-name-field>
            Snapshot name
            <input type="text" maxlength="80" required data-snapshot-dialog-name>
        </label>
        <div class="snapshot-dialog__actions">
            <button type="button" class="app-header__btn" data-snapshot-dialog-cancel>Cancel</button>
            <button type="submit" class="app-header__btn" data-snapshot-dialog-submit>Rename</button>
        </div>
    </form>
</div>

<?php if ($snapshotMissing): ?>
<aside class="banner banner--warn">
    <h2>Snapshot not found</h2>
    <p>
        No file for <code>snapshots/<?= e($snapshotQueryId) ?>.json</code>.
        <a href="index.php">Back to live session</a> (phase unchanged).
    </p>
</aside>
<?php endif; ?>

<details class="adhd-about">
    <summary>ADHD — what this is for</summary>
    <div class="adhd-about__body">
        <p>
            For ADHD minds that jot notes everywhere in real time and wish to turn the mess into clear tasks and reference.
            Notes rarely land in one place; this harness meets that workflow: dump scattered notes first, process them later.
        </p>
        <p>
            Chaotic, on-the-fly jotting from a fast ADHD brain is the point. It does not matter if notes on the same subject are split across different files, if each line is a different idea, or if a document is broken up with empty lines. Dividers are fine — dashes (<code>--</code>, <code>---</code>), <code>&lt;hr&gt;</code>, a line of asterisks, or two blank lines in a row. One blank line is just a paragraph break. The skill decides whether each divider starts a new idea or is only a section of the same article. You can paste an entire article into a note full of other random thoughts; mark it off with a divider, and the skill will keep the article together when its internal rules are just sections.
        </p>
        <p>
            Shorthands and symbols are welcome. When something is unclear, AI asks you to clarify, then builds a dictionary of your shorthands so it can understand your notes the way you write them.
        </p>
        <p>
        This app is an artifact container for the Cursor / Claude Code skill. Make your changes at Cursor / Claude Code's AI, then this container will show your notes and its suggestions in an easy to review interface.
        </p>
    </div>
</details>

<?php if ($showEmpty): ?>
    <main class="empty-state">
        <h1>No input folder pointed</h1>
        <ol>
            <li>
                Dump scattered notes as <code>.md</code> and/or <code>.txt</code> files into a folder under <code>inputs/</code>, e.g. <code>inputs/linux-dump/</code>.
                One thought per file or many thoughts in one file — both are fine. A divider (<code>--</code>, <code>---</code>, <code>&lt;hr&gt;</code>, <code>***</code>, or two blank lines) marks a possible new idea; the skill decides whether it is a new idea or a section of the same article.
            </li>
            <li>
                In Cursor or Claude Code, run the skill at
                <code>.agents/skills/scattered-notes</code> or invoke it with the command <code>/scattered-notes</code>, or with the prompt <code>use the note organizing skill</code>, and then pick your desired input folder.
                The skill will set <code>app.config.json</code>. Then this app will read the folder and show you the notes and its suggestions.
            </li>
            <li>Refresh this page to review Phase 1 artifacts. At each phase, the AI will update its suggestions in this view until the notes become useful.</li>
        </ol>
        <?php if ($inputProjects !== []): ?>
            <p>Or open a project that is already on disk:</p>
            <ul class="project-picker">
                <?php foreach ($inputProjects as $projectName): ?>
                    <li>
                        <button type="button" class="app-header__btn" data-switch-project="<?= e($projectName) ?>">
                            inputs/<?= e($projectName) ?>
                            <span class="muted"><?= e(phase_label((int) project_phase_for($config, $projectName))) ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="muted">
            Return to Cursor or Claude Code to continue the skill chat after you have notes ready in an input folder.
        </p>
    </main>
<?php elseif ($phase === 1):
    $phase1Counts = phase1_review_counts($phase1, $noteFiles, $phase1Suggestions);
    $phase1Jumps = phase1_review_jumps($phase1, $noteFiles, $activeFile, $isPhasePreview ? (int) $phase : 0, $phase1Suggestions);
    $attentionTotal = (int) $phase1Counts['uncertain'] + (int) $phase1Counts['blocked'];
    render_count_bar([
        [
            'id' => 'links',
            'label' => 'Links',
            'items' => [
                ['label' => 'Files', 'count' => $phase1Counts['files'], 'jumps' => $phase1Jumps['files']],
                ['label' => 'YouTube', 'count' => $phase1Counts['youtube'], 'jumps' => $phase1Jumps['youtube']],
                ['label' => 'Links', 'count' => $phase1Counts['links'], 'jumps' => $phase1Jumps['links']],
            ],
        ],
        [
            'id' => 'attention',
            'label' => 'Need attention',
            'tone' => 'attention',
            'items' => [
                ['label' => 'Uncertain', 'count' => $phase1Counts['uncertain'], 'countKey' => 'uncertain', 'jumps' => $phase1Jumps['uncertain']],
                ['label' => 'Blocked', 'count' => $phase1Counts['blocked'], 'countKey' => 'blocked', 'jumps' => $phase1Jumps['blocked']],
            ],
        ],
    ], 'Phase 1 review counts', $attentionTotal > 0 ? 'attention' : 'links');
    $attentionCatalog = phase1_attention_catalog($phase1, $noteFiles);
    echo '<script type="application/json" id="phase1-attention-data">'
        . str_replace(
            '</',
            '<\/',
            (string) json_encode($attentionCatalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        )
        . '</script>';
    ?>
    <aside class="banner banner--warn<?= $isPhasePreview ? ' banner--preview' : '' ?>">
        <h2><?= e(phase_label(1)) ?></h2>
        <div class="banner__phase">
            <?php render_phase_carousel((int) $livePhase, (int) $phase, $isPhasePreview); ?>
        </div>
        <p>
            Uncertain symbols and shorthand are highlighted. YouTube and fetched links show a green added descriptor.
            Blocked URLs open a paste panel — visit the page in your browser, describe what you need, and paste relevant text.
            Reload this page after the skill updates <code>phase1.json</code>.
        </p>
        <ul>
            <li>
                Distinct notes are numbered in the file. A divider line
                (<code>--</code>, <code>---</code>, <code>&lt;hr&gt;</code>, <code>***</code>,
                or two blank lines in a row)
                starts a candidate note. One blank line does not. The skill decides whether that divider is a new idea
                or only a section of the same article. Files with no divider are split on blank lines.
                The map on the right is the whole file.
                If a boundary is wrong, use <strong>Split</strong> inside a note or <strong>Merge up</strong> / <strong>Merge down</strong> on the note header.
                Drag the top edge of a note, except the first one, or the bottom edge, except the last.
                When you let go: if you dragged back into the note, choose to split those lines into a new idea or let the neighbor take them; if you dragged into the neighbor, take those lines into this note.
                Colors update on their own, and two notes in a row never share a color.
            </li>
            <li>
                Click the colored edge of a note to leave a suggestion for that whole idea.
                Click a line to leave a suggestion for that line’s text.
                Both save to <code>phase1-suggestions.md</code> and are read when you start Phase 2.
            </li>
            <li>
                For flagged shorthand: explain it in Cursor / Claude Code, <em>or</em> add an in-app <strong>Suggest</strong> note.
                One Suggest for a shorthand covers every other flag of that same shorthand (they share the same comment).
            </li>
            <li>
                For URLs the skill could read, check the green added notes. If you’re not satisfied, tell Cursor / Claude Code
                <em>or</em> add an in-app Suggest on that enrichment.
            </li>
            <li>For blocked URLs, paste text here, then tell chat you pasted so the skill can enrich from it.</li>
            <li>Or say Phase 1 looks good and ask to start Phase 2.</li>
        </ul>
    </aside>

    <div class="layout" data-phase1-root>
        <?php
        $phase1Thoughts = [];
        $phase1ThoughtMode = 'blanks';
        ?>
        <nav class="file-nav" aria-label="Note files">
            <h2>Files</h2>
            <?php if ($noteFiles === []): ?>
                <p class="muted">No <code>.md</code> / <code>.txt</code> notes in this folder yet.</p>
            <?php else:
                $fileNavStats = phase1_file_nav_stats($phase1, $noteFiles, $phase1Suggestions);
                ?>
                <div class="file-nav__cols" role="row">
                    <span class="file-nav__col-file" role="columnheader">Name</span>
                    <div class="file-nav__col-help file-nav__col-help--attention" data-section-info role="columnheader">
                        <button
                            type="button"
                            class="file-nav__col-btn file-nav__col-btn--attention"
                            data-section-info-toggle
                            aria-expanded="false"
                            aria-controls="file-nav-tip-attention"
                            title="What ! means"
                            aria-label="Column: needs attention (!)"
                        >!</button>
                        <div class="file-nav__col-tip" id="file-nav-tip-attention" data-section-info-tip role="tooltip">
                            <strong>Needs attention</strong>
                            Shorthands the AI flagged, and blocked URLs that still need a paste. Suggest on a shorthand, comment on that line, or save a paste, and it leaves this count.
                        </div>
                    </div>
                    <div class="file-nav__col-help file-nav__col-help--added" data-section-info role="columnheader">
                        <button
                            type="button"
                            class="file-nav__col-btn file-nav__col-btn--added"
                            data-section-info-toggle
                            aria-expanded="false"
                            aria-controls="file-nav-tip-added"
                            title="What + means"
                            aria-label="Column: added enrichments (+)"
                        >+</button>
                        <div class="file-nav__col-tip" id="file-nav-tip-added" data-section-info-tip role="tooltip">
                            <strong>Added enrichments</strong>
                            Green “+” lines from YouTube or fetched web links. Informational.
                        </div>
                    </div>
                </div>
                <ul class="file-nav__list">
                    <?php foreach ($noteFiles as $name):
                        $stats = $fileNavStats[$name] ?? [
                            'uncertain' => 0,
                            'blocked' => 0,
                            'attention' => 0,
                            'added' => 0,
                        ];
                        $attention = (int) ($stats['attention'] ?? 0);
                        $added = (int) ($stats['added'] ?? 0);
                        $uncertainN = (int) ($stats['uncertain'] ?? 0);
                        $blockedN = (int) ($stats['blocked'] ?? 0);
                        $attentionTitle = [];
                        if ($uncertainN > 0) {
                            $attentionTitle[] = $uncertainN . ' shorthand' . ($uncertainN === 1 ? '' : 's');
                        }
                        if ($blockedN > 0) {
                            $attentionTitle[] = $blockedN . ' blocked URL' . ($blockedN === 1 ? '' : 's');
                        }
                        $attentionLabel = $attentionTitle !== []
                            ? 'Needs attention: ' . implode(', ', $attentionTitle)
                            : 'No attention items';
                        $addedLabel = $added > 0
                            ? $added . ' added enrichment' . ($added === 1 ? '' : 's')
                            : 'No added enrichments';
                        ?>
                        <li>
                            <a href="?file=<?= e(rawurlencode($name)) ?><?= $isPhasePreview ? '&amp;preview=' . (int) $phase : '' ?>" class="file-nav__row<?= $name === $activeFile ? ' is-active' : '' ?><?= $attention > 0 ? ' has-attention' : '' ?>" data-file-nav="<?= e($name) ?>">
                                <span class="file-nav__name"><?= e($name) ?></span>
                                <span
                                    class="file-nav__badge file-nav__badge--attention<?= $attention > 0 ? ' is-on' : '' ?>"
                                    data-file-attention
                                    title="<?= e($attentionLabel) ?>"
                                    aria-label="<?= e($attentionLabel) ?>"
                                ><?= $attention > 0 ? (int) $attention : '' ?></span>
                                <span
                                    class="file-nav__badge file-nav__badge--added<?= $added > 0 ? ' is-on' : '' ?>"
                                    title="<?= e($addedLabel) ?>"
                                    aria-label="<?= e($addedLabel) ?>"
                                ><?= $added > 0 ? (int) $added : '' ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </nav>

        <main class="main">
            <?php if ($activeFile === ''): ?>
                <p class="muted">Add note files, then re-run the skill to generate <code>phase1.json</code>.</p>
            <?php else: ?>
                <?php
                $lines = array_values(lines_for_file($dir, $activeFile, $phase1));
                $regionOverrides = $phase1Regions[$activeFile] ?? ['splits' => [], 'joins' => []];
                $thoughtPack = phase1_thoughts($lines, $regionOverrides);
                $phase1Thoughts = $thoughtPack['thoughts'];
                $phase1ThoughtMode = (string) $thoughtPack['mode'];
                $thoughtById = [];
                $indexToThought = [];
                foreach ($phase1Thoughts as $thought) {
                    $thoughtById[(int) $thought['id']] = $thought;
                    foreach ($thought['indexes'] as $idx) {
                        $indexToThought[(int) $idx] = (int) $thought['id'];
                    }
                }
                $thoughtCount = count($phase1Thoughts);
                $thoughtNoun = $thoughtCount === 1 ? 'note' : 'notes';
                $regionEdited = ($regionOverrides['splits'] ?? []) !== [] || ($regionOverrides['joins'] ?? []) !== [];
                if ($thoughtCount === 0) {
                    $thoughtSummary = 'No note text in this file yet.';
                } elseif ($phase1ThoughtMode === 'dividers' || $phase1ThoughtMode === 'dashes') {
                    $thoughtSummary = $thoughtCount . ' distinct ' . $thoughtNoun . ', split on divider lines or two blank lines.';
                } else {
                    $thoughtSummary = $thoughtCount . ' distinct ' . $thoughtNoun . ', split on blank lines.';
                }
                if ($regionEdited && $thoughtCount > 0) {
                    $thoughtSummary .= ' Your boundary edits are saved.';
                }
                ?>
                <h1 class="panel-title" id="p1-file"><?= e($activeFile) ?></h1>
                <p class="thought-summary" data-thought-summary data-thought-mode="<?= e($phase1ThoughtMode) ?>" data-thought-edited="<?= $regionEdited ? '1' : '0' ?>"><?= e($thoughtSummary) ?> The map is this whole file.</p>
                <p class="thought-hint">Click the colored edge to suggest a change for that whole idea. Click a line to suggest a change for that line’s text. Both are saved for the next phase.</p>
                <?php if ($phase1 === null): ?>
                    <p class="muted">
                        Showing raw lines — <code>phase1.json</code> is not present yet.
                        Return to Cursor / Claude Code and run Phase 1 of the skill.
                    </p>
                <?php endif; ?>
                <script type="application/json" id="phase1-suggestions-data"><?=
                    str_replace(
                        '</',
                        '<\/',
                        (string) json_encode($phase1Suggestions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    )
                ?></script>
                <div class="line-view" data-phase1-lines data-phase1-file="<?= e($activeFile) ?>">
                    <?php
                    $thoughtOpen = 0;
                    foreach ($lines as $lineIndex => $line):
                        if (!is_array($line)) {
                            continue;
                        }
                        $n = (int) ($line['n'] ?? 0);
                        $text = (string) ($line['text'] ?? '');
                        $marks = $line['marks'] ?? [];
                        if (!is_array($marks)) {
                            $marks = [];
                        }
                        $youtube = $line['youtube'] ?? null;
                        $link = $line['link'] ?? null;
                        $enrichmentKey = $activeFile . ':' . $n;
                        $enrichmentSuggestion = (string) ($phase1Suggestions['enrichments'][$enrichmentKey] ?? '');
                        $hasEnrichmentSuggestion = $enrichmentSuggestion !== '';
                        $enrichmentFieldId = 'p1-enrich-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $enrichmentKey);
                        $lineKey = $activeFile . ':' . $n;
                        $hasLineSuggestion = trim((string) ($phase1Suggestions['lines'][$lineKey] ?? '')) !== '';
                        $hasUncertain = false;
                        foreach ($marks as $mark) {
                            if (is_array($mark) && ($mark['type'] ?? '') === 'uncertain') {
                                $hasUncertain = true;
                                break;
                            }
                        }
                        $thoughtId = $indexToThought[$lineIndex] ?? 0;
                        if ($thoughtId === 0) {
                            continue;
                        }
                        if ($thoughtId !== $thoughtOpen) {
                            if ($thoughtOpen !== 0) {
                                echo '</section>';
                            }
                            $thoughtOpen = $thoughtId;
                            if (isset($thoughtById[$thoughtId])) {
                                $thought = $thoughtById[$thoughtId];
                                $rangeLabel = (int) $thought['start'] === (int) $thought['end']
                                    ? 'line ' . (int) $thought['start']
                                    : 'lines ' . (int) $thought['start'] . '–' . (int) $thought['end'];
                                $ideaKey = $activeFile . ':' . (int) $thought['start'] . '-' . (int) $thought['end'];
                                $hasIdeaSuggestion = trim((string) ($phase1Suggestions['ideas'][$ideaKey] ?? '')) !== '';
                                echo '<section class="thought' . ($hasIdeaSuggestion ? ' has-idea-suggestion' : '') . '" data-thought="' . (int) $thought['id'] . '" data-idea-key="' . e($ideaKey) . '" id="thought-' . (int) $thought['id'] . '" style="--thought-hue: ' . (int) $thought['hue'] . '">';
                                echo '<button type="button" class="thought__edge" data-idea-suggest aria-label="Suggestion for this whole idea" title="Suggestion for this whole idea"></button>';
                                echo '<header class="thought__head">';
                                echo '<span class="thought__index">Note ' . (int) $thought['id'] . '</span>';
                                echo '<span class="thought__range">' . e($rangeLabel) . '</span>';
                                if ((string) $thought['preview'] !== '') {
                                    echo '<span class="thought__preview">' . e((string) $thought['preview']) . '</span>';
                                } else {
                                    echo '<span class="thought__preview"></span>';
                                }
                                echo '<div class="thought__actions">';
                                echo '<button type="button" class="thought__action" data-thought-split>Split</button>';
                                echo '<button type="button" class="thought__action" data-thought-merge="prev">Merge up</button>';
                                echo '<button type="button" class="thought__action" data-thought-merge="next">Merge down</button>';
                                echo '</div>';
                                echo '</header>';
                            }
                        }
                        echo '<div class="thought-row" data-source-line="' . (int) $n . '">';
                        echo '<button type="button" class="thought-row__split" data-thought-split-at="' . (int) $n . '">Split into a new note here</button>';
                        if (is_array($youtube) && !empty($youtube['added'])):
                            $ytTitle = (string) ($youtube['title'] ?? 'YouTube');
                            $ytAbout = (string) ($youtube['about'] ?? '');
                            $addedText = '+ ' . $ytTitle;
                            if ($ytAbout !== '') {
                                $addedText .= ' — ' . $ytAbout;
                            }
                            ?>
                            <div class="line line--added<?= $hasEnrichmentSuggestion ? ' has-phase1-suggestion' : '' ?>" id="<?= e(phase1_anchor('yt', $activeFile, $n)) ?>" data-phase1-enrichment-wrap="<?= e($enrichmentKey) ?>">
                                <div class="line__n">+</div>
                                <div class="line__body">
                                    <div class="line__text"><?= linkify_note($addedText) ?></div>
                                    <div class="line__toolbar">
                                        <div class="phase1-suggest" data-phase1-suggest data-phase1-suggest-kind="enrichment" data-phase1-suggest-key="<?= e($enrichmentKey) ?>">
                                            <button type="button" class="phase1-suggest__toggle" data-phase1-suggest-toggle aria-expanded="false" title="Suggestion for AI" aria-label="Suggestion for this enrichment">
                                                <svg class="suggest-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                                                <span>Suggest</span>
                                            </button>
                                            <div class="phase1-suggest__panel" data-phase1-suggest-panel hidden>
                                                <label class="phase1-suggest__caption" for="<?= e($enrichmentFieldId) ?>">Tell the AI how to change this enrichment</label>
                                                <textarea
                                                    class="phase1-suggest__input"
                                                    id="<?= e($enrichmentFieldId) ?>"
                                                    data-phase1-suggest-input
                                                    data-phase1-suggest-kind="enrichment"
                                                    data-phase1-suggest-key="<?= e($enrichmentKey) ?>"
                                                    rows="3"
                                                    maxlength="4000"
                                                    placeholder="e.g. about should focus on X; title is wrong…"
                                                ><?= e($enrichmentSuggestion) ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        if (is_array($link) && !empty($link['added'])):
                            $linkTitle = (string) ($link['title'] ?? 'Link');
                            $linkAbout = (string) ($link['about'] ?? '');
                            $linkAddedText = '+ ' . $linkTitle;
                            if ($linkAbout !== '') {
                                $linkAddedText .= ' — ' . $linkAbout;
                            }
                            ?>
                            <div class="line line--added<?= $hasEnrichmentSuggestion ? ' has-phase1-suggestion' : '' ?>" id="<?= e(phase1_anchor('link', $activeFile, $n)) ?>" data-phase1-enrichment-wrap="<?= e($enrichmentKey) ?>">
                                <div class="line__n">+</div>
                                <div class="line__body">
                                    <div class="line__text"><?= linkify_note($linkAddedText) ?></div>
                                    <div class="line__toolbar">
                                        <div class="phase1-suggest" data-phase1-suggest data-phase1-suggest-kind="enrichment" data-phase1-suggest-key="<?= e($enrichmentKey) ?>">
                                            <button type="button" class="phase1-suggest__toggle" data-phase1-suggest-toggle aria-expanded="false" title="Suggestion for AI" aria-label="Suggestion for this enrichment">
                                                <svg class="suggest-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                                                <span>Suggest</span>
                                            </button>
                                            <div class="phase1-suggest__panel" data-phase1-suggest-panel hidden>
                                                <label class="phase1-suggest__caption" for="<?= e($enrichmentFieldId) ?>-link">Tell the AI how to change this enrichment</label>
                                                <textarea
                                                    class="phase1-suggest__input"
                                                    id="<?= e($enrichmentFieldId) ?>-link"
                                                    data-phase1-suggest-input
                                                    data-phase1-suggest-kind="enrichment"
                                                    data-phase1-suggest-key="<?= e($enrichmentKey) ?>"
                                                    rows="3"
                                                    maxlength="4000"
                                                    placeholder="e.g. about should focus on X; pull the complexity table…"
                                                ><?= e($enrichmentSuggestion) ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="line<?= $hasUncertain ? ' line--uncertain' : '' ?><?= $hasLineSuggestion ? ' has-line-suggestion' : '' ?>" id="<?= e(phase1_anchor('line', $activeFile, $n)) ?>" data-line-suggest data-line-key="<?= e($lineKey) ?>" title="Suggestion for this line">
                            <div class="line__n"><?= $n > 0 ? (int) $n : '' ?></div>
                            <div class="line__body">
                                <div class="line__text"><?= linkify_note($text) ?></div>
                                <?php
                                $flagBits = '';
                                foreach ($marks as $mark):
                                    if (!is_array($mark)) {
                                        continue;
                                    }
                                    $token = (string) ($mark['token'] ?? '');
                                    $note = (string) ($mark['note'] ?? '');
                                    $label = $token !== '' ? $token : (string) ($mark['type'] ?? 'mark');
                                    if ($note !== '') {
                                        $label .= ': ' . $note;
                                    }
                                    $isUncertain = ($mark['type'] ?? '') === 'uncertain';
                                    $tokenKey = phase1_shorthand_key($token);
                                    if ($isUncertain && $tokenKey !== ''):
                                        $shSuggestion = phase1_shorthand_suggestion($phase1Suggestions, $tokenKey);
                                        $hasShSuggestion = $shSuggestion !== '';
                                        $fieldId = 'p1-sh-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $tokenKey) . '-' . $n;
                                        ob_start();
                                        ?>
                                        <span class="mark-chip-wrap<?= $hasShSuggestion ? ' has-phase1-suggestion' : '' ?>" data-phase1-shorthand-wrap="<?= e($tokenKey) ?>">
                                            <span class="mark-chip"><?= e($label) ?></span>
                                            <div class="phase1-suggest" data-phase1-suggest data-phase1-suggest-kind="shorthand" data-phase1-suggest-key="<?= e($tokenKey) ?>">
                                                <button type="button" class="phase1-suggest__toggle" data-phase1-suggest-toggle aria-expanded="false" title="Suggestion for this shorthand (shared across all flags of this type)" aria-label="Suggestion for shorthand <?= e($tokenKey) ?>">
                                                    <svg class="suggest-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                                                    <span>Suggest</span>
                                                </button>
                                                <div class="phase1-suggest__panel" data-phase1-suggest-panel hidden>
                                                    <label class="phase1-suggest__caption" for="<?= e($fieldId) ?>">One note covers every <code><?= e($tokenKey) ?></code> flag</label>
                                                    <textarea
                                                        class="phase1-suggest__input"
                                                        id="<?= e($fieldId) ?>"
                                                        data-phase1-suggest-input
                                                        data-phase1-suggest-kind="shorthand"
                                                        data-phase1-suggest-key="<?= e($tokenKey) ?>"
                                                        rows="3"
                                                        maxlength="4000"
                                                        placeholder="e.g. wtf means write the function"
                                                    ><?= e($shSuggestion) ?></textarea>
                                                </div>
                                            </div>
                                        </span>
                                        <?php
                                        $flagBits .= ob_get_clean();
                                    else:
                                        $flagBits .= '<span class="mark-chip">' . e($label) . '</span>';
                                    endif;
                                endforeach;
                                if ($flagBits !== ''):
                                    ?>
                                    <div class="line__toolbar"><?= $flagBits ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        if (is_array($link) && !empty($link['blocked'])):
                            $linkUrl = (string) ($link['url'] ?? $text);
                            $linkIntent = (string) ($link['intent'] ?? '');
                            $userPaste = (string) ($link['userPaste'] ?? '');
                            $userNote = (string) ($link['userNote'] ?? '');
                            $hasPaste = trim($userPaste) !== '' || trim($userNote) !== '';
                            ?>
                            <div
                                class="url-block<?= $hasPaste ? ' has-paste' : '' ?>"
                                id="<?= e(phase1_anchor('blocked', $activeFile, $n)) ?>"
                                data-url-block
                                data-url-file="<?= e($activeFile) ?>"
                                data-url-line="<?= (int) $n ?>"
                            >
                                <div class="url-block__header">
                                    <strong>Blocked URL — paste from your browser</strong>
                                    <span class="url-block__status" data-url-status><?= $hasPaste ? 'Saved — return to chat' : 'Needs paste' ?></span>
                                </div>
                                <p class="url-block__hint">
                                    This page could not be opened from here. Open the link in your browser, then describe what you need and paste the relevant text.
                                </p>
                                <p class="url-block__url">
                                    <a href="<?= e($linkUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($linkUrl) ?></a>
                                </p>
                                <?php if ($linkIntent !== ''): ?>
                                    <p class="url-block__intent"><span>Looking for:</span> <?= e($linkIntent) ?></p>
                                <?php endif; ?>
                                <label class="url-block__label" for="url-note-<?= e($activeFile) ?>-<?= (int) $n ?>">Your note (optional)</label>
                                <textarea
                                    id="url-note-<?= e($activeFile) ?>-<?= (int) $n ?>"
                                    class="url-block__input"
                                    data-url-note
                                    rows="2"
                                    placeholder="e.g. need the bubble vs merge complexity table"
                                ><?= e($userNote) ?></textarea>
                                <label class="url-block__label" for="url-paste-<?= e($activeFile) ?>-<?= (int) $n ?>">Paste relevant text from the page</label>
                                <textarea
                                    id="url-paste-<?= e($activeFile) ?>-<?= (int) $n ?>"
                                    class="url-block__input url-block__input--paste"
                                    data-url-paste
                                    rows="6"
                                    placeholder="Paste title, sections, or quotes from your browser…"
                                ><?= e($userPaste) ?></textarea>
                                <div class="url-block__footer">
                                    <button type="button" class="url-block__save" data-url-save>Save paste</button>
                                    <span class="url-block__saved muted" data-url-saved hidden>Saved to phase1.json</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php echo '</div>'; ?>
                    <?php endforeach;
                    if ($thoughtOpen !== 0) {
                        echo '</section>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </main>
        <?php if ($phase1Thoughts !== []): ?>
            <aside class="minimap" data-phase1-minimap aria-label="Map of every note in this file">
                <div class="minimap__title">
                    Map
                    <span><?= count($phase1Thoughts) ?></span>
                </div>
                <div class="minimap__track" data-minimap-track>
                    <?php foreach ($phase1Thoughts as $thought):
                        $rangeLabel = (int) $thought['start'] === (int) $thought['end']
                            ? 'line ' . (int) $thought['start']
                            : 'lines ' . (int) $thought['start'] . '–' . (int) $thought['end'];
                        $tip = 'Note ' . (int) $thought['id'] . ' · ' . $rangeLabel;
                        if ((string) $thought['preview'] !== '') {
                            $tip .= ' · ' . (string) $thought['preview'];
                        }
                        $markClass = '';
                        if (!empty($thought['blocked'])) {
                            $markClass .= ' has-blocked';
                        }
                        if (!empty($thought['uncertain'])) {
                            $markClass .= ' has-uncertain';
                        }
                        if (!empty($thought['added'])) {
                            $markClass .= ' has-added';
                        }
                        ?>
                        <button type="button"
                                class="minimap__note<?= e($markClass) ?>"
                                data-minimap-note="<?= (int) $thought['id'] ?>"
                                style="--thought-hue: <?= (int) $thought['hue'] ?>; --thought-lines: <?= max(1, (int) $thought['lines']) ?>"
                                aria-label="<?= e($tip) ?>">
                            <span class="minimap__note-label"><?= (int) $thought['id'] ?></span>
                        </button>
                    <?php endforeach; ?>
                    <div class="minimap__viewport" data-minimap-viewport hidden></div>
                </div>
            </aside>
        <?php endif; ?>
    </div>
<?php else:
    $organized = $phase2 !== null
        ? normalize_organized(is_array($phase2['organized'] ?? null) ? $phase2['organized'] : [])
        : normalize_organized(null);
    $tasks = $organized['tasks'];
    $reference = $organized['reference'];
    $articles = $organized['articleCandidates'];
    render_count_bar([
        ['label' => 'Scattered Notes', 'count' => count($tasks), 'key' => 'tasks'],
        ['label' => 'Reference', 'count' => count($reference), 'key' => 'reference'],
        ['label' => 'Articles', 'count' => count($articles), 'key' => 'articleCandidates'],
    ], 'Note counts by section');
    ?>
    <?php if ($isSnapshotView): ?>
    <aside class="banner banner--snapshot">
        <h2>Snapshot — <?= e((string) $viewingSnapshot['name']) ?></h2>
        <p>
            Saved Phase 3 view. Filter + Copy and Notebook work here — including dragging notebooks between folders
            (same as live Phase 3). Regroup edits save into this snapshot file only; your live session stays at
            <?php if ($liveFolder === ''): ?>
                no folder selected
            <?php else: ?>
                <code>inputs/<?= e($liveFolder) ?></code> · phase <?= (int) $livePhase ?>
            <?php endif; ?>
            — refreshing without <code>?snapshot=</code> returns there. Nothing here writes <code>app.config.json</code>.
        </p>
        <ul>
            <li>Saved file: <code>snapshots/<?= e((string) $viewingSnapshot['id']) ?>.json</code><?php if ((string) ($viewingSnapshot['createdAt'] ?? '') !== ''): ?> · <?= e((string) $viewingSnapshot['createdAt']) ?><?php endif; ?></li>
            <li><a href="index.php">Exit snapshot</a> to resume the live phase.</li>
        </ul>
    </aside>
    <?php elseif ($phase === 3): ?>
    <aside class="banner<?= $isPhasePreview ? ' banner--preview' : '' ?>">
        <h2><?= e(phase_label(3)) ?></h2>
        <div class="banner__phase">
            <?php render_phase_carousel((int) $livePhase, (int) $phase, $isPhasePreview); ?>
        </div>
        <p>
            Filter by tag and copy the finished notes into your own app.
            Suggest and green-check are off.
            Use <strong>Panels</strong> to filter and Copy, or switch to <strong>Notebook</strong> for an UpNote-style sidebar of filter groups → notebooks.
            You can still tweak tags, move items, and rearrange. Use the hamburger for original notes with per-line accounting.
            <strong>Save snapshot as</strong> stores this Phase 3 under <code>snapshots/</code> so you can reopen it anytime without changing your live phase.
        </p>
        <ul>
            <li>Filter + Copy (Panels) or open a notebook and Copy compiled notes — paste into your own notes app.</li>
            <li>Tag/move edits still save into <code>phase2.json</code>. Return to chat only to refine tags if needed.</li>
        </ul>
    </aside>
    <?php else: ?>
    <aside class="banner<?= $isPhasePreview ? ' banner--preview' : '' ?>">
        <h2><?= e(phase_label(2)) ?></h2>
        <div class="banner__phase">
            <?php render_phase_carousel((int) $livePhase, (int) $phase, $isPhasePreview); ?>
        </div>
        <p>
            Review whether the AI classified each note correctly as a task or not:
            open <strong>Suggest</strong> and write e.g. “not a task — make this Reference” or “this should be a task”.
            You can also move the card between Scattered / Reference / Articles yourself.
        </p>
        <p>
            Also check that it did not drop lines unnecessarily, and that each card reads the way you meant.
            Want a note changed (different title, keep a detail, merge, wrong bucket)? Open <strong>Suggest</strong> on that card — or on a section header for whole-panel feedback (tagging, heading style, ordering) — and write what should change. Saved to <code>phase2-suggestions.md</code> for the next AI pass.
            Green-check items as you review them (saved into <code>phase2.json</code>).
            Tags are already on each card and in the Filter bar. Add, remove, or rename one on the right if it is wrong.
            When this looks right, Phase 3 turns off Suggest and green-check so you can filter by tag and copy the notes into your own app.
            Review original scattered notes and which lines were dropped by opening
            <span class="banner__menu-icon" aria-hidden="true" title="Menu icon">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </span>
            at the top right.
        </p>
        <ul>
            <li>Use <strong>Suggest</strong> when a note should be rewritten or reorganized (including task vs not-a-task); return to chat so the AI can apply it.</li>
            <li>Panel moves/tags save into <code>phase2.json</code>; Suggest notes save into <code>phase2-suggestions.md</code>.</li>
        </ul>
    </aside>
    <?php endif; ?>

    <main class="main organized"
          data-phase2-root
          data-phase="<?= (int) $phase ?>"
          <?php if ($isSnapshotView): ?>
          data-snapshot-id="<?= e((string) $viewingSnapshot['id']) ?>"
          data-snapshot-readonly="1"
          <?php endif; ?>>
        <?php if ($phase2 === null): ?>
            <p class="muted">
                <code>phase2.json</code> is missing. Return to Cursor / Claude Code and ask the skill to run Phase 2.
            </p>
        <?php else:
            $allTags = collect_organized_tags($organized);
            $filterLayout = normalize_filter_layout(
                is_array($phase2['filterLayout'] ?? null) ? $phase2['filterLayout'] : [],
                $allTags
            );
            $notebookOrder = [];
            if (isset($phase2['notebookOrder']) && is_array($phase2['notebookOrder'])) {
                foreach ($phase2['notebookOrder'] as $nbKey => $nbIds) {
                    if (!is_string($nbKey) || $nbKey === '' || !is_array($nbIds)) {
                        continue;
                    }
                    $cleanIds = [];
                    foreach ($nbIds as $nbId) {
                        $idStr = trim((string) $nbId);
                        if ($idStr !== '' && !in_array($idStr, $cleanIds, true)) {
                            $cleanIds[] = $idStr;
                        }
                    }
                    $notebookOrder[$nbKey] = $cleanIds;
                }
            }
            ?>
            <?php if ($phase === 3): ?>
                <div class="view-mode-bar" data-view-mode-bar>
                    <span class="view-mode__label">Mode</span>
                    <div class="view-mode" data-view-mode role="tablist" aria-label="Phase 3 view">
                        <button type="button"
                                class="view-mode__btn is-active"
                                data-view-mode-btn="panels"
                                role="tab"
                                aria-selected="true">
                            Panels
                        </button>
                        <button type="button"
                                class="view-mode__btn"
                                data-view-mode-btn="notebook"
                                role="tab"
                                aria-selected="false">
                            Notebook
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <div class="kind-filter" data-kind-filter role="group" aria-label="Filter by task notes">
                <span class="kind-filter__label">Show</span>
                <button type="button"
                        class="kind-filter__chip is-active"
                        data-kind-filter-btn=""
                        aria-pressed="true">
                    All
                </button>
                <button type="button"
                        class="kind-filter__chip"
                        data-kind-filter-btn="task"
                        aria-pressed="false"
                        title="Titles that start with Task: ">
                    Tasks
                </button>
                <button type="button"
                        class="kind-filter__chip"
                        data-kind-filter-btn="not-task"
                        aria-pressed="false"
                        title="Titles that do not start with Task: ">
                    Not tasks
                </button>
            </div>
            <div class="panels-view" data-panels-view>
            <div class="tag-filter" data-tag-filter <?= $allTags === [] ? 'hidden' : '' ?>>
                <span class="tag-filter__label">Filter</span>
                <button type="button" class="tag-filter__chip is-active" data-filter-tag="" aria-pressed="true">All</button>
                <?php
                $filterGroupOpen = false;
                foreach ($filterLayout as $layoutItem):
                    $layoutType = (string) ($layoutItem['type'] ?? '');
                    if ($layoutType === 'heading'):
                        $headingName = trim((string) ($layoutItem['name'] ?? ''));
                        if ($filterGroupOpen) {
                            echo '</div></div>';
                            $filterGroupOpen = false;
                        }
                        ?>
                        <div class="tag-filter__cluster">
                            <div class="tag-filter__heading-row">
                                <?php if ($headingName !== ''): ?>
                                    <button type="button" class="tag-filter__heading is-named"><?= e($headingName) ?></button>
                                <?php else: ?>
                                    <button type="button" class="tag-filter__heading is-placeholder" title="Name this filter group" aria-label="Name this filter group">
                                        <svg class="tag-filter__heading-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                        <span class="tag-filter__heading-label">Name group</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="tag-filter__cluster-tags">
                        <?php
                        $filterGroupOpen = true;
                        continue;
                    endif;
                    if ($layoutType === 'divider'):
                        if ($filterGroupOpen) {
                            echo '</div></div>';
                            $filterGroupOpen = false;
                        }
                        ?>
                        <button type="button"
                                class="tag-filter__divider"
                                data-filter-divider
                                title="Remove divider"
                                aria-label="Remove divider"></button>
                    <?php elseif ($layoutType === 'tag'):
                        $tagName = (string) ($layoutItem['name'] ?? '');
                        if ($tagName === '') {
                            continue;
                        }
                        if (!$filterGroupOpen): ?>
                            <div class="tag-filter__cluster">
                                <div class="tag-filter__heading-row">
                                    <button type="button" class="tag-filter__heading is-placeholder" title="Name this filter group" aria-label="Name this filter group">
                                        <svg class="tag-filter__heading-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                        <span class="tag-filter__heading-label">Name group</span>
                                    </button>
                                </div>
                                <div class="tag-filter__cluster-tags">
                        <?php
                            $filterGroupOpen = true;
                        endif;
                        ?>
                        <button type="button"
                                class="tag-filter__chip"
                                data-filter-tag="<?= e($tagName) ?>"
                                draggable="true"
                                aria-pressed="false">
                            <?= e($tagName) ?>
                        </button>
                    <?php endif;
                endforeach;
                if ($filterGroupOpen) {
                    echo '</div></div>';
                }
                ?>
            </div>
            <script type="application/json" id="phase2-organized-data"><?=
                str_replace(
                    '</',
                    '<\/',
                    (string) json_encode($organized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                )
            ?></script>
            <script type="application/json" id="phase2-filter-layout-data"><?=
                str_replace(
                    '</',
                    '<\/',
                    (string) json_encode($filterLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                )
            ?></script>
            <script type="application/json" id="phase2-notebook-order-data"><?=
                str_replace(
                    '</',
                    '<\/',
                    (string) json_encode(
                        $notebookOrder === [] ? new stdClass() : $notebookOrder,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    )
                )
            ?></script>
            <script type="application/json" id="phase2-suggestions-data"><?=
                str_replace(
                    '</',
                    '<\/',
                    (string) json_encode($phase2Suggestions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                )
            ?></script>
            <?php
            $reviewLocked = $phase === 3;
            $itemSuggestions = is_array($phase2Suggestions['items'] ?? null)
                ? $phase2Suggestions['items']
                : [];
            $panelSuggestions = is_array($phase2Suggestions['panel'] ?? null)
                ? $phase2Suggestions['panel']
                : [];
            $sections = [
                'tasks' => [
                    'Scattered',
                    'No scattered items yet.',
                    $tasks,
                    'e.g. prefer short action headings; order by urgency; tag by server area…',
                ],
                'reference' => [
                    'Reference',
                    'No reference items yet.',
                    $reference,
                    'e.g. keep commands and facts here; move how-to steps to Scattered…',
                ],
                'articleCandidates' => [
                    'Articles',
                    'No article candidates yet.',
                    $articles,
                    'e.g. group related ideas; prefer question-style headings; drop weak candidates…',
                ],
            ];
            foreach ($sections as $bucketKey => $sectionMeta):
                [$sectionTitle, $emptyLabel, $sectionItems, $panelSuggestPlaceholder] = $sectionMeta;
                $panelSuggestion = (string) ($panelSuggestions[$bucketKey] ?? '');
                $hasPanelSuggestion = !$reviewLocked && $panelSuggestion !== '';
                $suggestPanelId = 'suggest-panel-' . $bucketKey;
                ?>
                <section class="section-block<?= $hasPanelSuggestion ? ' has-panel-suggestion' : '' ?>" id="section-<?= e($bucketKey) ?>" data-section-bucket="<?= e($bucketKey) ?>">
                    <div class="section-block__header">
                        <button type="button"
                                class="section-block__collapse"
                                data-collapse-toggle
                                aria-expanded="true"
                                aria-controls="section-body-<?= e($bucketKey) ?>">
                            <span class="section-block__chevron" aria-hidden="true"></span>
                            <h2><?= e($sectionTitle) ?></h2>
                        </button>
                        <?php if ($bucketKey === 'articleCandidates'): ?>
                            <div class="section-block__info" data-section-info>
                                <button type="button"
                                        class="section-block__info-btn"
                                        data-section-info-toggle
                                        aria-expanded="false"
                                        aria-controls="articles-info-tip"
                                        title="What Articles are for"
                                        aria-label="What Articles are for">
                                    <span aria-hidden="true">i</span>
                                </button>
                                <div class="section-block__info-tip" id="articles-info-tip" data-section-info-tip role="tooltip">
                                    Turn these into a full article with ChatGPT (or similar): expand the thought into a complete, easy-to-digest piece you can publish or reuse.
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="section-block__header-actions">
                            <?php if (!$reviewLocked): ?>
                                <div class="section-block__suggest" data-panel-suggest="<?= e($bucketKey) ?>">
                                    <button type="button"
                                            class="section-block__suggest-toggle"
                                            data-panel-suggest-toggle
                                            aria-expanded="false"
                                            title="Suggestion for <?= e($sectionTitle) ?> section"
                                            aria-label="Suggestion for <?= e($sectionTitle) ?> section">
                                        <svg class="suggest-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                                        <span>Suggest</span>
                                    </button>
                                </div>
                                <span class="section-block__actions-divider" aria-hidden="true"></span>
                            <?php endif; ?>
                            <button type="button"
                                    class="section-block__rearrange"
                                    data-rearrange-toggle
                                    aria-pressed="false"
                                    title="Rearrange items"
                                    aria-label="Rearrange <?= e($sectionTitle) ?> items">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M8 9l-3 3 3 3M16 9l3 3-3 3M5 12h14"/>
                                </svg>
                            </button>
                            <button type="button"
                                    class="section-block__copy"
                                    data-copy-section
                                    title="Copy visible items"
                                    aria-label="Copy visible <?= e($sectionTitle) ?> items">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <rect x="9" y="9" width="13" height="13" rx="2"/>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <?php if (!$reviewLocked): ?>
                        <div class="section-block__suggest-panel" data-panel-suggest-panel hidden>
                            <label class="section-block__suggest-caption" for="<?= e($suggestPanelId) ?>">Tell the AI how to change the <?= e($sectionTitle) ?> section</label>
                            <textarea class="section-block__suggest-input"
                                      data-panel-suggest-input="<?= e($bucketKey) ?>"
                                      id="<?= e($suggestPanelId) ?>"
                                      rows="3"
                                      maxlength="4000"
                                      placeholder="<?= e($panelSuggestPlaceholder) ?>"><?= e($panelSuggestion) ?></textarea>
                        </div>
                    <?php endif; ?>
                    <div class="section-block__body" id="section-body-<?= e($bucketKey) ?>" data-section-body>
                        <div class="section-block__body-inner">
                            <?php render_item_cards($sectionItems, $bucketKey, $emptyLabel, $itemSuggestions, $reviewLocked); ?>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>
            </div><!-- /.panels-view -->
            <?php if ($phase === 3): ?>
                <div class="notebook" data-notebook-view hidden>
                    <aside class="notebook__sidebar" aria-label="Notebook folders">
                        <div class="notebook__sidebar-head">Notebooks</div>
                        <div class="notebook__folders" data-notebook-folders></div>
                    </aside>
                    <div class="notebook__main">
                        <div class="notebook__empty" data-notebook-empty>
                            <p>Select a notebook in the sidebar to see its notes compiled here.</p>
                        </div>
                        <div class="notebook__workspace" data-notebook-workspace hidden>
                            <header class="notebook__toolbar">
                                <div class="notebook__toolbar-text">
                                    <p class="notebook__crumb" data-notebook-crumb></p>
                                    <h1 class="notebook__title" data-notebook-title></h1>
                                </div>
                                <div class="notebook__toolbar-actions" data-notebook-toolbar-actions>
                                    <button type="button"
                                            class="notebook__rearrange"
                                            data-notebook-rearrange
                                            aria-pressed="false"
                                            title="Rearrange notes"
                                            aria-label="Rearrange notes in this notebook">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M8 9l-3 3 3 3M16 9l3 3-3 3M5 12h14"/>
                                        </svg>
                                        <span class="notebook__action-label">Rearrange</span>
                                    </button>
                                    <button type="button"
                                            class="notebook__copy"
                                            data-notebook-copy
                                            title="Copy all notes in this notebook"
                                            aria-label="Copy all notes in this notebook">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                        </svg>
                                        <span class="notebook__action-label">Copy</span>
                                    </button>
                                </div>
                            </header>
                            <div class="notebook__notes" data-notebook-notes></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <div class="drawer-backdrop" data-drawer-backdrop></div>
    <aside class="drawer" data-drawer aria-hidden="true" aria-label="Original notes blame">
        <div class="drawer__header">
            <h2>Original notes · line accounting</h2>
            <button type="button" class="drawer__close" data-drawer-close aria-label="Close">×</button>
        </div>
        <?php if ($noteFiles === []): ?>
            <p class="muted" style="padding:1rem">No source note files.</p>
        <?php else: ?>
            <div class="drawer__files">
                <?php foreach ($noteFiles as $i => $name): ?>
                    <button type="button"
                            data-blame-file="<?= e($name) ?>"
                            class="<?= $i === 0 ? 'is-active' : '' ?>">
                        <?= e($name) ?>
                    </button>
                <?php endforeach; ?>
                <button type="button"
                        class="drawer__dropped-btn"
                        data-highlight-dropped
                        aria-pressed="false"
                        title="Highlight dropped lines"
                        aria-label="Highlight dropped lines">
                    <span class="drawer__dropped-btn__icon" aria-hidden="true">−</span>
                    Dropped
                </button>
            </div>
            <div class="drawer__body">
                <?php
                $blame = is_array($phase2) ? ($phase2['blame'] ?? []) : [];
                if (!is_array($blame)) {
                    $blame = [];
                }
                foreach ($noteFiles as $i => $name):
                    $entries = $blame[$name] ?? null;
                    if (!is_array($entries)) {
                        $entries = [];
                        foreach (raw_file_lines($dir . '/' . $name) as $rawLine) {
                            $entries[] = [
                                'n' => $rawLine['n'],
                                'text' => $rawLine['text'],
                                'fate' => 'unaccounted',
                                'to' => [],
                                'comment' => 'No blame entry yet — return to chat to fix fidelity.',
                            ];
                        }
                    }
                    ?>
                    <div data-blame-panel="<?= e($name) ?>" <?= $i === 0 ? '' : 'hidden' ?>>
                        <?php foreach ($entries as $entry):
                            if (!is_array($entry)) {
                                continue;
                            }
                            $n = (int) ($entry['n'] ?? 0);
                            $text = (string) ($entry['text'] ?? '');
                            $fate = (string) ($entry['fate'] ?? '');
                            $comment = (string) ($entry['comment'] ?? '');
                            $to = $entry['to'] ?? [];
                            if (!is_array($to)) {
                                $to = [];
                            }
                            $toIds = [];
                            foreach ($to as $target) {
                                $targetId = trim((string) $target);
                                if ($targetId !== '') {
                                    $toIds[] = $targetId;
                                }
                            }
                            $isDropped = $toIds === [] || strpos($fate, 'dropped') === 0;
                            $toText = $toIds !== [] ? ' → ' . implode(', ', $toIds) : '';
                            ?>
                            <div class="blame-line<?= $isDropped ? ' blame-line--dropped' : ' blame-line--mapped' ?>"
                                 role="button"
                                 tabindex="0"
                                 data-blame-line
                                 data-dropped="<?= $isDropped ? '1' : '0' ?>"
                                 data-to="<?= e(implode(',', $toIds)) ?>">
                                <div class="blame-line__n">
                                    <?php if ($isDropped): ?>
                                        <span class="blame-line__drop-mark" aria-hidden="true" title="Dropped">−</span>
                                    <?php endif; ?>
                                    <?= $n > 0 ? $n : '' ?>
                                </div>
                                <div class="blame-line__main">
                                    <div class="blame-line__text"><?= linkify_note($text) ?></div>
                                    <div class="blame-line__comment">
                                        <?php if ($fate !== ''): ?>
                                            <span class="blame-line__fate"><?= e($fate) ?></span>
                                        <?php endif; ?>
                                        <?= e($comment . $toText) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>

    <div class="note-modal" data-note-modal hidden>
        <div class="note-modal__backdrop" data-note-modal-close tabindex="-1"></div>
        <div class="note-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="note-modal-title">
            <div class="note-modal__header">
                <h2 id="note-modal-title" data-note-modal-title></h2>
                <button type="button" class="note-modal__copy" data-note-modal-copy title="Copy full note text" aria-label="Copy full note text">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span>Copy</span>
                </button>
                <button type="button" class="note-modal__close" data-note-modal-close aria-label="Close">×</button>
            </div>
            <div class="note-modal__body" data-note-modal-body></div>
        </div>
    </div>
<?php endif; ?>

<footer class="app-credits">
    <details>
        <summary>Credits &amp; links</summary>
        <p class="app-credits__by">By Weng (Weng Fei Fung)</p>
        <div class="app-credits__badges">
            <img src="https://img.shields.io/github/last-commit/Siphon880gh/adhd-scattered-notes-harness/main" alt="Last Commit">
            <a href="https://github.com/Siphon880gh" target="_blank" rel="noopener noreferrer">
                <img src="https://img.shields.io/badge/GitHub--blue?style=social&amp;logo=GitHub" alt="GitHub">
            </a>
            <a href="https://www.linkedin.com/in/weng-fung/" target="_blank" rel="noopener noreferrer">
                <img src="https://img.shields.io/badge/LinkedIn-blue?style=flat&amp;logo=linkedin&amp;labelColor=blue" alt="LinkedIn">
            </a>
            <a href="https://www.youtube.com/@WayneTeachesCode/" target="_blank" rel="noopener noreferrer">
                <img src="https://img.shields.io/badge/Youtube-red?style=flat&amp;logo=youtube&amp;labelColor=red" alt="YouTube">
            </a>
        </div>
    </details>
</footer>

<div class="app-toast" data-app-toast hidden role="status" aria-live="polite"></div>

<script src="<?= e(asset_url('assets/app.js')) ?>"></script>
</body>
</html>
