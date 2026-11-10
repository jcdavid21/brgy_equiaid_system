<?php
declare(strict_types=1);
require_once __DIR__.'/demo-impact-import.php';

function streetImpactCsvPath(): string { return __DIR__.'/data/typhoon-street-impacts.csv'; }

/** Resolve an imported PAGASA identity, never create a second typhoon by display name. */
function streetImpactEventId(PDO $pdo, string $name, int $year): int
{
    $query=$pdo->prepare('SELECT DISTINCT te.event_id FROM pagasa_typhoon_imports pi JOIN typhoon_events te ON te.event_id=pi.event_id WHERE pi.pagasa_name=? AND pi.report_year=? AND pi.is_latest=1');
    $query->execute([$name,$year]);
    $ids=$query->fetchAll(PDO::FETCH_COLUMN);
    if(count($ids)!==1) throw new InvalidArgumentException($name.' '.$year.': '.(count($ids)?'multiple imported events match. Resolve duplicate events first.':'no matching PAGASA event. Import the PAGASA typhoon dataset first.'));
    return (int)$ids[0];
}

function validateStreetImpactCsv(PDO $pdo, string $csv): array
{
    $fields=['dataset_type','pagasa_name','report_year','street_id','street_name','barangay','city','observed_at_utc','flood_status','flood_height_m','damage_status','road_accessible','affected_households','affected_persons','notes'];
    $catalog=[];
    foreach(pagasaReadCsv((string)file_get_contents(pagasaCsvCatalogPath())) as $entry){
        $r=$entry['data'];$catalog[$r['report_year'].':'.$r['pagasa_name']]=$r;
    }
    $stream=fopen('php://temp','r+');
    $legacyFields=['dataset_type','event_key','event_name','date_started','date_ended','wind_speed_kph','street_id','street_name','barangay','city','observed_at_utc','flood_status','flood_height_m','damage_status','road_accessible','affected_households','affected_persons','notes'];
    fputcsv($stream,$legacyFields,',','"','');
    $identities=[];
    foreach(pagasaReadCsv($csv,1000) as $entry){
        $r=$entry['data'];$headers=array_keys($r);sort($headers);$expected=$fields;sort($expected);
        if($headers!==$expected) throw new InvalidArgumentException('Use the Street Impact CSV sample with pagasa_name and report_year columns. Export Excel files as CSV UTF-8.');
        $name=strtoupper(trim($r['pagasa_name']));$year=$r['report_year'];
        if(!preg_match('/^\d{4}$/D',$year)||!isset($catalog[$year.':'.$name])) throw new InvalidArgumentException('Row '.$entry['line'].': unknown PAGASA typhoon name/year.');
        if(!in_array($r['dataset_type'],['SYNTHETIC','ASSESSMENT'],true)) throw new InvalidArgumentException('Row '.$entry['line'].': dataset_type must be SYNTHETIC or ASSESSMENT.');
        $key='IMPACT-'.$year.'-'.$name;
        $reference=$catalog[$year.':'.$name];
        if(!isset($identities[$key])) $identities[$key]=['event_id'=>streetImpactEventId($pdo,$name,(int)$year),'pagasa_name'=>$name,'report_year'=>(int)$year,'dataset_type'=>$r['dataset_type']];
        if($identities[$key]['dataset_type']!==$r['dataset_type']) throw new InvalidArgumentException($name.': inconsistent dataset_type within one event.');
        // Reuse street, capacity, flood, count and lifecycle validation from the existing importer.
        $legacy=['dataset_type'=>'SYNTHETIC','event_key'=>$key,'event_name'=>$name.' '.$year,'date_started'=>substr($reference['started_at_utc'],0,10),'date_ended'=>substr($reference['ended_at_utc'],0,10),'wind_speed_kph'=>$reference['peak_wind_kph']?:'1']+$r;
        fputcsv($stream,array_map(fn($field)=>$legacy[$field],$legacyFields),',','"','');
    }
    rewind($stream);$normalized=(string)stream_get_contents($stream);fclose($stream);
    $groups=validateDemoImpacts($pdo,$normalized,1000);
    foreach($groups as $key=>&$group) $group['identity']=$identities[$key];
    unset($group);
    return $groups;
}

