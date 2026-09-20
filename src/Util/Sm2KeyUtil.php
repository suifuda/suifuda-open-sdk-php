<?php

namespace Suifuda\Sdk\Util;

use FG\ASN1\ASNObject;
use FG\ASN1\Universal\BitString;
use FG\ASN1\Universal\Integer;
use FG\ASN1\Universal\OctetString;
use FG\ASN1\Universal\Sequence;
use Suifuda\Sdk\Exception\SfdSdkException;

/**
 * 从 PKCS#8 / X.509 中按 ASN.1 结构提取 SM2 原始密钥，供国密库使用。
 */
class Sm2KeyUtil
{
    /**
     * 提取 SM2 私钥 D（64 位小写十六进制）。
     *
     * 支持：
     * - 64 位 hex（已是原始 D）
     * - PKCS#8 PrivateKeyInfo（BEGIN PRIVATE KEY）
     * - 裸 ECPrivateKey（RFC 5915）
     *
     * @param string $privateKeyBase64 PKCS#8 Base64 或 64 位 hex
     * @return string
     */
    public static function extractPrivateKeyHex($privateKeyBase64)
    {
        $raw = KeyFormatUtil::normalize($privateKeyBase64);
        if (preg_match('/^[0-9a-fA-F]{64}$/', $raw)) {
            return strtolower($raw);
        }

        $der = base64_decode($raw, true);
        if ($der === false) {
            throw SfdSdkException::ofCode('KEY_ERROR', 'SM2 私钥 Base64 解码失败');
        }

        try {
            $asn = ASNObject::fromBinary($der);
            if (!($asn instanceof Sequence)) {
                throw new \RuntimeException('顶层不是 SEQUENCE');
            }
            $ecPrivateKey = self::resolveEcPrivateKey($asn);
            if (!isset($ecPrivateKey[1]) || !($ecPrivateKey[1] instanceof OctetString)) {
                throw new \RuntimeException('缺少 ECPrivateKey.privateKey');
            }
            $hex = strtolower($ecPrivateKey[1]->getContent());
            $hex = ltrim($hex, '0');
            if ($hex === '') {
                $hex = '0';
            }
            $hex = str_pad($hex, 64, '0', STR_PAD_LEFT);
            if (strlen($hex) !== 64 || !preg_match('/^[0-9a-f]{64}$/', $hex)) {
                throw new \RuntimeException('私钥 D 长度无效');
            }
            return $hex;
        } catch (SfdSdkException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw SfdSdkException::ofCode('KEY_ERROR', '无法从 PKCS#8 中解析 SM2 私钥: ' . $e->getMessage());
        }
    }

    /**
     * 提取 SM2 公钥（未压缩点：04 || X || Y 的十六进制，或 X||Y）。
     *
     * 支持：
     * - 128/130 位 hex（X||Y 或 04||X||Y）
     * - X.509 SubjectPublicKeyInfo（BEGIN PUBLIC KEY）
     *
     * @param string $publicKeyBase64 X.509 SubjectPublicKeyInfo Base64 或 hex
     * @return string hex
     */
    public static function extractPublicKeyHex($publicKeyBase64)
    {
        $raw = KeyFormatUtil::normalize($publicKeyBase64);
        if (preg_match('/^[0-9a-fA-F]{128}$/', $raw) || preg_match('/^[0-9a-fA-F]{130}$/', $raw)) {
            return strtolower($raw);
        }

        $der = base64_decode($raw, true);
        if ($der === false) {
            throw SfdSdkException::ofCode('KEY_ERROR', 'SM2 公钥 Base64 解码失败');
        }

        try {
            $asn = ASNObject::fromBinary($der);
            if (!($asn instanceof Sequence) || !isset($asn[1]) || !($asn[1] instanceof BitString)) {
                throw new \RuntimeException('不是有效的 SubjectPublicKeyInfo');
            }
            $hex = strtolower($asn[1]->getContent());
            if (preg_match('/^04[0-9a-f]{128}$/', $hex) || preg_match('/^[0-9a-f]{128}$/', $hex)) {
                return $hex;
            }
            throw new \RuntimeException('公钥点格式无效');
        } catch (SfdSdkException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw SfdSdkException::ofCode('KEY_ERROR', '无法从 X.509 中解析 SM2 公钥: ' . $e->getMessage());
        }
    }

    /**
     * 从 PKCS#8 PrivateKeyInfo 或裸 ECPrivateKey 解析出 ECPrivateKey SEQUENCE。
     *
     * @param Sequence $asn
     * @return Sequence
     */
    private static function resolveEcPrivateKey(Sequence $asn)
    {
        // PKCS#8 PrivateKeyInfo: version(0) + AlgorithmIdentifier + privateKey OCTET STRING
        if (
            $asn->getNumberOfChildren() >= 3
            && $asn[0] instanceof Integer
            && (string) $asn[0]->getContent() === '0'
            && $asn[2] instanceof OctetString
        ) {
            $innerDer = hex2bin($asn[2]->getContent());
            if ($innerDer === false) {
                throw new \RuntimeException('privateKey OCTET STRING 无效');
            }
            $inner = ASNObject::fromBinary($innerDer);
            if (!($inner instanceof Sequence)) {
                throw new \RuntimeException('ECPrivateKey 不是 SEQUENCE');
            }
            return $inner;
        }

        // 裸 ECPrivateKey (RFC 5915): version(1) + privateKey OCTET STRING + ...
        if (
            $asn->getNumberOfChildren() >= 2
            && $asn[0] instanceof Integer
            && (string) $asn[0]->getContent() === '1'
            && $asn[1] instanceof OctetString
        ) {
            return $asn;
        }

        throw new \RuntimeException('无法识别为 PKCS#8 或 ECPrivateKey');
    }
}
