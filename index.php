<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$config = load_config();
$folder = $config['inputFolder'];
$phase = $config['phase'];
$dir = $folder !== '' ? input_dir($folder) : '';
$folderExists = $folder !== '' && is_dir($dir);

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

    if (!save_json_file($phase2Path, $existing)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write phase2.json.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'organized' => $existing['organized'],
        'filterLayout' => $existing['filterLayout'],
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
        echo json_encode(['ok' => false, 'error' => 'Expected JSON body with shorthands and/or enrichments.']);
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
$phase2Suggestions = $folderExists
    ? load_phase2_suggestions($dir)
    : normalize_phase2_suggestions(null);

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
 * @param list<array{label: string, count: int, key?: string}> $items
 */
function render_count_bar(array $items, string $ariaLabel): void
{
    echo '<div class="bucket-counts" data-bucket-counts aria-label="' . e($ariaLabel) . '">';
    $first = true;
    foreach ($items as $item) {
        if (!$first) {
            echo '<span class="bucket-counts__divider" aria-hidden="true"></span>';
        }
        $first = false;
        $label = (string) ($item['label'] ?? '');
        $count = (int) ($item['count'] ?? 0);
        $key = isset($item['key']) ? (string) $item['key'] : '';
        if ($key !== '') {
            echo '<button type="button" class="bucket-counts__item bucket-counts__item--link" data-jump-bucket="' . e($key) . '" title="Jump to ' . e($label) . '">';
            echo e($label) . ' (<span data-bucket-count="' . e($key) . '">' . $count . '</span>)';
            echo '</button>';
        } else {
            echo '<span class="bucket-counts__item">';
            echo e($label) . ' (<span>' . $count . '</span>)';
            echo '</span>';
        }
    }
    echo '</div>';
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
        echo '<h3>' . e($title) . '</h3>';
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
            echo '<p class="item-card__body-text">' . e($previewBody) . '</p>';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scattered Notes Harness</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body>
<header class="app-header">
    <a href="index.php" class="app-header__brand">Scattered Notes</a>
    <div class="app-header__meta">
        <?php if ($showEmpty): ?>
            no folder configured
        <?php else: ?>
            inputs/<?= e($folder) ?> · phase <?= (int) $phase ?>
        <?php endif; ?>
    </div>
    <?php if (!$showEmpty && $phase === 2): ?>
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
</header>

<details class="adhd-about">
    <summary>ADHD — what this is for</summary>
    <div class="adhd-about__body">
        <p>
            For ADHD minds that jot notes everywhere in real time and wish to turn the mess into clear tasks and reference.
            Notes rarely land in one place; this harness meets that workflow: dump scattered notes first, process them later.
        </p>
        <p>
            Chaotic, on-the-fly jotting from a fast ADHD brain is the point. It does not matter if notes on the same subject are split across different files, if each line is a different idea, or if a document is broken up with empty lines. Empty lines or dashes as dividers are fine—AI figures out the boundaries and sorts related thoughts into something usable: actionable or referable. You can even paste an entire article into a note full of other random thoughts, as long as you made a reasonable effort to mark it as a different idea (separated by empty lines or dashes). If the article already has dash lines, add twice the dash lines to separate the article from your other thoughts (e.g. article uses <code>---</code> → wrap it with <code>------</code>).
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
                One thought per file or many thoughts in one file (use <code>---</code> separators) — both are fine.
            </li>
            <li>
                In Cursor or Claude Code, run the skill at
                <code>.agents/skills/scattered-notes</code> or invoke it with the command <code>/scattered-notes</code>, or with the prompt <code>use the note organizing skill</code>, and then pick your desired input folder.
                The skill will set <code>app.config.json</code>. Then this app will read the folder and show you the notes and its suggestions.
            </li>
            <li>Refresh this page to review Phase 1 artifacts. At each phase, the AI will update its suggestions in this view until the notes become useful.</li>
        </ol>
        <p class="muted">
            Return to Cursor or Claude Code to continue the skill chat after you have notes ready in an input folder.
        </p>
    </main>
<?php elseif ($phase === 1):
    $phase1Counts = phase1_review_counts($phase1, $noteFiles);
    render_count_bar([
        ['label' => 'Files', 'count' => $phase1Counts['files']],
        ['label' => 'Uncertain', 'count' => $phase1Counts['uncertain']],
        ['label' => 'YouTube', 'count' => $phase1Counts['youtube']],
        ['label' => 'Links', 'count' => $phase1Counts['links']],
        ['label' => 'Blocked', 'count' => $phase1Counts['blocked']],
    ], 'Phase 1 review counts');
    ?>
    <aside class="banner banner--warn">
        <h2>Phase 1 — review marks, then return to chat</h2>
        <p>
            Uncertain symbols and shorthand are highlighted. YouTube and fetched links show a green added descriptor.
            Blocked URLs open a paste panel — visit the page in your browser, describe what you need, and paste relevant text.
            Reload this page after the skill updates <code>phase1.json</code>.
        </p>
        <ul>
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
        <nav class="file-nav" aria-label="Note files">
            <h2>Files</h2>
            <?php if ($noteFiles === []): ?>
                <p class="muted">No <code>.md</code> / <code>.txt</code> notes in this folder yet.</p>
            <?php else:
                $fileNavStats = phase1_file_nav_stats($phase1, $noteFiles);
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
                            Shorthands the AI flagged, and blocked URLs that need you to open the page and paste text here.
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
                            Green “+” lines from YouTube or fetched web links. Informational — not urgent like !.
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
                            <a href="?file=<?= e(rawurlencode($name)) ?>" class="file-nav__row<?= $name === $activeFile ? ' is-active' : '' ?><?= $attention > 0 ? ' has-attention' : '' ?>">
                                <span class="file-nav__name"><?= e($name) ?></span>
                                <span
                                    class="file-nav__badge file-nav__badge--attention<?= $attention > 0 ? ' is-on' : '' ?>"
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
                <h1 class="panel-title"><?= e($activeFile) ?></h1>
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
                <div class="line-view">
                    <?php
                    $lines = lines_for_file($dir, $activeFile, $phase1);
                    foreach ($lines as $line):
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
                        $hasUncertain = false;
                        foreach ($marks as $mark) {
                            if (is_array($mark) && ($mark['type'] ?? '') === 'uncertain') {
                                $hasUncertain = true;
                                break;
                            }
                        }
                        if (is_array($youtube) && !empty($youtube['added'])):
                            $ytTitle = (string) ($youtube['title'] ?? 'YouTube');
                            $ytAbout = (string) ($youtube['about'] ?? '');
                            $addedText = '+ ' . $ytTitle;
                            if ($ytAbout !== '') {
                                $addedText .= ' — ' . $ytAbout;
                            }
                            ?>
                            <div class="line line--added<?= $hasEnrichmentSuggestion ? ' has-phase1-suggestion' : '' ?>" data-phase1-enrichment-wrap="<?= e($enrichmentKey) ?>">
                                <div class="line__n">+</div>
                                <div class="line__body">
                                    <div class="line__text"><?= e($addedText) ?></div>
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
                            <div class="line line--added<?= $hasEnrichmentSuggestion ? ' has-phase1-suggestion' : '' ?>" data-phase1-enrichment-wrap="<?= e($enrichmentKey) ?>">
                                <div class="line__n">+</div>
                                <div class="line__body">
                                    <div class="line__text"><?= e($linkAddedText) ?></div>
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
                        <div class="line<?= $hasUncertain ? ' line--uncertain' : '' ?>">
                            <div class="line__n"><?= $n > 0 ? (int) $n : '' ?></div>
                            <div class="line__body">
                                <div class="line__text"><?= e($text) ?></div>
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
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
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
    <?php if ($phase === 3): ?>
    <aside class="banner">
        <h2>Phase 3 — locked in</h2>
        <p>
            Review is finished: no Suggest notes and no green-check marking in this phase.
            Filter by tag, then <strong>Copy</strong> cards (or a whole panel) into your permanent notes app.
            You can still tweak tags, move items, and rearrange. Use the hamburger for original notes with per-line accounting.
        </p>
        <ul>
            <li>Filter + Copy is the main exit path — paste into your own notes app.</li>
            <li>Tag/move edits still save into <code>phase2.json</code>. Return to chat only to refine tags if needed.</li>
        </ul>
    </aside>
    <?php else: ?>
    <aside class="banner">
        <h2>Phase 2 — organized notes</h2>
        <p>
            Review whether the AI parsed your notes correctly: that it did not drop lines unnecessarily, and that each card reads the way you meant.
            Want a note changed (different title, keep a detail, merge, wrong bucket)? Open <strong>Suggest</strong> on that card — or on a section header for whole-panel feedback (tagging, heading style, ordering) — and write what should change. Saved to <code>phase2-suggestions.md</code> for the next AI pass.
            Green-check items as you review them (saved into <code>phase2.json</code>).
            Move items between panels, or add tags on the right (filter chips appear at the top).
            Phase 3 will ultimately tag items for you by area — optional tags here are fine in the meantime.
            Review original scattered notes and which lines were dropped by opening
            <span class="banner__menu-icon" aria-hidden="true" title="Menu icon">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </span>
            at the top right.
        </p>
        <ul>
            <li>Use <strong>Suggest</strong> when a note should be rewritten or reorganized; return to chat so the AI can apply it.</li>
            <li>Panel moves/tags save into <code>phase2.json</code>; Suggest notes save into <code>phase2-suggestions.md</code>.</li>
        </ul>
    </aside>
    <?php endif; ?>

    <main class="main organized" data-phase2-root data-phase="<?= (int) $phase ?>">
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
            ?>
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
                                    <div class="blame-line__text"><?= e($text) ?></div>
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

<script src="<?= e(asset_url('assets/app.js')) ?>"></script>
</body>
</html>
