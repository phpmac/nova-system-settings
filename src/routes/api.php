<?php

use Illuminate\Http\JsonResponse;
use Laravel\Nova\Http\Requests\NovaRequest;
use Vcsb\SystemSettings\Enums\SettingGroup;
use Vcsb\SystemSettings\Models\SystemSetting;
use Vcsb\SystemSettings\SettingSaver;

/**
 * 系统设置读写端点 (nova 鉴权链 + 工具授权, 前缀 nova-vendor/system-settings).
 * 按需加载: GET / 只发分组页签, GET /{group} 点开哪组才发哪组的 schema+value, 校验收口在 SettingSaver.
 */
Route::get('/', function (NovaRequest $request): JsonResponse {
    // 页签轻查询: 只取 group 列, 组内 schema+value 点页签才拉
    $existing = SystemSetting::get(['group'])
        ->map(static fn (SystemSetting $row): string => $row->group->value);

    return response()->json([
        'groups' => collect(SettingGroup::cases())
            ->filter(static fn (SettingGroup $group): bool => $existing->contains($group->value))
            ->map(static fn (SettingGroup $group): array => [
                'key' => $group->value,
                'label' => $group->label(),
            ])
            ->values()
            ->all(),
    ]);
});

Route::get('{group}', function (NovaRequest $request, string $group): JsonResponse {
    // 分组白名单: 枚举外的组直接 404 (枚举是分组唯一来源)
    if (SettingGroup::tryFrom($group) === null) {
        abort(404, '设置分组不存在: '.$group);
    }

    $row = SystemSetting::where('group', $group)->first();
    if ($row === null) {
        throw new RuntimeException('系统设置分组行缺失, 请重跑种子: '.$group);
    }

    $value = is_array($row->value) ? $row->value : [];
    $properties = $row->properties();
    if (! isset($row->schema['x-order']) || ! is_array($row->schema['x-order'])) {
        throw new RuntimeException('系统设置 schema 缺少 x-order: '.$group);
    }

    // 组内字段按 schema 的 x-order 渲染
    $settings = [];
    foreach ($row->schema['x-order'] as $key) {
        if (! is_string($key) || ! array_key_exists($key, $properties)) {
            throw new RuntimeException('系统设置 x-order 指向未知字段: '.$group.'.'.strval($key));
        }
        $field = $properties[$key];
        if (! array_key_exists($key, $value)) {
            throw new RuntimeException('设置值缺失: '.$group.'.'.$key.' (重跑种子补默认值)');
        }

        $settings[] = [
            'key' => $key,
            'label' => $field['title'],
            'description' => array_key_exists('description', $field) ? $field['description'] : null,
            'type' => $field['x-control'],
            'options' => array_key_exists('x-options', $field) ? $field['x-options'] : null,
            'value' => $value[$key],
            'default' => $field['default'],
        ];
    }

    return response()->json([
        'key' => $group,
        'label' => SettingGroup::from($group)->label(),
        'settings' => $settings,
    ]);
});

Route::put('save', function (NovaRequest $request): JsonResponse {
    $saved = app(SettingSaver::class)->save($request->input('values'));

    return response()->json(['saved' => $saved]);
});
