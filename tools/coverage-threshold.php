<?php

declare(strict_types=1);

/**
 * Fail if line coverage falls below a floor.
 *
 * PHPUnit reports coverage but has no option to fail on it, so CI would happily
 * stay green while coverage fell. This reads the Clover report and exits
 * non-zero below the threshold.
 *
 * Usage: php tools/coverage-threshold.php <clover.xml> <minimum-percent>
 */

$reportPath = $argv[1] ?? null;
$minimum = isset($argv[2]) ? (float)$argv[2] : null;

if ($reportPath === null || $minimum === null) {
    fwrite(STDERR, "Usage: php tools/coverage-threshold.php <clover.xml> <minimum-percent>\n");
    exit(2);
}

if (!is_file($reportPath)) {
    fwrite(STDERR, "Coverage report not found: $reportPath\n");
    exit(2);
}

$xml = @simplexml_load_file($reportPath);

if ($xml === false) {
    fwrite(STDERR, "Could not parse the coverage report: $reportPath\n");
    exit(2);
}

$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    fwrite(STDERR, "No <project><metrics> element in: $reportPath\n");
    exit(2);
}

$statements = (int)$metrics['statements'];
$covered = (int)$metrics['coveredstatements'];

if ($statements === 0) {
    fwrite(STDERR, "The coverage report contains no statements — was a driver loaded?\n");
    exit(2);
}

$percent = $covered / $statements * 100;

printf("Line coverage: %.2f%% (%d/%d statements), floor %.2f%%\n", $percent, $covered, $statements, $minimum);

// Compared on the printed value so a report reading exactly at the floor is not
// failed by a difference in the digits beyond it.
if (round($percent, 2) + 1e-9 < $minimum) {
    fwrite(STDERR, sprintf("Coverage %.2f%% is below the %.2f%% floor.\n", $percent, $minimum));
    exit(1);
}

echo "Coverage floor met.\n";
