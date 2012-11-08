<?php

include(__DIR__ . '/../init.inc.php');

class CourtCrawler
{
    protected $_courts = null;
    /**
     * 取得法院的列表
     *
     * @return array
     */
    public function getCourts()
    {
        if (is_null($this->_courts)) {
            $this->_courts = array();
            foreach (file(__DIR__ . '/court', FILE_IGNORE_NEW_LINES) as $court) {
                list($id, $name) = explode(' ', $court);
                $this->_courts[$id] = $name;
            }
        }
        return $this->_courts;
    }

    protected $_last_fetch = null;
    protected $_cookies = array();
    /**
     * http request
     *
     * @param string $method POST/GET
     * @param string $url
     * @param array $post_params
     * @return object {code, body}
     */
    public function http($method, $url, $post_params = array())
    {
        // 一秒鐘只抓一次，以避免被當作惡意行為
        while (!is_null($this->_last_fetch) and (microtime(true) - $this->_last_fetch) < 1) {
            usleep(1000);
        }
        error_log("Fetch $url");
        $this->_last_fetch = microtime(true);

        $options = array(
            'useragent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:16.0) Gecko/20100101 Firefox/16.0',
            'cookies' => $this->_cookies,
            'referer' => 'http://jirs.judicial.gov.tw/FJUD/FJUDQRY01_1.aspx',
            'headers' => array(),
        );

        $info = array();
        if ('POST' == $method) {
            $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $response = http_post_fields($url, $post_params, array(), $options, $info);
        } elseif ('GET' == $method) {
            $response = http_get($url, $options, $info);
        } else {
            throw new Exception("http method 只給用 GET/POST");
        }

        if (!$message = http_parse_message($response)) {
            $ret = new StdClass;
            $ret->code = 0;
            return $ret;
        }

        if (array_key_exists('Set-Cookie', $message->headers) and is_array($message->headers['Set-Cookie'])) {
            foreach ($message->headers['Set-Cookie'] as $cookie) {
                $cookie_data = http_parse_cookie($cookie);
                $this->_cookies = array_merge($cookie_data->cookies, $this->_cookies);
            }
        }

        $ret = new StdClass;
        $ret->code = $message->responseCode;
        $ret->body = $message->body;
        return $ret;
    }

    /**
     * 取得搜尋頁面的 POST 變數
     *
     * @param array $options start 開始時間 timestamp
     *                       end   結束時間 timestamp
     *                       court 法院代號
     *                       type  案件類型
     * @return array
     */
    public function getSearchParams($options = array())
    {
        $courts = $this->getCourts();
        if (!array_key_exists($options['court'], $courts)) {
            throw new Exception("找不到 {$options['court']} 這個地方法院");
        }

        return array(
            'Button' => '查詢',
            'dd1' => date('j', $options['start']), // 開始日
            'dd2' => date('j', $options['end']), // 結束日
            'dm1' => date('n', $options['start']), // 開始月
            'dm2' => date('n', $options['end']), // 結束月
            'dy1' => date('Y', $options['start']) - 1911, // 開始年(民國)
            'dy2' => date('Y', $options['end']) - 1911, // 結束年(民國)
            'edate' => date('Ymd', $options['end']), // 結束日期 YYYYMMDD
            'jt' => '',
            'jud_case' => '',
            'jud_no' => '',
            'jud_title' => '',
            'jud_year' => '',
            'keyword' => '',
            'kw' => '',
            'nccharset' => 'F6D8400C',
            'sdate' => date('Ymd', $options['start']), // 開始日期 YYYYMMDD
            'searchkw' => '',
            'sel_judword' => '常用字別',
            'v_court' => $options['court'] . ' ' . $courts[$options['court']],
            'v_sys' => $options['type'], // 類型, from getSysTypes()
        );
    }

    /**
     * 取得單一案件頁的參數
     *
     * @param string $case_id 裁判字號 Ex: 89,易,32
     * @param int $timestamp 裁判日期
     * @param string $court 法院代號
     * @param string $type 案件代號
     * @return array
     */
    public function getCaseParams($case_id, $timestamp, $court, $type)
    {
        list($year, $word, $no) = explode(',', $case_id);
        $courts = $this->getCourts();
        return array(
            'jrecno' => $case_id . ',' . date('Ymd', $timestamp),
            'v_court' => $court . ' ' . $courts[$court],
            'v_sys' => $type,
            'jyear' => $year,
            'jcase' => $word,
            'jno' => $no,
            'jdate' => date('Ymd', $timestamp) - 19110000,
            'jcheck' => '',
        );
    }

    /**
     * 取得案件類型
     *
     * @return array
     */
    public function getSysTypes()
    {
        return array(
            'M' => '刑事',
            'V' => '民事',
            'A' => '行政',
            'P' => '公懲',
        );
    }

