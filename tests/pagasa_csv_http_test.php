<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../backend/pagasa-csv-import.php';
function httpCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$base = getenv('EQUIAID_TEST_BASE_URL') ?: 'http://127.0.0.1/BrgyEquiaidSystem/';
session_id('equiaidcsvtest' . bin2hex(random_bytes(10)));
session_start();
$_SESSION = ['user_role' => 'admin', 'typhoon_csrf' => bin2hex(random_bytes(32))];
$csrf = $_SESSION['typhoon_csrf'];
$cookie = session_name() . '=' . session_id();
session_write_close();
$temp = tempnam(sys_get_temp_dir(), 'equiaid-csv-http-');
function requestLocal(string $path, ?array $fields = null, bool $authenticated = true): array {
    global $base, $cookie;
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false]);
    if ($authenticated) curl_setopt($curl, CURLOPT_COOKIE, $cookie);
    if ($fields !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields]);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return [$status, $body];
}
try {
    [$status,$html] = requestLocal('admin/typhoon-impact.php');
    httpCheck($status===200 && strpos($html,'id="pagasaCsvFile"')!==false, 'CSV upload UI unavailable.');
    httpCheck(strpos($html,'id="btnManageStatus"')===false && strpos($html,'id="btnAddImpact"')===false, 'Manual controls still visible.');
    $api='backend/admin-typhoon-impact.php';
    [$status,$body] = requestLocal($api.'?action=events',null,false);
    httpCheck($status===401,'Unauthenticated access accepted.');
    [$status,$body] = requestLocal($api.'?action=events');
    httpCheck($status===200 && json_decode($body,true)['ok'], 'Event selector API failed after migration.');
    [$status,$demoCsv] = requestLocal($api.'?action=download_impact_csv_sample');
    httpCheck($status===200 && count(pagasaReadCsv($demoCsv))===1, 'Impact format sample must contain exactly one example record.');
    [$status,$legacyDemoCsv] = requestLocal($api.'?action=download_demo_impacts');
    httpCheck($status===200 && $legacyDemoCsv===$demoCsv, 'Older demo links must return only the format sample.');
    $sampleRow=pagasaReadCsv($demoCsv)[0]['data'];
    httpCheck(isset($sampleRow['pagasa_name'],$sampleRow['report_year']) && !isset($sampleRow['event_key']), 'Impact format sample must identify the existing PAGASA event by name/year.');
    file_put_contents($temp,file_get_contents(__DIR__.'/../backend/data/typhoon-street-impacts.csv'));
    [$status,$body] = requestLocal($api,['action'=>'preview_street_impacts','csrf_token'=>$csrf,'report_csv'=>new CURLFile($temp,'text/csv','matched-impacts.csv')]);
    $matched=json_decode($body,true);
    httpCheck($status===200 && $matched['event_count']===50 && count($matched['rows'])===500, 'Matched 500-row multipart preview failed: '.$body);
    $inday=array_values(array_filter($matched['rows'],fn($r)=>$r['event_name']==='INDAY 2026'));
    httpCheck(count($inday)===10 && count(array_unique(array_column($inday,'event_id')))===1, 'INDAY upload preview is not linked to exactly one existing event.');
    [$status,$body] = requestLocal($api,['action'=>'import_street_impacts','csrf_token'=>$csrf,'preview_id'=>'wrong']);
    httpCheck($status===409,'Unbound matched-impact confirmation token accepted.');
    [$status,$body] = requestLocal($api,['action'=>'preview_street_impacts','csrf_token'=>'wrong','report_csv'=>new CURLFile($temp,'text/csv','matched-impacts.csv')]);
    httpCheck($status===403,'Invalid CSRF accepted for matched impacts.');
    file_put_contents($temp,file_get_contents(__DIR__.'/../backend/data/typhoon-demo-impacts.csv'));
    [$status,$body] = requestLocal($api,['action'=>'preview_demo_impacts','csrf_token'=>$csrf,'report_csv'=>new CURLFile($temp,'text/csv','impacts.csv')]);
    $demoPreview=json_decode($body,true);
    httpCheck($status===200 && $demoPreview['event_count']===5 && count($demoPreview['rows'])===50 && array_column($demoPreview['events'],'affected_households')===[46,204,377,550,691], 'Demo multipart preview or per-event totals failed: '.$body);
    [$status,$body] = requestLocal($api,['action'=>'import_demo_impacts','csrf_token'=>$csrf,'preview_id'=>'wrong']);
    httpCheck($status===409,'Unbound demo confirmation token accepted.');
    [$status,$sample] = requestLocal($api.'?action=download_pagasa_csv_sample');
    httpCheck($status===200 && count(pagasaReadCsv($sample))===1 && count(pagasaValidateTrustedCsv($sample))===1, 'Format sample must contain the original header and exactly one valid example record.');
    [$status,$legacySample] = requestLocal($api.'?action=download_pagasa_csv');
    httpCheck($status===200 && $sample===$legacySample, 'Older download links must not expose the full dataset.');
    $download=file_get_contents(pagasaCsvCatalogPath());
    file_put_contents($temp,$download);
    $upload = fn()=>new CURLFile($temp,'text/csv','actual.csv');
    [$status,$body] = requestLocal($api,['action'=>'preview_pagasa_csv','csrf_token'=>$csrf,'report_csv'=>$upload()]);
    $preview=json_decode($body,true);
    httpCheck($status===200 && $preview['ok'] && count($preview['rows'])===50,'50-row HTTP preview failed: '.$body);
    [$status,$body] = requestLocal($api,['action'=>'import_pagasa_csv','csrf_token'=>$csrf,'preview_id'=>'wrong']);
    httpCheck($status===409,'Unbound confirmation token accepted.');
    [$status,$body] = requestLocal($api,['action'=>'preview_pagasa_csv','csrf_token'=>'wrong','report_csv'=>$upload()]);
    httpCheck($status===403,'Invalid CSRF accepted.');
    file_put_contents($temp,str_replace(',140,75,965,',',999,75,965,',$download));
    [$status,$body] = requestLocal($api,['action'=>'preview_pagasa_csv','csrf_token'=>$csrf,'report_csv'=>$upload()]);
    httpCheck($status===422 && strpos($body,'differs from')!==false,'Tampered multipart CSV accepted.');
    foreach(['set_event_status','add_impact'] as $action){
        [$status,$body]=requestLocal($api,['action'=>$action,'event_id'=>1,'status'=>'Active']);
        httpCheck($status===405,'Manual mutation action is still enabled.');
    }
} finally {
    unlink($temp);
    session_start();
    $_SESSION=[];
    session_destroy();
}
echo "HTTP smoke checks passed: admin page, sample-only downloads, 500 matched street rows / 50 typhoons with INDAY identity, legacy previews, auth/CSRF, tampering, and confirmation binding. No records imported.\n";
