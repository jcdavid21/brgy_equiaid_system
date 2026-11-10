<?php
declare(strict_types=1);
require_once __DIR__.'/pagasa-csv-import.php';

function demoImpactPath(): string { return __DIR__.'/data/typhoon-demo-impacts.csv'; }

function validateDemoImpacts(PDO $pdo, string $csv, int $maxRows = 100): array
{
    $required=['dataset_type','demo_event_key','event_name','date_started','date_ended','wind_speed_kph','street_id','street_name','barangay','city','observed_at_utc','flood_status','flood_height_m','damage_status','road_accessible','affected_households','affected_persons','notes'];
    $streets=[];
    foreach ($pdo->query('SELECT street_id,street_name,barangay,city,total_households,total_population FROM streets WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC) as $street) $streets[(int)$street['street_id']]=$street;
    $groups=[];$seen=[];
    foreach(pagasaReadCsv($csv, $maxRows) as $entry){
        $r=$entry['data'];
        // Accept the public event_key header and keep older uploads compatible.
        if(isset($r['event_key'])&&!isset($r['demo_event_key'])){
            $r['demo_event_key']=$r['event_key'];unset($r['event_key']);
        }
        $columns=array_keys($r);sort($columns);$expected=$required;sort($expected);
        if($columns!==$expected) throw new InvalidArgumentException('Use the demo-impact CSV format. PAGASA weather CSVs cannot be imported here.');
        $fail=function(string $message)use($entry):void{throw new InvalidArgumentException('Row '.$entry['line'].': '.$message);};
        if($r['dataset_type']!=='SYNTHETIC') $fail('Demo records must be explicitly labeled SYNTHETIC.');
        if(!preg_match('/^(?:IMPACT|DEMO)-[A-Z0-9-]{1,60}$/D',$r['demo_event_key']) || !preg_match('/^.{1,95}$/uD',$r['event_name'])) $fail('Use an IMPACT- event key and a nonempty event name of at most 95 characters.');
        foreach(['date_started','date_ended'] as $field){
            $d=DateTimeImmutable::createFromFormat('!Y-m-d',$r[$field]);
            if(!$d||$d->format('Y-m-d')!==$r[$field]) $fail('Invalid '.$field.'.');
        }
        if($r['date_ended']<$r['date_started']) $fail('End date precedes start date.');
        $observed=pagasaNormalizeDateTime($r['observed_at_utc'],true);
        if(substr($observed,0,10)<$r['date_started']||substr($observed,0,10)>$r['date_ended']) $fail('Assessment date must be within the scenario dates.');
        foreach(['street_id','road_accessible','affected_households','affected_persons'] as $field){
            if(!preg_match('/^\d+$/D',$r[$field])) $fail($field.' must be a nonnegative integer.');
            $r[$field]=(int)$r[$field];
        }
        $street=$streets[$r['street_id']]??null;
        if(!$street || $street['street_name']!==$r['street_name'] || $street['barangay']!==$r['barangay'] || $street['city']!==$r['city'] || $r['barangay']!=='Bagong Silang' || $r['city']!=='Caloocan City') $fail('Street and location must match an active Bagong Silang street in the system.');
        if($r['affected_households']>$street['total_households'] || $r['affected_persons']>$street['total_population'] || $r['affected_persons']<$r['affected_households']) $fail('Affected counts exceed street capacity or are inconsistent.');
        if(($r['affected_households']===0)!==($r['affected_persons']===0)) $fail('Household and person counts must both be zero or both be positive.');
        if(!in_array($r['road_accessible'],[0,1],true)) $fail('Road accessibility must be 0 or 1.');
        if(!in_array($r['flood_status'],['None','Flooded','Severely Flooded'],true)||!in_array($r['damage_status'],['None','Minor Damage','Moderate Damage','Severely Damaged'],true)) $fail('Unknown flood or damage status.');
        $r['flood_height_m']=pagasaNullableNumber($r,'flood_height_m',0,10);
        $r['wind_speed_kph']=pagasaNullableNumber($r,'wind_speed_kph',1,350);
        if($r['flood_height_m']===null || $r['wind_speed_kph']===null) $fail('Flood height and scenario wind speed are required.');
        if(($r['flood_status']==='None')!==($r['flood_height_m']===0.0)) $fail('Flood status and depth disagree.');
        if($r['flood_status']==='Severely Flooded' && $r['flood_height_m']<1.5) $fail('Severe demo flooding requires at least 1.5 m.');
        if(strlen($r['notes'])>1000) $fail('Notes exceed 1000 bytes.');
        $r['observed_at_utc']=$observed;
        // Canonical field order makes re-imports insensitive to CSV column order.
        $r=array_replace(array_fill_keys($required,''),$r);
        $key=$r['demo_event_key'];$identity=$key.':'.$r['street_id'];
        if(isset($seen[$identity])) $fail('Duplicate event/street pair.');
        $seen[$identity]=true;
        $meta=array_intersect_key($r,array_flip(['demo_event_key','event_name','date_started','date_ended','wind_speed_kph']));
        if(isset($groups[$key]) && $groups[$key]['metadata']!==$meta) $fail('Scenario metadata differs between streets.');
        if(!isset($groups[$key])) $groups[$key]=['metadata'=>$meta,'rows'=>[]];
        $groups[$key]['rows'][]=$r;
    }
    foreach($groups as &$group){
        usort($group['rows'],fn($a,$b)=>$a['street_id']<=>$b['street_id']);
        $group['hash']=hash('sha256',json_encode($group['rows'],JSON_THROW_ON_ERROR));
    }
    unset($group);ksort($groups);
    return $groups;
}

function demoImpactPreview(PDO $pdo,array $groups):array
{
    $counts=['create'=>0,'revise'=>0,'skip'=>0];$rows=[];$events=[];
    foreach($groups as $key=>$group){
        $query=$pdo->prepare('SELECT content_sha256 FROM typhoon_demo_imports WHERE demo_key=?');$query->execute([$key]);$existing=$query->fetchColumn();
        if($existing && !hash_equals($existing,$group['hash'])) throw new InvalidArgumentException($key.' already exists with different demo data. Use a new DEMO event key.');
        $action=$existing?'skip':'create';$counts[$action]+=count($group['rows']);
        $summary=['event_name'=>$group['metadata']['event_name'],'impacted_streets'=>0,'flooded_streets'=>0,'damaged_streets'=>0,'inaccessible_streets'=>0,'affected_households'=>0,'affected_persons'=>0];
        foreach($group['rows'] as $r){
            $rows[]=['event_name'=>$r['event_name'],'street_name'=>$r['street_name'],'flood_status'=>$r['flood_status'],'flood_height_m'=>$r['flood_height_m'],'damage_status'=>$r['damage_status'],'road_accessible'=>$r['road_accessible'],'affected_households'=>$r['affected_households'],'affected_persons'=>$r['affected_persons'],'action'=>$action];
            $summary['impacted_streets']+=(int)($r['flood_status']!=='None'||$r['damage_status']!=='None'||$r['affected_households']>0||$r['affected_persons']>0||$r['road_accessible']===0);
            $summary['flooded_streets']+=(int)($r['flood_status']!=='None');
            $summary['damaged_streets']+=(int)in_array($r['damage_status'],['Moderate Damage','Severely Damaged'],true);
            $summary['inaccessible_streets']+=(int)($r['road_accessible']===0);
            $summary['affected_households']+=$r['affected_households'];
            $summary['affected_persons']+=$r['affected_persons'];
        }
        $events[]=$summary;
    }
    return ['counts'=>$counts,'rows'=>$rows,'events'=>$events,'event_count'=>count($groups),'is_demo'=>true];
}

function commitDemoImpacts(PDO $pdo,array $groups,?int $userId):array
{
    $counts=['created'=>0,'revised'=>0,'skipped'=>0,'events_created'=>0];
    $pdo->beginTransaction();
    try{
        $pdo->query('SELECT event_id FROM typhoon_events ORDER BY event_id FOR UPDATE')->fetchAll();
        $pdo->query('SELECT event_id FROM typhoon_demo_imports ORDER BY event_id FOR UPDATE')->fetchAll();
        foreach($groups as $key=>$group){
            $check=demoImpactPreview($pdo,[$key=>$group]);
            if($check['counts']['skip']){$counts['skipped']+=$check['counts']['skip'];continue;}
            $m=$group['metadata'];
            $pdo->prepare("INSERT INTO typhoon_events (event_name,local_name,date_started,date_ended,wind_speed_kph,status,notes,created_by) VALUES (?,?,?,?,?,'Passed',?,?)")->execute([$m['event_name'],$key,$m['date_started'],$m['date_ended'],$m['wind_speed_kph'],'DEMO / SYNTHETIC: fictitious scenario and impacts for testing. Not an official disaster report.',$userId]);
            $eventId=(int)$pdo->lastInsertId();
            $counts['event_id']=$eventId;
            $pdo->prepare('INSERT INTO typhoon_demo_imports (event_id,demo_key,content_sha256,imported_json,imported_by) VALUES (?,?,?,?,?)')->execute([$eventId,$key,$group['hash'],json_encode($group,JSON_THROW_ON_ERROR),$userId]);
            $insert=$pdo->prepare("INSERT INTO typhoon_street_impacts (event_id,street_id,flood_status,damage_status,flood_height_m,road_accessible,affected_households,affected_persons,report_source,notes,recorded_by,date_recorded) VALUES (?,?,?,?,?,?,?,?,'Demo',?,?,?)");
            foreach($group['rows'] as $r){
                $recorded=(new DateTimeImmutable($r['observed_at_utc'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
                $insert->execute([$eventId,$r['street_id'],$r['flood_status'],$r['damage_status'],$r['flood_height_m'],$r['road_accessible'],$r['affected_households'],$r['affected_persons'],'DEMO / SYNTHETIC DATA. '.$r['notes'],$userId,$recorded]);
                $counts['created']++;
            }
            $counts['events_created']++;
        }
        $pdo->commit();return $counts;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
