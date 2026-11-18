<?php

namespace Vcsb\SystemSettings\Seeders;

use Illuminate\Database\Seeder;
use Vcsb\SystemSettings\Enums\SettingGroup;
use Vcsb\SystemSettings\Models\SystemSetting;
use Vcsb\SystemSettings\SettingSchemaValidator;

/**
 * 系统设置种子: schema 是标准 JSON Schema (draft 2020-12, strategies 的 launch.json 同款结构:
 * properties + required + additionalProperties + x-order, 控件语义走 x-control/x-options 注解),
 * 一行一个分组. 加设置在对应分组的 PROPERTIES 里加一条, 写库前过 SettingSchemaValidator::assertSchema 断言.
 * value 是后台页改的当前值: 首次创建按 default 落演示值, 重跑只补新增键的默认值, 不覆盖已有值.
 */
class SystemSettingSeeder extends Seeder
{
    /** 分组 => properties (设置键 => JSON Schema 字段定义), x-control 取 Vcsb\SystemSettings\Enums\SettingType 的值 */
    private const PROPERTIES = [
        'general' => [
            'site_name' => ['type' => 'string', 'title' => '站点名称', 'description' => '站点对外显示的名称', 'default' => 'vc.sb', 'x-control' => 'text'],
            'site_motto' => ['type' => 'string', 'title' => '站点标语', 'description' => '页脚标语与登录页口号', 'default' => '金融交易实验室', 'x-control' => 'text'],
            'maintenance' => ['type' => 'boolean', 'title' => '维护模式', 'description' => '开启后前台显示维护提示 (演示开关, 未接实际功能)', 'default' => false, 'x-control' => 'toggle'],
            'maintenance_note' => ['type' => 'string', 'title' => '维护公告', 'description' => '维护模式开启时展示给用户的文案', 'default' => '系统升级中, 预计 1 小时后恢复, 数据不受影响.', 'x-control' => 'textarea'],
            'default_locale' => ['type' => 'string', 'title' => '默认语言', 'description' => '新访客与注册用户的默认界面语言', 'enum' => ['zh_CN', 'en', 'ja'], 'x-options' => [
                ['value' => 'zh_CN', 'label' => '简体中文'],
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'ja', 'label' => '日本語'],
            ], 'default' => 'zh_CN', 'x-control' => 'select'],
            'timezone' => ['type' => 'string', 'title' => '默认时区', 'description' => '后台时间显示与新用户的默认时区', 'enum' => ['Asia/Shanghai', 'UTC'], 'x-options' => [
                ['value' => 'Asia/Shanghai', 'label' => 'UTC+8 北京'],
                ['value' => 'UTC', 'label' => 'UTC'],
            ], 'default' => 'Asia/Shanghai', 'x-control' => 'select'],
        ],

        'user' => [
            'register' => ['type' => 'boolean', 'title' => '用户注册开关', 'description' => '关闭后注册页停止接收新用户 (演示开关)', 'default' => true, 'x-control' => 'toggle'],
            'email_verify' => ['type' => 'boolean', 'title' => '注册邮箱验证', 'description' => '注册后必须验证邮箱才能进入控制台', 'default' => true, 'x-control' => 'toggle'],
            'password_min' => ['type' => 'integer', 'title' => '密码最小长度', 'description' => '注册与改密码的最小长度要求 (位)', 'default' => 8, 'x-control' => 'number'],
            'default_plan' => ['type' => 'string', 'title' => '注册默认套餐', 'description' => '新用户注册后归属的套餐档', 'enum' => ['free', 'pro', 'max'], 'x-options' => [
                ['value' => 'free', 'label' => '免费版'],
                ['value' => 'pro', 'label' => '专业版'],
                ['value' => 'max', 'label' => '旗舰版'],
            ], 'default' => 'free', 'x-control' => 'select'],
            'login_domains' => ['type' => 'array', 'title' => '允许的邮箱域名', 'description' => '限制注册邮箱后缀, 不选 = 不限制', 'x-options' => [
                ['value' => 'qq.com', 'label' => 'qq.com'],
                ['value' => '163.com', 'label' => '163.com'],
                ['value' => 'gmail.com', 'label' => 'gmail.com'],
                ['value' => 'outlook.com', 'label' => 'outlook.com'],
            ], 'items' => ['type' => 'string', 'enum' => ['qq.com', '163.com', 'gmail.com', 'outlook.com']], 'default' => [], 'x-control' => 'multi'],
        ],

