<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../backend/street-impact-csv-import.php';
require __DIR__.'/../backend/db.php';
if(!$pdo)exit(1);
function impactAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function impactReject(callable $call,string $message):void{try{$call();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Accepted '.$message);}
$original=[];
foreach(['typhoon_events','typhoon_street_impacts','pagasa_typhoon_imports'] as $table)$original[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
$test='equiaid_street_csv_test_'.bin2hex(random_bytes(5));
$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
 foreach(['typhoon_events','typhoon_street_impacts','pagasa_typhoon_imports','streets'] as $table)$pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `".DB_NAME."`.`{$table}`");
 $pdo->exec("INSERT INTO `{$test}`.streets SELECT * FROM `".DB_NAME."`.streets");
 $pdo->exec("USE `{$test}`");$pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES'");
 $csv=(string)file_get_contents(streetImpactCsvPath());
 impactReject(fn()=>validateStreetImpactCsv($pdo,$csv),'street impacts without imported PAGASA events');
 $catalog=(string)file_get_contents(pagasaCsvCatalogPath());
 pagasaCommitCsv($pdo,pagasaValidateTrustedCsv($catalog),'test.csv',hash('sha256',$catalog),null);
 $eventsBefore=$pdo->query('SELECT * FROM typhoon_events ORDER BY event_id')->fetchAll();
 $groups=validateStreetImpactCsv($pdo,$csv);
 impactAssert(count($groups)===50,'Expected 50 matched events.');
 impactAssert(count(array_unique(array_column(array_column($groups,'identity'),'event_id')))===50,'Event identities overlap.');
 $preview=streetImpactCsvPreview($pdo,$groups);
 impactAssert($preview['counts']['create']===500&&count($preview['rows'])===500,'500-row preview failed.');
 impactReject(fn()=>validateStreetImpactCsv($pdo,str_replace(',INDAY,2026,',',INDAY,2025,',$csv)),'wrong event year');
 impactReject(fn()=>validateStreetImpactCsv($pdo,str_replace(',INDAY,',',UNKNOWN,',$csv)),'unknown name');
 impactReject(fn()=>validateStreetImpactCsv($pdo,str_replace(',Sampaguita St,',',Wrong Street,',$csv)),'wrong street name');
 $lines=preg_split('/\r\n|\n|\r/',trim($csv));
 impactReject(fn()=>validateStreetImpactCsv($pdo,$csv.$lines[1]."\r\n"),'duplicate event/street rows');
 $saved=commitStreetImpactCsv($pdo,$groups,null);
 impactAssert($saved['created']===500&&$saved['events_created']===0,'Import created extra events or missed impacts.');
 impactAssert($pdo->query('SELECT * FROM typhoon_events ORDER BY event_id')->fetchAll()===$eventsBefore,'PAGASA event details changed.');
 $inday=$groups['IMPACT-2026-INDAY'];$indayId=$inday['identity']['event_id'];
 $indayRows=$pdo->query('SELECT * FROM typhoon_street_impacts WHERE event_id='.(int)$indayId.' ORDER BY street_id')->fetchAll();
 impactAssert(count($indayRows)===10,'INDAY selector must return exactly 10 imported street rows.');
 foreach($groups as $group){
  $query=$pdo->prepare('SELECT COUNT(*) AS n,SUM(affected_households) AS households,SUM(affected_persons) AS persons,SUM(flood_status<>"None") AS flooded FROM typhoon_street_impacts WHERE event_id=?');
  $query->execute([$group['identity']['event_id']]);$summary=$query->fetch();
  impactAssert((int)$summary['n']===10,'A selected event contains another event\'s streets.');
  impactAssert((int)$summary['households']===array_sum(array_column($group['rows'],'affected_households')),'Selected-event household totals mismatch.');
 }
 $again=commitStreetImpactCsv($pdo,$groups,null);
 impactAssert($again['skipped']===500&&$again['created']===0&&$again['revised']===0,'Repeat upload duplicated impacts.');
 $changed=['IMPACT-2026-INDAY'=>$inday];$changed['IMPACT-2026-INDAY']['rows'][0]['notes']='Updated INDAY assessment';
 $update=commitStreetImpactCsv($pdo,$changed,null);
 impactAssert($update['revised']===1&&$update['skipped']===9,'Changed INDAY record was not updated in place.');
 $unchangedId=$groups['IMPACT-2024-CARINA']['identity']['event_id'];
 $carinaBefore=$pdo->query('SELECT * FROM typhoon_street_impacts WHERE event_id='.(int)$unchangedId)->fetchAll();
 impactAssert(count($carinaBefore)===10,'CARINA rows lost during INDAY update.');
 $state=$pdo->query('SELECT * FROM typhoon_street_impacts ORDER BY impact_id')->fetchAll();
 $fault=$changed;$fault['IMPACT-2026-INDAY']['rows'][0]['notes']='Should roll back';$fault['IMPACT-2026-INDAY']['rows'][1]['affected_households']=-1;
 try{commitStreetImpactCsv($pdo,$fault,null);throw new RuntimeException('Invalid batch committed.');}catch(PDOException $expected){}
 impactAssert($pdo->query('SELECT * FROM typhoon_street_impacts ORDER BY impact_id')->fetchAll()===$state,'Failed batch left partial updates.');
 echo "Street impact tests passed: 50 actual typhoon identities / 500 rows, INDAY filtering, validation, updates, duplicates, rollback, and unchanged weather events.\n";
}finally{$pdo->exec('USE `'.DB_NAME.'`');$pdo->exec("DROP DATABASE `{$test}`");}
foreach($original as $table=>$count)impactAssert($count===(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(),'Application records changed during tests.');
echo "Temporary test database removed; application records preserved.\n";
