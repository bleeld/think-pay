<?php
declare(strict_types=1);

namespace bleeld\pay;

use bleeld\pay\Exception\ConfigException;
use bleeld\pay\Exception\SignatureException;
use bleeld\pay\Security\ReplayAttackGuard;
use bleeld\pay\Security\IpWhitelist;

/**
 * 支付服务类
 * 提供统一的支付接口
 */
class PayService
{
    /**
     * 配置数据
     */
    protected static array $config = [];

    /**
     * 默认驱动名称
     */
    protected static string $defaultDriver = '';

    /**
     * 已处理的订单号（用于幂等性检查）
     */
    protected static array $processedOrders = [];

    /**
     * 初始化服务
     */
    public static function init(array $config = []): void
    {
        if (empty($config)) {
            // 尝试从ThinkPHP配置加载
            if (function_exists('config')) {
                $config = config('pay', []);
            }
        }

        if (empty($config)) {
            throw new ConfigException('支付配置不能为空');
        }

        self::$config = $config;
        self::$defaultDriver = $config['default'] ?? 'alipay';
    }

    /**
     * 获取配置
     */
    public static function getConfig(): array
    {
        return self::$config;
    }

    /**
     * 设置配置
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
        self::$defaultDriver = $config['default'] ?? 'alipay';
        
        // 清除驱动缓存
        DriverFactory::clearCache();
    }

    /**
     * 获取默认驱动实例
     */
    public static function driver(?string $name = null): DriverInterface
    {
        if (empty(self::$config)) {
            self::init();
        }

        $driverName = $name ?? self::$defaultDriver;
        $driverConfig = self::$config[$driverName] ?? [];

        if (empty($driverConfig)) {
            throw new ConfigException("支付驱动 [{$driverName}] 配置不存在");
        }

        return DriverFactory::make($driverName, $driverConfig);
    }

    /**
     * 创建支付订单
     */
    public static function createOrder(array $orderData, ?string $driver = null): array
    {
        return self::driver($driver)->createOrder($orderData);
    }

    /**
     * 查询订单状态
     */
    public static function queryOrder(string $outTradeNo, ?string $driver = null): array
    {
        return self::driver($driver)->queryOrder($outTradeNo);
    }

    /**
     * 关闭订单
     */
    public static function closeOrder(string $outTradeNo, ?string $driver = null): array
    {
        return self::driver($driver)->closeOrder($outTradeNo);
    }

    /**
     * 退款
     */
    public static function refund(array $refundData, ?string $driver = null): array
    {
        return self::driver($driver)->refund($refundData);
    }

    /**
     * 处理回调（统一入口）
     */
    public static function handleCallback(array $callbackData): array
    {
        // 1. 识别支付方式
        $driver = self::identifyDriver($callbackData);
        
        // 2. 获取驱动实例
        $payDriver = self::driver($driver);
        
        // 3. 验证签名
        if (!$payDriver->verifyCallback($callbackData)) {
            throw new SignatureException('签名验证失败');
        }
        
        // 4. 防重放检查
        $securityConfig = self::$config['security'] ?? [];
        if ($securityConfig['enable_replay_guard'] ?? true) {
            $nonce = $callbackData['nonce_str'] ?? $callbackData['nonce'] ?? '';
            $timestamp = $callbackData['timestamp'] ?? time();
            
            if (!empty($nonce) && !ReplayAttackGuard::check($nonce, $timestamp)) {
                throw new SignatureException('检测到重放攻击');
            }
            
            if (!empty($nonce)) {
                ReplayAttackGuard::record($nonce);
            }
        }
        
        // 5. IP白名单验证
        if ($securityConfig['enable_ip_whitelist'] ?? true) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $whitelist = self::getIpWhitelist($driver);
            
            if (!IpWhitelist::verify($clientIp, $whitelist)) {
                throw new SignatureException('IP不在白名单中');
            }
        }
        
        // 6. 标准化回调数据
        $normalizedData = $payDriver->handleCallback($callbackData);
        
        // 7. 幂等性检查
        if (self::isOrderProcessed($normalizedData['out_trade_no'])) {
            return ['code' => 1, 'msg' => '订单已处理', 'data' => $normalizedData];
        }
        
        // 8. 触发支付成功事件
        if (function_exists('event')) {
            event('PaymentSuccess', [$normalizedData]);
        }
        
        // 9. 标记订单为已处理
        self::markOrderAsProcessed($normalizedData['out_trade_no']);
        
        return ['code' => 1, 'msg' => '处理成功', 'data' => $normalizedData];
    }

    /**
     * 注册新驱动
     */
    public static function registerDriver(string $name, string $class): void
    {
        DriverFactory::register($name, $class);
    }

    /**
     * 检查驱动是否已注册
     */
    public static function hasDriver(string $name): bool
    {
        return DriverFactory::has($name);
    }

    /**
     * 获取所有已注册的驱动
     */
    public static function getDrivers(): array
    {
        return DriverFactory::getDrivers();
    }

    /**
     * 切换默认驱动
     */
    public static function use(string $driverName): self
    {
        self::$defaultDriver = $driverName;
        return new self();
    }

    /**
     * 识别支付方式
     */
    protected static function identifyDriver(array $callbackData): string
    {
        // 支付宝特征字段
        if (isset($callbackData['app_id']) || isset($callbackData['notify_type'])) {
            return 'alipay';
        }
        
        // 微信特征字段
        if (isset($callbackData['appid']) || isset($callbackData['mch_id'])) {
            return 'wechat';
        }
        
        // 默认使用配置的默认驱动
        return self::$defaultDriver;
    }

    /**
     * 获取IP白名单
     */
    protected static function getIpWhitelist(string $driver): array
    {
        $securityConfig = self::$config['security'] ?? [];
        
        if ($driver === 'alipay') {
            return $securityConfig['alipay_ips'] ?? IpWhitelist::getAlipayIps();
        } elseif ($driver === 'wechat') {
            return $securityConfig['wechat_ips'] ?? IpWhitelist::getWechatIps();
        }
        
        return [];
    }

    /**
     * 检查订单是否已处理
     */
    protected static function isOrderProcessed(string $outTradeNo): bool
    {
        return isset(self::$processedOrders[$outTradeNo]);
    }

    /**
     * 标记订单为已处理
     */
    protected static function markOrderAsProcessed(string $outTradeNo): void
    {
        self::$processedOrders[$outTradeNo] = time();
    }

    /**
     * 魔术方法：动态调用驱动方法
     */
    public function __call(string $method, array $arguments)
    {
        $driver = self::driver();
        
        if (method_exists($driver, $method)) {
            return call_user_func_array([$driver, $method], $arguments);
        }

        throw new \BadMethodCallException("方法 {$method} 不存在");
    }

    /**
     * 静态魔术方法
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $instance = new self();
        return $instance->__call($method, $arguments);
    }
}
