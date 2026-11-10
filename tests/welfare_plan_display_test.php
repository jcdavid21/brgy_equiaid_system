<?php
declare(strict_types=1);
require __DIR__.'/../backend/welfare-plan-display.php';
function displayAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$text='Repair of 30 damaged houses along Sampaguita St.';
$legacy='__EXT__:'.json_encode(['priority'=>'Medium','needs'=>[],'steps'=>[],'beneficiary_type'=>'street','beneficiary_name'=>'','target_date'=>'','remarks'=>'']).'||'.$text;
displayAssert(welfarePlanDescription($legacy)===$text,'Legacy metadata leaked into card description.');
displayAssert(welfarePlanDescription($text)===$text,'Plain description changed.');
displayAssert(welfarePlanDescription(null)===null,'Missing description changed.');
displayAssert(welfarePlanDescription('')==='','Empty description changed.');
displayAssert(welfarePlanDescription('__EXT__:{"remarks":"a||b"}||'.$text)===$text,'Delimiter inside JSON broke extraction.');
displayAssert(welfarePlanDescription('__EXT__:{"remarks":"x"}||A || B')==='A || B','Description delimiter lost.');
displayAssert(welfarePlanDescription('__EXT__:{"priority":')==='','Incomplete metadata leaked.');
displayAssert(welfarePlanDescription('__EXT__:{invalid}||text')==='','Malformed metadata leaked.');
$plan=['plan_id'=>1,'description'=>$legacy,'status'=>'Ongoing'];
$display=welfarePlanForDisplay($plan);
displayAssert($display['description']===$text&&$display['status']==='Ongoing'&&$plan['description']===$legacy,'Display decoding changed stored values or other fields.');
displayAssert(!array_key_exists('beneficiary_name',$display),'Internal metadata was added to public response.');
echo "Welfare description tests passed: legacy and plain descriptions, embedded delimiters, malformed payloads, and preserved plan fields.\n";
