<?php

$t = microtime(true);
$d = new DOMDocument;
$d->preserveWhiteSpace = false;
$d->formatOutput = true;
$d->load('/var/www/personal/bible-app/assets/kjv.osis.xml');
$x = new DOMXPath($d);
$x->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');
$a = $x->query('//osis:chapter[@osisID]')->length;
$b = $x->query('//osis:verse[@osisID][@sID]')->length;
printf("chap=%d verses=%d t=%.2fs peakphp=%dMB\n", $a, $b, microtime(true) - $t, memory_get_peak_usage() / 1e6);
echo 'rss_kb=',trim(shell_exec('grep VmHWM /proc/'.getmypid().'/status')),"\n";
