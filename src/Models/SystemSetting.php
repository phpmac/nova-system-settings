<?php

namespace Vcsb\SystemSettings\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Vcsb\SystemSettings\Database\Factories\SystemSettingFactory;
use Vcsb\SystemSettings\Enums\SettingGroup;

#[Fillable([
    'group',
    'label',
    'schema',
    'value',
])]
class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * 系统设置: 一行一个分组. schema jsonb 是整组字段定义 (JSON Schema 文档), value jsonb 是后台页改的当前值.
     */
    protected function casts(): array
    {
        return [
            'group' => SettingGroup::class,
            'schema' => 'array',
            'value' => 'array',
        ];
    }

    /**
     * 本组字段定义 (schema.properties), 结构由 Seeder 常量为源.
     *
     * @return array<string, mixed>
     */
    public function properties(): array
    {
        $schema = $this->schema;
        if (! is_array($schema) || ! array_key_exists('properties', $schema) || ! is_array($schema['properties'])) {
            throw new RuntimeException('系统设置分组 schema 缺少 properties: '.$this->group->value);
        }

        return $schema['properties'];
    }
}
