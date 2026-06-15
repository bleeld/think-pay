# Think-Pay 支付服务插件

一个企业级安全的 ThinkPHP 8 支付服务插件，支持支付宝、微信支付等多厂商支付服务，零依赖大厂SDK。

## ✨ 特性

- 🔒 **企业级安全**：RSA2/SHA256强签名验证、防重放攻击、IP白名单、幂等性检查
- 🚀 **零依赖**：不引用大厂完整SDK包，只提取核心功能
- 💳 **多厂商支持**：支付宝、微信支付（当前），银联、PayPal（扩展中）
- 🎯 **易于扩展**：策略模式 + 工厂模式设计，轻松添加新服务商
- ⚙️ **配置简洁**：只需配置参数，驱动类自动映射
- 📊 **统一接口**：所有驱动使用相同的API接口
- 🔄 **完整回调**：自动验证签名、防重放、幂等性检查
- 📝 **日志记录**：完整的请求日志记录

## 📦 安装

### 方式一：本地开发（推荐）

1. 将插件放在 `vendor/bleeld/think-pay` 目录

2. 在主项目的 `composer.json` 中添加：

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./vendor/bleeld/think-pay",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "bleeld/think-pay": "@dev"
    }
}
```

3. 执行安装：

```bash
composer update bleeld/think-pay
```

配置文件会自动发布到 `config/pay.php`。

## ⚙️ 配置

编辑 `config/pay.php`，填写您的支付服务商配置：

```php
return [
    // 默认支付方式
    'default' => 'alipay',

    // 支付宝配置
    'alipay' => [
        'app_id' => 'your_app_id',              // 必填
        'private_key' => 'your_private_key',    // 必填（不包含头尾）
        'public_key' => 'alipay_public_key',    // 必填（不包含头尾）
        'gateway' => 'https://openapi.alipay.com/gateway.do',
        'notify_url' => 'https://yourdomain.com/pay/notify/alipay',
        'return_url' => 'https://yourdomain.com/pay/return',
        'sign_type' => 'RSA2',
    ],

    // 微信支付配置
    'wechat' => [
        'app_id' => 'your_app_id',              // 必填
        'mch_id' => 'your_mch_id',              // 必填
        'api_key' => 'your_api_key',            // 必填
        'app_secret' => 'your_app_secret',      // 可选
        'notify_url' => 'https://yourdomain.com/pay/notify/wechat',
        'sign_type' => 'HMAC-SHA256',           // MD5 或 HMAC-SHA256
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
```

## 🚀 快速开始

### 1. 创建支付订单

```php
use bleeld\pay\PayService;

// 方式1：使用默认驱动（支付宝）
$orderData = [
    'out_trade_no' => 'ORDER_' . date('YmdHis') . rand(1000, 9999),
    'total_amount' => 100.00,
    'subject' => '商品名称',
    'body' => '商品描述',
];

$result = PayService::createOrder($orderData);

if ($result['code'] === 1) {
    // 跳转到支付宝支付页面
    redirect($result['data']['pay_url']);
} else {
    echo '创建订单失败: ' . $result['msg'];
}

// 方式2：指定微信支付
$result = PayService::driver('wechat')->createOrder($orderData);

if ($result['code'] === 1) {
    // 显示二维码
    echo '<img src="https://api.qrserver.com/v1/create-qr-code/?data=' . 
         urlencode($result['data']['code_url']) . '" />';
}
```

### 2. 查询订单状态

```php
$result = PayService::queryOrder('ORDER_202605231200001234');

if ($result['code'] === 1) {
    $status = $result['data']['trade_status'];
    if ($status === 'SUCCESS') {
        echo '支付成功';
    } elseif ($status === 'TRADE_CLOSED') {
        echo '订单已关闭';
    } else {
        echo '等待支付';
    }
}
```

### 3. 申请退款

```php
$refundData = [
    'out_trade_no' => 'ORDER_202605231200001234',
    'refund_amount' => 50.00,
    'refund_reason' => '用户申请退款',
];

$result = PayService::refund($refundData);

if ($result['code'] === 1) {
    echo '退款申请成功';
} else {
    echo '退款失败: ' . $result['msg'];
}
```

### 4. 处理支付回调

创建回调控制器：

```php
namespace app\index\controller;

use bleeld\pay\PayService;
use bleeld\pay\Exception\SignatureException;

class PayNotify
{
    // 支付宝回调
    public function alipay()
    {
        try {
            $callbackData = input('post.');
            $result = PayService::handleCallback($callbackData);
            
            if ($result['code'] === 1) {
                echo 'success';
            } else {
                echo 'fail';
            }
        } catch (SignatureException $e) {
            echo 'fail';
        }
    }
    
    // 微信支付回调
    public function wechat()
    {
        try {
            $xml = file_get_contents('php://input');
            $callbackData = $this->parseXml($xml);
            $result = PayService::handleCallback($callbackData);
            
            if ($result['code'] === 1) {
                echo '<xml><return_code><![CDATA[SUCCESS]]></return_code></xml>';
            } else {
                echo '<xml><return_code><![CDATA[FAIL]]></return_code></xml>';
            }
        } catch (SignatureException $e) {
            echo '<xml><return_code><![CDATA[FAIL]]></return_code></xml>';
        }
    }
    
    private function parseXml(string $xml): array
    {
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
}
```

### 5. 监听支付成功事件

在 `config/event.php` 中注册：

```php
return [
    'listen' => [
        'PaymentSuccess' => [
            \app\common\listener\PaymentSuccessListener::class,
        ],
    ],
];
```

创建监听器 `app/common/listener/PaymentSuccessListener.php`：

```php
namespace app\common\listener;

class PaymentSuccessListener
{
    public function handle(array $data)
    {
        // 更新订单状态
        $orderNo = $data['out_trade_no'];
        $transactionId = $data['transaction_id'];
        
        // 执行业务逻辑...
        \think\facade\Log::info("订单 {$orderNo} 支付成功，交易号: {$transactionId}");
    }
}
```

## 🔒 安全措施

### 1. 签名验证

- **支付宝**：使用RSA2（SHA256）签名算法
- **微信支付**：支持MD5和HMAC-SHA256签名

### 2. 防重放攻击

通过nonce和时间戳防止请求被重放：

```php
// 自动在回调处理中启用
'security' => [
    'enable_replay_guard' => true,
    'replay_tolerance' => 300,  // 5分钟容差
]
```

### 3. IP白名单

验证回调请求的IP是否在允许的范围内：

```php
'security' => [
    'enable_ip_whitelist' => true,
    'alipay_ips' => [],  // 空则使用官方IP段
    'wechat_ips' => [],
]
```

### 4. 敏感信息保护

**强烈建议**使用环境变量存储敏感信息：

```php
// config/pay.php
'alipay' => [
    'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
    'public_key' => env('ALIPAY_PUBLIC_KEY', ''),
],
```

在 `.env` 文件中配置：

```env
ALIPAY_PRIVATE_KEY=your_private_key_here
ALIPAY_PUBLIC_KEY=your_public_key_here
```

## 🧪 测试

运行测试套件：

```bash
php vendor/bleeld/think-pay/tests/run_all_tests.php
```

测试包括：
- ✓ 驱动接口测试
- ✓ 驱动工厂测试
- ✓ 支付服务测试
- ✓ 安全组件测试

## 📚 API参考

### PayService 主要方法

```php
// 初始化配置
PayService::init(array $config): void

// 获取/设置配置
PayService::getConfig(): array
PayService::setConfig(array $config): void

// 获取驱动实例
PayService::driver(?string $name = null): DriverInterface

// 快捷方法
PayService::createOrder(array $orderData, ?string $driver = null): array
PayService::queryOrder(string $outTradeNo, ?string $driver = null): array
PayService::closeOrder(string $outTradeNo, ?string $driver = null): array
PayService::refund(array $refundData, ?string $driver = null): array

// 回调处理
PayService::handleCallback(array $callbackData): array

// 注册新驱动
PayService::registerDriver(string $name, string $class): void

// 切换默认驱动
PayService::use(string $driverName): self

// 检查驱动状态
PayService::hasDriver(string $name): bool
PayService::getDrivers(): array
```

### DriverInterface 接口

```php
interface DriverInterface
{
    public function setConfig(array $config): void;
    public function createOrder(array $orderData): array;
    public function queryOrder(string $outTradeNo): array;
    public function closeOrder(string $outTradeNo): array;
    public function refund(array $refundData): array;
    public function verifyCallback(array $callbackData): bool;
    public function handleCallback(array $callbackData): array;
    public function getName(): string;
}
```

## 🔧 扩展指南

### 添加新的支付驱动

1. 创建驱动类，实现 `DriverInterface`：

```php
namespace bleeld\pay\drivers;

use bleeld\pay\BaseDriver;

class UnionPayDriver extends BaseDriver
{
    protected string $name = 'unionpay';
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function createOrder(array $orderData): array
    {
        // 实现银联支付逻辑
    }
    
    // 实现其他必需方法...
}
```

2. 在 `DriverFactory` 中注册：

```php
// 方式1：修改 DriverFactory::$driverMap
protected static array $driverMap = [
    'alipay' => AlipayDriver::class,
    'wechat' => WechatDriver::class,
    'unionpay' => UnionPayDriver::class,  // 添加新驱动
];

// 方式2：运行时动态注册
PayService::registerDriver('unionpay', UnionPayDriver::class);
```

3. 在配置文件中添加配置：

```php
'unionpay' => [
    'merchant_id' => '',
    'terminal_id' => '',
    // ... 其他配置
],
```

## 📝 注意事项

### 安全性

1. **私钥保护**：永远不要将私钥提交到版本控制系统
2. **HTTPS**：生产环境必须使用HTTPS
3. **日志脱敏**：记录日志时隐藏敏感信息
4. **金额校验**：回调时必须重新计算金额，防止篡改
5. **订单状态**：以支付平台返回的状态为准

### 兼容性

- PHP 8.0+
- 需要 openssl、curl、json、simplexml 扩展
- 支持 ThinkPHP 8.0+

### 常见问题

**Q: 如何获取支付宝公钥？**  
A: 登录支付宝开放平台，在应用详情中查看并复制公钥。

**Q: 微信支付的API密钥在哪里获取？**  
A: 登录微信支付商户平台，在账户中心 > API安全中设置。

**Q: 回调地址配置后收不到通知？**  
A: 确保回调地址可公网访问，且防火墙允许支付平台的IP。

## 📄 License

MIT License

## 👥 作者

Bleeld - support@bleeld.com

---

**祝您使用愉快！** 🎉
