<?php
/**
 * 驱动接口测试
 */

require_once dirname(__DIR__, 3) . '/autoload.php';

use bleeld\pay\DriverInterface;
use bleeld\pay\drivers\AlipayDriver;
use bleeld\pay\drivers\WechatDriver;

echo "=== 驱动接口测试 ===\n\n";

// 测试1：检查驱动是否实现接口
echo "测试1：检查驱动是否实现 DriverInterface\n";
$alipayDriver = new AlipayDriver();
$wechatDriver = new WechatDriver();

if ($alipayDriver instanceof DriverInterface) {
    echo "✓ AlipayDriver 实现了 DriverInterface\n";
} else {
    echo "✗ AlipayDriver 未实现 DriverInterface\n";
}

if ($wechatDriver instanceof DriverInterface) {
    echo "✓ WechatDriver 实现了 DriverInterface\n";
} else {
    echo "✗ WechatDriver 未实现 DriverInterface\n";
}

// 测试2：检查驱动方法是否存在
echo "\n测试2：检查驱动方法是否存在\n";
$requiredMethods = ['setConfig', 'createOrder', 'queryOrder', 'closeOrder', 'refund', 'verifyCallback', 'handleCallback', 'getName'];

foreach (['AlipayDriver' => $alipayDriver, 'WechatDriver' => $wechatDriver] as $name => $driver) {
    echo "\n{$name}:\n";
    foreach ($requiredMethods as $method) {
        if (method_exists($driver, $method)) {
            echo "  ✓ {$method}() 存在\n";
        } else {
            echo "  ✗ {$method}() 不存在\n";
        }
    }
}

// 测试3：验证 getName() 返回值
echo "\n测试3：验证 getName() 返回值\n";
if ($alipayDriver->getName() === 'alipay') {
    echo "✓ AlipayDriver::getName() 返回 'alipay'\n";
} else {
    echo "✗ AlipayDriver::getName() 返回 '{$alipayDriver->getName()}'\n";
}

if ($wechatDriver->getName() === 'wechat') {
    echo "✓ WechatDriver::getName() 返回 'wechat'\n";
} else {
    echo "✗ WechatDriver::getName() 返回 '{$wechatDriver->getName()}'\n";
}

// 测试4：测试 setConfig() 功能
echo "\n测试4：测试 setConfig() 功能\n";
$testConfig = [
    'app_id' => 'test_app_id',
    'private_key' => 'test_private_key',
];

$alipayDriver->setConfig($testConfig);
echo "✓ AlipayDriver 配置设置成功\n";

$testConfig2 = [
    'app_id' => 'test_wechat_app_id',
    'mch_id' => 'test_mch_id',
    'api_key' => 'test_api_key',
];

$wechatDriver->setConfig($testConfig2);
echo "✓ WechatDriver 配置设置成功\n";

echo "\n=== 驱动接口测试完成 ===\n";
