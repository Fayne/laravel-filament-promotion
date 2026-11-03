<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Promotion

简体中文 | [English](README.en.md)

Laravel 促销规则与优惠券管理包，提供 Filament 后台、优惠计算服务和优惠券核销服务。

## 功能与边界

在 Filament 管理促销规则和优惠券，在购物车试算优惠，并在结账时预占、核销或释放券额度。计算结果包含行级与单位分摊，可由 Commerce 保存为部分退款依据。

## 1. 安装与面板接入

### 环境要求

- PHP 8.4+
- Laravel 13+
- Filament 5+
- Fayne CMS Core（租户上下文和权限集成）

### 安装并注册插件

```bash
composer require fayne/laravel-filament-promotion
php artisan ec:promotion:install
```

安装命令会运行宿主的待执行迁移，并询问是否创建默认停用的 9 折演示促销和优惠券。使用 `--demo` 跳过询问，多租户可加 `--tenant=租户ID或slug`。自动化安装且不创建演示数据时使用 `--no-interaction`；生产环境需要确认，可用 `--force`。

在需要管理促销的 Filament PanelProvider 中注册插件：

```php
use Fayne\Promotion\PromotionPlugin;

->plugins([
    PromotionPlugin::make(),
])
```

插件会在该面板注册“促销活动”和“优惠券”资源。包会注册按 CMS TenantContext 校验租户归属的 Laravel Policy，并向 CMS 权限注册表声明促销和优惠券 CRUD 权限；用户仍须由宿主授予对应权限。管理界面和写入会拒绝缺失租户上下文，模型保存时忽略表单提交的 tenant_id 并采用可信租户。

## 2. 后台能力

促销资源支持名称搜索、类型/启用状态筛选、优先级排序、有效期、条件和动作配置。优惠券资源支持创建、查看列表、编辑、删除、所属促销、唯一券码、有效期和使用次数上限。已用次数由核销服务维护，后台不可手动修改。

规则条件以根级 AND 组合，可配置购物车金额、用户组、SKU、分类、时间条件和 AND/OR 组合组；组合组最多两层。动作包括固定金额优惠、百分比优惠、免运费和满件折扣。满件折扣针对指定 SKU 的每组 buy_quantity 商品按百分比优惠，未实现赠品。

金额采用最小货币单位整数，例如 USD 12.34 表示 1234；百分比使用基点，例如 10% 表示 1000。Context 的 subtotal 必须等于购物车明细总和。单次计算只处理一种币种。

### 配置第一个优惠

1. 在「促销活动」新建自动促销或优惠券促销，设置有效期、适用条件和优惠动作。
2. 如果选择优惠券促销，在「优惠券」中关联该活动，设置券码和可选的使用次数上限。
3. 需要停止新使用时，停用促销或优惠券。仍关联优惠券的促销，以及已有预占记录的优惠券，受删除保护。

## 3. 计算优惠

```php
use Fayne\Promotion\DTO\CartItem;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\Engine\PromotionEngine;
use Fayne\Promotion\Services\TenantContext;

$tenant = app(TenantContext::class);
$result = app(PromotionEngine::class)->calculate(new Context(
    tenantId: $tenant->tenantId(),
    currency: $tenant->currency(),
    subtotal: 10000,
    shipping: 1000,
    items: [new CartItem(lineId: 'line-1', sku: 'SKU-A', unitPrice: 10000, quantity: 1)],
    userGroups: ['vip'],
    couponCode: 'WELCOME10',
));

$result->totalDiscount;
$result->payable;
```

Commerce 每次调用必须提供可信的 tenantId、customerId（如有）和该租户配置的币种。每租户首期只支持一种交易币种，默认 CNY；Context 必须显式传入 tenantId，不能从请求表单信任租户 ID。引擎按 priority 降序、ID 升序处理有效自动促销和单个优惠码对应的促销，二者共同参与计算；每个促销的条件按原始 Context 判断，动作按配置顺序执行。条件中的 SKU/分类仅决定规则资格，固定/百分比优惠作用于全商品金额；满件优惠只作用于配置 SKU 并受目标行剩余金额限制。`stop_processing` 仅在该促销产生正数优惠后生效。计算为纯试算，不写库、不预占或核销优惠券。

