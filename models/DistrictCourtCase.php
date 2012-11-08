<?php

class DistrictCourtCase extends Pix_Table
{
    public function init()
    {
        $this->_name = 'district_court_case';
        $this->_primary = array('court', 'type', 'case_id');

        // 法院代號
        $this->_columns['court'] = array('type' => 'char', 'size' => 3);
        // 案件類型 M-刑事, A-民事...
        $this->_columns['type'] = array('type' => 'char', 'size' => 1);
        // 案件代號
        $this->_columns['case_id'] = array('type' => 'char', 'size' => 16);

        // 結案日期(timestamp)
        $this->_columns['date'] = array('type' => 'int', 'unsigned' => true);

        $this->_relations['eavs'] = array('rel' => 'has_many', 'type' => 'DistrictCourtCaseEAV');

        $this->addRowHelper('Pix_Table_Helper_EAV', array('getEAV', 'setEAV'));
    }
}
