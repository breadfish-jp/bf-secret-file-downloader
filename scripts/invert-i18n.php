#!/usr/bin/env php
<?php
/**
 * Invert WordPress i18n strings using a source .po (ja->en) to:
 *  - Update PHP files to use English strings in translation calls
 *  - Generate an inverted .po (en->ja)
 *
 * Usage examples:
 *  php scripts/invert-i18n.php --po languages/bf-secret-file-downloader-en_US.po --php inc/FrontEnd.php --dry-run
 *  php scripts/invert-i18n.php --po languages/bf-secret-file-downloader-en_US.po --all-php --out-po languages/bf-secret-file-downloader-ja.inverted.po
 */

declare(strict_types=1);

// --- CLI args parsing ------------------------------------------------------
function parse_args(array $argv): array {
    $opts = [
        'po' => null,
        'php' => [],
        'all-php' => false,
        'out-po' => null,
        'dry-run' => false,
        'verbose' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--dry-run') { $opts['dry-run'] = true; continue; }
        if ($arg === '--verbose') { $opts['verbose'] = true; continue; }
        if ($arg === '--all-php') { $opts['all-php'] = true; continue; }
        if ($arg === '--po' && isset($argv[$i+1])) { $opts['po'] = $argv[++$i]; continue; }
        if ($arg === '--php' && isset($argv[$i+1])) { $opts['php'][] = $argv[++$i]; continue; }
        if ($arg === '--out-po' && isset($argv[$i+1])) { $opts['out-po'] = $argv[++$i]; continue; }
        fwrite(STDERR, "Unknown or incomplete arg: {$arg}\n");
        exit(1);
    }

    if (!$opts['po']) {
        fwrite(STDERR, "--po <path/to/source_ja_to_en.po> is required\n");
        exit(1);
    }
    if (!is_file($opts['po'])) {
        fwrite(STDERR, "PO file not found: {$opts['po']}\n");
        exit(1);
    }
    if ($opts['all-php'] === false && empty($opts['php'])) {
        fwrite(STDERR, "Specify either --all-php or one/more --php <file>.\n");
        exit(1);
    }
    if ($opts['out-po'] === null) {
        // Default output .po next to input
        $opts['out-po'] = preg_replace('/\.po$/', '', $opts['po']) . '.en2ja.po';
    }
    return $opts;
}

// --- PO parsing ------------------------------------------------------------
class PoEntry {
    public ?string $context = null; // msgctxt
    public ?string $msgid = null;
    public ?string $msgidPlural = null;
    /** @var array<int,string> */
    public array $msgstrPlural = []; // [0] singular, [1] plural, ...
    public ?string $msgstr = null;   // singular
    public array $comments = [];
}

class PoFile {
    /** @var PoEntry[] */
    public array $entries = [];
    public array $headers = [];
}

function unquote_po_string(string $s): string {
    // Remove leading/ending quotes and unescape
    if (strlen($s) >= 2 && ($s[0] === '"' && substr($s, -1) === '"')) {
        $s = substr($s, 1, -1);
    }
    // Unescape standard PO escapes
    $s = str_replace(["\\n", "\\t", '\\r', '\\"', '\\' . '\\'], ["\n", "\t", "\r", '"', "\\"], $s);
    return $s;
}

