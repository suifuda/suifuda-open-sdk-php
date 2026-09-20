<?php

namespace Suifuda\Sdk;

use Suifuda\Sdk\Auth\AuthorizationBuilder;
use Suifuda\Sdk\Auth\ResponseHeaders;
use Suifuda\Sdk\Auth\ResponseVerifier;
use Suifuda\Sdk\Config\SfdConfig;
use Suifuda\Sdk\Exception\SfdSdkException;
use Suifuda\Sdk\Http\HttpExecutor;
use Suifuda\Sdk\Model\SfdHttpResponse;

/**
 * 随付达开放平台 SDK 客户端。
 *
 * 建议全局单例复用。
 *
 * <code>
 * $sfdConfig = SfdConfig::builder()
 *     ->useTestEnv()
 *     ->appKey('your_app_key')
 *     ->privateKey('your_private_key')
 *     ->signType(SignType::RSA())
 *     ->platformPublicKey('platform_public_key')
 *     ->build();
 * $client = SfdClient::create($sfdConfig);
 *
 * $httpResponse = $client->execute('/open/trade/v1/pay/barcode', array(
 *     'orderNo' => '20260917001',
 *     'authCode' => '134567890123456789',
 * ));
 * $response = $httpResponse->getResponse();
 * </code>
 */
class SfdClient
{
    /** @var SfdConfig */
    private $config;
    /** @var HttpExecutor */
    private $httpExecutor;

    public function __construct(SfdConfig $config)
    {
        $this->config = $config;
        $this->httpExecutor = new HttpExecutor($config);
    }

    /**
     * @param SfdConfig $config
     * @return self
     */
    public static function create(SfdConfig $config)
    {
        return new self($config);
    }

    /**
     * @return SfdConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * POST JSON，并返回完整 HTTP 响应（按配置自动验签）。
     *
     * @param string $path
     * @param mixed $body array|object|string|null
     * @return SfdHttpResponse
     */
    public function execute($path, $body = null)
    {
        $url = $this->resolveUrl($path);
        $bodyJson = self::toJson($body);
        $authorization = AuthorizationBuilder::buildFromConfig($this->config, $bodyJson);
        return $this->afterResponse($this->httpExecutor->postJson($url, $bodyJson, $authorization));
    }

    /**
     * @param mixed $body
     * @return string
     */
    public function buildAuthorization($body)
    {
        return AuthorizationBuilder::buildFromConfig($this->config, self::toJson($body));
    }

    /**
     * @param string $bodyJson
     * @return string
     */
    public function buildAuthorizationFromJson($bodyJson)
    {
        return AuthorizationBuilder::buildFromConfig($this->config, $bodyJson);
    }

    /**
     * @param SfdHttpResponse $httpResponse
     * @return bool
     */
    public function verifyResponse(SfdHttpResponse $httpResponse)
    {
        return ResponseVerifier::verify(
            $this->config->getPlatformPublicKey(),
	        $httpResponse
        );
    }

    /**
     * @param array $headers
     * @param string $body
     * @return bool
     */
    public function verifyNotify(array $headers, $body)
    {
        return self::verifyNotifyStatic(
            $this->config->getPlatformPublicKey(),
            $headers,
            $body
        );
    }

    /**
     * @param SfdHttpResponse $notifyRequest
     * @return bool
     */
    public function verifyNotifyResponse(SfdHttpResponse $notifyRequest)
    {
        return ResponseVerifier::verify(
            $this->config->getPlatformPublicKey(),
            $notifyRequest
        );
    }

    /**
     * @param string $platformPublicKey
     * @param array $headers
     * @param string $body
     * @return bool
     */
    public static function verifyNotifyStatic($platformPublicKey, array $headers, $body)
    {
        if ($platformPublicKey === null || trim((string) $platformPublicKey) === '') {
            throw SfdSdkException::ofCode('VERIFY_ERROR', 'platformPublicKey 不能为空');
        }
        $notify = self::toNotifyResponse($headers, $body);
        return ResponseVerifier::verify($platformPublicKey, $notify);
    }

    /**
     * @param string $appKey
     * @param string $nonce
     * @param string $sign
     * @param string $timestamp
     * @param string $signType
     * @param string $body
     * @return bool
     */
    public function verifyNotifyFields($appKey,$nonce, $sign, $timestamp, $signType, $body)
    {
        $headers = array(
            ResponseHeaders::APP_KEY => $appKey,
            ResponseHeaders::NONCE => $nonce,
            ResponseHeaders::SIGN => $sign,
            ResponseHeaders::TIMESTAMP => $timestamp,
            ResponseHeaders::SIGN_TYPE => $signType,
        );
        return $this->verifyNotify($headers, $body);
    }

    /**
     * @param SfdHttpResponse $httpResponse
     * @return SfdHttpResponse
     */
    private function afterResponse(SfdHttpResponse $httpResponse)
    {
        if (!$this->config->isEnableResponseVerify()) {
            return $httpResponse;
        }
        $verified = ResponseVerifier::verifyIfPresentOrThrow(
            $this->config->getPlatformPublicKey(),
	        $httpResponse
        );
        return $verified ? $httpResponse->withSignVerified(true) : $httpResponse;
    }

    /**
     * @param string $path
     * @return string
     */
    private function resolveUrl($path)
    {
        if ($path === null || trim((string) $path) === '') {
            throw SfdSdkException::of('path 不能为空');
        }
        $path = (string) $path;
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        if (isset($path[0]) && $path[0] === '/') {
            return $this->config->getBaseUrl() . $path;
        }
        return $this->config->getBaseUrl() . '/' . $path;
    }

    /**
     * @param mixed $body
     * @return string
     */
    private static function toJson($body)
    {
        if ($body === null || (is_array($body) && count($body) === 0)) {
            return '{}';
        }
        if (is_string($body)) {
            return $body;
        }
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw SfdSdkException::of('JSON 编码失败: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * @param array $headers
     * @param string|null $body
     * @return SfdHttpResponse
     */
    private static function toNotifyResponse(array $headers, $body)
    {
        return new SfdHttpResponse(200, $body === null ? '' : (string) $body, $headers);
    }
}


