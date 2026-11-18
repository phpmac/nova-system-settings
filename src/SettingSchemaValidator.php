<?php

namespace Vcsb\SystemSettings;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RuntimeException;
use stdClass;
use Throwable;
use Vcsb\SystemSettings\Enums\SettingType;

/**
 * 设置 schema 校验器: 分组 schema 文档必须是合法 JSON Schema (draft 2020-12),
 * 控件语义走 x- 注解 (x-control / x-options / x-order, strategies 的 x-widget 同款思路).
 * assertSchema 在 Seeder 写库前断言结构, errors 用 opis 校验提交值.
 */
final class SettingSchemaValidator
{
    /** 控件类型 => 允许的 JSON Schema 类型 (select 的 enum 可以是字符串或数值) */
    private const CONTROL_TYPES = [
        'toggle' => ['boolean'],
        'select' => ['string', 'integer', 'number'],
        'text' => ['string'],
        'textarea' => ['string'],
        'number' => ['integer', 'number'],
        'multi' => ['array'],
        'secret' => ['string'],
    ];

    /**
     * 断言分组 schema 文档结构合法; 非法抛 RuntimeException (结构由 Seeder 固定, 属程序错误).
     *
     * @param  array<string, mixed>  $schema  完整 JSON Schema 文档
     */
    public static function assertSchema(array $schema): void
    {
        if (! isset($schema['type']) || $schema['type'] !== 'object') {
            throw new RuntimeException('设置 schema 必须是 object');
        }
        if (! isset($schema['additionalProperties']) || $schema['additionalProperties'] !== false) {
            throw new RuntimeException('设置 schema 必须关闭 additionalProperties');
        }

        if (! isset($schema['properties']) || ! is_array($schema['properties']) || $schema['properties'] === []) {
            throw new RuntimeException('设置 schema 缺少 properties');
        }
        $properties = $schema['properties'];
        $keys = array_keys($properties);

        // required 与 x-order 都必须恰好覆盖全部字段 (集合相等, 不比顺序: jsonb 会重排 properties 对象键)
        if (! isset($schema['required']) || ! is_array($schema['required']) || ! self::sameSet($schema['required'], $keys)) {
            throw new RuntimeException('设置 schema required 必须覆盖全部字段');
        }
        if (! isset($schema['x-order']) || ! is_array($schema['x-order']) || ! self::sameSet($schema['x-order'], $keys)) {
            throw new RuntimeException('设置 schema x-order 必须列出全部字段');
        }

        foreach ($properties as $key => $field) {
            if (! is_string($key) || $key === '' || ! is_array($field)) {
                throw new RuntimeException('设置 schema 字段损坏: '.strval($key));
            }
            self::assertField($key, $field);
        }

        // 整文档过一遍 opis 加载, 断言是合法的 draft 2020-12 JSON Schema
        try {
            self::opis()->loader()->loadObjectSchema(self::schemaDoc($schema));
        } catch (Throwable $error) {
            throw new RuntimeException('设置 schema 不是合法 JSON Schema: '.$error->getMessage());
        }
    }

    /**
     * 校验本组提交值, 返回 字段 => 错误信息 (空数组 = 通过).
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload  本组 {设置键: 值}
     * @return array<string, string>
     */
    public static function errors(array $schema, array $payload): array
    {
        if (! isset($schema['properties']) || ! is_array($schema['properties'])) {
            throw new RuntimeException('设置 schema 缺少 properties');
        }
        $properties = $schema['properties'];

        // 键集先自查: 未知键直接指名; 允许部分键提交 (写库侧合并), required 换成已提交键集再校验
        $out = [];
        foreach (array_keys($payload) as $key) {
            if (! array_key_exists(strval($key), $properties)) {
                $out[strval($key)] = '未知设置键';
            }
        }
        if ($out !== []) {
            return $out;
        }

        $schema['required'] = array_keys($payload);
        $result = self::opis()->validate(self::payloadDoc($payload), self::schemaDoc($schema));
        $error = $result->error();
        if ($error instanceof ValidationError) {
            foreach (array_keys((new ErrorFormatter)->format($error)) as $path) {
                $field = trim(strval($path), '/');
                $field = $field === '' ? 'value' : explode('/', $field)[0];
                $out[$field] = '值类型或选项不符';
            }
        }

        return $out;
    }

    /**
     * 全部控件类型值 (x-control 合法取值), HasLabels 只有 labels/options, 这里从 cases 取.
     *
     * @return list<string>
     */
    private static function controlValues(): array
    {
        return array_map(static fn (SettingType $type): string => $type->value, SettingType::cases());
    }

