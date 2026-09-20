<?php

namespace Suifuda\Sdk\Exception;

use RuntimeException;
use Throwable;

/**
 * SDK 统一异常。
 */
class SfdSdkException extends RuntimeException
{
    /** @var string */
    private $errorCode;

    public function __construct($message, $code = 'SDK_ERROR', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $code === null || $code === '' ? 'SDK_ERROR' : (string) $code;
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @param string $message
     * @return self
     */
    public static function of($message)
    {
        return new self($message);
    }

    /**
     * @param string $code
     * @param string $message
     * @param Throwable|null $previous
     * @return self
     */
    public static function ofCode($code, $message, ?Throwable $previous = null)
    {
        return new self($message, $code, $previous);
    }
}
