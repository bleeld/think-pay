<?php
declare(strict_types=1);

namespace bleeld\pay;

/**
 * 支付驱动基础抽象类
 * 提供所有驱动共用的功能
 */
abstract class BaseDriver implements DriverInterface
{
    /**
     * 驱动配置
     */
    protected array $config = [];

    /**
     * 驱动名称
     */
    protected string $name = '';

    /**
     * 设置配置
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * 获取配置项
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * HTTP请求封装
     */
    protected function httpRequest(
        string $url, 
        mixed $data = null, 
        string $method = 'POST', 
        array $headers = []
    ): string {
        $ch = curl_init();
        
        $timeout = $this->getConfig('timeout', 30);
        
        if (strtoupper($method) === 'GET' && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        } elseif (!empty($data)) {
            if (is_array($data)) {
                // 检测是否为XML
                $isXml = isset($headers['Content-Type']) && strpos($headers['Content-Type'], 'xml') !== false;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $isXml ? $this->arrayToXml($data) : http_build_query($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $this->buildHeaders($headers),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        if (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($response === false || $httpCode >= 400) {
            throw new \Exception("HTTP请求失败: {$error} (HTTP {$httpCode})");
        }
        
        return $response;
    }

    /**
     * 构建请求头
     */
    protected function buildHeaders(array $headers): array
    {
        $defaultHeaders = [
            'User-Agent: Think-Pay/1.0',
            'Accept: application/json',
        ];
        
        $result = [];
        foreach ($headers as $key => $value) {
            if (is_numeric($key)) {
                $result[] = $value;
            } else {
                $result[] = "{$key}: {$value}";
            }
        }
        
        return array_merge($defaultHeaders, $result);
    }

    /**
     * XML转数组（微信支付需要）
     */
    protected function parseXml(string $xml): array
    {
        if (empty($xml)) {
            return [];
        }
        
        // 禁用外部实体加载，防止XXE攻击
        libxml_disable_entity_loader(true);
        
        $xmlObject = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        if ($xmlObject === false) {
            throw new \Exception('XML解析失败');
        }
        
        $json = json_encode($xmlObject);
        $array = json_decode($json, true);
        
        return $array ?: [];
    }

    /**
     * 数组转XML（微信支付需要）
     */
    protected function arrayToXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $xml .= "<{$key}>{$value}</{$key}>";
            } else {
                $xml .= "<{$key}><![CDATA[{$value}]]></{$key}>";
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    /**
     * 生成随机字符串
     */
    protected function generateNonce(int $length = 32): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * 生成商户订单号
     */
    protected function generateOutTradeNo(): string
    {
        return date('YmdHis') . sprintf('%04d', random_int(0, 9999));
    }

    /**
     * 日志记录
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (class_exists('\think\facade\Log')) {
            \think\facade\Log::record($message, $level, $context);
        }
    }

    /**
     * 构建成功响应
     */
    protected function success(array $data = []): array
    {
        return [
            'code' => 1,
            'msg' => '操作成功',
            'data' => $data,
        ];
    }

    /**
     * 构建失败响应
     */
    protected function error(string $msg, array $data = []): array
    {
        return [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
        ];
    }
}
