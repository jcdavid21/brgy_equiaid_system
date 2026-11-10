<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../backend/demo-impact-import.php';
require __DIR__.'/../backend/db.php';
if(!$pdo)exit(1);
function demoAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function demoReject(callable $call,string $message):void{try{$call();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Accepted '.$message);}
$original=[];
foreach(['typhoon_events','typhoon_street_impacts','pagasa_typhoon_imports'] as $table)$original[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
$test='equiaid_demo_test_'.bin2hex(random_bytes(5));
$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
 foreach(['typhoon_events','typhoon_street_impacts','streets'] as $table)$pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `".DB_NAME."`.`{$table}`");
 $pdo->exec("INSERT INTO `{$test}`.streets SELECT * FROM `".DB_NAME."`.streets");
 $pdo->exec("USE `{$test}`");$pdo->exec(file_get_contents(__DIR__.'/../database/20261110_add_demo_impacts.sql'));
 $pdo->exec("INSERT INTO typhoon_events(event_name,local_name,date_started,status) VALUES ('Official existing event','NANDO','2025-09-17','Passed')");
 $csv=file_get_contents(demoImpactPath());$groups=validateDemoImpacts($pdo,$csv);
 demoAssert(count($groups)===5&&array_sum(array_map(fn($g)=>count($g['rows']),$groups))===50,'Expected 5 scenarios / 50 impact rows.');
 demoReject(fn()=>validateDemoImpacts($pdo,str_replace('SYNTHETIC','ACTUAL_SOURCE_EXTRACT',$csv)),'non-demo labels');
 demoReject(fn()=>validateDemoImpacts($pdo,str_replace(',Sampaguita St,',',Fake St,',$csv)),'unknown street');
 demoReject(fn()=>validateDemoImpacts($pdo,str_replace(',Bagong Silang,',',Other Barangay,',$csv)),'outside location');
 demoReject(fn()=>validateDemoImpacts($pdo,str_replace(',2026-06-14,',',2026-06-01,',$csv)),'inconsistent dates');
 demoReject(fn()=>validateDemoImpacts($pdo,str_replace(',85,1,',',999,1,',$csv)),'out-of-range wind');
 demoReject(fn()=>validateDemoImpacts($pdo,file_get_contents(pagasaCsvCatalogPath())),'official CSV through demo route');
 $preview=demoImpactPreview($pdo,$groups);demoAssert($preview['counts']['create']===50,'Preview missing rows.');
 demoAssert(count($preview['events'])===5,'Per-event dashboard preview missing.');
 demoAssert(array_column($preview['events'],'flooded_streets')===[4,6,7,8,8],'Preview flood totals do not match the sample scenarios.');
 demoAssert(array_column($preview['events'],'damaged_streets')===[0,0,1,3,5],'Preview damage totals do not match dashboard rules.');
 demoAssert(array_column($preview['events'],'inaccessible_streets')===[0,1,3,4,5],'Preview closed-road totals are incorrect.');
 demoAssert(count(array_unique(array_column($preview['events'],'affected_households')))===5,'Scenario household totals must differ.');
 $fault=$groups;$key=array_key_first($fault);$fault[$key]['rows'][1]['street_id']=$fault[$key]['rows'][0]['street_id'];
 try{commitDemoImpacts($pdo,$fault,null);throw new RuntimeException('Fault injection succeeded.');}catch(PDOException $expected){}
 demoAssert((int)$pdo->query('SELECT COUNT(*) FROM typhoon_demo_imports')->fetchColumn()===0,'Failed batch left demo metadata.');
 demoAssert((int)$pdo->query('SELECT COUNT(*) FROM typhoon_events')->fetchColumn()===1,'Failed batch left events.');
 $result=commitDemoImpacts($pdo,$groups,null);
 demoAssert($result['created']===50&&$result['events_created']===5,'Import totals wrong.');
 demoAssert((int)$pdo->query("SELECT COUNT(*) FROM typhoon_street_impacts WHERE report_source='Demo'")->fetchColumn()===50,'Synthetic source markers missing.');
 demoAssert((int)$pdo->query("SELECT COUNT(*) FROM typhoon_events WHERE event_name='Official existing event'")->fetchColumn()===1,'Official event overwritten.');
 $again=commitDemoImpacts($pdo,$groups,null);demoAssert($again['created']===0&&$again['skipped']===50,'Repeat import duplicated data.');
 foreach($pdo->query('SELECT event_id FROM typhoon_demo_imports')->fetchAll(PDO::FETCH_COLUMN) as $id){
  $summary=$pdo->query('SELECT COUNT(*) AS rows_count,SUM(affected_households) AS households,SUM(affected_persons) AS persons,SUM(flood_status<>"None") AS flooded FROM typhoon_street_impacts WHERE event_id='.(int)$id)->fetch();
  demoAssert((int)$summary['rows_count']===10&&(int)$summary['households']>0&&(int)$summary['persons']>0&&(int)$summary['flooded']>0,'Scenario has no usable impact metrics.');
  $name=$pdo->query('SELECT event_name FROM typhoon_events WHERE event_id='.(int)$id)->fetchColumn();
  $expected=array_values(array_filter($preview['events'],fn($e)=>$e['event_name']===$name))[0];
  demoAssert((int)$summary['households']===$expected['affected_households']&&(int)$summary['persons']===$expected['affected_persons']&&(int)$summary['flooded']===$expected['flooded_streets'],'Saved event metrics differ from preview.');
 }
 echo "Demo tests passed: 50 rows / 5 scenarios, validation, official-event isolation, duplicates, rollback, and populated impact metrics.\n";
}finally{$pdo->exec('USE `'.DB_NAME.'`');$pdo->exec("DROP DATABASE `{$test}`");}
foreach($original as $table=>$count)demoAssert($count===(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(),'Application data changed during tests.');
echo "Temporary test database removed; application records preserved.\n";
