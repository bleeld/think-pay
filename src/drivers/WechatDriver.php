<?php
declare(strict_types=1);

namespace bleeld\pay\drivers;

use bleeld\pay\BaseDriver;
use bleeld\pay\Security\SignatureVerifier;

/**
 * 微信支付驱动
 */
class WechatDriver extends BaseDriver
{
    protected string $name = 'wechat';

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 创建支付订单（Native扫码支付）
     */
    public function createOrder(array $orderData): array
    {
        try {
            if (empty($orderData['out_trade_no']) || empty($orderData['total_amount']) || empty($orderData['subject'])) {
                return $this->error('订单参数不完整');
            }

            $appId = $this->getConfig('app_id');
            $mchId = $this->getConfig('mch_id');
            $apiKey = $this->getConfig('api_key');
            
            if (empty($appId) || empty($mchId) || empty($apiKey)) {
                return $this->error('微信支付配置不完整');
            }

            // 构造请求参数
            $params = [
                'appid' => $appId,
                'mch_id' => $mchId,
                'nonce_str' => $this->generateNonce(),
                'body' => $orderData['subject'],
                'out_trade_no' => $orderData['out_trade_no'],
                'total_fee' => intval($orderData['total_amount'] * 100), // 转为分
                'spbill_create_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'notify_url' => $this->getConfig('notify_url', ''),
                'trade_type' => 'NATIVE', // PC扫码支付
            ];

            if (!empty($orderData['body'])) {
                $params['attach'] = $orderData['body'];
            }

            // 生成签名
            $signType = $this->getConfig('sign_type', 'HMAC-SHA256');
            $params['sign'] = SignatureVerifier::generateWechatSign($params, $apiKey, $signType);

            // 发送请求
            $xml = $this->arrayToXml($params);
            $response = $this->httpRequest(
                'https://api.mch.weixin.qq.com/pay/unifiedorder',
                $xml,
                'POST',
                ['Content-Type' => 'text/xml']
            );

            // 解析响应
            $result = $this->parseXml($response);

            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                $this->log('info', '微信创建订单', [
                    'out_trade_no' => $orderData['out_trade_no'],
                    'amount' => $orderData['total_amount'],
                ]);

                return $this->success([
                    'code_url' => $result['code_url'] ?? '', // 二维码链接
                    'out_trade_no' => $orderData['out_trade_no'],
                    'prepay_id' => $result['prepay_id'] ?? '',
                ]);
            }

            $errorMsg = $result['return_msg'] ?? $result['err_code_des'] ?? '支付失败';
            return $this->error($errorMsg);

        } catch (\Exception $e) {
            $this->log('error', '微信创建订单异常', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->error('创建订单失败: ' . $e->getMessage());
        }
    }

