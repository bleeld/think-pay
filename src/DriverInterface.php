<?php
declare(strict_types=1);

namespace bleeld\pay;

/**
 * 支付驱动接口
 * 所有支付驱动必须实现此接口
 */
interface DriverInterface
{
    /**
     * 设置配置
     * 
     * @param array $config 驱动配置
     * @return void
     */
    public function setConfig(array $config): void;

    /**
     * 创建支付订单
     * 
     * @param array $orderData 订单数据
     *   - out_trade_no: 商户订单号
     *   - total_amount: 订单金额（元）
     *   - subject: 商品名称
     *   - body: 商品描述（可选）
     * @return array ['code' => 1/0, 'msg' => '消息', 'data' => [...]]
     */
    public function createOrder(array $orderData): array;

    /**
     * 查询订单状态
     * 
     * @param string $outTradeNo 商户订单号
     * @return array ['code' => 1/0, 'msg' => '消息', 'data' => [...]]
     */
    public function queryOrder(string $outTradeNo): array;

    /**
     * 关闭订单
     * 
     * @param string $outTradeNo 商户订单号
     * @return array ['code' => 1/0, 'msg' => '消息', 'data' => [...]]
     */
    public function closeOrder(string $outTradeNo): array;

    /**
     * 退款
     * 
     * @param array $refundData 退款数据
     *   - out_trade_no: 原订单号
     *   - refund_amount: 退款金额
     *   - refund_reason: 退款原因（可选）
     * @return array ['code' => 1/0, 'msg' => '消息', 'data' => [...]]
     */
    public function refund(array $refundData): array;

    /**
     * 验证回调签名
     * 
     * @param array $callbackData 回调数据
     * @return bool
     */
    public function verifyCallback(array $callbackData): bool;

    /**
     * 处理回调数据（返回标准化格式）
     * 
     * @param array $callbackData 回调数据
     * @return array 标准化后的数据
     *   - out_trade_no: 商户订单号
     *   - transaction_id: 平台交易号
     *   - total_amount: 订单金额
     *   - trade_status: 交易状态
     *   - pay_time: 支付时间
     */
    public function handleCallback(array $callbackData): array;

    /**
     * 获取驱动名称
     * 
     * @return string
     */
    public function getName(): string;
}
