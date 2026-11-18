<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Vcsb\SystemSettings\Enums\SettingGroup;
use Vcsb\SystemSettings\Enums\SettingType;
use Vcsb\SystemSettings\Models\SystemSetting;
use Vcsb\SystemSettings\Seeders\SystemSettingSeeder;
use Vcsb\SystemSettings\SettingSchemaValidator;

// 包内测试: 显式绑 app 测试基类, 否则 Pest 默认 TestCase 没有 Laravel 容器 (strategy 包同坑)
uses(TestCase::class, RefreshDatabase::class);

/** 管理员: viewNova 邮箱白名单内 */
function settingsAdmin(): User
{
    return User::factory()->admin()->create();
}

/** 播种设置结构并返回分组行数 */
function seedSettings(): int
{
    (new SystemSettingSeeder)->run();

    return SystemSetting::count();
}

it('种子按分组落库, schema 是合法 JSON Schema 且控件类型全覆盖', function () {
    expect(seedSettings())->toBe(4)
        ->and(SystemSetting::get('group')->map(fn ($row) => $row->group->value)->sort()->values()->all())
        ->toBe(collect(SettingGroup::cases())->map(fn ($group) => $group->value)->sort()->values()->all());

    $keys = 0;
    $controls = [];
    SystemSetting::all()->each(function (SystemSetting $row) use (&$keys, &$controls): void {
        // 写库前 Seeder 已断言过, 这里再独立断言一次文档结构与 x-order 完整性
        SettingSchemaValidator::assertSchema($row->schema);
        expect($row->schema['type'])->toBe('object')
            ->and($row->schema['additionalProperties'])->toBeFalse()
            // jsonb 会重排 properties 对象键, 集合相等即可 (渲染顺序以 x-order 为准)
            ->and($row->schema['required'])->toEqualCanonicalizing(array_keys($row->schema['properties']))
            ->and($row->schema['x-order'])->toEqualCanonicalizing(array_keys($row->schema['properties']));

        foreach ($row->schema['properties'] as $key => $field) {
            $keys += 1;
            $controls[] = $field['x-control'];
            if (! array_key_exists($key, $row->value)) {
                throw new RuntimeException('设置值缺失: '.$row->group->value.'.'.$key);
            }
        }
    });

    expect($keys)->toBe(21)
        ->and(collect($controls)->unique()->sort()->values()->all())
        ->toBe(collect(SettingType::cases())->map(fn ($type) => $type->value)->sort()->values()->all())
        ->and(SystemSetting::where('group', 'general')->sole()->value['site_name'])->toBe('vc.sb');
});

it('管理员拉分组清单只含页签, 不带组内数据 (按需加载)', function () {
    seedSettings();
    $this->actingAs(settingsAdmin());

    $json = $this->getJson('/nova-vendor/system-settings')
        ->assertSuccessful()
        ->json();

    if (! isset($json['groups']) || ! is_array($json['groups']) || ! isset($json['groups'][0])) {
        throw new RuntimeException('分组清单响应缺少 groups');
    }

    $first = $json['groups'][0];

    expect($json['groups'])->toHaveCount(4)
        ->and($first['key'])->toBe('general')
        ->and($first['label'])->toBe('常规设置')
        ->and($first)->not->toHaveKey('settings');
});

it('管理员按分组拉取设置返回 schema 组装值, 非法分组 404', function () {
    seedSettings();
    $this->actingAs(settingsAdmin());

    $json = $this->getJson('/nova-vendor/system-settings/general')
        ->assertSuccessful()
        ->json();

    if (! isset($json['settings'][0])) {
        throw new RuntimeException('分组设置响应缺少 settings');
    }

    expect($json['key'])->toBe('general')
        ->and($json['label'])->toBe('常规设置')
        ->and($json['settings'][0]['key'])->toBe('site_name')
        ->and($json['settings'][0]['value'])->toBe('vc.sb')
        ->and($json['settings'][0]['type'])->toBe('text')
        ->and($this->getJson('/nova-vendor/system-settings/nope')->status())->toBe(404);
});

it('未登录打设置端点 401, 数据不变', function () {
    seedSettings();

    $this->getJson('/nova-vendor/system-settings')->assertUnauthorized();
    $this->getJson('/nova-vendor/system-settings/general')->assertUnauthorized();
    $this->putJson('/nova-vendor/system-settings/save', ['values' => ['maintenance' => true]])
        ->assertUnauthorized();

    expect(SystemSetting::where('group', 'general')->sole()->value['maintenance'])->toBeFalse();
});

it('普通用户打设置端点 403, 数据不变', function () {
    seedSettings();
    $this->actingAs(User::factory()->create());

    $this->getJson('/nova-vendor/system-settings')->assertForbidden();
    $this->getJson('/nova-vendor/system-settings/general')->assertForbidden();
    $this->putJson('/nova-vendor/system-settings/save', ['values' => ['maintenance' => true]])
        ->assertForbidden();

    expect(SystemSetting::where('group', 'general')->sole()->value['maintenance'])->toBeFalse();
});

