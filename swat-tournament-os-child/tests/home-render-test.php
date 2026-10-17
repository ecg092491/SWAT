<?php
/** Render actual PHP templates with isolated fixture stubs; no WordPress claim. */
$fail=0;$count=0;
foreach(['open','limited','sold_out','closed','empty','live'] as $case){
 $cmd='SWAT_FIXTURE_ENABLED=1 SWAT_HOME_CASE='.escapeshellarg($case).' '.escapeshellarg(PHP_BINARY).' -d display_errors=1 '.escapeshellarg(__DIR__.'/browser/home-fixture.php');
 $html=shell_exec($cmd);$doc=new DOMDocument();@$doc->loadHTML($html);$xp=new DOMXPath($doc);
 $checks=[
 'no PHP notices'=>!preg_match('/(?:Warning|Notice|Fatal error|Deprecated):/',$html),
 'escaped source title'=>false===strpos($html,'<script>alert(1)</script>'),
 'hero direct registration'=>$xp->query('//div[contains(@class,"swh-hero__ctas")]/a[@href="https://play.swatourneys.com/"]')->length===1,
 'Game Day supports subdirectory'=>$xp->query('//div[contains(@class,"swh-hero__ctas")]/a[@href="/site/gameday/"]')->length===1,
 'all tournaments route'=>$xp->query('//a[@href="/site/tournaments/"]')->length>=1,
 'facility route'=>$xp->query('//a[@href="/site/fields-facilities/"]')->length>=1,
 'upcoming expanded'=>$xp->query('//*[@id="swh-upcoming-panel" and not(@hidden)]')->length===1,
 'banner only on active date'=>($xp->query('//a[contains(@class,"swh-live-banner")]')->length===1)===($case==='live'),
 ];
 if(in_array($case,['sold_out','closed'])){$checks['unavailable actions disabled']=$xp->query('//button[@disabled and normalize-space(.)="Registration unavailable"]')->length===3;$checks['no card register']=$xp->query('//article//a[@href="https://play.swatourneys.com/"]')->length===0;}
 if($case==='empty')$checks['empty state']=$xp->query('//*[contains(@class,"swh-empty")]')->length===1;
 foreach($checks as $name=>$ok){$count++;if(!$ok){$fail++;echo "FAIL $case: $name\n";}}
}
echo "$count home render checks, $fail failures\n";exit($fail?1:0);
