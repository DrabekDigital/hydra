#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Integration test runner for examples/.
 *
 * Usage:
 *   php bin/test-examples.php              # compare all examples against .out snapshots
 *   php bin/test-examples.php --update     # regenerate all .out snapshot files
 *   php bin/test-examples.php 07           # filter by name fragment
 *   php bin/test-examples.php --update 07  # update only matching examples
 */

$update = false;
$filter = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--update' || $arg === '-u') {
        $update = true;
    } else {
        $filter = $arg;
    }
}

$examplesDir = dirname(__DIR__) . '/examples';
$phpFiles    = glob($examplesDir . '/[0-9]*.php');
sort($phpFiles);

if (empty($phpFiles)) {
    echo "No example files found in {$examplesDir}\n";
    exit(1);
}

$passed  = 0;
$failed  = 0;
$updated = 0;

foreach ($phpFiles as $phpFile) {
    $name    = basename($phpFile, '.php');
    $outFile = $examplesDir . '/' . $name . '.out';

    if ($filter !== null && !str_contains($name, $filter)) {
        continue;
    }

    $actual = runExample($phpFile);

    if ($update || !file_exists($outFile)) {
        file_put_contents($outFile, $actual);
        echo "  UPDATE  {$name}\n";
        ++$updated;
        continue;
    }

    $expected = file_get_contents($outFile);

    if ($actual === $expected) {
        echo "  PASS    {$name}\n";
        ++$passed;
    } else {
        echo "  FAIL    {$name}\n";
        echo showDiff($expected, $actual, $name);
        ++$failed;
    }
}

echo "\n";

if ($update) {
    echo "Snapshots updated: {$updated}.\n";
} else {
    $total = $passed + $failed;
    echo "{$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . ".\n";
}

exit($failed > 0 ? 1 : 0);

// ─────────────────────────────────────────────────────────────────────────────

function runExample(string $phpFile): string
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open([PHP_BINARY, $phpFile], $descriptors, $pipes);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $output = $stdout;
    if ($stderr !== '') {
        $output .= "\n--- STDERR ---\n" . $stderr;
    }

    return $output;
}

function showDiff(string $expected, string $actual, string $name): string
{
    $tmpExpected = tempnam(sys_get_temp_dir(), 'hydra_exp_');
    $tmpActual   = tempnam(sys_get_temp_dir(), 'hydra_act_');

    file_put_contents($tmpExpected, $expected);
    file_put_contents($tmpActual, $actual);

    $diff = shell_exec(
        'diff -u '
        . escapeshellarg($tmpExpected) . ' '
        . escapeshellarg($tmpActual)
        . ' 2>/dev/null'
    );

    unlink($tmpExpected);
    unlink($tmpActual);

    if ($diff === null || $diff === '') {
        return '';
    }

    // Strip the temp file paths from the diff header, replace with readable labels
    $lines = explode("\n", $diff);
    foreach ($lines as $i => $line) {
        if (str_starts_with($line, '---')) {
            $lines[$i] = "--- {$name}.out (expected)";
        } elseif (str_starts_with($line, '+++')) {
            $lines[$i] = "+++ {$name}.out (actual)";
        }
    }

    return implode("\n", $lines) . "\n";
}
