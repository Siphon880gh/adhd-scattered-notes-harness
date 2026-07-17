<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$config = load_config();
$folder = $config['inputFolder'];
$phase = $config['phase'];
$dir = $folder !== '' ? input_dir($folder) : '';
$folderExists = $folder !== '' && is_dir($dir);
$noteFiles = $folderExists ? list_note_files($dir) : [];
$activeFile = isset($_GET['file']) ? (string) $_GET['file'] : '';
if ($activeFile === '' || !in_array($activeFile, $noteFiles, true)) {
    $activeFile = $noteFiles[0] ?? '';
}

$phase1 = $folderExists ? load_json_file($dir . '/phase1.json') : null;
$phase2 = $folderExists ? load_json_file($dir . '/phase2.json') : null;

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
function render_item_cards(array $items, string $emptyLabel): void
{
    if ($items === []) {
        echo '<p class="muted">' . e($emptyLabel) . '</p>';
        return;
    }
    echo '<div class="card-list">';
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = (string) ($item['title'] ?? $item['id'] ?? 'Untitled');
        $body = (string) ($item['body'] ?? $item['why'] ?? '');
        $sources = $item['sources'] ?? [];
        if (!is_array($sources)) {
            $sources = [];
        }
        echo '<article class="item-card">';
        echo '<h3>' . e($title) . '</h3>';
        if ($body !== '') {
            echo '<p>' . nl2br(e($body)) . '</p>';
        }
        if ($sources !== []) {
            $srcText = implode(', ', array_map('strval', $sources));
            echo '<div class="item-card__meta">' . e($srcText) . '</div>';
        }
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
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="app-header">
    <?php if (!$showEmpty && $phase === 2): ?>
        <button type="button" class="app-header__menu" data-drawer-open aria-label="Open original notes">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
        </button>
    <?php endif; ?>
    <div class="app-header__brand">Scattered Notes</div>
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
            For ADHD minds that jot notes everywhere in real and wishes to turn the mess into clear tasks and reference.
            Notes rarely land in one place; this harness meets that workflow: dump scattered notes first, process them later.
        </p>
        <p>
            This PHP app is an <strong>artifact container</strong>, not where you organize.
            You run the skill in Cursor or Claude Code; it writes artifacts next to your notes; you review them here, then return to the chat to continue.
        </p>
    </div>
</details>

<?php if ($showEmpty): ?>
    <main class="empty-state">
        <h1>No input folder pointed</h1>
        <p class="muted">
            This app is an artifact container for the Cursor / Claude Code skill.
            It shows whatever <code>app.config.json</code> points at — there is no folder switcher here.
        </p>
        <ol>
            <li>Create a folder under <code>inputs/</code>, e.g. <code>inputs/linux-dump/</code>.</li>
            <li>
                Dump scattered notes as <code>.md</code> and/or <code>.txt</code>.
                One thought per file or many thoughts in one file (use <code>---</code> separators) — both are fine.
            </li>
            <li>
                In Cursor or Claude Code, run the skill at
                <code>.agents/skills/scattered-notes</code> and pick your desired input folder.
                The skill will set <code>app.config.json</code>.
                Or you can prompt:
                <pre class="prompt-block"><code>use the scattered note organizing skill</code></pre>
            </li>
            <li>Refresh this page to review Phase 1 artifacts.</li>
        </ol>
        <p class="muted">
            Return to Cursor or Claude Code to continue the skill chat after you have notes ready.
        </p>
    </main>
<?php elseif ($phase === 1): ?>
    <aside class="banner banner--warn">
        <h2>Phase 1 — review marks, then return to chat</h2>
        <p>
            Uncertain symbols and shorthand are highlighted. YouTube links show a green added descriptor.
            Reload this page after the skill updates <code>phase1.json</code>.
        </p>
        <ul>
            <li>In Cursor / Claude Code, explain any flagged shorthand (the skill updates <code>shorthands.md</code>).</li>
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
<?php else: ?>
    <aside class="banner">
        <h2>Phase 2 — organized notes</h2>
        <p>
            Main view is the organized result. Use the hamburger to open original notes with per-line accounting
            (what each line became).
        </p>
        <ul>
            <li>Return to Cursor / Claude Code to refine groupings or fix fidelity gaps.</li>
            <li>Example: “Merge t1 and t3” or “Line 12 should stay as reference.”</li>
        </ul>
    </aside>

    <main class="main organized">
        <?php if ($phase2 === null): ?>
            <p class="muted">
                <code>phase2.json</code> is missing. Return to Cursor / Claude Code and ask the skill to run Phase 2.
            </p>
        <?php else:
            $organized = $phase2['organized'] ?? [];
            if (!is_array($organized)) {
                $organized = [];
            }
            $tasks = $organized['tasks'] ?? [];
            $reference = $organized['reference'] ?? [];
            $articles = $organized['articleCandidates'] ?? [];
            if (!is_array($tasks)) {
                $tasks = [];
            }
            if (!is_array($reference)) {
                $reference = [];
            }
            if (!is_array($articles)) {
                $articles = [];
            }
            ?>
            <section class="section-block">
                <h2>Tasks</h2>
                <?php render_item_cards($tasks, 'No tasks yet.'); ?>
            </section>
            <section class="section-block">
                <h2>Reference</h2>
                <?php render_item_cards($reference, 'No reference items yet.'); ?>
            </section>
            <section class="section-block">
                <h2>Article candidates</h2>
                <?php render_item_cards($articles, 'No article candidates yet.'); ?>
            </section>
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
                            $toText = $to !== [] ? ' → ' . implode(', ', array_map('strval', $to)) : '';
                            ?>
                            <div class="blame-line">
                                <div class="blame-line__n"><?= $n > 0 ? $n : '' ?></div>
                                <div>
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

<script src="assets/app.js"></script>
</body>
</html>
