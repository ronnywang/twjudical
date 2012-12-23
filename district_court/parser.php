<?php

ini_set('memory_limit', '4096m');
mb_internal_encoding('UTF-8');
include(__DIR__ . '/../init.inc.php');

class Parser
{
    protected $_case_callbacks = array();

    public function setCaseCallbacks()
    {
        $this->_case_callbacks['易'] = function($case, $raw){
            // 全形空白轉成半形雙空白，以減少地雷...
            $raw = str_replace('　', '  ', $raw);
            $cur = 0;
            $ret = new StdClass;
            $lines = explode("\n", $raw);
            // 臺灣臺北地方法院刑事判決 八十八年度易字第二九二五號
            $cur ++;

            // 公訴人, 被告, 聲請人... 可能有多個，特徵是N個空白開始
            $space_count = strlen($lines[$cur]) - strlen(ltrim($lines[$cur], ' '));
            $person = '';
            while (strpos(rtrim($lines[$cur]), str_repeat(' ', $space_count)) === 0) {
                $person .= substr(rtrim($lines[$cur]), $space_count) . "\n";
                $cur ++;
            }
            $ret->{'人物'} = $this->parsePerson(rtrim($person));

            // 前言: 右列被告因為 xxxx ，經檢察官提起公訴(xx年偵字xxxx號)，判決如右列:
            $str = '';
            while (strpos($lines[$cur], ' ') !== 0) {
                $str .= rtrim($lines[$cur]);
                $cur ++;
            }
            // TODO: 前言可以抓出偵字和事由
            $ret->{'前言'} = $str;

            $list_types = array(
                '主文', 
                '事實',
                '理由',
            );

            // 事實
            while (in_array(trim(str_replace(' ', '', $lines[$cur])), $list_types)) {
                $type = str_replace(' ', '', trim($lines[$cur]));
                $cur ++;
                $str = '';
                while (true) {
                    if (in_array(trim(str_replace(' ', '', $lines[$cur])), $list_types)) {
                        break;
                    }

                    $str .= rtrim($lines[$cur]) . "\n";
                    $cur ++;

                    if (!isset($lines[$cur])) {
                        //var_dump($raw);
                        throw new Exception("無窮迴圈了?");
                    }

                    if (strpos($lines[$cur], '據上論斷') === 0) {
                        break;
                    } elseif (strpos($lines[$cur], '據上論結') === 0) {
                        break;
                    } elseif (strpos($lines[$cur], '本案經') === 0) {
                        break;
                    } elseif (strpos($lines[$cur], '中') === 0 and strpos(str_replace(' ', '', $lines[$cur]), '中華民國') === 0) {
                        break;
                    }

                }
                $ret->{$type} = $this->parseContent(rtrim($str), $type);
            }

            $str = '';
            while (strpos(str_replace(' ', '', trim($lines[$cur])), '中華民國') !== 0) {
                $str .= trim($lines[$cur]);
                $cur ++;
            }
            $ret->{'結論'} = rtrim($str);
            $ret->{'審理時間'} = $this->parseDateFromWording(str_replace(' ', '', trim($lines[$cur])));
            $cur ++;
            $ret->{'法庭'} = str_replace(' ', '', trim($lines[$cur]));
            $cur ++;
            if (strpos(str_replace(' ', '', trim($lines[$cur])), '法官') !== 0) {
                var_dump($raw);
                throw new Exception("法庭完下一行應該是法官開頭");
            }
            $ret->{'法官'} = mb_substr(str_replace(' ', '', trim($lines[$cur])), 2);
            $cur ++;

            // 備註
            $str = '';
            while (strpos(str_replace(' ', '', trim($lines[$cur])), '書記官') !== 0) {
                $str .= trim($lines[$cur]) . "\n";
                $cur ++;
            }
            $ret->{'書記官'} = mb_substr(str_replace(' ', '', trim($lines[$cur])), 3);
            $cur ++;
            $ret->{'書記時間'} = $this->parseDateFromWording(str_replace(' ', '', trim($lines[$cur])));
            $cur ++;

            $str = '';
            while (isset($lines[$cur])) {
                $str .= rtrim($lines[$cur]) . "\n";
                $cur ++;
            }
            // TODO: parse 附錄法條
            $this->{'附錄'} = rtrim($str);
            return $ret;
        };
    }

    public function parseContent($str, $type = '')
    {
        if (!in_array($type, array('理由', '事實'))) {
            return $str;
        }

        $list = array();
        $lines = explode("\n", $str);
        if (strpos($str, '一、') === 0) {
        } elseif (strpos($str, '壹、') === 0) {
        } else {
            var_dump($str);
            exit;
            throw new Exception("開頭...");
        }
        // TODO: 要 parse 出分開項目
        return $str;
    }

    public function parsePerson($str)
    {
        // TODO: 要 parse 出人物
        return $str;
    }

    public function parseDateFromWording($str)
    {
        // TODO: 中華民國xx年x月x日 => 轉成 timestamp
        if (strpos($str, '中華民國') !== 0) {
            throw new Exception('不是中華民國開頭');
        }
        return $str;
    }
    protected $_i = 0;
    public function parseCase($case)
    {
        $raw = $case->getEAV('raw');
        if (!$raw) {
            // TODO: 需要再找找看為什麼沒有 raw
            return array();
        }
        $ret = preg_match_all('#<pre><font size="3">(.*?)</pre>#s', $raw, $matches);
        if (2 !== $ret) {
            throw new Exception("內容應該要出現兩次...");
            // TODO: 這邊可能是沒抓到，需要檢查一下
        }

        if ($matches[1][0] != $matches[1][1]) {
            throw new Exception("裡面的兩次內容不相同");
        }
        $raw = $matches[1][0];
        list($year, $case_type, $no) = explode(',', $case->case_id);
        if (!array_key_exists($case_type, $this->_case_callbacks)) {
            // TODO
            return array();
            throw new Exception("找不到案件類型 '{$case_type}' 的處理函式");
        }
        $infos = call_user_func($this->_case_callbacks[$case_type], $case, $raw);
        /*$this->_i ++;
        if ($this->_i > 0) {
            var_dump($raw);
            var_dump($infos);
            //readline($this->_i);
        }*/
        return $infos;
    }


    public function main(){
        $this->setCaseCallbacks();

        $cases = array();
        $query_cases = DistrictCourtCase::search(1)->volumemode(100);
        foreach ($query_cases as $case) {
            error_log("{$case->court}-{$case->type}-{$case->case_id} start");
            $case_obj = new StdClass;
            $case_obj->court = $case->court;
            $case_obj->type = $case->type;
            $case_obj->case_id = $case->case_id;
            $case_obj->date = $case->date;
            $case_obj->reason = $case->getEAV('reason');
            try {
                $case_obj->infos = $this->parseCase($case);
            } catch (Exception $e) {
                file_put_contents("error", "{$case->court}-{$case->type}-{$case->case_id} failed: " . $e->getMessage() . "\n", FILE_APPEND);
            }
            $cases[] = $case_obj;
            error_log("{$case->court}-{$case->type}-{$case->case_id} done");
        }
        echo json_encode($cases, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

$parser = new Parser;
$parser->main();
