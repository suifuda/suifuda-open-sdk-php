<?php

namespace Suifuda\Sdk\Config;

use Suifuda\Sdk\Exception\SfdSdkException;

/**
 * SDK 配置（不可变）。
 */
class SfdConfig
{
    const TEST_BASE_URL = 'https://open-api.test.suifuda.com';
    const PROD_BASE_URL = 'https://open-api.suifuda.com';
	
	/**
	 * 基础请求地址
	 * @var string|null
	 */
    private $baseUrl;
	
	/**
	 * 应用标识
	 * @var string
	 */
    private $appKey;
	
	/**
	 * 应用私钥
	 * @var string
	 */
    private $privateKey;
	
	/**
	 * 平台公钥
	 * @var string
	 */
    private $platformPublicKey;
	
	/**
	 * 签名算法类型：RSA,SM
	 * @var SignType
	 */
    private $signType;
	
	/**
	 * 连接超时时间：毫秒
	 * @var int|null
	 */
    private $connectTimeoutMs;
	
	/**
	 * 响应超时时间：毫秒
	 * @var int|null
	 */
    private $readTimeoutMs;
	
	/**
	 * 日志打印开关
	 * @var bool|null
	 */
    private $enableLog;
	
	/**
	 * 响应验签开关
	 * @var bool
	 */
    private $enableResponseVerify;
	
	/**
	 * 是否校验 HTTPS 证书
	 * @var bool|null
	 */
    private $sslVerify;

    private function __construct(SfdConfigBuilder $builder)
    {
        $this->baseUrl = self::trimTrailingSlash($builder->baseUrl);
        $this->appKey = $builder->appKey;
        $this->privateKey = $builder->privateKey;
        $this->platformPublicKey = $builder->platformPublicKey;
        $this->signType = $builder->signType;
        $this->connectTimeoutMs = $builder->connectTimeoutMs;
        $this->readTimeoutMs = $builder->readTimeoutMs;
        $this->enableLog = $builder->enableLog;
        $this->enableResponseVerify = $builder->enableResponseVerify;
        $this->sslVerify = $builder->sslVerify;
    }

    /**
     * @return SfdConfigBuilder
     */
    public static function builder()
    {
        return new SfdConfigBuilder();
    }

    /**
     * @internal
     * @param SfdConfigBuilder $builder
     * @return self
     */
    public static function fromBuilder(SfdConfigBuilder $builder)
    {
        return new self($builder);
    }

    /**
     * @param string|null $url
     * @return string|null
     */
    private static function trimTrailingSlash($url)
    {
        if ($url === null) {
            return null;
        }
        return substr($url, -1) === '/' ? substr($url, 0, -1) : $url;
    }

    /** @return string */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /** @return string */
    public function getAppKey()
    {
        return $this->appKey;
    }

    /** @return string */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /** @return string|null */
    public function getPlatformPublicKey()
    {
        return $this->platformPublicKey;
    }

    /** @return SignType */
    public function getSignType()
    {
        return $this->signType;
    }

    /** @return int */
    public function getConnectTimeoutMs()
    {
        return $this->connectTimeoutMs;
    }

    /** @return int */
    public function getReadTimeoutMs()
    {
        return $this->readTimeoutMs;
    }

    /** @return bool */
    public function isEnableLog()
    {
        return $this->enableLog;
    }

    /** @return bool */
    public function isEnableResponseVerify()
    {
        return $this->enableResponseVerify;
    }

    /** @return bool */
    public function isSslVerify()
    {
        return $this->sslVerify;
    }
}

/**
 * {@see SfdConfig} 构造器。
 */
