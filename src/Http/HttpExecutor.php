<?php

namespace Suifuda\Sdk\Http;

use Suifuda\Sdk\Config\SfdConfig;
use Suifuda\Sdk\Exception\SfdSdkException;
use Suifuda\Sdk\Model\SfdHttpResponse;

/**
 * HTTP 执行器。
 */
class HttpExecutor
{
    /** @var SfdConfig */
    private $config;

    public function __construct(SfdConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $url
     * @param string $body
     * @param string $authorization
     * @return SfdHttpResponse
     */
    public function postJson($url, $body, $authorization)
    {
        $headers = array(
            'Authorization: ' . $authorization,
            'Content-Type: application/json;charset=UTF-8',
            'Accept: application/json',
        );

        if ($this->config->isEnableLog()) {
            error_log('SFD 请求地址：' . $url);
            error_log('SFD 请求头：' . json_encode($headers, JSON_UNESCAPED_UNICODE));
            error_log('SFD 请求参数：' . $body);
        }

        try {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException('curl_init failed');
            }

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $connectTimeoutMs = $this->config->getConnectTimeoutMs();
            $readTimeoutMs = $this->config->getReadTimeoutMs();
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connectTimeoutMs);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $connectTimeoutMs + $readTimeoutMs);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config->isSslVerify());
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->config->isSslVerify() ? 2 : 0);

            $raw = curl_exec($ch);
            if ($raw === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException($err);
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $headerText = substr($raw, 0, $headerSize);
            $responseBody = substr($raw, $headerSize);
            $parsedHeaders = self::parseHeaders($headerText);

            $result = new SfdHttpResponse($statusCode, $responseBody, $parsedHeaders);

            if ($this->config->isEnableLog()) {
                error_log('SFD 响应状态：' . $result->getStatusCode());
                error_log('SFD 响应内容：' . $result->getBody());
                error_log('SFD 响应头：' . json_encode($result->getHeaders(), JSON_UNESCAPED_UNICODE));
            }
            return $result;
        } catch (SfdSdkException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->config->isEnableLog()) {
                error_log('SFD 请求失败：' . $e->getMessage());
            }
            throw SfdSdkException::ofCode('HTTP_ERROR', '请求失败: ' . $e->getMessage(), $e);
        }
    }

    /**
     * @param string $headerText
     * @return array
     */
    private static function parseHeaders($headerText)
    {
        $headers = array();
        $lines = preg_split('/\r\n|\n|\r/', (string) $headerText);
        if (!is_array($lines)) {
            return $headers;
        }
        foreach ($lines as $line) {
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }
            $parts = explode(':', $line, 2);
            $name = trim($parts[0]);
            $value = isset($parts[1]) ? trim($parts[1]) : '';
            if ($name === '') {
                continue;
            }
            if (!isset($headers[$name])) {
                $headers[$name] = array();
            }
            $headers[$name][] = $value;
        }
        return $headers;
    }
}
