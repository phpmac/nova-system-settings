<?php

namespace Vcsb\SystemSettings\Enums;

/** 系统设置的控件类型: 前端按 x-control 渲染控件, 后端按类型校验保存值 */
enum SettingType: string
{
    case Toggle = 'toggle';
    case Select = 'select';
    case MultiSelect = 'multi';
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    /** 密文输入: 显示星号防旁窥, 值仍明文存取 (API Key 类) */
    case Secret = 'secret';

    public function label(): string
    {
        return match ($this) {
            self::Toggle => '开关',
            self::Select => '单选',
            self::MultiSelect => '多选',
            self::Text => '文本',
            self::Textarea => '多行文本',
            self::Number => '数字',
            self::Secret => '密文',
        };
    }
}
