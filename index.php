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

$noteFiles = $folderExists ? list_note_files($dir) : [];
$activeFile = isset($_GET['file']) ? (string) $_GET['file'] : '';
if ($activeFile === '' || !in_array($activeFile, $noteFiles, true)) {
    $activeFile = $noteFiles[0] ?? '';
}

$phase1 = $folderExists ? load_json_file($dir . '/phase1.json') : null;
$phase2 = $folderExists ? load_json_file($dir . '/phase2.json') : null;

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
 */
function render_item_cards(array $items, string $bucket, string $emptyLabel): void
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
        $tagAttr = htmlspecialchars(json_encode($tags, JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fullTextAttr = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $attrs = 'class="item-card' . ($checked ? ' is-checked' : '') . '" data-bucket="' . e($bucket) . '"';
        if ($id !== '') {
            $attrs .= ' id="org-' . e($id) . '" data-org-id="' . e($id) . '"';
        }
        $attrs .= ' data-tags="' . $tagAttr . '"';
        $attrs .= ' data-full-text="' . $fullTextAttr . '"';
        $attrs .= ' data-checked="' . ($checked ? '1' : '0') . '"';
        echo '<article ' . $attrs . '>';
        echo '<div class="item-card__main">';
        echo '<div class="item-card__title-row">';
        echo '<button type="button" class="item-card__check" data-check-item aria-pressed="' . ($checked ? 'true' : 'false') . '" title="' . ($checked ? 'Unmark as reviewed' : 'Mark as reviewed') . '" aria-label="' . ($checked ? 'Unmark as reviewed' : 'Mark as reviewed') . '">';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>';
        echo '</button>';
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
    <link rel="stylesheet" href="assets/app.css?v=1.23">
</head>
<body>
<header class="app-header">
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
    <a href="index.php" class="app-header__brand">Scattered Notes</a>
    <div class="app-header__meta">
        <?php if ($showEmpty): ?>
            no folder configured
        <?php else: ?>
            inputs/<?= e($folder) ?> · phase <?= (int) $phase ?>
        <?php endif; ?>
    </div>
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
    ], 'Phase 1 review counts');
    ?>
    <aside class="banner banner--warn">
        <h2>Phase 1 — review marks, then return to chat</h2>
        <p>
            Uncertain symbols and shorthand are highlighted. YouTube links show a green added descriptor.
            Reload this page after the skill updates <code>phase1.json</code>.
        </p>
        <ul>
            <li>In Cursor / Claude Code, explain any flagged shorthand (the skill updates the app-wide <code>shorthands.md</code>).</li>
            <li>Or say Phase 1 looks good and ask to start Phase 2.</li>
        </ul>
    </aside>

    <div class="layout">
        <nav class="file-nav" aria-label="Note files">
            <h2>Files</h2>
            <?php if ($noteFiles === []): ?>
                <p class="muted">No <code>.md</code> / <code>.txt</code> notes in this folder yet.</p>
            <?php else: ?>
                <ul class="file-nav__list">
                    <?php foreach ($noteFiles as $name): ?>
                        <li>
                            <a href="?file=<?= e(rawurlencode($name)) ?>" class="<?= $name === $activeFile ? 'is-active' : '' ?>">
                                <?= e($name) ?>
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
                            <div class="line line--added">
                                <div class="line__n">+</div>
                                <div class="line__body"><?= e($addedText) ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="line<?= $hasUncertain ? ' line--uncertain' : '' ?>">
                            <div class="line__n"><?= $n > 0 ? (int) $n : '' ?></div>
                            <div class="line__body">
                                <?= e($text) ?>
                                <?php foreach ($marks as $mark):
                                    if (!is_array($mark)) {
                                        continue;
                                    }
                                    $token = (string) ($mark['token'] ?? '');
                                    $note = (string) ($mark['note'] ?? '');
                                    $label = $token !== '' ? $token : (string) ($mark['type'] ?? 'mark');
                                    if ($note !== '') {
                                        $label .= ': ' . $note;
                                    }
                                    ?>
                                    <span class="mark-chip"><?= e($label) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
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
        <h2>Phase 3 — tagged notes</h2>
        <p>
            Items are auto-tagged by area. You can still green-check items as reviewed, add or remove tags, move items between Scattered / Reference / Articles, and rearrange.
            Use the hamburger for original notes with per-line accounting.
        </p>
        <ul>
            <li>Edits here save into <code>phase2.json</code> so Cursor / Claude Code can see them.</li>
            <li>Return to chat to refine tags or groupings (e.g. “Retag t1 under docker networking”).</li>
        </ul>
    </aside>
    <?php else: ?>
    <aside class="banner">
        <h2>Phase 2 — organized notes</h2>
        <p>
            Review whether the AI parsed your notes correctly: that it did not drop lines unnecessarily, and that the suggestions for your lines make sense.
            Green-check items in Scattered / Reference / Articles as you review them (saved into <code>phase2.json</code>).
            Move items between those panels, or add tags on the right of any item (filter chips appear at the top).
            Phase 3 will ultimately tag items for you by area — optional tags here are fine in the meantime.
            Use the hamburger for original notes with per-line accounting.
        </p>
        <ul>
            <li>Edits here save into <code>phase2.json</code> so Cursor / Claude Code can see them.</li>
            <li>Return to Cursor / Claude Code for merges, fidelity fixes, or to start Phase 3.</li>
        </ul>
    </aside>
    <?php endif; ?>

    <main class="main organized" data-phase2-root>
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
                <?php foreach ($filterLayout as $layoutItem):
                    if (($layoutItem['type'] ?? '') === 'divider'): ?>
                        <button type="button"
                                class="tag-filter__divider"
                                data-filter-divider
                                title="Remove divider"
                                aria-label="Remove divider"></button>
                    <?php elseif (($layoutItem['type'] ?? '') === 'tag'):
                        $tagName = (string) ($layoutItem['name'] ?? '');
                        if ($tagName === '') {
                            continue;
                        }
                        ?>
                        <button type="button"
                                class="tag-filter__chip"
                                data-filter-tag="<?= e($tagName) ?>"
                                draggable="true"
                                aria-pressed="false">
                            <?= e($tagName) ?>
                        </button>
                    <?php endif;
                endforeach; ?>
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
            <?php
            $sections = [
                'tasks' => ['Scattered', 'No scattered items yet.', $tasks],
                'reference' => ['Reference', 'No reference items yet.', $reference],
                'articleCandidates' => ['Articles', 'No article candidates yet.', $articles],
            ];
            foreach ($sections as $bucketKey => $sectionMeta):
                [$sectionTitle, $emptyLabel, $sectionItems] = $sectionMeta;
                ?>
                <section class="section-block" id="section-<?= e($bucketKey) ?>" data-section-bucket="<?= e($bucketKey) ?>">
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
                    <div class="section-block__body" id="section-body-<?= e($bucketKey) ?>" data-section-body>
                        <div class="section-block__body-inner">
                            <?php render_item_cards($sectionItems, $bucketKey, $emptyLabel); ?>
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

<script src="assets/app.js?v=1.22"></script>
</body>
</html>
