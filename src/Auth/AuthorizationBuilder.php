<?php

namespace Suifuda\Sdk\Auth;

use Suifuda\Sdk\Config\SignType;
use Suifuda\Sdk\Config\SfdConfig;
use Suifuda\Sdk\Util\RequestIdUtil;
use Suifuda\Sdk\Util\SignUtil;

/**
 * Authorization 请求头构造器。
 *
 * 格式：
 * Authorization: SFD appKey="...",nonce="...",sign="...",signType="RSA",timestamp="..."
 *
 * 签名字段包含：appKey、timestamp、signType、nonce、data（请求体 JSON），
 * 其中 data 仅参与签名，不放入 Authorization。
 */
class AuthorizationBuilder
{
    const AUTH_SCHEME = 'SFD';

    /**
     * @param SfdConfig $config
     * @param string $bodyJson
     * @return string
     */
    public static function buildFromConfig(SfdConfig $config, $bodyJson)
    {
        return self::build(
            $config->getAppKey(),
            $config->getPrivateKey(),
            $config->getSignType(),
            $bodyJson,
            RequestIdUtil::currentTimestamp(),
            RequestIdUtil::generateNonce()
        );
    }

    /**
     * @param string $appKey
     * @param string $privateKey
     * @param SignType $signType
     * @param string|null $bodyJson
     * @param string $timestamp
     * @param string $nonce
     * @return string
     */
    public static function build($appKey, $privateKey, SignType $signType, $bodyJson, $timestamp, $nonce)
    {
        $signParams = array(
            'appKey' => $appKey,
            'timestamp' => $timestamp,
            'signType' => $signType->getCode(),
            'nonce' => $nonce,
            'data' => $bodyJson === null ? '' : (string) $bodyJson,
        );

        $sign = SignUtil::sign($signParams, $privateKey, $signType);
        $signParams['sign'] = $sign;

        ksort($signParams, SORT_STRING);
        $parts = array();
        foreach ($signParams as $key => $value) {
            if ($key === 'data') {
                continue;
            }
            if ($value === null || (string) $value === '') {
                continue;
            }
            $parts[] = $key . '="' . $value . '"';
        }

        return self::AUTH_SCHEME . ' ' . implode(',', $parts);
    }
}
