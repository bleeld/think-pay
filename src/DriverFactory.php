<?php
declare(strict_types=1);

namespace bleeld\pay;

use bleeld\pay\Exception\DriverNotFoundException;

/**
 * 驱动工厂类
 * 负责驱动的注册、实例化和管理
 */
class DriverFactory
{
    /**
     * 驱动映射（驱动名称 => 驱动类）
     */
    protected static array $driverMap = [
        'alipay' => \bleeld\pay\drivers\AlipayDriver::class,
        'wechat' => \bleeld\pay\drivers\WechatDriver::class,
        // 未来扩展
        // 'unionpay' => \bleeld\pay\drivers\UnionPayDriver::class,
        // 'paypal' => \bleeld\pay\drivers\PayPalDriver::class,
    ];

    /**
     * 驱动实例缓存
     */
    protected static array $instances = [];

    /**
     * 注册驱动
     */
    public static function register(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("驱动类 {$class} 不存在");
        }

        if (!is_subclass_of($class, DriverInterface::class)) {
            throw new \InvalidArgumentException("驱动类 {$class} 必须实现 DriverInterface 接口");
        }

        self::$driverMap[$name] = $class;
        unset(self::$instances[$name]);
    }

    /**
     * 创建驱动实例
     */
    public static function make(string $name, array $config): DriverInterface
    {
        if (isset(self::$instances[$name])) {
            $driver = self::$instances[$name];
            $driver->setConfig($config);
            return $driver;
        }

        if (!isset(self::$driverMap[$name])) {
            throw new DriverNotFoundException("支付驱动 [{$name}] 未注册");
        }

        $class = self::$driverMap[$name];
        $driver = new $class();
        $driver->setConfig($config);
        self::$instances[$name] = $driver;

        return $driver;
    }

    /**
     * 检查驱动是否已注册
     */
    public static function has(string $name): bool
    {
        return isset(self::$driverMap[$name]);
    }

    /**
     * 获取所有已注册的驱动名称
     */
    public static function getDrivers(): array
    {
        return array_keys(self::$driverMap);
    }

    /**
     * 清除驱动实例缓存
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }
}
