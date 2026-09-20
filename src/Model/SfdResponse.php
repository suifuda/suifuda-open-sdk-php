<?php

namespace Suifuda\Sdk\Model;

use Suifuda\Sdk\Exception\SfdSdkException;

/**
 * 开放平台业务响应体。
 *
 * {"code":200,"data":object,"message":"","timestamp":"2026-09-18 15:12:14"}
 */
class SfdResponse
{
    /**
     * 业务成功码。
     */
    const SUCCESS_CODE = 200;

    /** @var int|null */
    private $code;
    /** @var mixed */
    private $data;
    /** @var string|null */
    private $message;
    /** @var mixed 字符串时间或数值时间戳 */
    private $timestamp;

    /**
     * @param int|null $code
     * @param mixed $data
     * @param string|null $message
     * @param mixed $timestamp
     */
    public function __construct($code = null, $data = null, $message = null, $timestamp = null)
    {
        $this->code = $code === null ? null : (int) $code;
        $this->data = $data;
        $this->message = $message === null ? null : (string) $message;
        $this->timestamp = $timestamp;
    }

    /**
     * 从响应 Body JSON 解析。
     *
     * @param string $body
     * @return self
     */
    public static function fromJson($body)
    {
        if ($body === null || trim((string) $body) === '') {
            throw SfdSdkException::ofCode('PARSE_ERROR', '响应 body 为空，无法解析业务结果');
        }
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw SfdSdkException::ofCode('PARSE_ERROR', '解析业务响应失败: ' . json_last_error_msg());
        }
        return new self(
            isset($decoded['code']) ? $decoded['code'] : null,
            array_key_exists('data', $decoded) ? $decoded['data'] : null,
            isset($decoded['message']) ? $decoded['message'] : null,
            array_key_exists('timestamp', $decoded) ? $decoded['timestamp'] : null
        );
    }

    /**
     * 业务是否成功（code == SUCCESS_CODE）。
     *
     * @return bool
     */
    public function isSuccess()
    {
        return $this->code !== null && (int) $this->code === self::SUCCESS_CODE;
    }

    /** @return int|null */
    public function getCode()
    {
        return $this->code;
    }

    /** @return mixed */
    public function getData()
    {
        return $this->data;
    }

    /** @return string|null */
    public function getMessage()
    {
        return $this->message;
    }

    /** @return mixed */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * timestamp 字符串形式。
     *
     * @return string|null
     */
    public function getTimestampAsString()
    {
        return $this->timestamp === null ? null : (string) $this->timestamp;
    }
}
