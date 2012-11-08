<?php

include(__DIR__ . '/../init.inc.php');

class CrawlerDetail
{
    public function main()
    {
        foreach (DistrictCourtCase::search(1) as $case) {
            if ($case->getEAV('raw')) {
                continue;
            }

            $params = $this->getCaseParams($case);
            $result = $this->http('GET', 'http://jirs.judicial.gov.tw/FJUD/PrintFJUD03_0.aspx?' . http_build_query($params));
            if (200 !== $result->code) {
                continue;
            }
            $case->setEAV('raw', $result->body);
        }
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
     * 取得單一案件頁的參數
     *
     * @param string $case_id 裁判字號 Ex: 89,易,32
     * @param int $timestamp 裁判日期
     * @param string $court 法院代號
     * @param string $type 案件代號
     * @return array
     */
    public function getCaseParams($case)
    {
        list($year, $word, $no) = explode(',', $case->case_id);
        $courts = DistrictCourt::getCourts();
        return array(
            'jrecno' => $case->case_id . ',' . date('Ymd', $case->date),
            'v_court' => $case->court . ' ' . $courts[$case->court],
            'v_sys' => $case->type,
            'jyear' => $year,
            'jcase' => $word,
            'jno' => $no,
            'jdate' => date('Ymd', $case->date) - 19110000,
            'jcheck' => '',
        );
    }
}

$crawler = new CrawlerDetail;
$crawler->main();