function parse_po(string $path): PoFile {
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException("Failed to read PO: {$path}");
    }
    $po = new PoFile();
    $entry = null;
    $mode = null; // 'msgctxt'|'msgid'|'msgid_plural'|'msgstr'|'msgstr[n]'

    // Helpers to push completed entry
    $push = function () use (&$entry, &$po) {
        if ($entry !== null) {
            // Determine header vs normal entry
            if ($entry->msgid === "" && ($entry->msgstr !== null || isset($entry->msgstrPlural[0]))) {
                // Header entry: parse msgstr as headers
                $headerBlob = $entry->msgstr ?? $entry->msgstrPlural[0] ?? '';
                $headers = [];
                foreach (explode("\n", $headerBlob) as $line) {
                    if (strpos($line, ':') !== false) {
                        [$k, $v] = array_map('trim', explode(':', $line, 2));
                        $headers[$k] = $v;
                    }
                }
                $po->headers = $headers;
            } else {
                $po->entries[] = $entry;
            }
        }
        $entry = null;
    };

    // Accumulators for continued strings
    $acc = '';
    $accTarget = null;
    $accIndex = null;

    foreach ($lines as $raw) {
        $line = rtrim($raw, "\r\n");
        if ($line === '') {
            // blank line ends entry
            $push();
            $mode = null;
            $acc = '';
            $accTarget = null;
            $accIndex = null;
            continue;
        }
        if ($line[0] === '#') {
            if ($entry === null) $entry = new PoEntry();
            $entry->comments[] = $line;
            continue;
        }

        if (preg_match('/^msgctxt\s+(".*")$/', $line, $m)) {
            if ($entry === null) $entry = new PoEntry();
            $entry->context = unquote_po_string($m[1]);
            $mode = 'msgctxt';
            $acc = '';
            $accTarget = 'context';
            $accIndex = null;
            $entry->context = '';
            $entry->context .= unquote_po_string($m[1]);
            continue;
        }

        if (preg_match('/^msgid\s+(".*")$/', $line, $m)) {
            if ($entry === null) $entry = new PoEntry();
            $mode = 'msgid';
            $accTarget = 'msgid';
            $accIndex = null;
            $entry->msgid = '';
            $entry->msgid .= unquote_po_string($m[1]);
            continue;
        }
        if (preg_match('/^msgid_plural\s+(".*")$/', $line, $m)) {
            if ($entry === null) $entry = new PoEntry();
            $mode = 'msgid_plural';
            $accTarget = 'msgid_plural';
            $accIndex = null;
            $entry->msgidPlural = '';
            $entry->msgidPlural .= unquote_po_string($m[1]);
            continue;
        }
        if (preg_match('/^msgstr\[(\d+)\]\s+(".*")$/', $line, $m)) {
            if ($entry === null) $entry = new PoEntry();
            $mode = 'msgstr_plural';
            $accTarget = 'msgstr_plural';
            $accIndex = (int)$m[1];
            $entry->msgstrPlural[$accIndex] = '';
            $entry->msgstrPlural[$accIndex] .= unquote_po_string($m[2]);
            continue;
        }
        if (preg_match('/^msgstr\s+(".*")$/', $line, $m)) {
            if ($entry === null) $entry = new PoEntry();
            $mode = 'msgstr';
            $accTarget = 'msgstr';
            $accIndex = null;
            $entry->msgstr = '';
            $entry->msgstr .= unquote_po_string($m[1]);
            continue;
        }

        // Continued string lines
        if (preg_match('/^(".*")$/', $line, $m)) {
            $val = unquote_po_string($m[1]);
            if ($entry === null || $accTarget === null) {
                // Should not happen
                continue;
            }
            if ($accTarget === 'msgid') {
                $entry->msgid .= $val;
            } elseif ($accTarget === 'msgid_plural' && $entry->msgidPlural !== null) {
                $entry->msgidPlural .= $val;
            } elseif ($accTarget === 'msgstr' && $entry->msgstr !== null) {
                $entry->msgstr .= $val;
            } elseif ($accTarget === 'msgstr_plural' && $accIndex !== null) {
                $entry->msgstrPlural[$accIndex] .= $val;
            } elseif ($accTarget === 'context' && $entry->context !== null) {
                $entry->context .= $val;
            }
            continue;
        }

        // Unknown line: treat as separator
        $push();
        $mode = null;
        $acc = '';
        $accTarget = null;
        $accIndex = null;
    }
    // push last
    $push();
    return $po;
}

// --- Mapping build ---------------------------------------------------------
/**
 * Build mappings from a ja->en PO file
 * @return array{sing: array<string,string>, plural: array<string,array{ja_sing:string,ja_plur:string,en_sing:string,en_plur:string}>}
 */
function build_mapping(PoFile $po, bool $verbose = false): array {
    $sing = [];
    $plural = [];
    foreach ($po->entries as $e) {
        // Skip empty or untranslated
        if ($e->msgid === null) continue;
        $ja = $e->msgid;
        if ($e->msgidPlural !== null) {
            // Plural entry
            $jaPlural = $e->msgidPlural;
            $enSing = $e->msgstrPlural[0] ?? '';
            $enPlur = $e->msgstrPlural[1] ?? ($e->msgstrPlural[0] ?? '');
            $plural[$ja] = [
                'ja_sing' => $ja,
                'ja_plur' => $jaPlural,
                'en_sing' => $enSing,
                'en_plur' => $enPlur,
            ];
            if ($verbose) {
                fwrite(STDERR, "[plural] {$ja} | {$jaPlural} => {$enSing} | {$enPlur}\n");
            }
        } else {
            $en = $e->msgstr ?? '';
            $sing[$ja] = $en;
            if ($verbose) {
                fwrite(STDERR, "[sing] {$ja} => {$en}\n");
            }
        }
    }
    return ['sing' => $sing, 'plural' => $plural];
}

