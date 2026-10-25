<?php
// Summarizes a CSV from tests/bench/http or tests/bench/cpu. Per PHP version it prints the
// median of the rounds and the change against the first version. For HTTP, the spread between
// rounds is the median over versions of (slowest - fastest) / median. For CPU, every version is
// compared with the first one within each round, which cancels background load that changes
// between rounds. Run it with tests/bench/summarize.

if ($argc !== 2) {
    fwrite(STDERR, "usage: php summarize.php <results.csv>\n");
    exit(2);
}

$handle = fopen($argv[1], 'r');
if ($handle === false) {
    exit(1);
}
$header = fgetcsv($handle, null, ',', '"', '');
$rows = [];
while (($line = fgetcsv($handle, null, ',', '"', '')) !== false) {
    if ($line !== [null]) {
        $rows[] = array_combine($header, $line);
    }
}

function median(array $xs): float
{
    sort($xs);
    $mid = intdiv(count($xs), 2);
    return count($xs) % 2 === 1 ? $xs[$mid] : ($xs[$mid - 1] + $xs[$mid]) / 2;
}

function spread(array $xs): float
{
    return (max($xs) - min($xs)) / median($xs);
}

function change(float $value, float $base): string
{
    // Adding 0.0 turns a rounded -0 into +0.
    return sprintf('%+.0f%%', round(($value / $base - 1) * 100) + 0.0);
}

/** Collects one column as floats, grouped by the values of the key columns joined with "|". */
function group(array $rows, array $keys, string $column): array
{
    $groups = [];
    foreach ($rows as $row) {
        $groups[implode('|', array_map(fn ($k) => $row[$k], $keys))][] = (float) $row[$column];
    }
    return $groups;
}

function row(string $label, array $cells): string
{
    return str_pad($label, 16) . implode('', array_map(fn ($c) => str_pad($c, 21, ' ', STR_PAD_LEFT), $cells)) . "\n";
}

function summarizeHttp(array $rows, array $versions, int $rounds): void
{
    $base = $versions[0];
    $endpoints = array_values(array_unique(array_column($rows, 'endpoint')));
    $caches = array_values(array_unique(array_column($rows, 'cache')));
    $rps = group($rows, ['cache', 'endpoint', 'php'], 'rps');
    $meanMs = group($rows, ['cache', 'endpoint', 'php'], 'mean_ms');

    foreach ($caches as $cache) {
        $label = $cache === '0' ? 'cache off' : "APCu cache on (CACHE_TTL=$cache)";
        echo "\n== Requests per second, $label: median of $rounds rounds (slowest-fastest)\n";
        echo row('request', array_map(fn ($v) => "PHP $v", $versions));
        $logChange = array_fill_keys($versions, 0.0);
        $spreads = [];
        $means = [];
        foreach ($endpoints as $e) {
            $cells = [];
            foreach ($versions as $v) {
                $xs = $rps["$cache|$e|$v"];
                $cells[] = sprintf('%.0f (%.0f-%.0f)', median($xs), min($xs), max($xs));
                $logChange[$v] += log(median($xs) / median($rps["$cache|$e|$base"]));
                $spreads[] = spread($xs);
                $means[] = median($meanMs["$cache|$e|$v"]);
            }
            echo row($e, $cells);
        }
        echo row("vs PHP $base", array_map(fn ($v) => change(exp($logChange[$v] / count($endpoints)), 1), $versions));
        printf(
            "(geometric mean over the requests)\nspread between rounds %.0f%%, mean time per request %.1f to %.1f ms\n",
            median($spreads) * 100,
            min($means),
            max($means)
        );
    }

    foreach ($caches as $cache) {
        if ($cache === '0' || !in_array('0', $caches, true)) {
            continue;
        }
        echo "\n== APCu cache speed-up: requests per second with CACHE_TTL=$cache against cache off\n";
        echo row('request', array_map(fn ($v) => "PHP $v", $versions));
        foreach ($endpoints as $e) {
            echo row($e, array_map(
                fn ($v) => sprintf('%.1fx', median($rps["$cache|$e|$v"]) / median($rps["0|$e|$v"])),
                $versions
            ));
        }
    }

    printf("\nfailed requests: %d\n", array_sum(array_map('intval', array_column($rows, 'failed'))));
}

function summarizeCpu(array $rows, array $versions, int $rounds): void
{
    $base = $versions[0];
    $jits = array_values(array_unique(array_column($rows, 'jit')));
    $metrics = [
        'micro_s' => 'Zend/micro_bench.php total in s',
        'bench_s' => 'Zend/bench.php total in s',
        'service_us' => 'service request work in us',
    ];

    $roundIds = array_values(array_unique(array_column($rows, 'round')));

    foreach ($metrics as $column => $label) {
        $data = group($rows, ['jit', 'php'], $column);
        $byRound = group($rows, ['jit', 'php', 'round'], $column);
        echo "\n== $label, lower is faster, $rounds rounds\n";
        echo "PHP $base: median. Others: median change against PHP $base within a round (rounds faster)\n";
        echo row('JIT', array_map(fn ($v) => "PHP $v", $versions));
        foreach ($jits as $jit) {
            $cells = [sprintf('%.3f', median($data["$jit|$base"]))];
            foreach (array_slice($versions, 1) as $v) {
                $ratios = array_map(fn ($r) => $byRound["$jit|$v|$r"][0] / $byRound["$jit|$base|$r"][0], $roundIds);
                $faster = count(array_filter($ratios, fn ($x) => $x < 1));
                $cells[] = sprintf('%s (%d/%d)', change(median($ratios), 1), $faster, count($ratios));
            }
            echo row($jit, $cells);
        }
        if (isset($data["off|$base"], $data["tracing|$base"])) {
            echo row('JIT gain', array_map(
                fn ($v) => sprintf('%.2fx', median($data["off|$v"]) / median($data["tracing|$v"])),
                $versions
            ));
        }
    }
}

$versions = array_values(array_unique(array_column($rows, 'php')));
usort($versions, 'version_compare');
$rounds = count(array_unique(array_column($rows, 'round')));

if (in_array('endpoint', $header, true)) {
    summarizeHttp($rows, $versions, $rounds);
} elseif (in_array('jit', $header, true)) {
    summarizeCpu($rows, $versions, $rounds);
} else {
    fwrite(STDERR, "not a results file from tests/bench/http or tests/bench/cpu\n");
    exit(1);
}
