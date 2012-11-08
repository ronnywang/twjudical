<?php

class DistrictCourtCrawlerLog extends Pix_Table
{
    public function init()
    {
        $this->_name = 'district_court_crawler_log';
        $this->_primary = array('court', 'type', 'year', 'month');

        // 法院代號
        $this->_columns['court'] = array('type' => 'char', 'size' => 3);
        // 案件類型 M-刑事, A-民事...
        $this->_columns['type'] = array('type' => 'char', 'size' => 1);
        // 爬資料時間
        $this->_columns['year'] = array('type' => 'int');
        $this->_columns['month'] = array('type' => 'int');
        $this->_columns['crawlered_at'] = array('type' => 'int', 'unsigned' => true);
    }
}