// --- PHP file transformation ----------------------------------------------
function php_escape_string(string $s, string $quote): string {
    if ($quote === "'") {
        // Escape for single-quoted PHP string
        $s = str_replace(["\\", "'"], ["\\\\", "\\'"], $s);
        return $s;
    }
    // Escape for double-quoted PHP string (also escape control chars)
    $s = str_replace(["\\", '"'], ["\\\\", '\\"'], $s);
    $s = str_replace(["\n", "\r", "\t"], ["\\n", "\\r", "\\t"], $s);
    return $s;
}

function transform_php_file(string $path, array $map, array $mapPlural, bool $dryRun = false): array {
    $code = file_get_contents($path);
    if ($code === false) throw new RuntimeException("Failed to read: {$path}");

    $orig = $code;
    $changes = [];

    // 1) __/_e/esc_* variants (single string)
    $funcs = ['__', '_e', 'esc_html__', 'esc_attr__', 'esc_html_e', 'esc_attr_e'];
    $funcPattern = implode('|', array_map('preg_quote', $funcs));
    $code = preg_replace_callback(
        '/\b(' . $funcPattern . ')\s*\(\s*([\"\'])((?:\\\\.|(?!\2).)*)\2/sU',
        function ($m) use ($map, &$changes, $path) {
            $func = $m[1];
            $quote = $m[2];
            $raw = $m[3];
            // Unescape roughly
            $ja = $quote === "'" ? str_replace(['\\\'','\\\\'], ["'",'\\'], $raw) : stripcslashes($raw);
            if (!array_key_exists($ja, $map) || $map[$ja] === '') {
                return $m[0];
            }
            $en = $map[$ja];
            $repl = $func . '(' . $quote . php_escape_string($en, $quote) . $quote;
            // keep the rest of call (params after first literal)
            $tail = substr($m[0], strpos($m[0], $quote.$raw.$quote) + strlen($quote.$raw.$quote));
            $changes[] = [
                'file' => $path,
                'func' => $func,
                'from' => $ja,
                'to' => $en,
            ];
            return $repl . $tail;
        },
        $code,
        -1,
        $count1
    );

    // 2) _n(sing, plur, ...)
    $code = preg_replace_callback(
        '/\b_n\s*\(\s*([\"\'])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\"\'])((?:\\\\.|(?!\3).)*)\3/sU',
        function ($m) use ($mapPlural, &$changes, $path) {
            $q1 = $m[1]; $s1 = $m[2];
            $q2 = $m[3]; $s2 = $m[4];
            $jaSing = $q1 === "'" ? str_replace(['\\\'','\\\\'], ["'",'\\'], $s1) : stripcslashes($s1);
            $jaPlur = $q2 === "'" ? str_replace(['\\\'','\\\\'], ["'",'\\'], $s2) : stripcslashes($s2);
            if (!array_key_exists($jaSing, $mapPlural)) {
                return $m[0];
            }
            $pair = $mapPlural[$jaSing];
            $enSing = $pair['en_sing'] ?? '';
            $enPlur = $pair['en_plur'] ?? '';
            if ($enSing === '' && $enPlur === '') return $m[0];
            $prefix = substr($m[0], 0, strpos($m[0], $m[1].$m[2].$m[1]));
            $tailPos = strpos($m[0], $m[3].$m[4].$m[3]);
            $tail = substr($m[0], $tailPos + strlen($m[3].$m[4].$m[3]));
            $repl = '_n(' . $q1 . php_escape_string($enSing, $q1) . $q1 . ', ' . $q2 . php_escape_string($enPlur, $q2) . $q2;
            $changes[] = [
                'file' => $path,
                'func' => '_n',
                'from' => $jaSing . ' | ' . $jaPlur,
                'to' => $enSing . ' | ' . $enPlur,
            ];
            return $repl . $tail;
        },
        $code,
        -1,
        $count2
    );

    if (!$dryRun && $code !== $orig) {
        file_put_contents($path, $code);
    }

    return [
        'changed' => $code !== $orig,
        'count_single' => $count1 ?? 0,
        'count_plural' => $count2 ?? 0,
        'changes' => $changes,
    ];
}

// --- Generate inverted PO (en->ja) ----------------------------------------
function po_escape(string $s): string {
    $s = str_replace(["\\", '"', "\n", "\r", "\t"], ["\\\\", '\\"', "\\n", "\\r", "\\t"], $s);
    return '"' . $s . '"';
}

