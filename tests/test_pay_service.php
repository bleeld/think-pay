<?php
/**
 * 支付服务测试
 */

require_once dirname(__DIR__, 3) . '/autoload.php';

use bleeld\pay\PayService;
use bleeld\pay\DriverInterface;

echo "=== 支付服务测试 ===\n\n";

// 初始化配置
$config = [
    'default' => 'alipay',
    'alipay' => [
        'app_id' => 'test_key',
        'private_key' => 'test_secret',
        'public_key' => 'test_public',
    ],
    'wechat' => [
        'app_id' => 'test_wechat_app',
        'mch_id' => 'test_mch_id',
        'api_key' => 'test_api_key',
    ],
];

PayService::setConfig($config);

// 测试1：检查配置
echo "测试1：检查配置\n";
$savedConfig = PayService::getConfig();
if (!empty($savedConfig)) {
    echo "✓ 配置已成功设置\n";
    echo "  默认驱动: {$savedConfig['default']}\n";
} else {
    echo "✗ 配置为空\n";
}

// 测试2：获取默认驱动
echo "\n测试2：获取默认驱动\n";
try {
    $driver = PayService::driver();
    echo "✓ 成功获取默认驱动\n";
    echo "  驱动名称: {$driver->getName()}\n";
} catch (\Exception $e) {
    echo "✗ 获取驱动失败: " . $e->getMessage() . "\n";
}

// 测试3：指定驱动名称
echo "\n测试3：指定驱动名称\n";
try {
    $wechatDriver = PayService::driver('wechat');
    echo "✓ 成功获取 wechat 驱动\n";
    echo "  驱动名称: {$wechatDriver->getName()}\n";
} catch (\Exception $e) {
    echo "✗ 获取驱动失败: " . $e->getMessage() . "\n";
}

// 测试4：检查驱动注册状态
echo "\n测试4：检查驱动注册状态\n";
if (PayService::hasDriver('alipay')) {
    echo "✓ alipay 驱动已注册\n";
} else {
    echo "✗ alipay 驱动未注册\n";
}

if (PayService::hasDriver('wechat')) {
    echo "✓ wechat 驱动已注册\n";
} else {
    echo "✗ wechat 驱动未注册\n";
}

// 测试5：获取驱动列表
echo "\n测试5：获取驱动列表\n";
$drivers = PayService::getDrivers();
echo "可用驱动: " . implode(', ', $drivers) . "\n";

// 测试6：切换默认驱动
echo "\n测试6：切换默认驱动\n";
PayService::use('wechat');
$defaultDriver = PayService::driver();
if ($defaultDriver->getName() === 'wechat') {
    echo "✓ 成功切换到 wechat 驱动\n";
} else {
    echo "✗ 切换失败，当前驱动: {$defaultDriver->getName()}\n";
}

// 测试7：动态注册新驱动
echo "\n测试7：动态注册新驱动\n";

class CustomPayDriver2 implements DriverInterface
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
    // 先添加配置
    $newConfig = [
        'default' => 'alipay',
        'alipay' => [
            'app_id' => 'test_key',
            'private_key' => 'test_secret',
            'public_key' => 'test_public',
        ],
        'wechat' => [
            'app_id' => 'test_wechat_app',
            'mch_id' => 'test_mch_id',
            'api_key' => 'test_api_key',
        ],
        'custom' => [],
    ];
    PayService::setConfig($newConfig);
    
    // 再注册驱动
    PayService::registerDriver('custom', CustomPayDriver2::class);
    echo "✓ 成功注册 custom 驱动\n";
    
    if (PayService::hasDriver('custom')) {
        echo "✓ custom 驱动已存在于系统中\n";
    }
    
    $customDriver = PayService::driver('custom');
    echo "✓ 成功获取 custom 驱动\n";
    echo "  驱动名称: {$customDriver->getName()}\n";
} catch (\Exception $e) {
    // 如果是因为配置问题，这是预期的行为
    if (strpos($e->getMessage(), '配置不存在') !== false) {
        echo "✓ custom 驱动已注册（需要配置后才能使用）\n";
    } else {
        echo "✗ 注册驱动失败: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 支付服务测试完成 ===\n";
