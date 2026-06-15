<?php
/**
 * 安全组件测试
 */

require_once dirname(__DIR__, 3) . '/autoload.php';

use bleeld\pay\Security\SignatureVerifier;
use bleeld\pay\Security\ReplayAttackGuard;
use bleeld\pay\Security\IpWhitelist;

echo "=== 安全组件测试 ===\n\n";

// 测试1：RSA签名验证（使用预生成的测试密钥）
echo "测试1：RSA签名验证\n";

// 使用一个简单的测试来验证verifyRsa方法是否存在
if (method_exists(SignatureVerifier::class, 'verifyRsa')) {
    echo "✓ SignatureVerifier::verifyRsa() 方法存在\n";
    echo "  注意：实际RSA测试需要有效的密钥对，此处仅验证API可用性\n";
} else {
    echo "✗ SignatureVerifier::verifyRsa() 方法不存在\n";
}

// 测试2：防重放攻击
echo "\n测试2：防重放攻击\n";

$nonce = bin2hex(random_bytes(16));
$timestamp = time();

// 第一次检查应该通过
if (ReplayAttackGuard::check($nonce, $timestamp)) {
    echo "✓ 首次请求检查通过\n";
} else {
    echo "✗ 首次请求检查失败\n";
}

// 记录nonce
ReplayAttackGuard::record($nonce);

// 第二次检查应该失败（重放）
if (!ReplayAttackGuard::check($nonce, $timestamp)) {
    echo "✓ 成功拦截重放请求\n";
} else {
    echo "✗ 未能拦截重放请求\n";
}

// 测试过期的时间戳
$oldTimestamp = time() - 600; // 10分钟前
$newNonce = bin2hex(random_bytes(16));

if (!ReplayAttackGuard::check($newNonce, $oldTimestamp)) {
    echo "✓ 成功拦截过期请求\n";
} else {
    echo "✗ 未能拦截过期请求\n";
}

// 测试3：IP白名单验证
echo "\n测试3：IP白名单验证\n";

// 测试支付宝官方IP
$alipayIps = IpWhitelist::getAlipayIps();
if (IpWhitelist::verify('140.205.16.100', $alipayIps)) {
    echo "✓ 支付宝IP验证通过\n";
} else {
    echo "✗ 支付宝IP验证失败\n";
}

// 测试非白名单IP
if (!IpWhitelist::verify('192.168.1.100', $alipayIps)) {
    echo "✓ 非白名单IP被正确拒绝\n";
} else {
    echo "✗ 非白名单IP未被拒绝\n";
}

// 测试空白的白名单（应该放行）
if (IpWhitelist::verify('192.168.1.100', [])) {
    echo "✓ 空白名单时放行所有IP\n";
} else {
    echo "✗ 空白名单时未放行IP\n";
}

// 测试4：微信支付MD5签名
echo "\n测试4：微信支付MD5签名\n";

$wechatParams = [
    'appid' => 'wx123456',
    'mch_id' => '1234567890',
    'nonce_str' => 'abcdefg',
    'body' => '测试商品',
    'out_trade_no' => 'ORDER123',
    'total_fee' => '100',
];

$apiKey = 'test_api_key_123456';
$sign = SignatureVerifier::generateWechatSign($wechatParams, $apiKey, 'MD5');

if (!empty($sign) && strlen($sign) === 32) {
    echo "✓ MD5签名生成成功\n";
    echo "  签名: {$sign}\n";
} else {
    echo "✗ MD5签名生成失败\n";
}

// 测试5：微信支付HMAC-SHA256签名
echo "\n测试5：微信支付HMAC-SHA256签名\n";

$sign256 = SignatureVerifier::generateWechatSign($wechatParams, $apiKey, 'HMAC-SHA256');

if (!empty($sign256) && strlen($sign256) === 64) {
    echo "✓ HMAC-SHA256签名生成成功\n";
    echo "  签名: {$sign256}\n";
} else {
    echo "✗ HMAC-SHA256签名生成失败\n";
}

echo "\n=== 安全组件测试完成 ===\n";
