<?php
const SWN_CATEGORIES=['Food','Medicine','Housing','Livelihood','Sanitation','Evacuation','Financial Assistance'];
const SWN_PRIORITIES=['Urgent','High','Medium','Low'];
const SWN_STATUSES=['Reported','Under Assessment','Approved','In Progress','Completed','Rejected'];
function validate_street_welfare_need(array $in):array{
 foreach(['street_id','category','priority','description','date_reported','status'] as $f)if(!isset($in[$f])||trim((string)$in[$f])==='')throw new InvalidArgumentException("{$f} is required.");
 if(!in_array($in['category'],SWN_CATEGORIES,true)||!in_array($in['priority'],SWN_PRIORITIES,true)||!in_array($in['status'],SWN_STATUSES,true))throw new InvalidArgumentException('Invalid category, priority, or status.');
 $date=DateTime::createFromFormat('Y-m-d',(string)$in['date_reported']);if(!$date||$date->format('Y-m-d')!==$in['date_reported'])throw new InvalidArgumentException('Invalid reporting date.');
 $hh=filter_var($in['affected_households']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);$res=filter_var($in['affected_residents']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
 if($hh===false||$res===false||($hh===0&&$res===0))throw new InvalidArgumentException('Enter at least one affected household or resident.');
 $desc=trim(strip_tags((string)$in['description']));if(mb_strlen($desc)<5||mb_strlen($desc)>2000)throw new InvalidArgumentException('Description must be 5–2,000 characters.');
 return [(int)$in['street_id'],$in['category'],$hh,$res,$in['priority'],$desc,$in['date_reported'],!empty($in['assigned_to'])?(int)$in['assigned_to']:null,$in['status']];
}