class SfdConfigBuilder
{
    /** @var string */
    public $baseUrl = SfdConfig::PROD_BASE_URL;
    /** @var string|null */
    public $appKey;
    /** @var string|null */
    public $privateKey;
    /** @var string|null */
    public $platformPublicKey;
    /** @var SignType|null */
    public $signType;
    /** @var int */
    public $connectTimeoutMs = 10000;
    /** @var int */
    public $readTimeoutMs = 30000;
    /** @var bool */
    public $enableLog = false;
    /** @var bool */
    public $enableResponseVerify = true;
    /** @var bool */
    public $sslVerify = false;

    public function __construct()
    {
        $this->signType = SignType::RSA();
    }

    /**
     * @param string $baseUrl
     * @return $this
     */
    public function baseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * @return $this
     */
    public function useTestEnv()
    {
        $this->baseUrl = SfdConfig::TEST_BASE_URL;
        return $this;
    }

    /**
     * @return $this
     */
    public function useProdEnv()
    {
        $this->baseUrl = SfdConfig::PROD_BASE_URL;
        return $this;
    }

    /**
     * @param string $appKey
     * @return $this
     */
    public function appKey($appKey)
    {
        $this->appKey = $appKey;
        return $this;
    }

    /**
     * @param string $privateKey
     * @return $this
     */
    public function privateKey($privateKey)
    {
        $this->privateKey = $privateKey;
        return $this;
    }

    /**
     * @param string $platformPublicKey
     * @return $this
     */
    public function platformPublicKey($platformPublicKey)
    {
        $this->platformPublicKey = $platformPublicKey;
        return $this;
    }

    /**
     * @param SignType|string $signType
     * @return $this
     */
    public function signType($signType)
    {
        $this->signType = $signType instanceof SignType ? $signType : SignType::fromCode($signType);
        return $this;
    }

    /**
     * @param int $connectTimeoutMs
     * @return $this
     */
    public function connectTimeoutMs($connectTimeoutMs)
    {
        $this->connectTimeoutMs = (int) $connectTimeoutMs;
        return $this;
    }

    /**
     * 读超时（毫秒）。cURL 无独立读超时，实际整次请求上限为 connectTimeoutMs + readTimeoutMs。
     *
     * @param int $readTimeoutMs
     * @return $this
     */
    public function readTimeoutMs($readTimeoutMs)
    {
        $this->readTimeoutMs = (int) $readTimeoutMs;
        return $this;
    }

    /**
     * @param bool $enableLog
     * @return $this
     */
    public function enableLog($enableLog)
    {
        $this->enableLog = (bool) $enableLog;
        return $this;
    }

    /**
     * @param bool $enableResponseVerify
     * @return $this
     */
    public function enableResponseVerify($enableResponseVerify)
    {
        $this->enableResponseVerify = (bool) $enableResponseVerify;
        return $this;
    }

    /**
     * 是否校验 HTTPS 证书（测试环境证书主机名不一致时可临时关闭）。
     *
     * @param bool $sslVerify
     * @return $this
     */
    public function sslVerify($sslVerify)
    {
        $this->sslVerify = (bool) $sslVerify;
        return $this;
    }

    /**
     * @return SfdConfig
     */
    public function build()
    {
        if ($this->isBlank($this->baseUrl)) {
            throw SfdSdkException::of('baseUrl 不能为空');
        }
        if ($this->isBlank($this->appKey)) {
            throw SfdSdkException::of('appKey 不能为空');
        }
        if ($this->isBlank($this->privateKey)) {
            throw SfdSdkException::of('privateKey 不能为空');
        }
        if ($this->signType === null) {
            throw SfdSdkException::of('signType 不能为空');
        }
        if ($this->connectTimeoutMs <= 0 || $this->readTimeoutMs <= 0) {
            throw SfdSdkException::of('超时时间必须大于 0');
        }
        if ($this->enableResponseVerify && $this->isBlank($this->platformPublicKey)) {
            throw SfdSdkException::of('开启响应验签时必须配置 platformPublicKey');
        }
        return SfdConfig::fromBuilder($this);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isBlank($value)
    {
        return $value === null || trim((string) $value) === '';
    }
}
