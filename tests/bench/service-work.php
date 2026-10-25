<?php
// The PHP work of one search request once MySQL has answered, as in src/index.php: deduplicate
// the 40 UNION rows down to 20 places, drop match_rank, and encode the result as JSON.
// Prints the mean time per request in microseconds. Run by tests/bench/cpu.

$rows = [];
for ($i = 0; $i < 40; $i++) {
    $rows[] = [
        'geonameid' => 2657896 + intdiv($i, 2), 'name' => "Zürich $i", 'matched_name' => "Zurich $i",
        'latitude' => '47.3666700', 'longitude' => '8.5500000', 'country' => 'CH', 'state' => 'Zurich',
        'timezone' => 'Europe/Zurich', 'population' => 415367 - $i, 'match_rank' => $i % 3,
    ];
}

$n = 100000;
$start = hrtime(true);
for ($k = 0; $k < $n; $k++) {
    $seen = [];
    $cities = [];
    foreach ($rows as $row) {
        if (isset($seen[$row['geonameid']])) {
            continue;
        }
        $seen[$row['geonameid']] = true;
        unset($row['match_rank']);
        $cities[] = $row;
        if (count($cities) >= 20) {
            break;
        }
    }
    $out = json_encode(['success' => true, 'data' => $cities]);
}
printf("%.3f\n", (hrtime(true) - $start) / $n / 1000);
