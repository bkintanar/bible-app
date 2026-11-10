<?php

/*
 * Simulate BibleChapter::parseEnhancedVerseText (the Strong's-ON path) for every verse, read-only.
 * Uses the real class via composer autoload + reflection (no Laravel boot, no DB writes).
 * The Strong's-OFF path needs \DB (red_letter_text) so it is approximated: text column = enhanceVerseText(formatted_text)
 * emulated by calling DatabaseBibleReader::cleanOsisMarkup via reflection.
 *
 * Usage: php -d memory_limit=1G sim_render.php <app_root> out.jsonl [db_path]
 *   app_root: whose render CODE is simulated; db_path defaults to <app_root>/database/bible_app.sqlite
 */
$root = $argv[1];
$out = fopen($argv[2], 'w');
$dbPath = $argv[3] ?? ($root.'/database/bible_app.sqlite');
require $root.'/vendor/autoload.php';

$rc = new ReflectionClass(\App\Livewire\BibleChapter::class);
$comp = $rc->newInstanceWithoutConstructor();
$prop = $rc->getProperty('showStrongsNumbers');
$prop->setValue($comp, true);

$rr = new ReflectionClass(\App\Services\DatabaseBibleReader::class);
$reader = $rr->newInstanceWithoutConstructor();
$clean = $rr->getMethod('cleanOsisMarkup');

$pdo = new PDO('sqlite:file:'.$dbPath.'?mode=ro', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$st = $pdo->query('select id, osis_id, verse_number, text, formatted_text, original_xml from verses order by id');
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $offText = $clean->invoke($reader, $r['formatted_text'], true);       // what getVerses() puts in $verse['text']
    $offHtml = strip_tags($offText, '<em><strong><sup><sub><span>');       // Strong's-OFF first step (before applyRedLetterFormatting)
    $v = ['id' => $r['id'], 'osis_id' => $r['osis_id'], 'verse_number' => $r['verse_number'], 'text' => $offText, 'original_xml' => $r['original_xml']];
    $onHtml = $comp->parseEnhancedVerseText($v);
    $hasQ = (bool) preg_match('/<q[^>]*who="Jesus"[^>]*>/i', (string) $r['original_xml']);
    fwrite($out, json_encode(['id' => $r['osis_id'], 'vid' => (int) $r['id'], 'on' => $onHtml, 'off' => $offHtml, 'xml_has_q' => $hasQ], JSON_UNESCAPED_UNICODE)."\n");
}
fclose($out);
