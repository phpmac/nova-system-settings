<?php

namespace Vcsb\SystemSettings\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Vcsb\SystemSettings\Models\SystemSetting;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SystemSetting>
     */
    protected $model = SystemSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }
}
