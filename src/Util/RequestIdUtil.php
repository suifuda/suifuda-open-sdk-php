<?php

namespace Suifuda\Sdk\Util;

use DateTime;
use DateTimeZone;

/**
 * 请求公共字段工具。
 */
class RequestIdUtil
{
    /**
     * 当前时间，格式：yyyy-MM-dd HH:mm:ss
     *
     * @return string
     */
    public static function currentTimestamp()
    {
	    $dt = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * 生成 32 位十六进制随机串（16 字节，大写）。
     *
     * @return string
     */
    public static function generateNonce()
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }
}
