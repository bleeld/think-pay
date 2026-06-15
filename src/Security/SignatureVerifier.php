<?php
declare(strict_types=1);

namespace bleeld\pay\Security;

use bleeld\pay\Exception\SignatureException;

/**
 * 签名验证器
 * 提供支付宝和微信支付的签名验证功能
 */
class SignatureVerifier
{
    /**
     * 验证支付宝RSA2签名
     * 
     * @param array $params 回调参数
     * @param string $publicKey 支付宝公钥
     * @return bool
     */
    public static function verifyAlipay(array $params, string $publicKey): bool
    {
        if (!isset($params['sign']) || !isset($params['sign_type'])) {
            return false;
        }

        $sign = $params['sign'];
        $signType = $params['sign_type'];
        
        // 移除签名相关字段
        unset($params['sign'], $params['sign_type']);
        
        // 参数排序并拼接
        ksort($params);
        $signString = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $signString .= "{$key}={$value}&";
            }
        }
        $signString = rtrim($signString, '&');
        
        // 格式化公钥
        $publicKey = self::formatPublicKey($publicKey);
        
        // 验证签名
        if ($signType === 'RSA2') {
            return openssl_verify(
                $signString,
                base64_decode($sign),
                $publicKey,
                OPENSSL_ALGO_SHA256
            ) === 1;
        } elseif ($signType === 'RSA') {
            return openssl_verify(
                $signString,
                base64_decode($sign),
                $publicKey,
                OPENSSL_ALGO_SHA1
            ) === 1;
        }
        
        return false;
    }

    /**
     * 验证微信支付签名
     * 
     * @param array $params 回调参数
     * @param string $apiKey API密钥
     * @param string $signType 签名类型 MD5/HMAC-SHA256
     * @return bool
     */
    public static function verifyWechat(array $params, string $apiKey, string $signType = 'MD5'): bool
    {
        if (!isset($params['sign'])) {
            return false;
        }

        $sign = $params['sign'];
        unset($params['sign']);
        
        // 参数排序并拼接
        ksort($params);
        $signString = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $signString .= "{$key}={$value}&";
            }
        }
        $signString .= "key={$apiKey}";
        
        // 计算签名
        if ($signType === 'HMAC-SHA256') {
            $calculatedSign = strtoupper(hash_hmac('sha256', $signString, $apiKey));
        } else {
            $calculatedSign = strtoupper(md5($signString));
        }
        
        return $sign === $calculatedSign;
    }

    /**
     * 通用RSA签名验证
     * 
     * @param string $data 原始数据
     * @param string $signature 签名
     * @param string $publicKey 公钥
     * @param string $algorithm 算法 SHA256/SHA1
     * @return bool
     */
    public static function verifyRsa(string $data, string $signature, string $publicKey, string $algorithm = 'SHA256'): bool
    {
        $publicKey = self::formatPublicKey($publicKey);
        
        $algo = $algorithm === 'SHA256' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
        
        return openssl_verify(
            $data,
            base64_decode($signature),
            $publicKey,
            $algo
        ) === 1;
    }

    /**
     * 生成支付宝RSA2签名
     * 
     * @param array $params 参数
     * @param string $privateKey 私钥
     * @return string
     */
    public static function generateAlipaySign(array $params, string $privateKey): string
    {
        // 移除空值和签名相关字段
        unset($params['sign'], $params['sign_type']);
        
        // 参数排序并拼接
        ksort($params);
        $signString = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $signString .= "{$key}={$value}&";
            }
        }
        $signString = rtrim($signString, '&');
        
        // 格式化私钥
        $privateKey = self::formatPrivateKey($privateKey);
        
        // 生成签名
        openssl_sign($signString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        return base64_encode($signature);
    }

    /**
     * 生成微信支付签名
     * 
     * @param array $params 参数
     * @param string $apiKey API密钥
     * @param string $signType 签名类型
     * @return string
     */
    public static function generateWechatSign(array $params, string $apiKey, string $signType = 'MD5'): string
    {
        // 移除空值和签名
        unset($params['sign']);
        
        // 参数排序并拼接
        ksort($params);
        $signString = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $signString .= "{$key}={$value}&";
            }
        }
        $signString .= "key={$apiKey}";
        
        // 计算签名
        if ($signType === 'HMAC-SHA256') {
            return strtoupper(hash_hmac('sha256', $signString, $apiKey));
        } else {
            return strtoupper(md5($signString));
        }
    }

    /**
     * 格式化公钥
     */
    private static function formatPublicKey(string $publicKey): string
    {
        $publicKey = trim($publicKey);
        
        // 如果已经包含BEGIN PUBLIC KEY，直接返回
        if (strpos($publicKey, '-----BEGIN PUBLIC KEY-----') !== false) {
            return $publicKey;
        }
        
        // 添加PEM格式头尾
        return "-----BEGIN PUBLIC KEY-----\n" . 
               wordwrap($publicKey, 64, "\n", true) . 
               "\n-----END PUBLIC KEY-----";
    }

    /**
     * 格式化私钥
     */
    private static function formatPrivateKey(string $privateKey): string
    {
        $privateKey = trim($privateKey);
        
        // 如果已经包含BEGIN PRIVATE KEY或BEGIN RSA PRIVATE KEY，直接返回
        if (strpos($privateKey, '-----BEGIN') !== false) {
            return $privateKey;
        }
        
        // 添加PEM格式头尾
        return "-----BEGIN RSA PRIVATE KEY-----\n" . 
               wordwrap($privateKey, 64, "\n", true) . 
               "\n-----END RSA PRIVATE KEY-----";
    }
}
