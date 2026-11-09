<?php
// Usage: php harness.php <label> <path> [strongs_off] [method=GET] [repeat=1]
// Runs the REAL HTTP kernel in-process against a COPY of the DB with all write paths redirected.
$scratch = __DIR__;
$over = [
  'DB_DATABASE' => "$scratch/dbcopy.sqlite",
  'SESSION_DRIVER' => 'array',
  'LOG_CHANNEL' => 'stderr',
  'VIEW_COMPILED_PATH' => "$scratch/views",
  'CACHE_STORE' => 'database',
  'QUEUE_CONNECTION' => 'database',
];
foreach ($over as $k => $v) { putenv("$k=$v"); $_ENV[$k] = $v; $_SERVER[$k] = $v; }
[$_, $label, $path] = $argv; $strongsOff = ($argv[3] ?? '') === 'strongs_off';
$base = '/var/www/personal/bible-app';
require "$base/vendor/autoload.php";
$app = require "$base/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (config('database.connections.sqlite.database') !== "$scratch/dbcopy.sqlite") { fwrite(STDERR, "REFUSING: DB not redirected\n"); exit(2); }
fwrite(STDERR, "session=".config("session.driver")." log=".config("logging.default")." view=".config("view.compiled")."\n"); if (config("session.driver") !== "array") { fwrite(STDERR, "REFUSING\n"); exit(2); }
$q = [];
Illuminate\Support\Facades\DB::listen(function ($e) use (&$q) { $q[] = ['sql' => $e->sql, 'b' => $e->bindings, 't' => $e->time]; });
$mk = function ($path, $cookies = []) { return Illuminate\Http\Request::create('http://bible.b8.co.nz' . $path, 'GET', [], $cookies, [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'perf-harness']); };
$cookies = [];
if ($strongsOff) {
  // prime a session, then flip show_strongs_numbers=false directly in the array handler
  $r0 = $kernel->handle($req0 = $mk($path)); $kernel->terminate($req0, $r0);
  foreach ($r0->headers->getCookies() as $c) $cookies[$c->getName()] = $c->getValue();
  $sid = app('encrypter')->decrypt($cookies[config('session.cookie')], false);
  $sid = Illuminate\Cookie\CookieValuePrefix::remove($sid);
  $h = app('session')->driver()->getHandler();
  $data = unserialize($h->read($sid)); $data['show_strongs_numbers'] = false; $h->write($sid, serialize($data));
  $q = [];
}
$t0 = microtime(true); $mem0 = memory_get_usage();
$req = $mk($path, $cookies);
$resp = $kernel->handle($req);
$kernel->terminate($req, $resp);
$wall = (microtime(true) - $t0) * 1000;
$shapes = [];
$dbt = 0; $cacheR = 0; $cacheW = 0; $writes = 0;
foreach ($q as $x) {
  $dbt += $x['t'];
  $s = preg_replace('/\s+/', ' ', trim($x['sql']));
  $isW = preg_match('/^(insert|update|delete|replace)/i', $s);
  if ($isW) $writes++;
  if (preg_match('/"cache"/', $s)) { $isW ? $cacheW++ : $cacheR++; }
  $key = $s;
  $shapes[$key] = ($shapes[$key] ?? ['n' => 0, 't' => 0, 'b' => $x['b']]);
  $shapes[$key]['n']++; $shapes[$key]['t'] += $x['t'];
}
printf("### %s %s -> HTTP %d, %d bytes, wall %.1fms (CLI, no opcache), queries=%d (writes=%d; cache reads=%d writes=%d), DB time %.2fms, distinct shapes=%d, peak mem %.1fMB\n",
  $label, $path, $resp->getStatusCode(), strlen($resp->getContent()), $wall, count($q), $writes, $cacheR, $cacheW, $dbt, count($shapes), memory_get_peak_usage(true)/1048576);
uasort($shapes, fn($a, $b) => $b['n'] <=> $a['n']);
foreach ($shapes as $s => $v) printf("  %3dx %7.2fms  %s  | b=%s\n", $v['n'], $v['t'], substr($s, 0, 230), substr(json_encode(array_map(fn($z) => is_string($z) && strlen($z) > 40 ? substr($z, 0, 40) . '…' : $z, $v['b'])), 0, 120));
file_put_contents("$scratch/shapes-" . preg_replace('/\W+/', '_', $label) . ".json", json_encode(array_map(fn($s, $v) => ['sql' => $s, 'b' => $v['b'], 'n' => $v['n']], array_keys($shapes), $shapes)));
if (getenv('DUMP_CACHE')) {
  $k = [];
  foreach ($q as $x) { if (preg_match('/"cache"/', $x['sql'])) { $op = preg_match('/^\s*insert/i', $x['sql']) ? 'W' : 'R'; $key = preg_replace('/(last_visited_page_)\w+/', '$1<sid>', (string)($op === 'W' ? $x['b'][1] : $x['b'][0])); $k["$op $key"] = ($k["$op $key"] ?? 0) + 1; } }
  arsort($k); foreach ($k as $kk => $n) printf("    cache %2dx %s\n", $n, $kk);
}
