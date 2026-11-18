<?php

namespace Vcsb\SystemSettings;

use Illuminate\Validation\ValidationException;
use Vcsb\SystemSettings\Models\SystemSetting;

/**
 * 设置保存器: 提交值按分组 schema 文档走 opis (draft 2020-12) 校验, 整批通过才落库.
 * 类型/enum/数组子集全部由 JSON Schema 判, 本类只负责分组归拢与错误转 422.
 */
class SettingSaver
{
    /**
     * 校验并保存一批设置值, 返回落库条数.
     *
     * @param  mixed  $values  前端提交的 {设置键: 值} 对象, 非对象/未知键/类型不符直接 422
     */
    public function save(mixed $values): int
    {
        if (! is_array($values)) {
            throw ValidationException::withMessages(['values' => 'values 必须是对象']);
        }

        // 设置键 => 分组行 (schema 按行取), 一次取齐避免逐键查库
        $owner = [];
        $rows = SystemSetting::all();
        foreach ($rows as $row) {
            foreach (array_keys($row->properties()) as $key) {
                $owner[$key] = $row;
            }
        }

        // 按分组归拢提交值; 键归属不到任何分组 = 未知键
        $payloads = [];
        foreach ($values as $key => $value) {
            $key = strval($key);
            if (! array_key_exists($key, $owner)) {
                throw ValidationException::withMessages(['values' => '未知设置键: '.$key]);
            }
            $groupKey = $owner[$key]->group->value;
            $payloads[$groupKey][$key] = $value;
        }

        // 每组过一遍 JSON Schema 校验, 第一个出错字段带键名 422, 整批不落库
        foreach ($payloads as $groupKey => $payload) {
            $row = $rows->firstWhere(static fn (SystemSetting $item): bool => $item->group->value === $groupKey);
            if ($row === null) {
                throw ValidationException::withMessages(['values' => '分组行缺失, 请重跑种子: '.$groupKey]);
            }
            $errors = SettingSchemaValidator::errors($row->schema, $payload);
            if ($errors !== []) {
                $field = array_key_first($errors);

                throw ValidationException::withMessages(['values' => '设置 '.$field.' '.$errors[$field]]);
            }
        }

        // 全部通过才写库: 提交键合并回各组 value, 未提交键保持原值
        foreach ($payloads as $groupKey => $payload) {
            $row = $rows->firstWhere(static fn (SystemSetting $item): bool => $item->group->value === $groupKey);
            if ($row === null) {
                throw ValidationException::withMessages(['values' => '分组行缺失, 请重跑种子: '.$groupKey]);
            }
            $current = $row->value;
            if (! is_array($current)) {
                throw ValidationException::withMessages(['values' => '分组值损坏, 请重跑种子: '.$groupKey]);
            }
            $row->value = array_merge($current, $payload);
            $row->save();
        }

        return count($values);
    }
}
