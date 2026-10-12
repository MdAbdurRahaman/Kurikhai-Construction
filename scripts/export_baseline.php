<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../includes/baseline.php';
$o=getopt('',['snapshot:','format:','output:']);
try{$s=baseline_snapshot(isset($o['snapshot'])?(int)$o['snapshot']:null);if(!$s)throw new RuntimeException('No captured snapshot. Capture one through the authorized admin first.');$text=baseline_report($s,($o['format']??'csv')==='csv'?'csv':'markdown');if(isset($o['output'])){if(file_put_contents($o['output'],$text)===false)throw new RuntimeException('Unable to write report.');}else echo$text;}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