    /**
     * 断言单个字段定义: JSON 类型与控件语义匹配, 选项表两份对齐.
     *
     * @param  array<string, mixed>  $field
     */
    private static function assertField(string $key, array $field): void
    {
        if (! isset($field['type']) || ! is_string($field['type']) || ! in_array($field['type'], ['string', 'boolean', 'integer', 'number', 'array'], true)) {
            throw new RuntimeException('设置 schema 字段 JSON 类型非法: '.$key);
        }
        $type = $field['type'];

        if (! isset($field['x-control']) || ! is_string($field['x-control']) || ! in_array($field['x-control'], self::controlValues(), true)) {
            throw new RuntimeException('设置 schema 缺少合法 x-control: '.$key);
        }
        if (! in_array($type, self::CONTROL_TYPES[$field['x-control']], true)) {
            throw new RuntimeException('设置 schema x-control 与 JSON 类型不匹配: '.$key);
        }

        if (! isset($field['title']) || ! is_string($field['title']) || $field['title'] === '') {
            throw new RuntimeException('设置 schema 缺少 title: '.$key);
        }
        if (isset($field['description']) && ! is_string($field['description'])) {
            throw new RuntimeException('设置 schema description 必须是字符串: '.$key);
        }
        if (! array_key_exists('default', $field) || ! self::defaultMatches($type, $field['default'])) {
            throw new RuntimeException('设置 schema default 缺失或类型不符: '.$key);
        }

        // select: enum (标准) 与 x-options (渲染) 同时存在且值列一致
        if ($field['x-control'] === 'select') {
            if (! isset($field['enum']) || ! is_array($field['enum']) || $field['enum'] === []) {
                throw new RuntimeException('设置 schema 选项字段缺少 enum: '.$key);
            }
            if (! isset($field['x-options']) || ! is_array($field['x-options']) || array_values(array_column($field['x-options'], 'value')) !== array_values($field['enum'])) {
                throw new RuntimeException('设置 schema x-options 与 enum 不一致: '.$key);
            }
        }

        // multi: 选项约束只在 items.enum 上 (property 级 enum 会要求整个数组等于某个标量, 恒假)
        if ($field['x-control'] === 'multi') {
            if (isset($field['enum'])) {
                throw new RuntimeException('设置 schema multi 不允许 property 级 enum: '.$key);
            }
            if (! isset($field['items']) || ! is_array($field['items']) || ! isset($field['items']['type']) || ! isset($field['items']['enum']) || ! is_array($field['items']['enum']) || $field['items']['enum'] === []) {
                throw new RuntimeException('设置 schema multi 缺少 items enum: '.$key);
            }
            if (! isset($field['x-options']) || ! is_array($field['x-options']) || array_values(array_column($field['x-options'], 'value')) !== array_values($field['items']['enum'])) {
                throw new RuntimeException('设置 schema x-options 与 items enum 不一致: '.$key);
            }
        }
    }

    /**
     * 两个列表作为集合是否相等 (忽略顺序与键).
     */
    private static function sameSet(array $left, array $right): bool
    {
        $left = array_values($left);
        $right = array_values($right);
        sort($left);
        sort($right);

        return $left === $right;
    }

    /**
     * default 是否与 JSON 类型一致 (存在性由调用方的 array_key_exists 先判).
     */
    private static function defaultMatches(string $type, mixed $default): bool
    {
        return match ($type) {
            'boolean' => is_bool($default),
            'string' => is_string($default),
            'integer' => is_int($default),
            'number' => is_int($default) || is_float($default),
            'array' => is_array($default),
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function schemaDoc(array $schema): object
    {
        if (isset($schema['properties']) && $schema['properties'] === []) {
            // JSON Schema 的 properties 必须是对象, 空数组会被当成 array 类型
            $schema['properties'] = new stdClass;
        }
        try {
            $doc = json_decode(json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('设置 schema 不是 JSON: '.$error->getMessage());
        }
        if (! is_object($doc)) {
            throw new RuntimeException('设置 schema 必须是对象');
        }

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function payloadDoc(array $payload): object
    {
        if ($payload === []) {
            // 空载荷必须是对象, 数组会被 JSON Schema 当成 list
            return new stdClass;
        }
        try {
            $doc = json_decode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('设置载荷不是 JSON: '.$error->getMessage());
        }
        if (! is_object($doc)) {
            throw new RuntimeException('设置载荷必须是对象');
        }

        return $doc;
    }

    private static function opis(): Validator
    {
        return new Validator;
    }
}
