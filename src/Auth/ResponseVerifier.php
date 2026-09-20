<?php

namespace Suifuda\Sdk\Auth;

use Suifuda\Sdk\Config\SignType;
use Suifuda\Sdk\Exception\SfdSdkException;
use Suifuda\Sdk\Model\SfdHttpResponse;
use Suifuda\Sdk\Util\SignUtil;

/**
 * 响应验签。
 */
class ResponseVerifier
{
    /**
     * @param SfdHttpResponse|null $httpResponse
     * @return bool
     */
    public static function hasSignHeaders($httpResponse)
    {
        if ($httpResponse === null) {
            return false;
        }
        return !self::isBlank($httpResponse->getNonce())
            && !self::isBlank($httpResponse->getSign())
            && !self::isBlank($httpResponse->getTimestamp())
            && !self::isBlank($httpResponse->getSignType())
	        && !self::isBlank($httpResponse->getAppKey());
    }

    /**
     * @param string $platformPublicKey
     * @param SfdHttpResponse $httpResponse
     * @return bool
     */
    public static function verify($platformPublicKey, SfdHttpResponse $httpResponse)
    {
        if (!self::hasSignHeaders($httpResponse)) {
            return false;
        }
        if ($platformPublicKey === null || trim((string) $platformPublicKey) === '') {
            throw SfdSdkException::ofCode('VERIFY_ERROR', '未配置 platformPublicKey，无法验签');
        }

        $nonce = $httpResponse->getNonce();
        $sign = $httpResponse->getSign();
        $timestamp = $httpResponse->getTimestamp();
        $signTypeCode = $httpResponse->getSignType();
        $appKey = $httpResponse->getAppKey();

        $signParams = array(
            'appKey' => $appKey,
            'timestamp' => $timestamp,
            'signType' => $signTypeCode,
            'nonce' => $nonce,
            'data' => $httpResponse->getBody() === null ? '' : $httpResponse->getBody(),
        );

        return SignUtil::verify($signParams, $sign, $platformPublicKey, SignType::fromCode($signTypeCode));
    }

    /**
     * HTTP 2xx：必须有完整签名头且验签通过，否则抛异常。
     * 非 2xx：有签名头则验签，无签名头则跳过（如 400 错误响应）。
     *
     * @param string $platformPublicKey
     * @param SfdHttpResponse $httpResponse
     * @return bool true=已验签通过；false=非 2xx 且无签名头已跳过
     */
    public static function verifyIfPresentOrThrow($platformPublicKey, SfdHttpResponse $httpResponse)
    {
        if (!self::hasSignHeaders($httpResponse)) {
            if ($httpResponse->isOk()) {
                throw SfdSdkException::ofCode('VERIFY_ERROR', 'HTTP 2xx 响应缺少签名头，无法验签');
            }
            return false;
        }
        if (!self::verify($platformPublicKey, $httpResponse)) {
            throw SfdSdkException::ofCode('VERIFY_ERROR', '响应验签失败');
        }
        return true;
    }

    /**
     * @param array $headers
     * @param string $name
     * @return string|null
     */
    public static function firstHeader(array $headers, $name)
    {
        if (empty($headers) || $name === null || trim((string) $name) === '') {
            return null;
        }
        foreach ($headers as $key => $value) {
            if ($key !== null && strcasecmp((string) $key, (string) $name) === 0) {
                if (is_array($value)) {
                    if (empty($value)) {
                        return null;
                    }
                    $first = reset($value);
                    return self::isBlank($first) ? null : (string) $first;
                }
                return self::isBlank($value) ? null : (string) $value;
            }
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private static function isBlank($value)
    {
        return $value === null || trim((string) $value) === '';
    }
}
