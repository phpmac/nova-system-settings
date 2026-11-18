<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 系统设置: 一行一个分组, schema jsonb 存整组字段定义 (strategies.schema 同款 properties 结构),
     * value jsonb 存当前值, 结构由 Seeder 常量固定, 后台自定义页只改 value.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // 分组取值唯一来源是 Vcsb\SystemSettings\Enums\SettingGroup, 列只存枚举 value
            $table->string('group', 24)->unique()->comment('分组标识');
            $table->string('label', 64)->comment('分组显示名');
            // properties: 设置键 => {label, description, type, options, default, sort}
            $table->jsonb('schema')->comment('分组字段定义');
            $table->jsonb('value')->comment('当前值(设置键=>值)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
