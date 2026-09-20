<?php

namespace Suifuda\Sdk\Config;

use Suifuda\Sdk\Exception\SfdSdkException;

/**
 * 签名算法类型。
 */
class SignType
{
    const RSA = 'RSA';
    const SM = 'SM';

    /** @var string */
    private $code;

    private function __construct($code)
    {
        $this->code = $code;
    }

    /**
     * @return self
     */
    public static function RSA()
    {
        return new self(self::RSA);
    }

    /**
     * @return self
     */
    public static function SM()
    {
        return new self(self::SM);
    }

    /**
     * @param string $code
     * @return self
     */
    public static function fromCode($code)
    {
        if ($code === null || trim((string) $code) === '') {
            throw new SfdSdkException('signType 不能为空');
        }
        $normalized = strtoupper(trim((string) $code));
        if ($normalized === self::RSA) {
            return self::RSA();
        }
        if ($normalized === self::SM) {
            return self::SM();
        }
        throw new SfdSdkException('不支持的 signType: ' . $code);
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return bool
     */
    public function isRsa()
    {
        return $this->code === self::RSA;
    }

    /**
     * @return bool
     */
    public function isSm()
    {
        return $this->code === self::SM;
    }
}
