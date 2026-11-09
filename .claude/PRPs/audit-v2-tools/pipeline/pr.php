<?php
$xml='<verse osisID="Gen.1.1" sID="Gen.1.1"/>';
$title='Cost $0 and \\1 &amp;';
$rep='<title type="verse" canonical="false">'.htmlspecialchars($title).'</title>$1';
echo preg_replace('/(<verse osisID="Gen.1.1"[^>]*sID="[^"]*"[^>]*>)/',$rep,$xml),"\n";
echo htmlspecialchars("a&amp;b \x01 ]]> 'q'"),"\n";