    /**
     * 取得某個月內所有案件
     *
     * @param string $court
     * @param string $type
     * @param int $year
     * @param int $month
     * @return array(Object...)
     */
    public function getCases($court, $type, $year, $month)
    {
        $start_time = mktime(0, 0, 0, $month, 1, $year);
        $end_time = strtotime('+1 month', $start_time) - 86400;

        if (!$this->_cookies) {
            // 如果沒有 cookie, 就先 load 首頁得到 cookie
            $this->http('GET', 'http://jirs.judicial.gov.tw/FJUD/FJUDQRY01_1.aspx');
        }

        $params = $this->getSearchParams(array(
            'start' => $start_time,
            'end' => $end_time,
            'court' => $court,
            'type' => $type,
        ));
        $url = 'http://jirs.judicial.gov.tw/FJUD/FJUDQRY02_1.aspx?' . http_build_query($params);

        while (true) {
            $result = $this->http('GET', $url);
            if (!preg_match('#<TABLE.*</TABLE>#s', $result->body, $matches)) {
                throw new Exception("找不到大寫的 TABLE");
            }

            if (strpos($result->body, '查無資料！請重新設定查詢條件或詳閱')) {
                break;
            }
            $doc = new DOMDocument();
            $full_body = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></html><body>' . $matches[0] . '</body></html>';
            @$doc->loadHTML($full_body);

            $tr_doms = $doc->getElementsByTagName('tr');
            $first_time = $last_time = null;

            for ($i = 1; $i < $tr_doms->length; $i ++) {

                $td_doms = $tr_doms->item($i)->getElementsByTagName('td');
                // 1 - 序號
                // 2 - 裁判字號
                $case_id = trim($td_doms->item(1)->getElementsByTagName('a')->item(0)->nodeValue);

                // 3 - 裁判日期
                $date = intval($td_doms->item(2)->nodeValue);
                $day = $date % 100;
                $date /= 100;
                $month = $date % 100;
                $date /= 100;
                $year = $date + 1911;
                $timestamp = mktime(0, 0, 0, $month, $day, $year);

                // 4 - 裁判案由
                $reason = trim($td_doms->item(3)->nodeValue);

                try {
                    $case = DistrictCourtCase::insert(array(
                        'court' => $court,
                        'type' => $type,
                        'case_id' => $case_id,
                    ));
                } catch (Pix_Table_DuplicateException $e) {
                    $case = DistrictCourtCase::search(array(
                        'court' => $court,
                        'type' => $type,
                        'case_id' => $case_id,
                    ))->first();
                }
                $case->update(array(
                    'date' => $timestamp,
                ));
                $case->setEAV('reason', $reason);
            }

            // 如果有下一頁，就抓下一頁
            if (preg_match('#<a href="([^"]*)"[^>]*>下一頁</a>#', $result->body, $matches)) {
                $url = http_build_url($url, parse_url($matches[1]), HTTP_URL_JOIN_PATH);
                continue;
            }

            // 如果沒有下一頁，卻有顯示 "本次查詢結果共xx筆，超出100筆" 那就繼續往下抓日期...
            if (FALSE !== strpos($result->body, '本次查詢結果共')) {
                // 如果最後一筆的裁判時間等於查詢時間，表示該時間的數量超過 100 筆，那一天必需要用其他方法查
                if ($timestamp == $end_time) {
                    file_put_contents("warning", sprintf("%s %s %s %s\n", $court, $type, date('Ymd', $end_time), 'over_100'), FILE_APPEND);
                    $end_time -= 86400;
                } else {
                    $end_time = $timestamp;
                }

                $params = $this->getSearchParams(array(
                    'start' => $start_time,
                    'end' => $end_time,
                    'court' => $court,
                    'type' => $type,
                ));
                $url = 'http://jirs.judicial.gov.tw/FJUD/FJUDQRY02_1.aspx?' . http_build_query($params);
                continue;
            }
            break;
        }
    }

    public function main()
    {
        foreach (range(2000, 2012) as $year) {
            foreach (range(1, 12) as $month) {
                foreach ($this->getCourts() as $court_id => $court_name) {
                    foreach ($this->getSysTypes() as $type_id => $type_name) {
                        if (DistrictCourtCrawlerLog::search(array('court' => $court_id, 'type' => $type_id, 'year' => $year, 'month' => $month))->first()) {
                            continue;
                        }
                        $this->getCases($court_id, $type_id, $year, $month);
                        DistrictCourtCrawlerLog::insert(array(
                            'court' => $court_id,
                            'type' => $type_id,
                            'year' => $year,
                            'month' => $month,
                            'crawlered_at' => time(),
                        ));
                    }
                }
            }
        }
    }
}

$crawler = new CourtCrawler();
$crawler->main();
