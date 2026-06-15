<?php
declare(strict_types=1);

namespace bleeld\pay\drivers;

use bleeld\pay\BaseDriver;
use bleeld\pay\Security\SignatureVerifier;

/**
 * 支付宝支付驱动
 */
class AlipayDriver extends BaseDriver
{
    protected string $name = 'alipay';

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 创建支付订单（PC网站支付）
     */
    public function createOrder(array $orderData): array
    {
        try {
            // 验证必要参数
            if (empty($orderData['out_trade_no']) || empty($orderData['total_amount']) || empty($orderData['subject'])) {
                return $this->error('订单参数不完整');
            }

            $appId = $this->getConfig('app_id');
            $privateKey = $this->getConfig('private_key');
            
            if (empty($appId) || empty($privateKey)) {
                return $this->error('支付宝配置不完整');
            }

            // 构造业务参数
            $bizContent = [
                'out_trade_no' => $orderData['out_trade_no'],
                'total_amount' => number_format($orderData['total_amount'], 2, '.', ''),
                'subject' => $orderData['subject'],
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
            ];
            
            if (!empty($orderData['body'])) {
                $bizContent['body'] = $orderData['body'];
            }

            // 构造公共参数
            $params = [
                'app_id' => $appId,
                'method' => 'alipay.trade.page.pay',
                'charset' => 'utf-8',
                'sign_type' => 'RSA2',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0',
                'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
            ];

            // 添加通知地址
            if ($notifyUrl = $this->getConfig('notify_url')) {
                $params['notify_url'] = $notifyUrl;
            }
            if ($returnUrl = $this->getConfig('return_url')) {
                $params['return_url'] = $returnUrl;
            }

            // 生成签名
            $params['sign'] = SignatureVerifier::generateAlipaySign($params, $privateKey);

            // 构造支付URL
            $gateway = $this->getConfig('gateway', 'https://openapi.alipay.com/gateway.do');
            $payUrl = $gateway . '?' . http_build_query($params);

            $this->log('info', '支付宝创建订单', [
                'out_trade_no' => $orderData['out_trade_no'],
                'amount' => $orderData['total_amount'],
            ]);

            return $this->success([
                'pay_url' => $payUrl,
                'out_trade_no' => $orderData['out_trade_no'],
            ]);

        } catch (\Exception $e) {
            $this->log('error', '支付宝创建订单异常', [
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
            $privateKey = $this->getConfig('private_key');

            // 构造业务参数
            $bizContent = [
                'out_trade_no' => $outTradeNo,
            ];

            // 构造公共参数
            $params = [
                'app_id' => $appId,
                'method' => 'alipay.trade.query',
                'charset' => 'utf-8',
                'sign_type' => 'RSA2',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0',
                'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
            ];

            // 生成签名
            $params['sign'] = SignatureVerifier::generateAlipaySign($params, $privateKey);

            // 发送请求
            $gateway = $this->getConfig('gateway', 'https://openapi.alipay.com/gateway.do');
            $response = $this->httpRequest($gateway, $params, 'POST');
            $result = json_decode($response, true);

            // 解析响应
            $responseKey = 'alipay_trade_query_response';
            if (!isset($result[$responseKey])) {
                return $this->error('查询失败：响应格式错误');
            }

            $tradeData = $result[$responseKey];
            
            if ($tradeData['code'] === '10000') {
                return $this->success([
                    'out_trade_no' => $tradeData['out_trade_no'] ?? '',
                    'trade_no' => $tradeData['trade_no'] ?? '',
                    'trade_status' => $tradeData['trade_status'] ?? '',
                    'total_amount' => $tradeData['total_amount'] ?? '',
                    'send_pay_date' => $tradeData['send_pay_date'] ?? '',
                ]);
            } else {
                return $this->error($tradeData['msg'] ?? '查询失败');
            }

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
            $privateKey = $this->getConfig('private_key');

            $bizContent = ['out_trade_no' => $outTradeNo];

            $params = [
                'app_id' => $appId,
                'method' => 'alipay.trade.close',
                'charset' => 'utf-8',
                'sign_type' => 'RSA2',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0',
                'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
            ];

            $params['sign'] = SignatureVerifier::generateAlipaySign($params, $privateKey);

            $gateway = $this->getConfig('gateway', 'https://openapi.alipay.com/gateway.do');
            $response = $this->httpRequest($gateway, $params, 'POST');
            $result = json_decode($response, true);

            $responseKey = 'alipay_trade_close_response';
            if (isset($result[$responseKey]) && $result[$responseKey]['code'] === '10000') {
                return $this->success(['out_trade_no' => $outTradeNo]);
            }

            return $this->error($result[$responseKey]['msg'] ?? '关闭失败');

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
            $privateKey = $this->getConfig('private_key');

            $bizContent = [
                'out_trade_no' => $refundData['out_trade_no'],
                'refund_amount' => number_format($refundData['refund_amount'], 2, '.', ''),
            ];

            if (!empty($refundData['refund_reason'])) {
                $bizContent['refund_reason'] = $refundData['refund_reason'];
            }

            $params = [
                'app_id' => $appId,
                'method' => 'alipay.trade.refund',
                'charset' => 'utf-8',
                'sign_type' => 'RSA2',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0',
                'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
            ];

            $params['sign'] = SignatureVerifier::generateAlipaySign($params, $privateKey);

            $gateway = $this->getConfig('gateway', 'https://openapi.alipay.com/gateway.do');
            $response = $this->httpRequest($gateway, $params, 'POST');
            $result = json_decode($response, true);

            $responseKey = 'alipay_trade_refund_response';
            if (isset($result[$responseKey]) && $result[$responseKey]['code'] === '10000') {
                return $this->success([
                    'out_trade_no' => $refundData['out_trade_no'],
                    'refund_fee' => $result[$responseKey]['refund_fee'] ?? '',
                ]);
            }

            return $this->error($result[$responseKey]['msg'] ?? '退款失败');

        } catch (\Exception $e) {
            return $this->error('退款失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证回调签名
     */
    public function verifyCallback(array $callbackData): bool
    {
        $publicKey = $this->getConfig('public_key');
        
        if (empty($publicKey)) {
            return false;
        }

        return SignatureVerifier::verifyAlipay($callbackData, $publicKey);
    }

    /**
     * 处理回调数据
     */
    public function handleCallback(array $callbackData): array
    {
        // 标准化数据格式
        return [
            'out_trade_no' => $callbackData['out_trade_no'] ?? '',
            'transaction_id' => $callbackData['trade_no'] ?? '',
            'total_amount' => $callbackData['total_amount'] ?? '',
            'trade_status' => $callbackData['trade_status'] ?? '',
            'pay_time' => $callbackData['gmt_payment'] ?? '',
            'buyer_id' => $callbackData['buyer_id'] ?? '',
            'seller_id' => $callbackData['seller_id'] ?? '',
        ];
    }
}