        'security' => [
            'two_factor' => ['type' => 'boolean', 'title' => '强制二次验证', 'description' => '要求全部用户开启 2FA 后才能进控制台 (演示开关)', 'default' => false, 'x-control' => 'toggle'],
            'login_limit' => ['type' => 'integer', 'title' => '登录失败锁定', 'description' => '连续失败达到该次数后锁定账号', 'default' => 5, 'x-control' => 'number'],
            'lock_minutes' => ['type' => 'integer', 'title' => '锁定时长', 'description' => '触发登录锁定后的等待时长 (分钟)', 'enum' => [5, 15, 60], 'x-options' => [
                ['value' => 5, 'label' => '5 分钟'],
                ['value' => 15, 'label' => '15 分钟'],
                ['value' => 60, 'label' => '1 小时'],
            ], 'default' => 15, 'x-control' => 'select'],
            'session_life' => ['type' => 'integer', 'title' => '会话有效期', 'description' => '登录态保持时长 (分钟), 到期重新登录', 'enum' => [30, 120, 1440], 'x-options' => [
                ['value' => 30, 'label' => '30 分钟'],
                ['value' => 120, 'label' => '2 小时'],
                ['value' => 1440, 'label' => '24 小时'],
            ], 'default' => 120, 'x-control' => 'select'],
            'admin_ip_allow' => ['type' => 'array', 'title' => '后台 IP 白名单', 'description' => '限制访问后台的来源 IP, 不选 = 不限制', 'x-options' => [
                ['value' => '192.168.1.0/24', 'label' => '192.168.1.0/24 (内网)'],
                ['value' => '10.0.0.0/8', 'label' => '10.0.0.0/8 (内网)'],
                ['value' => '203.0.113.7', 'label' => '203.0.113.7 (示例)'],
            ], 'items' => ['type' => 'string', 'enum' => ['192.168.1.0/24', '10.0.0.0/8', '203.0.113.7']], 'default' => [], 'x-control' => 'multi'],
            'password_expire' => ['type' => 'integer', 'title' => '密码过期天数', 'description' => '超过该天数提示改密, 0 = 不过期', 'default' => 90, 'x-control' => 'number'],
            'webhook_secret' => ['type' => 'string', 'title' => '回调签名密钥', 'description' => '外部回调签名校验密钥 (输入显示星号, 值仍明文保存)', 'default' => '', 'x-control' => 'secret'],
        ],

        'notification' => [
            'alert_mail' => ['type' => 'boolean', 'title' => '邮件提醒总开关', 'description' => '关闭后全站停止发送邮件提醒 (演示开关)', 'default' => true, 'x-control' => 'toggle'],
            'alert_channel' => ['type' => 'string', 'title' => '告警渠道', 'description' => '系统级告警 (健康检查/密钥失效) 发到哪里', 'enum' => ['mail', 'discord', 'both'], 'x-options' => [
                ['value' => 'mail', 'label' => '邮件'],
                ['value' => 'discord', 'label' => 'Discord'],
                ['value' => 'both', 'label' => '邮件 + Discord'],
            ], 'default' => 'mail', 'x-control' => 'select'],
            'alert_throttle' => ['type' => 'integer', 'title' => '告警节流', 'description' => '同类告警的重复提醒间隔 (分钟)', 'default' => 60, 'x-control' => 'number'],
            'notify_retention' => ['type' => 'integer', 'title' => '通知保留天数', 'description' => '站内通知的保留天数, 到期由清理任务删除', 'default' => 90, 'x-control' => 'number'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PROPERTIES as $group => $properties) {
            // 写库前断言文档是合法 JSON Schema 且符合控件语义约定 (非法当场炸, 不落脏结构)
            $schema = self::schemaDoc($properties);
            SettingSchemaValidator::assertSchema($schema);

            // 首次创建: value 按 default 落演示值; 已有行保住用户改过的值
            $row = SystemSetting::firstOrCreate(
                ['group' => $group],
                [
                    'label' => SettingGroup::from($group)->label(),
                    'schema' => $schema,
                    'value' => self::defaultsOf($schema),
                ],
            );

            // schema 以代码为源无条件刷新; value 只补 schema 新增键的默认值
            $row->label = SettingGroup::from($group)->label();
            $row->schema = $schema;
            $value = is_array($row->value) ? $row->value : [];
            foreach (self::defaultsOf($schema) as $key => $default) {
                if (! array_key_exists($key, $value)) {
                    $value[$key] = $default;
                }
            }
            $row->value = $value;

            if ($row->isDirty()) {
                $row->save();
            }
        }
    }

    /**
     * properties 组装成完整 JSON Schema 文档: required 按字段序全覆盖, x-order 即渲染顺序.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function schemaDoc(array $properties): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'x-order' => array_keys($properties),
            'properties' => $properties,
        ];
    }

    /**
     * 全部字段的 default 值表 (首次落 value 用).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function defaultsOf(array $schema): array
    {
        $out = [];
        foreach ($schema['properties'] as $key => $field) {
            $out[$key] = $field['default'];
        }

        return $out;
    }
}
