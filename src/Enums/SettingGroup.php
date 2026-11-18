<?php

namespace Vcsb\SystemSettings\Enums;

/** 系统设置分组: 后台页页签按枚举声明序, 加分组在这里加 case + Seeder 加行 */
enum SettingGroup: string
{
    case General = 'general';
    case User = 'user';
    case Security = 'security';
    case Notification = 'notification';

    public function label(): string
    {
        return match ($this) {
            self::General => '常规设置',
            self::User => '用户设置',
            self::Security => '安全设置',
            self::Notification => '通知设置',
        };
    }
}
