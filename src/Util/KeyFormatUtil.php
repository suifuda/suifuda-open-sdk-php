<?php

namespace Suifuda\Sdk\Util;

use Suifuda\Sdk\Exception\SfdSdkException;

/**
 * 密钥格式处理。
 */
class KeyFormatUtil
{
    /**
     * 规范化密钥：去除 PEM 头尾、空白字符，得到纯 Base64。
     *
     * @param string $key
     * @return string
     */
    public static function normalize($key)
    {
        if ($key === null || trim((string) $key) === '') {
            throw SfdSdkException::ofCode('KEY_ERROR', '密钥不能为空');
        }
	    
	    $normalized = trim((string) $key);
	    if (strpos($normalized, 'BEGIN RSA PRIVATE KEY') !== false
		    || strpos($normalized, 'BEGIN RSA PUBLIC KEY') !== false) {
		    throw SfdSdkException::ofCode(
			    'KEY_ERROR',
			    '不支持 PKCS#1（BEGIN RSA PRIVATE/PUBLIC KEY），请使用 PKCS#8/X.509（BEGIN PRIVATE/PUBLIC KEY）或对应纯 Base64'
		    );
	    }
		
        $normalized = str_replace(
            array(
                '-----BEGIN PRIVATE KEY-----',
                '-----END PRIVATE KEY-----',
                '-----BEGIN RSA PRIVATE KEY-----',
                '-----END RSA PRIVATE KEY-----',
                '-----BEGIN PUBLIC KEY-----',
                '-----END PUBLIC KEY-----',
                '-----BEGIN RSA PUBLIC KEY-----',
                '-----END RSA PUBLIC KEY-----',
            ),
            '',
            $normalized
        );
        $normalized = preg_replace('/\s+/', '', $normalized);

        if ($normalized === null || $normalized === '') {
            throw SfdSdkException::ofCode('KEY_ERROR', '密钥内容无效');
        }
        if (self::isPlaceholder($normalized)) {
            throw SfdSdkException::ofCode(
                'KEY_ERROR',
                '请将示例中的占位密钥替换为真实 Base64 私钥（不含 PEM 头尾），当前值: ' . $key
            );
        }
        return $normalized;
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isPlaceholder($key)
    {
        $upper = strtoupper($key);
        return strpos($upper, 'YOUR_') !== false
            || strpos($upper, 'YOURPRIVATE') !== false
            || strpos($upper, 'YOURPUBLIC') !== false
            || $upper === 'PRIVATE_KEY'
            || $upper === 'PUBLIC_KEY';
    }
}
