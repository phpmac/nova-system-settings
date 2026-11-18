# Nova 系统设置扩展包

Laravel Nova 5 系统设置扩展包: 以标准 JSON Schema (draft 2020-12) 驱动的后台设置页, 按分组页签渲染, 支持 toggle / select / multi / text / textarea / number 六类控件, 设置结构存库可程序消费, 替代 Nova 资源列表页形态的设置管理.

## 功能特性

- Schema 驱动: 设置结构以 JSON Schema draft 2020-12 存库, 后台按 schema 渲染控件, 外部程序可读 schema 与值
- 双重校验: opis 全文档加载校验 + 字段级断言 (类型/选项/default 一致性), 非法数据当场拒绝不落库
- 部分提交: 按分组保存, 未提交键保持原值, 未知键或非法值整批 422 不落库
- 自包含: 模型/迁移/Seeder/Policy/路由/构建产物全在包内, 自动发现, 零宿主代码侵入

## 环境要求

- PHP ^8.2
- Laravel Nova ^5.0 (宿主需已配置 nova.laravel.com composer 源与授权凭据)

## 安装

```bash
composer require aivcsb/nova-system-settings:^1.0
php artisan migrate
```

ServiceProvider 经 composer 自动发现, 无需手动注册. 在 `app/Providers/NovaServiceProvider.php` 注册工具与菜单:

```php
use Vcsb\SystemSettings\SystemSettings;
use Laravel\Nova\Menu\MenuSection;

// tools() 返回数组中加入
new SystemSettings,

// Nova::mainMenu 回调中加入
MenuSection::make('系统设置')->path('/system-settings')->icon('adjustments'),
```

在 `database/seeders/DatabaseSeeder.php` 注册种子 (写入四个内置分组与默认值):

```php
$this->call(\Vcsb\SystemSettings\Seeders\SystemSettingSeeder::class);
```

## 定义设置

设置结构只定义在 Seeder 常量一处, 每行一个分组, 加一条设置即加一个 property 条目, 写库前自动过 schema 断言:

```php
// src/Seeders/SystemSettingSeeder.php
'security' => [
    'lock_minutes' => ['type' => 'integer', 'title' => '锁定时长',
     'description' => '触发登录锁定后的等待时长 (分钟)',
     'enum' => [5, 15, 60], 'x-options' => [['value' => 5, 'label' => '5 分钟']],
     'default' => 15, 'x-control' => 'select'],
],
```

重跑种子: schema 无条件刷新 (加字段即生效), value 只补新增键的 default, 不覆盖后台已改的值.

## 数据结构

`system_settings` 表, 一行一个分组:

| 列 | 类型 | 说明 |
|---|---|---|
| group | string unique | 分组标识 (Enums\\SettingGroup) |
| label | string | 分组显示名 |
| schema | jsonb | 标准 JSON Schema draft 2020-12 文档 |
| value | jsonb | 当前值 {设置键: 值} |

schema 文档形状:

```json
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "type": "object",
    "additionalProperties": false,
    "required": ["全部设置键"],
    "x-order": ["渲染顺序的设置键"],
    "properties": {
        "设置键": {
            "type": "boolean|string|integer|number|array",
            "title": "显示名",
            "description": "说明",
            "default": "默认值",
            "x-control": "toggle|select|multi|text|textarea|number",
            "enum": ["单选的合法值 (仅 select)"],
            "items": {"type": "string", "enum": ["多选的合法值 (仅 multi)"]},
            "x-options": [{"value": "值", "label": "显示名"}]
        }
    }
}
```

## 控件类型

| x-control | schema type | 附加约束 |
|---|---|---|
| toggle | boolean | 无 |
| text / textarea | string | 无 |
| number | integer 或 number | 前端提交 JSON 数字 |
| select | integer 或 string | property 级 `enum` + `x-options` |
| multi | array | 选项约束只在 `items.enum`, x-options 与之对齐 |

## HTTP 接口

页面: `/system-settings` (nova 中间件链 + viewNova 门禁 + 工具授权).

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/nova-vendor/system-settings` | 返回 `{groups: [{key, label, settings: [{key, label, description, type, options, value, default}]}]}`, 由 schema+value 组装, 渲染序按 x-order |
| PUT | `/nova-vendor/system-settings/save` | body `{values: {设置键: 值}}` 返回 `{saved: N}`, 未知键/非法值 422 |

## 包结构

```
src/
├── ToolServiceProvider.php      # 迁移注册 / Gate::policy / 路由 / Nova::mix
├── SystemSettings.php           # Nova Tool (菜单兜底)
├── Models/SystemSetting.php     # 模型 (properties() 取 schema.properties)
├── Enums/{SettingGroup,SettingType}.php
├── SettingSchemaValidator.php   # 字段级断言 + opis 加载校验
├── SettingSaver.php             # 归拢/校验/合并写回
├── Policies/SystemSettingPolicy.php
├── Http/Middleware/Authorize.php
├── Seeders/SystemSettingSeeder.php
├── routes/{inertia,api}.php     # 页面壳 / 数据端点
└── Database/Factories/SystemSettingFactory.php
resources/js/pages/              # Tool.vue / SettingRow.vue / OptionSelect.vue / OptionMulti.vue
database/migrations/             # 建表迁移 (loadMigrationsFrom 注册)
dist/                            # 构建产物 (mix, 已入库)
```

## 测试

```bash
vendor/bin/pest nova-components/SystemSettings/tests
```

覆盖: schema 断言 / 读端点形状 / 游客 401 / 普通用户 403 / 六类控件混合保存 / 非法提交 422 数据不变 / 重跑种子不覆盖用户值 / Policy 只读白名单.

## 许可

MIT
