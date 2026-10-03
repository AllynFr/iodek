<?php

/**
 * Vérifie des fichiers de thème ioDek avec les règles du serveur (même ThemeParser, même liste de variables).
 * Checks ioDek theme files with the server rules (same ThemeParser, same variable list).
 *
 *   php scripts/check-theme.php themes/*.css
 *
 * Code de sortie 0 si tout est accepté, 1 sinon (CI GitHub). Exit code 0 if every file is accepted, 1 otherwise.
 */

require __DIR__.'/ThemeParser.php';

use App\Services\Dashboard\ThemeParser;

$files = array_slice($argv, 1);
if (! $files) {
    fwrite(STDERR, "Usage: php scripts/check-theme.php <theme.css>...\n");
    exit(2);
}

$parser = ThemeParser::fromFile(__DIR__.'/themes.json');
$failed = 0;
foreach ($files as $file) {
    $css = @file_get_contents($file);
    if ($css === false) {
        echo "✗ {$file}: unreadable file\n";
        $failed++;

        continue;
    }
    $r = $parser->parse($css);
    if ($r['errors']) {
        echo "✗ {$file}\n";
        foreach ($r['errors'] as $e) {
            echo '    '.ThemeParser::message($e)."\n";
        }
        $failed++;

        continue;
    }
    echo '✓ '.$file.' — '.($r['name'] ?? 'unnamed').', '.count($r['vars'])." variables\n";
}

exit($failed ? 1 : 0);