function write_inverted_po(string $outPath, PoFile $src, array $map, array $mapPlural): void {
    $lines = [];
    $lines[] = 'msgid ""';
    $lines[] = 'msgstr ""';
    $headers = [
        'Project-Id-Version' => ($src->headers['Project-Id-Version'] ?? 'BF Secret File Downloader') . ' inverted',
        'POT-Creation-Date' => date('c'),
        'PO-Revision-Date' => date('c'),
        'Last-Translator' => '',
        'Language-Team' => 'Japanese',
        'Language' => 'ja',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
        'X-Domain' => $src->headers['X-Domain'] ?? '',
        'Plural-Forms' => 'nplurals=1; plural=0;',
    ];
    foreach ($headers as $k => $v) {
        $lines[] = po_escape("{$k}: {$v}\n");
    }
    $lines[] = '';

    // Singular entries (deduplicate by English msgid)
    $seenSing = [];
    foreach ($map as $ja => $en) {
        if ($en === '') continue;
        if (isset($seenSing[$en])) continue;
        $seenSing[$en] = $ja;
    }
    foreach ($seenSing as $en => $ja) {
        $lines[] = 'msgid ' . po_escape($en);
        $lines[] = 'msgstr ' . po_escape($ja);
        $lines[] = '';
    }
    // Plural entries (deduplicate by English pair)
    $seenPlu = [];
    foreach ($mapPlural as $jaSing => $pair) {
        $enSing = $pair['en_sing'] ?? '';
        $enPlur = $pair['en_plur'] ?? '';
        $jaSingStr = $pair['ja_sing'] ?? '';
        if ($enSing === '') continue;
        $key = $enSing . "\0" . $enPlur;
        if (isset($seenPlu[$key])) continue;
        $seenPlu[$key] = [$enSing, $enPlur, $jaSingStr];
    }
    foreach ($seenPlu as [$enSing, $enPlur, $jaSingStr]) {
        $lines[] = 'msgid ' . po_escape($enSing);
        if ($enPlur !== '') {
            $lines[] = 'msgid_plural ' . po_escape($enPlur);
        }
        $lines[] = 'msgstr[0] ' . po_escape($jaSingStr);
        $lines[] = '';
    }

    file_put_contents($outPath, implode("\n", $lines));
}

// --- Main ------------------------------------------------------------------
if (php_sapi_name() === 'cli') {
    $opts = parse_args($argv);
    $po = parse_po($opts['po']);
    $maps = build_mapping($po, $opts['verbose']);
    $map = $maps['sing'];
    $mapPlural = $maps['plural'];

    // Choose files to process
    $files = [];
    if ($opts['all-php']) {
        // find all .php under cwd
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(getcwd()));
        foreach ($it as $f) {
            if ($f->isFile() && preg_match('/\.php$/', $f->getFilename())) {
                $files[] = $f->getPathname();
            }
        }
    } else {
        foreach ($opts['php'] as $p) {
            if (is_dir($p)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p));
                foreach ($it as $f) {
                    if ($f->isFile() && preg_match('/\.php$/', $f->getFilename())) {
                        $files[] = $f->getPathname();
                    }
                }
            } elseif (is_file($p)) {
                $files[] = $p;
            } else {
                fwrite(STDERR, "Path not found: {$p}\n");
            }
        }
    }

    $summary = [
        'files' => 0,
        'changed' => 0,
        'single' => 0,
        'plural' => 0,
        'detail' => [],
    ];

    foreach ($files as $file) {
        $summary['files']++;
        $res = transform_php_file($file, $map, $mapPlural, $opts['dry-run']);
        if ($res['changed']) $summary['changed']++;
        $summary['single'] += $res['count_single'];
        $summary['plural'] += $res['count_plural'];
        $summary['detail'][] = [$file, $res['changed'], $res['count_single'], $res['count_plural']];
    }

    // Write inverted PO
    write_inverted_po($opts['out-po'], $po, $map, $mapPlural);

    // Report
    fwrite(STDOUT, "Converted files: {$summary['changed']} / {$summary['files']}\n");
    fwrite(STDOUT, "Replacements => single: {$summary['single']}, plural: {$summary['plural']}\n");
    fwrite(STDOUT, "Wrote inverted PO: {$opts['out-po']}\n");
    if ($opts['dry-run']) {
        fwrite(STDOUT, "(dry-run) No files were modified.\n");
    }
}
