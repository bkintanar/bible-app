<?php

$pdo = new PDO('sqlite:'.__DIR__.'/dbcopy.sqlite', null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$seen = [];
foreach (glob(__DIR__.'/shapes-*.json') as $f) {
    foreach (json_decode(file_get_contents($f), true) as $s) {
        $norm = preg_replace('/in \([\d, ]+\)/', 'in (…)', $s['sql']);
        if (isset($seen[$norm]) || preg_match('/^(insert|update|delete)/i', $s['sql'])) {
            continue;
        }
        $seen[$norm] = 1;
        $st = $pdo->prepare('EXPLAIN QUERY PLAN '.$s['sql']);
        $b = array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $s['b']);
        $st->execute($b);
        $plan = implode(' ; ', array_map(fn ($r) => $r['detail'], $st->fetchAll(PDO::FETCH_ASSOC)));
        $flag = preg_match('/\bSCAN (?!.*VIRTUAL)/', $plan) ? 'SCAN' : 'ok  ';
        // time it
        $t = microtime(true);
        for ($i = 0; $i < 20; $i++) {
            $x = $pdo->prepare($s['sql']);
            $x->execute($b);
            $x->fetchAll();
        } $ms = (microtime(true) - $t) * 1000 / 20;
        printf("[%s] %6.3fms  %s\n        PLAN: %s\n", $flag, $ms, substr($norm, 0, 170), $plan);
    }
}
