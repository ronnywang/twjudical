<?php

class DistrictCourt
{
    protected static $_courts = null;
    /**
     * 取得法院的列表
     *
     * @return array
     */
    public static function getCourts()
    {
        if (is_null(self::$_courts)) {
            self::$_courts = array();
            foreach (file(__DIR__ . '/court', FILE_IGNORE_NEW_LINES) as $court) {
                list($id, $name) = explode(' ', $court);
                self::$_courts[$id] = $name;
            }
        }
        return self::$_courts;
    }

    /**
     * 取得案件類型
     *
     * @return array
     */
    public static function getSysTypes()
    {
        return array(
            'M' => '刑事',
            'V' => '民事',
            'A' => '行政',
            'P' => '公懲',
        );
    }


}
