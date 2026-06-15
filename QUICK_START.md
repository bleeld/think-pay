# Think-Pay 快速开始指南

## 📦 安装完成

支付插件已成功安装到您的项目中！

**位置：** `vendor/bleeld/think-pay`

**版本：** v1.0.0

---

## ⚙️ 配置步骤

### 1. 编辑配置文件

打开 `config/pay.php`，填写您的支付服务商配置：

```php
// 支付宝配置示例
'alipay' => [
    'app_id' => 'your_app_id',              // 必填
    'private_key' => 'your_private_key',    // 必填（不包含头尾）
    'public_key' => 'alipay_public_key',    // 必填（不包含头尾）
    'notify_url' => 'https://yourdomain.com/pay/notify/alipay',
],

// 微信支付配置示例
'wechat' => [
    'app_id' => 'your_app_id',              // 必填
    'mch_id' => 'your_mch_id',              // 必填
    'api_key' => 'your_api_key',            // 必填
    'notify_url' => 'https://yourdomain.com/pay/notify/wechat',
],
```

### 2. 获取支付凭证

#### 支付宝
1. 登录 [支付宝开放平台](https://open.alipay.com/)
2. 创建应用并获取 AppID
3. 生成应用私钥和公钥
4. 在应用中配置公钥
5. 复制支付宝公钥

#### 微信支付
1. 登录 [微信支付商户平台](https://pay.weixin.qq.com/)
2. 获取商户号（MCH ID）
3. 在账户中心 > API安全中设置API密钥
4. 获取AppID（公众号或小程序）

### 3. 配置回调地址

确保回调地址可公网访问，并在配置文件中设置：

```php
'alipay' => [
    'notify_url' => 'https://yourdomain.com/pay/notify/alipay',
    'return_url' => 'https://yourdomain.com/pay/return',
],
'wechat' => [
    'notify_url' => 'https://yourdomain.com/pay/notify/wechat',
],
```

---

## 🚀 使用示例

### 创建支付订单

```php
use bleeld\pay\PayService;

$orderData = [
    'out_trade_no' => 'ORDER_' . date('YmdHis') . rand(1000, 9999),
    'total_amount' => 100.00,
    'subject' => '商品名称',
    'body' => '商品描述',
];

// 使用默认驱动（支付宝）
$result = PayService::createOrder($orderData);

if ($result['code'] === 1) {
    // 跳转到支付页面
    redirect($result['data']['pay_url']);
} else {
    echo '创建订单失败: ' . $result['msg'];
}
```

### 处理支付回调

创建控制器 `app/index/controller/PayNotify.php`：

```php
namespace app\index\controller;

use bleeld\pay\PayService;
use bleeld\pay\Exception\SignatureException;

class PayNotify
{
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
    
    public function wechat()
    {
        try {
            $xml = file_get_contents('php://input');
            $callbackData = json_decode(json_encode(
                simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)
            ), true);
            
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
}
```

添加路由 `route/app.php`：

```php
Route::post('pay/notify/alipay', 'index/PayNotify/alipay');
Route::post('pay/notify/wechat', 'index/PayNotify/wechat');
```

### 监听支付成功事件

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
        
        // TODO: 执行业务逻辑
        \think\facade\Log::info("订单 {$orderNo} 支付成功");
    }
}
```

---

## 🧪 测试

运行测试套件验证安装：

```bash
php vendor/bleeld/think-pay/tests/run_all_tests.php
```

预期输出：

```
总测试数: 4
通过: 4
失败: 0

🎉 所有测试通过！
```

---

## 🔒 安全建议

1. **使用环境变量**存储敏感信息：

```php
// config/pay.php
'alipay' => [
    'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
],
```

2. **生产环境必须使用HTTPS**

3. **定期更新IP白名单**

4. **记录完整的支付日志**

---

## 📚 更多资源

- [完整文档](README.md)
- [安全说明](SECURITY.md)
- [测试套件](tests/README.md)

---

**祝您使用愉快！** 🎉