it('管理员按类型保存全部控件并落库', function () {
    seedSettings();
    $this->actingAs(settingsAdmin());

    $json = $this->putJson('/nova-vendor/system-settings/save', [
        'values' => [
            // 开关
            'maintenance' => true,
            // 单选 (字符串 enum)
            'default_locale' => 'en',
            // 单选 (整数 enum)
            'lock_minutes' => 60,
            // 多选 (子集) + 空数组清空
            'login_domains' => ['163.com', 'qq.com'],
            'admin_ip_allow' => [],
            // 数字 (JSON 整数)
            'password_min' => 9,
            // 文本 / 多行文本
            'site_name' => '新站点名',
            'maintenance_note' => '维护中, 请稍候',
        ],
    ])->assertSuccessful()->json();

    if (! isset($json['saved'])) {
        throw new RuntimeException('保存响应缺少 saved');
    }

    $general = SystemSetting::where('group', 'general')->sole();
    $security = SystemSetting::where('group', 'security')->sole();
    $user = SystemSetting::where('group', 'user')->sole();

    expect($json['saved'])->toBe(8)
        ->and($general->value['maintenance'])->toBeTrue()
        ->and($general->value['default_locale'])->toBe('en')
        ->and($general->value['site_name'])->toBe('新站点名')
        ->and($general->value['maintenance_note'])->toBe('维护中, 请稍候')
        ->and($user->value['login_domains'])->toBe(['163.com', 'qq.com'])
        ->and($user->value['password_min'])->toBe(9)
        ->and($security->value['lock_minutes'])->toBe(60)
        ->and($security->value['admin_ip_allow'])->toBe([])
        // 未提交键保持原值 (部分提交合并语义)
        ->and($general->value['site_motto'])->toBe('金融交易实验室');
});

it('未知键与非法值整批拒绝, 数据不变', function () {
    seedSettings();
    $this->actingAs(settingsAdmin());

    // 未知键
    $this->putJson('/nova-vendor/system-settings/save', ['values' => ['hacker_key' => true]])
        ->assertStatus(422);
    // 开关收字符串 (JSON Schema boolean 不符)
    $this->putJson('/nova-vendor/system-settings/save', ['values' => ['maintenance' => 'yes']])
        ->assertStatus(422);
    // 单选不在 enum
    $this->putJson('/nova-vendor/system-settings/save', ['values' => ['default_locale' => 'fr']])
        ->assertStatus(422);
    // 多选含 enum 外的项 (items.enum)
    $this->putJson('/nova-vendor/system-settings/save', ['values' => ['login_domains' => ['nope.com']]])
        ->assertStatus(422);
    // 数字收非数值
    $this->putJson('/nova-vendor/system-settings/save', ['values' => ['password_min' => 'abc']])
        ->assertStatus(422);
    // values 不是对象
    $this->putJson('/nova-vendor/system-settings/save', ['values' => 'boom'])
        ->assertStatus(422);

    $general = SystemSetting::where('group', 'general')->sole();
    $user = SystemSetting::where('group', 'user')->sole();

    expect($general->value['maintenance'])->toBeFalse()
        ->and($general->value['default_locale'])->toBe('zh_CN')
        ->and($user->value['password_min'])->toBe(8);
});

it('重跑种子刷新 schema 但不覆盖用户改过的值', function () {
    seedSettings();
    $general = SystemSetting::where('group', 'general')->sole();
    $value = $general->value;
    $value['site_name'] = '用户改的名字';
    $general->value = $value;
    $general->save();

    seedSettings();

    $general = SystemSetting::where('group', 'general')->sole();
    expect($general->value['site_name'])->toBe('用户改的名字')
        ->and(array_key_exists('timezone', $general->schema['properties']))->toBeTrue()
        ->and(SystemSetting::count())->toBe(4);
});

it('Policy 禁新增删除, 读写只放 viewNova 白名单', function () {
    seedSettings();
    $admin = settingsAdmin();
    $plain = User::factory()->create();
    $setting = SystemSetting::where('group', 'general')->sole();

    expect($admin->can('viewAny', SystemSetting::class))->toBeTrue()
        ->and($admin->can('view', $setting))->toBeTrue()
        ->and($admin->can('update', $setting))->toBeTrue()
        ->and($admin->can('create', SystemSetting::class))->toBeFalse()
        ->and($admin->can('delete', $setting))->toBeFalse()
        ->and($admin->can('restore', $setting))->toBeFalse()
        ->and($admin->can('forceDelete', $setting))->toBeFalse()
        ->and($plain->can('viewAny', SystemSetting::class))->toBeFalse()
        ->and($plain->can('view', $setting))->toBeFalse()
        ->and($plain->can('update', $setting))->toBeFalse()
        ->and($plain->can('create', SystemSetting::class))->toBeFalse()
        ->and($plain->can('delete', $setting))->toBeFalse();
});
