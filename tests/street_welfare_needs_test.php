<?php
require __DIR__.'/../backend/street-welfare-needs-validation.php';
function ensure(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$valid=['street_id'=>1,'category'=>'Food','affected_households'=>4,'affected_residents'=>0,'priority'=>'Urgent','description'=>'Food packs required','date_reported'=>'2026-09-05','assigned_to'=>2,'status'=>'Reported'];
$result=validate_street_welfare_need($valid); ensure($result[2]===4&&$result[4]==='Urgent','valid need normalization failed');
foreach([array_merge($valid,['category'=>'Invalid']),array_merge($valid,['affected_households'=>0]),array_merge($valid,['date_reported'=>'09/05/2026'])] as $bad){try{validate_street_welfare_need($bad);ensure(false,'invalid input accepted');}catch(InvalidArgumentException $e){}}
echo "Street welfare needs tests passed.\n";
