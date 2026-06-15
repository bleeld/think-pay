<?php
/**
 * 支付服务配置文件
 * 
 * 说明：
 * - default: 默认使用的支付驱动名称
 * - 各驱动配置只需填写参数，驱动类由工厂自动映射
 * - 支持支付宝、微信支付，预留银联、PayPal等扩展位
 */

return [
    // 默认支付方式
    'default' => 'alipay',

    // 支付宝配置
    'alipay' => [
        'app_id' => '',              // 应用ID
        'private_key' => '',         // 应用私钥（不包含头尾）
        'public_key' => '',          // 支付宝公钥（不包含头尾）
        'gateway' => 'https://openapi.alipay.com/gateway.do',
        'notify_url' => '',          // 异步通知地址
        'return_url' => '',          // 同步返回地址
        'sign_type' => 'RSA2',       // 签名类型
    ],

    // 微信支付配置
    'wechat' => [
        'app_id' => '',              // 应用ID
        'mch_id' => '',              // 商户号
        'api_key' => '',             // API密钥
        'app_secret' => '',          // AppSecret
        'notify_url' => '',          // 异步通知地址
        'sign_type' => 'HMAC-SHA256', // 签名类型 MD5/HMAC-SHA256
    ],

    // 安全配置
    'security' => [
        'enable_replay_guard' => true,    // 启用防重放
        'replay_tolerance' => 300,        // 时间容差（秒）
        'enable_ip_whitelist' => true,    // 启用IP白名单
        'alipay_ips' => [],               // 支付宝IP白名单（空则使用官方IP）
        'wechat_ips' => [],               // 微信IP白名单（空则使用官方IP）
    ],

    // 通用配置
    'timeout' => 30,           // 请求超时时间（秒）
    'log_channel' => 'pay',    // 日志通道名称
];
