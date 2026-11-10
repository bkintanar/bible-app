<?php

// Replicates VerseTitleManager::saveTitle() transform (app/Livewire/VerseTitleManager.php:451-467)
// on a tiny in-memory fixture. Touches no repo file.
$xml = '<chapter osisID="Gen.1"><verse osisID="Gen.1.1" sID="Gen.1.1"/>In the beginning<verse eID="Gen.1.1"/></chapter>';
$verseOsisId = 'Gen.1.1';

foreach (['Plain title', 'Costs $10 today', 'Path C:\\1\\x', '$1', '${0}${0}'] as $title) {
    $versePattern = '/(<verse osisID="'.preg_quote($verseOsisId, '/').'"[^>]*sID="[^"]*"[^>]*>)/';
    $replacement = '<title type="verse" canonical="false">'.htmlspecialchars($title).'</title>$1';
    $out = preg_replace($versePattern, $replacement, $xml);
    $r = new XMLReader;
    $r->XML($out);
    libxml_use_internal_errors(true);
    while (@$r->read()) {
    } $wf = ! array_filter(libxml_get_errors(), fn ($e) => $e->level === LIBXML_ERR_FATAL);
    libxml_clear_errors();
    preg_match('/<title[^>]*>(.*?)<\/title>/', $out, $m);
    printf("%-18s -> title body: %-70s well-formed=%s sID-count=%d\n", json_encode($title), $m[1] ?? '(none)', $wf ? 'yes' : 'no', substr_count($out, 'sID="Gen.1.1"'));
}
