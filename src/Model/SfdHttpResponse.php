<?php

namespace Suifuda\Sdk\Model;

use Suifuda\Sdk\Auth\ResponseHeaders;
use Suifuda\Sdk\Auth\ResponseVerifier;
use Suifuda\Sdk\Exception\SfdSdkException;

/**
 * HTTP 响应封装。
 */
class SfdHttpResponse
{
    /** @var int */
    private $statusCode;
    /** @var string */
    private $body;
    /** @var array */
    private $headers;
    /** @var bool */
    private $signVerified;

    /**
     * @param int $statusCode
     * @param string $body
     * @param array $headers header name => string|string[]
     * @param bool $signVerified
     */
    public function __construct($statusCode, $body, array $headers = array(), $signVerified = false)
    {
        $this->statusCode = (int) $statusCode;
        $this->body = $body === null ? '' : (string) $body;
        $this->headers = self::normalizeHeaders($headers);
        $this->signVerified = (bool) $signVerified;
    }

    /**
     * @param array $headers
     * @return array
     */
    private static function normalizeHeaders(array $headers)
    {
        $normalized = array();
        foreach ($headers as $name => $value) {
            if ($name === null) {
                continue;
            }
            if (is_array($value)) {
                $values = array();
                foreach ($value as $item) {
                    if ($item !== null) {
                        $values[] = (string) $item;
                    }
                }
                $normalized[(string) $name] = $values;
            } else {
                $normalized[(string) $name] = array((string) $value);
            }
        }
        return $normalized;
    }

    /** @return int */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /** @return string */
    public function getBody()
    {
        return $this->body;
    }

    /** @return array */
    public function getHeaders()
    {
        return $this->headers;
    }

    /** @return bool */
    public function isSignVerified()
    {
        return $this->signVerified;
    }

    /** @return bool */
    public function isOk()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getHeader($name)
    {
        return ResponseVerifier::firstHeader($this->headers, $name);
    }

    /** @return string|null */
    public function getNonce()
    {
        return $this->getHeader(ResponseHeaders::NONCE);
    }

    /** @return string|null */
    public function getSign()
    {
        return $this->getHeader(ResponseHeaders::SIGN);
    }

    /** @return string|null */
    public function getTimestamp()
    {
        return $this->getHeader(ResponseHeaders::TIMESTAMP);
    }

    /** @return string|null */
    public function getSignType()
    {
        return $this->getHeader(ResponseHeaders::SIGN_TYPE);
    }
	
	/** @return string|null */
	public function getAppKey()
	{
		return $this->getHeader(ResponseHeaders::APP_KEY);
	}

    /**
     * 将 body 解析为业务响应模型。
     *
     * 仅允许在 HTTP 2xx 时调用。非 2xx（如网关 500）可能无签名头，
     *
     * @return SfdResponse
     */
    public function getResponse()
    {
        if (!$this->isOk()) {
            throw SfdSdkException::ofCode(
                'HTTP_ERROR',
                'HTTP 状态码非 2xx（' . $this->statusCode . '），拒绝解析业务响应，请检查 getBody()'
            );
        }
        return SfdResponse::fromJson($this->body);
    }

    /**
     * @param bool $verified
     * @return self
     */
    public function withSignVerified($verified)
    {
        return new self($this->statusCode, $this->body, $this->headers, $verified);
    }
}