/** Stable payload used both for duplicate checks and writes. */
function streetImpactValues(array $row, array $identity): array
{
    $recorded=(new DateTimeImmutable($row['observed_at_utc'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
    return ['flood_status'=>$row['flood_status'],'damage_status'=>$row['damage_status'],'flood_height_m'=>$row['flood_height_m'],'road_accessible'=>$row['road_accessible'],'affected_households'=>$row['affected_households'],'affected_persons'=>$row['affected_persons'],'report_source'=>$identity['dataset_type']==='SYNTHETIC'?'Demo':'Staff','notes'=>'CSV '.$identity['dataset_type'].': '.$row['notes'],'date_recorded'=>$recorded];
}

function streetImpactDecision(PDO $pdo, array $row, array $identity): array
{
    $find=$pdo->prepare('SELECT * FROM typhoon_street_impacts WHERE event_id=? AND street_id=?');
    $find->execute([$identity['event_id'],$row['street_id']]);$existing=$find->fetch(PDO::FETCH_ASSOC);
    $values=streetImpactValues($row,$identity);
    if(!$existing) return ['action'=>'create','values'=>$values];
    foreach($values as $field=>$value){
        $same=$field==='flood_height_m'?abs((float)$existing[$field]-(float)$value)<0.00001:(string)$existing[$field]===(string)$value;
        if(!$same) return ['action'=>'revise','values'=>$values];
    }
    return ['action'=>'skip','values'=>$values];
}

function streetImpactCsvPreview(PDO $pdo, array $groups): array
{
    $preview=['counts'=>['create'=>0,'revise'=>0,'skip'=>0],'rows'=>[],'events'=>[],'event_count'=>count($groups)];
    foreach($groups as $group){
        $id=$group['identity'];
        $summary=['event_id'=>$id['event_id'],'event_name'=>$id['pagasa_name'].' '.$id['report_year'],'impacted_streets'=>0,'flooded_streets'=>0,'damaged_streets'=>0,'inaccessible_streets'=>0,'affected_households'=>0,'affected_persons'=>0];
        foreach($group['rows'] as $r){
            $action=streetImpactDecision($pdo,$r,$id)['action'];$preview['counts'][$action]++;
            $preview['rows'][]=['event_name'=>$summary['event_name'],'event_id'=>$id['event_id'],'street_name'=>$r['street_name'],'flood_status'=>$r['flood_status'],'flood_height_m'=>$r['flood_height_m'],'damage_status'=>$r['damage_status'],'road_accessible'=>$r['road_accessible'],'affected_households'=>$r['affected_households'],'affected_persons'=>$r['affected_persons'],'action'=>$action];
            $summary['impacted_streets']+=(int)($r['flood_status']!=='None'||$r['damage_status']!=='None'||$r['affected_households']>0||$r['affected_persons']>0||$r['road_accessible']===0);
            $summary['flooded_streets']+=(int)($r['flood_status']!=='None');
            $summary['damaged_streets']+=(int)in_array($r['damage_status'],['Moderate Damage','Severely Damaged'],true);
            $summary['inaccessible_streets']+=(int)($r['road_accessible']===0);
            $summary['affected_households']+=$r['affected_households'];$summary['affected_persons']+=$r['affected_persons'];
        }
        $preview['events'][]=$summary;
    }
    return $preview;
}

function commitStreetImpactCsv(PDO $pdo, array $groups, ?int $userId): array
{
    $counts=['created'=>0,'revised'=>0,'skipped'=>0,'events_created'=>0];
    $pdo->beginTransaction();
    try{
        $pdo->query('SELECT event_id FROM typhoon_events ORDER BY event_id FOR UPDATE')->fetchAll();
        $pdo->query('SELECT import_id FROM pagasa_typhoon_imports ORDER BY import_id FOR UPDATE')->fetchAll();
        $pdo->query('SELECT impact_id FROM typhoon_street_impacts ORDER BY impact_id FOR UPDATE')->fetchAll();
        foreach($groups as $group){
            $id=$group['identity'];
            $id['event_id']=streetImpactEventId($pdo,$id['pagasa_name'],$id['report_year']);
            if(!isset($counts['event_id'])) $counts['event_id']=$id['event_id'];
            foreach($group['rows'] as $r){
                $decision=streetImpactDecision($pdo,$r,$id);
                if($decision['action']==='skip'){$counts['skipped']++;continue;}
                $values=$decision['values'];$values['recorded_by']=$userId;
                $assign=implode(',',array_map(fn($f)=>'`'.$f.'`=?',array_keys($values)));
                if($decision['action']==='revise'){
                    $pdo->prepare('UPDATE typhoon_street_impacts SET '.$assign.' WHERE event_id=? AND street_id=?')->execute([...array_values($values),$id['event_id'],$r['street_id']]);
                    $counts['revised']++;
                }else{
                    $pdo->prepare('INSERT INTO typhoon_street_impacts SET '.$assign.',event_id=?,street_id=?')->execute([...array_values($values),$id['event_id'],$r['street_id']]);
                    $counts['created']++;
                }
            }
        }
        $pdo->commit();return $counts;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
