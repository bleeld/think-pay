<?php
/**
 * 驱动工厂测试
 */

require_once dirname(__DIR__, 3) . '/autoload.php';

use bleeld\pay\DriverFactory;
use bleeld\pay\DriverInterface;

echo "=== 驱动工厂测试 ===\n\n";

// 测试1：检查内置驱动是否已注册
echo "测试1：检查内置驱动是否已注册\n";
$drivers = DriverFactory::getDrivers();
echo "已注册的驱动: " . implode(', ', $drivers) . "\n";

if (DriverFactory::has('alipay')) {
    echo "✓ alipay 驱动已注册\n";
} else {
    echo "✗ alipay 驱动未注册\n";
}

if (DriverFactory::has('wechat')) {
    echo "✓ wechat 驱动已注册\n";
} else {
    echo "✗ wechat 驱动未注册\n";
}

// 测试2：创建驱动实例
echo "\n测试2：创建驱动实例\n";
try {
    $alipayConfig = [
        'app_id' => 'test_key',
        'private_key' => 'test_secret',
        'public_key' => 'test_public',
    ];
    
    $driver = DriverFactory::make('alipay', $alipayConfig);
    echo "✓ 成功创建 AlipayDriver 实例\n";
    echo "  驱动名称: {$driver->getName()}\n";
} catch (\Exception $e) {
    echo "✗ 创建驱动失败: " . $e->getMessage() . "\n";
}

// 测试3：测试驱动缓存机制
echo "\n测试3：测试驱动缓存机制\n";
$driver1 = DriverFactory::make('alipay', $alipayConfig);
$driver2 = DriverFactory::make('alipay', $alipayConfig);

if ($driver1 === $driver2) {
    echo "✓ 驱动实例被正确缓存\n";
} else {
    echo "✗ 驱动实例未被缓存\n";
}

// 测试4：清除缓存功能
echo "\n测试4：清除缓存功能\n";
DriverFactory::clearCache();
$driver3 = DriverFactory::make('alipay', $alipayConfig);

if ($driver1 !== $driver3) {
    echo "✓ 缓存已成功清除并重新创建实例\n";
} else {
    echo "✗ 缓存清除失败\n";
}

// 测试5：动态注册新驱动
echo "\n测试5：动态注册新驱动\n";

class CustomPayDriver3 implements DriverInterface
{
    protected array $config = [];
    
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
    
    public function createOrder(array $orderData): array
    {
        return ['code' => 1, 'msg' => 'Custom创建订单'];
    }
    
    public function queryOrder(string $outTradeNo): array
    {
        return ['code' => 1, 'msg' => 'Custom查询订单'];
    }
    
    public function closeOrder(string $outTradeNo): array
    {
        return ['code' => 1, 'msg' => 'Custom关闭订单'];
    }
    
    public function refund(array $refundData): array
    {
        return ['code' => 1, 'msg' => 'Custom退款'];
    }
    
    public function verifyCallback(array $callbackData): bool
    {
        return true;
    }
    
    public function handleCallback(array $callbackData): array
    {
        return $callbackData;
    }
    
    public function getName(): string
    {
        return 'custom';
    }
}

try {
    DriverFactory::register('custom', CustomPayDriver3::class);
    echo "✓ 成功注册 custom 驱动\n";
    
    if (DriverFactory::has('custom')) {
        echo "✓ custom 驱动已存在于系统中\n";
    }
    
    $customDriver = DriverFactory::make('custom', []);
    echo "✓ 成功获取 custom 驱动\n";
    echo "  驱动名称: {$customDriver->getName()}\n";
} catch (\Exception $e) {
    echo "✗ 注册驱动失败: " . $e->getMessage() . "\n";
}

// 测试6：未注册驱动的异常处理
echo "\n测试6：未注册驱动的异常处理\n";
try {
    $invalidDriver = DriverFactory::make('invalid_driver', []);
    echo "✗ 应该抛出异常但未抛出\n";
} catch (\bleeld\pay\Exception\DriverNotFoundException $e) {
    echo "✓ 正确抛出 DriverNotFoundException\n";
    echo "  错误信息: {$e->getMessage()}\n";
} catch (\Exception $e) {
    echo "✗ 抛出异常但类型不正确: " . get_class($e) . "\n";
}

echo "\n=== 驱动工厂测试完成 ===\n";
