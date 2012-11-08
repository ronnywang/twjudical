<?php

class DistrictCourtCaseEAV extends Pix_Table
{
    public function init()
    {
        $this->_name = 'district_court_case_eav';
        $this->_primary = array('court', 'type', 'case_id', 'key');

        $this->_columns['court'] = array('type' => 'char', 'size' => 3);
        $this->_columns['type'] = array('type' => 'char', 'size' => 1);
        $this->_columns['case_id'] = array('type' => 'char', 'size' => 16);
        $this->_columns['key'] = array('type' => 'char', 'size' => 16);
        $this->_columns['value'] = array('type' => 'text');
    }
}
