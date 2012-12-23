<?php

include(__DIR__ . '/../init.inc.php');

echo "總數: " . count(DistrictCourtCase::search(1)) . "\n";
echo "Raw數: " . count(DistrictCourtCaseEAV::search(array('key' => 'raw'))) . "\n";