## 4. 优惠券验证与额度生命周期

```php
use Fayne\Promotion\Services\CouponService;
use Fayne\Promotion\Services\TenantContext;

$coupons = app(CouponService::class);
$tenant = app(TenantContext::class);
$tenantId = $tenant->tenantId();
$currency = $tenant->currency();
$coupon = $coupons->validate($tenantId, $currency, ' welcome10 '); // 不区分大小写
$reservation = $coupons->reserve($tenantId, $currency, 'WELCOME10', 'checkout', $checkoutId, $idempotencyKey, now()->addMinutes(15), $customerId);
$coupons->commit($tenantId, $reservation->id); // 支付确认后
$coupons->release($tenantId, $reservation->id); // 取消时；重复释放无副作用
$coupons->releaseExpired($tenantId); // 由宿主调度任务周期执行
```

预占请求按租户内幂等键和业务来源去重，参数不同会报冲突；预占、核销和释放通过事务按优惠券再预占记录的顺序加锁。过期预占在显式调用 `releaseExpired` 转为释放前仍占用额度。延迟支付若遇到已释放记录，`commit` 会冲突，宿主需处理支付异常/补偿，包不会自行退款。Commerce 负责在同数据库连接中协调订单与预占事务并保存成交规则、币种、行/单位分摊快照；退款按成交单位净额计算，首期退款不自动恢复优惠券额度。SQLite 不提供与 MySQL/PostgreSQL 等价的行锁并发保证，生产数据库仍需验证额度竞争。

限制：单次仅一个优惠码，不支持赠品、每消费者限额、自动退款返券或自动重算历史订单。跨租户管理员也必须由宿主按可信 tenant context 授权；包只提供租户归属校验，不自动授予业务权限。

## 5. 规则 JSON 契约

```php
$conditions = [
    ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => 5000]],
    ['type' => 'composite', 'config' => [
        'logic' => 'or',
        'conditions' => [
            ['type' => 'user_group', 'config' => ['operator' => 'in', 'values' => ['vip']]],
            ['type' => 'product_sku', 'config' => ['operator' => 'in', 'values' => ['SKU-A']]],
        ],
    ]],
];

$actions = [
    ['type' => 'percentage_discount', 'config' => ['rate' => 1000, 'max_discount' => 2500]],
];
```

## 6. 时间和语言

有效起止时间均包含边界。数据库时间按 Laravel 应用时区写入；建议宿主统一使用 UTC，并按面板时区显示。资源提供英文和简体中文导航翻译，字段标签当前使用英文。

## 7. 测试

```bash
composer install
composer test
composer format
```

MySQL 行锁并发验收由宿主仓库 GitHub Actions 的 `laravel-filament-promotion MySQL concurrency` job 执行，覆盖最后一次额度争抢和核销/释放竞争；普通 SQLite 测试会跳过这些用例。该测试要求专用 `cpt_` 表前缀，只清理自身隔离表。

### 包级测试隔离

测试使用 `Fayne\Promotion\Tests` 命名空间，避免与宿主测试基类冲突。启动文件优先加载包自身的 Composer 依赖，也支持开发宿主依赖作为备用。租户上下文按 Laravel 请求/队列任务生命周期隔离；宿主必须在每个请求或任务中初始化，缺少上下文时拒绝执行。

百分比和满件折扣均按原始合格商品金额计算折扣，再按适用行剩余额度及 max_discount 封顶。满件折扣仅分摊到目标 SKU 行；之前的优惠不会改变其百分比计算基数。最大余数同余时按 lineId 分配，返回映射的键插入顺序不属于业务契约。

计算、验券与预占要求显式租户和币种匹配当前可信上下文。核销、释放及清理任务也必须先初始化对应的租户上下文。

上线前的待验收事项见[生产就绪清单](PRODUCTION-READINESS.md)。

## 许可证

本软件为专有软件，使用和分发需获得版权持有人授权。详见 [LICENSE.md](LICENSE.md)。
