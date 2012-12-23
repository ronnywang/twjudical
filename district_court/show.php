<?php

include(__DIR__ .'/../init.inc.php');
// TTD-M-84,訴,54
$arg = $_SERVER['argv'][1];
list($court_id, $type, $case_id) = explode('-', $arg);
$case = DistrictCourtCase::find(array($court_id, $type, $case_id));

if (!$case) {
    die("{$arg} is not found\n");
}

print_r($case->toArray());
print_r($case->eavs->toArray());
