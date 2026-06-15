<?php
declare(strict_types=1);

namespace bleeld\pay;

use think\Service as ThinkService;

/**
 * ThinkPHP 服务提供者
 */
class Service extends ThinkService
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册支付服务单例
        $this->app->bind('pay', PayService::class);
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 自动加载配置
        $configFile = __DIR__ . '/../config/pay.php';
        
        if (file_exists($configFile)) {
            $config = include $configFile;
            
            // 合并到ThinkPHP配置
            if (function_exists('config')) {
                config($config, 'pay');
            }
        }
    }
}
