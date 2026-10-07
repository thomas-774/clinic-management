<?php

/**
 * NFR-Q.1 (T11-11): line coverage of one directory from a Clover report, so
 * `composer check` runs the suite once and still holds app/Services to a
 * higher bar than the whole app (Pest's --min checks the total).
 *
 *   php scripts/check-coverage.php build/coverage/clover.xml app/Services 95
 */
[$script, $report, $directory, $min] = $argv + [null, null, null, null];

if (! $report || ! $directory || ! is_numeric($min)) {
    fwrite(STDERR, "Usage: php {$script} <clover.xml> <directory> <min %>\n");
    exit(2);
}

if (! is_file($report)) {
    fwrite(STDERR, "No coverage report at {$report}: run Pest with --coverage-clover first.\n");
    exit(2);
}

$root = str_replace('\\', '/', realpath(__DIR__.'/..')).'/';
$prefix = trim(str_replace('\\', '/', $directory), '/').'/';
$statements = 0;
$covered = 0;

foreach (simplexml_load_file($report)->xpath('//file') as $file) {
    $path = str_replace('\\', '/', (string) $file['name']);
    if (! str_starts_with(str_replace($root, '', $path), $prefix)) {
        continue;
    }
    $statements += (int) $file->metrics['statements'];
    $covered += (int) $file->metrics['coveredstatements'];
}

if ($statements === 0) {
    fwrite(STDERR, "No covered files under {$directory} in {$report}.\n");
    exit(1);
}

$percent = floor($covered / $statements * 1000) / 10;
$ok = $percent >= (float) $min;
fwrite($ok ? STDOUT : STDERR, sprintf(
    "%s line coverage: %.1f %% (%d / %d lines, minimum %s %%)%s\n",
    $directory, $percent, $covered, $statements, $min, $ok ? '' : ' — below the minimum',
));

exit($ok ? 0 : 1);
