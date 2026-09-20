<?php

namespace Suifuda\Sdk\Util;

use Rtgm\sm\RtSm2;
use Suifuda\Sdk\Config\SignType;
use Suifuda\Sdk\Exception\SfdSdkException;

/**
 * 签名 / 验签工具。
 *
 * 待签名字符串规则：
 * 1. 排除 sign 参数
 * 2. 排除值为 null 或空字符串的参数
 * 3. 按参数名 ASCII 字典序排序
 * 4. 以 key=value& 格式拼接
 */
class SignUtil
{
    /**
     * SM2 默认用户 ID（与 Hutool / BouncyCastle 默认一致）。
     */
    const SM2_DEFAULT_USER_ID = '1234567812345678';

    /**
     * @param array $params
     * @param string $privateKey
     * @param SignType|string $signType
     * @return string Base64 签名
     */
    public static function sign(array $params, $privateKey, $signType)
    {
        if (empty($params)) {
            throw SfdSdkException::ofCode('SIGN_ERROR', '签名参数不能为空');
        }
        if ($privateKey === null || trim((string) $privateKey) === '') {
            throw SfdSdkException::ofCode('SIGN_ERROR', '私钥不能为空');
        }
        $type = $signType instanceof SignType ? $signType : SignType::fromCode($signType);
        if ($type === null) {
            throw SfdSdkException::ofCode('SIGN_ERROR', 'signType 不能为空');
        }

        $content = self::buildSignContent($params);
        if ($type->isRsa()) {
            return self::rsaSign($content, $privateKey);
        }
        return self::smSign($content, $privateKey);
    }

    /**
     * @param array $params
     * @param string $signBase64
     * @param string $publicKey
     * @param SignType|string $signType
     * @return bool
     */
    public static function verify(array $params, $signBase64, $publicKey, $signType)
    {
        if (empty($params)) {
            return false;
        }
        if ($signBase64 === null || trim((string) $signBase64) === ''
            || $publicKey === null || trim((string) $publicKey) === '') {
            return false;
        }
        $type = $signType instanceof SignType ? $signType : SignType::fromCode($signType);
        if ($type === null) {
            return false;
        }
        $content = self::buildSignContent($params);
        if ($type->isRsa()) {
            return self::rsaVerify($content, $signBase64, $publicKey);
        }
        return self::smVerify($content, $signBase64, $publicKey);
    }

    /**
     * @param array $params
     * @return string
     */
    public static function buildSignContent(array $params)
    {
        ksort($params, SORT_STRING);
        $parts = array();
        foreach ($params as $key => $value) {
            if ((string) $key === 'sign') {
                continue;
            }
            if ($value === null || (string) $value === '') {
                continue;
            }
            $parts[] = $key . '=' . $value;
        }
        return implode('&', $parts);
    }

    /**
     * @param string $content
     * @param string $privateKey
     * @return string
     */
    public static function rsaSign($content, $privateKey)
    {
        try {
            $pem = self::toPem(KeyFormatUtil::normalize($privateKey), true);
            $key = openssl_pkey_get_private($pem);
            if ($key === false) {
                throw new \RuntimeException(self::opensslErrors());
            }
            $signature = '';
            $ok = openssl_sign($content, $signature, $key, OPENSSL_ALGO_SHA256);
            if (is_resource($key)) {
                openssl_free_key($key);
            }
            if (!$ok) {
                throw new \RuntimeException(self::opensslErrors());
            }
            return base64_encode($signature);
        } catch (SfdSdkException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw SfdSdkException::ofCode(
                'SIGN_ERROR',
                'RSA 签名失败，请确认私钥为合法 PKCS#8 Base64（可带或不带 PEM 头尾）: ' . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * @param string $content
     * @param string $signBase64
     * @param string $publicKey
     * @return bool
     */
    public static function rsaVerify($content, $signBase64, $publicKey)
    {
        $key = false;
        try {
            $pem = self::toPem(KeyFormatUtil::normalize($publicKey), false);
            $key = openssl_pkey_get_public($pem);
            if ($key === false) {
                throw SfdSdkException::ofCode(
                    'VERIFY_ERROR',
                    'RSA 验签失败，请确认公钥为合法 X.509 Base64: ' . self::opensslErrors()
                );
            }
            $signature = base64_decode($signBase64, true);
            if ($signature === false) {
                throw SfdSdkException::ofCode(
                    'VERIFY_ERROR',
                    'RSA 验签失败，签名不是合法 Base64'
                );
            }
            $result = openssl_verify($content, $signature, $key, OPENSSL_ALGO_SHA256);
            return $result === 1;
        } catch (SfdSdkException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw SfdSdkException::ofCode(
                'VERIFY_ERROR',
                'RSA 验签失败，请确认公钥为合法 X.509 Base64: ' . $e->getMessage(),
                $e
            );
        } finally {
            if (is_resource($key)) {
                openssl_free_key($key);
            }
        }
    }

    /**
     * @param string $content
     * @param string $privateKey
     * @return string
     */
    public static function smSign($content, $privateKey)
    {
        try {
            $hexKey = Sm2KeyUtil::extractPrivateKeyHex($privateKey);
            $sm2 = new RtSm2('base64');
            $sign = $sm2->doSign($content, $hexKey, self::SM2_DEFAULT_USER_ID);
            if ($sign === null || $sign === false || $sign === '') {
                throw new \RuntimeException('SM2 签名返回空');
            }
            // RtSm2 可能在 Base64 末尾附带换行，写入 HTTP 头会触发 400
            return trim((string) $sign);
        } catch (SfdSdkException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw SfdSdkException::ofCode(
                'SIGN_ERROR',
                'SM2 签名失败，请确认私钥格式正确: ' . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * @param string $content
     * @param string $signBase64
     * @param string $publicKey
     * @return bool
     */
    public static function smVerify($content, $signBase64, $publicKey)
    {
        try {
            $hexPub = Sm2KeyUtil::extractPublicKeyHex($publicKey);
            // RtSm2 通常使用不含 04 前缀的 X||Y
            if (strlen($hexPub) === 130 && substr($hexPub, 0, 2) === '04') {
                $hexPub = substr($hexPub, 2);
            }
            $sm2 = new RtSm2('base64');
            return (bool) $sm2->verifySign($content, $signBase64, $hexPub, self::SM2_DEFAULT_USER_ID);
        } catch (SfdSdkException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw SfdSdkException::ofCode(
                'VERIFY_ERROR',
                'SM2 验签失败，请确认公钥格式正确:' . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * @param string $base64
     * @param bool $isPrivate
     * @return string
     */
    private static function toPem($base64, $isPrivate)
    {
        $label = $isPrivate ? 'PRIVATE KEY' : 'PUBLIC KEY';
        $wrapped = trim(chunk_split($base64, 64, "\n"));
        return "-----BEGIN {$label}-----\n{$wrapped}\n-----END {$label}-----";
    }

    /**
     * @return string
     */
    private static function opensslErrors()
    {
        $messages = array();
        while (($msg = openssl_error_string()) !== false) {
            $messages[] = $msg;
        }
        return empty($messages) ? 'unknown openssl error' : implode('; ', $messages);
    }
}
