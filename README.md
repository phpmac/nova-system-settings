# SystemSettings (Nova 系统设置扩展包)

自包含 Nova Tool 扩展包: Schema 驱动的后台系统设置页 (常规/用户/安全/通知四分组), 替代 Nova 资源列表页.
模型/枚举/迁移/Seeder/工厂/Policy/中间件全在包内, 零 `App\` 引用, `composer require` 即用.

## 结构: 一行一个分组, schema + value 两个 jsonb

    system_settings
    ├── group  string unique  分组标识 (枚举唯一来源, 列只存 value)
    ├── label  string         分组显示名 (枚举 label())
    ├── schema jsonb          标准 JSON Schema draft 2020-12 文档
    └── value  jsonb          当前值 {设置键: 值}

schema 文档形状 (strategies 的 launch.json 同款约定):

    {
        "$schema": "https://json-schema.org/draft/2020-12/schema",
        "type": "object",
        "additionalProperties": false,
        "required": [全部设置键],
        "x-order": [渲染顺序的设置键],
        "properties": {
            "设置键": {
                "type": "boolean|string|integer|number|array",
                "title": "显示名",
                "description": "说明",
                "default": 默认值,
                "x-control": "toggle|select|multi|text|textarea|number",
                "enum": [单选的合法值],            // 仅 select
                "items": {"type": "string", "enum": [多选的合法值]},  // 仅 multi
                "x-options": [{"value": v, "label": "显示"}]          // select/multi
            }
        }
    }

## 硬规则

- **结构只写一处**: Seeder 常量 (分组 => properties), 加设置 = 加一条; 写库前 `SettingSchemaValidator::assertSchema` 断言, 非法当场炸不落脏结构
- **双份断言**: 字段级断言 (JSON 类型与 x-control 匹配 / title 非空 / default 存在且类型一致 / select 的 enum 与 x-options 值列一致) + opis `loadObjectSchema` 整文档加载断言是合法 draft 2020-12
- **multi 的选项约束只在 items.enum**: property 级 enum 对数组恒假 (要求整个数组等于某个标量), 校验器必须禁掉 multi 的 property 级 enum; x-options 与 items.enum 对齐
- **jsonb 会重排对象键**: required/x-order 与 properties 键序的比较只能按集合相等, 渲染顺序以 x-order 列表为准
- **保存**: 按分组归拢提交值 → opis 按 schema 校验 (required 换成已提交键集, 允许部分提交) → 整批通过才合并写回; 未知键/非法值带键名 422 整批不落库; 未提交键保持原值
- **重跑种子**: schema 无条件刷新 (加字段即生效), value 只补新增键的 default, 禁止覆盖后台改过的值
- **恢复默认 = HTML reset 语义**: 还原到页面加载时的值, 不碰 schema default
- **控件类型枚举维护**: x-control 合法取值来自 `Enums\SettingType` (toggle/select/multi/text/textarea/number), 禁散写字符串; select 的 enum 允许整数/数值型

## 页面 (Nova Tool 自定义页, 禁资源列表页)

- 单卡布局: 分组段选页签 (SEG_ON/SEG_OFF) + 行区 + 行尾 恢复默认/保存; 卡片 min-height 撑到视口高
- 行 = 左标签列 (title+description) + 右控件列, 按 x-control 分发六种控件; hover/focus-within 高亮当前行 (focus 类 Nova css 没打包, 底色走包内 tool.css 变量补色)
- 下拉控件照 TeamSelect/SymbolSelect 形态: form-control form-input form-control-bordered 触发器 + 实心三角 chevron + 键盘↓↑EnterEsc + 点外关 + 开↔关
- 数字控件发 JSON 数字 (Number(...)), 否则 opis integer 判不过字符串
- Vue script setup 里函数名禁与 prop 同名 (曾因 function selected() 遮蔽 prop, 模板 .includes 运行时才炸)
- 分组记忆存 localStorage, 损坏直接抛; fetch 带 X-XSRF-TOKEN (从 cookie 解) + X-Requested-With

## 端点与安全

- 页面壳: `Nova::router(['nova', 'nova.auth', NovaAuthorize::class, 工具Authorize::class], 'system-settings')`
- 数据端点: `nova-vendor/system-settings` 同链. **链里必须带 Nova 自家 `Laravel\Nova\Http\Middleware\Authorize` (viewNova 门禁)**: nova.auth 只管登录态, 缺它普通用户也能拉数据 (三个旧工具链缺它被普通用户 200 的教训)
- `GET /nova-vendor/system-settings` → `{groups: [{key, label, settings: [{key, label, description, type, options, value, default}]}]}` (由 schema+value 组装, 前端契约与 schema 字段解耦)
- `PUT /nova-vendor/system-settings/save` body `{values: {设置键: 值}}` → `{saved: N}`, 未知键/非法值 422

## 测试口径

包内 Pest `tests/SystemSettingsTest.php`: `uses(TestCase::class, RefreshDatabase::class)` (包测试不在宿主根 tests/ 覆盖范围, 缺 TestCase 绑定就没有 Laravel 容器, 连库直接炸; 本包测试绑宿主基类 `Tests\TestCase` + `App\Models\User`, 只在开发宿主内跑).
必测: schema 断言独立再跑一遍 (required/x-order 集合覆盖 + x-control 全覆盖) / 读端点形状 / 游客 401 / 普通用户 403 数据不变 / 六类控件混合保存落库 / 未知键非法值 422 数据不变 / 重跑种子不覆盖用户值.

    vendor/bin/pest nova-components/SystemSettings/tests

## 踩过的坑

- 社区 outl1ne/nova-settings 用 Nova 原生字段当定义, 不是 Schema: 灵活只在 Nova UI 内, schema 不落库不可下发. 要程序消费/下发渲染选本方案
- 数据库 group 列注释禁复述枚举取值 (枚举是唯一来源)
- Seeder 的包内断言与测试断言双保险: Seeder 挡写库, 测试挡回潮

## 自包含边界与宿主职责

包内全有: ToolServiceProvider (loadMigrationsFrom + Gate::policy + Nova::mix + 路由) / Tool (菜单兜底 menu()) / Model / 枚举 / 校验器 / 保存器 / Policy / Authorize 中间件 / Seeder / 工厂 / dist 构建产物.
宿主只做三处登记: `NovaServiceProvider::tools()` 注册 `new SystemSettings` / `Nova::mainMenu` 挂 MenuSection (自定义了 mainMenu 时 Tool 的 menu() 不自动出现) / `DatabaseSeeder` 调 `Vcsb\SystemSettings\Seeders\SystemSettingSeeder` + `php artisan migrate`.

## 安装 (宿主项目)

宿主 composer.json: repositories 加 nova 源 + 本包 git 仓库源, require `vcsb/system-settings: ^1.0`;
nova.laravel.com 是私有源, 需 `composer config http-basic.nova.laravel.com <NOVA_USERNAME> <NOVA_LICENSE_KEY>`.

    composer require vcsb/system-settings:^1.0
    php artisan migrate

构建 (仅改前端时): 包内 `bun install && bun run production` → `dist/` 必须入库 (composer 安装不带 node_modules).

## 发布 (维护者)

版本只看 git tag, composer.json 禁写 version 字段 (inline version 永远压过 tag, composer 恒解析旧版).
发版: 改完 → commit → `git tag 1.0.x` → push 含 tag → 宿主 `composer update vcsb/system-settings`.
