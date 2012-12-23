<?php

include(__DIR__ . '/../init.inc.php');
ini_set('memory_limit', '2048m');
Pix_Table::$_save_memory = true;

$cases = array();
foreach (DistrictCourtCase::search(1) as $case) {
    $case_obj = new StdClass;
    $case_obj->court = $case->court;
    $case_obj->type = $case->type;
    $case_obj->case_id = $case->case_id;
    $case_obj->date = $case->date;
    $case_obj->reason = $case->getEAV('reason');
    $case_obj->raw = $case->getEAV('raw');
    $cases[] = $case_obj;
}
echo json_encode($cases, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