    /**
     * 查询订单状态
     */
    public function queryOrder(string $outTradeNo): array
    {
        try {
            $appId = $this->getConfig('app_id');
            $mchId = $this->getConfig('mch_id');
            $apiKey = $this->getConfig('api_key');

            $params = [
                'appid' => $appId,
                'mch_id' => $mchId,
                'out_trade_no' => $outTradeNo,
                'nonce_str' => $this->generateNonce(),
            ];

            $signType = $this->getConfig('sign_type', 'HMAC-SHA256');
            $params['sign'] = SignatureVerifier::generateWechatSign($params, $apiKey, $signType);

            $xml = $this->arrayToXml($params);
            $response = $this->httpRequest(
                'https://api.mch.weixin.qq.com/pay/orderquery',
                $xml,
                'POST',
                ['Content-Type' => 'text/xml']
            );

            $result = $this->parseXml($response);

            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                return $this->success([
                    'out_trade_no' => $result['out_trade_no'] ?? '',
                    'transaction_id' => $result['transaction_id'] ?? '',
                    'trade_state' => $result['trade_state'] ?? '',
                    'total_fee' => isset($result['total_fee']) ? $result['total_fee'] / 100 : 0,
                    'time_end' => $result['time_end'] ?? '',
                ]);
            }

            return $this->error($result['return_msg'] ?? '查询失败');

        } catch (\Exception $e) {
            return $this->error('查询订单失败: ' . $e->getMessage());
        }
    }

    /**
     * 关闭订单
     */
    public function closeOrder(string $outTradeNo): array
    {
        try {
            $appId = $this->getConfig('app_id');
            $mchId = $this->getConfig('mch_id');
            $apiKey = $this->getConfig('api_key');

            $params = [
                'appid' => $appId,
                'mch_id' => $mchId,
                'out_trade_no' => $outTradeNo,
                'nonce_str' => $this->generateNonce(),
            ];

            $signType = $this->getConfig('sign_type', 'HMAC-SHA256');
            $params['sign'] = SignatureVerifier::generateWechatSign($params, $apiKey, $signType);

            $xml = $this->arrayToXml($params);
            $response = $this->httpRequest(
                'https://api.mch.weixin.qq.com/pay/closeorder',
                $xml,
                'POST',
                ['Content-Type' => 'text/xml']
            );

            $result = $this->parseXml($response);

            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                return $this->success(['out_trade_no' => $outTradeNo]);
            }

            return $this->error($result['return_msg'] ?? '关闭失败');

        } catch (\Exception $e) {
            return $this->error('关闭订单失败: ' . $e->getMessage());
        }
    }

    /**
     * 退款
     */
    public function refund(array $refundData): array
    {
        try {
            if (empty($refundData['out_trade_no']) || empty($refundData['refund_amount'])) {
                return $this->error('退款参数不完整');
            }

            $appId = $this->getConfig('app_id');
            $mchId = $this->getConfig('mch_id');
            $apiKey = $this->getConfig('api_key');

            $params = [
                'appid' => $appId,
                'mch_id' => $mchId,
                'out_trade_no' => $refundData['out_trade_no'],
                'out_refund_no' => 'REFUND_' . date('YmdHis') . sprintf('%04d', random_int(0, 9999)),
                'total_fee' => 0, // 需要从原订单获取，这里简化处理
                'refund_fee' => intval($refundData['refund_amount'] * 100),
                'nonce_str' => $this->generateNonce(),
            ];

            if (!empty($refundData['refund_reason'])) {
                $params['refund_desc'] = $refundData['refund_reason'];
            }

            $signType = $this->getConfig('sign_type', 'HMAC-SHA256');
            $params['sign'] = SignatureVerifier::generateWechatSign($params, $apiKey, $signType);

            $xml = $this->arrayToXml($params);
            $response = $this->httpRequest(
                'https://api.mch.weixin.qq.com/secapi/pay/refund',
                $xml,
                'POST',
                ['Content-Type' => 'text/xml']
            );

            $result = $this->parseXml($response);

            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                return $this->success([
                    'out_trade_no' => $refundData['out_trade_no'],
                    'refund_id' => $result['refund_id'] ?? '',
                ]);
            }

            return $this->error($result['return_msg'] ?? '退款失败');

        } catch (\Exception $e) {
            return $this->error('退款失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证回调签名
     */
    public function verifyCallback(array $callbackData): bool
    {
        $apiKey = $this->getConfig('api_key');
        
        if (empty($apiKey)) {
            return false;
        }

        $signType = $callbackData['sign_type'] ?? $this->getConfig('sign_type', 'MD5');
        
        return SignatureVerifier::verifyWechat($callbackData, $apiKey, $signType);
    }

    /**
     * 处理回调数据
     */
    public function handleCallback(array $callbackData): array
    {
        // 标准化数据格式
        return [
            'out_trade_no' => $callbackData['out_trade_no'] ?? '',
            'transaction_id' => $callbackData['transaction_id'] ?? '',
            'total_amount' => isset($callbackData['total_fee']) ? $callbackData['total_fee'] / 100 : 0,
            'trade_status' => $callbackData['result_code'] ?? '',
            'pay_time' => $callbackData['time_end'] ?? '',
            'openid' => $callbackData['openid'] ?? '',
        ];
    }
}
