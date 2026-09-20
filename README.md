# 随付达开放平台 PHP SDK

对外对外 API 调用的 PHP SDK，封装请求签名、Authorization 头构造与 HTTP 调用。

## 环境要求

- PHP **7.2+**（兼容 PHP 7 / 8）
- 扩展：`openssl`、`curl`、`json`、`gmp`（国密 SM2 需要）
- Composer

## 快速开始

### 1. 引入依赖

```bash
composer require suifuda/suifuda-open-sdk
```

本地开发（path 仓库）：

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../php-sdk"
    }
  ],
  "require": {
    "suifuda/open-sdk": "*"
  }
}
```

### 2. 创建客户端

```php
use Suifuda\Sdk\SfdClient;
use Suifuda\Sdk\Config\SignType;
use Suifuda\Sdk\Config\SfdConfig;

$sfdConfig = SfdConfig::builder()
    ->useTestEnv()                          // 或 ->useProdEnv()
    ->appKey('YOUR_APP_KEY')
    ->privateKey('YOUR_PRIVATE_KEY')        // Base64，可不含 PEM 头尾
    ->signType(SignType::RSA())             // 或 SignType::SM() / 'SM'
    ->platformPublicKey('PLATFORM_PUBLIC_KEY')
    ->enableLog(true)
    ->enableResponseVerify(true)           // HTTP 2xx 强制验签；非 2xx 无签名头则跳过
    ->sslVerify(true)                       // 测试证书异常时可临时 false
    ->build();
$client = SfdClient::create($sfdConfig);
```

### 3. 发起业务请求

```php
$httpResponse = $client->execute('/open/trade/v1/pay/barcode', array(
    'orderNo' => '20260917001',
    'authCode' => '134567890123456789',
));

echo $httpResponse->getStatusCode();
echo $httpResponse->getBody();

// getResponse() 仅允许 HTTP 2xx；开启验签时 2xx 必须验签通过
$response = $httpResponse->getResponse();
if ($response->isSuccess()) {
    $response->getData();
}
```

### 4. 异步通知验签

```php
$ok = $client->verifyNotify($requestHeaders, $rawBody);
// 或
$ok2 = $client->verifyNotifyFields($appKey,$nonce, $sign, $timestamp, $signType, $rawBody);
```

验签通过后再解析 Body；Body 请使用 HTTP 原始字符串，勿重新序列化。

## 签名规则

参与签名字段：`appKey`、`timestamp`、`signType`、`nonce`、`data`（请求体 JSON 原文）。

1. 排除 `sign`
2. 排除空值
3. 按参数名 ASCII 字典序排序
4. 拼成 `key=value&key=value`
5. RSA 使用 `SHA256withRSA`，SM 使用 SM2（默认 userId：`1234567812345678`）
6. 签名结果做 Base64

`data` 仅参与签名，不放入 Authorization。

Authorization 示例：

```http
Authorization: SFD appKey="YOUR_APP_KEY",nonce="...",sign="...",signType="RSA",timestamp="yyyy-MM-dd HH:mm:ss"
```

## 环境地址

| 环境 | 方法 | Base URL |
|------|------|----------|
| 测试 | `useTestEnv()` | `https://open-api.test.suifuda.com` |
| 生产 | `useProdEnv()` | `https://open-api.suifuda.com` |

## 目录结构

```text
Suifuda\Sdk
├── SfdClient
├── Auth\AuthorizationBuilder
├── Auth\ResponseVerifier
├── Config\SfdConfig
├── Config\SignType
├── Util\SignUtil
├── Http\HttpExecutor
├── Model\SfdHttpResponse
├── Model\SfdResponse
└── Exception\SfdSdkException
```
