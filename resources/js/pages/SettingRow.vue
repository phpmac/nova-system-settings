<template>
  <!-- 设置行: 左标签列 (显示名 + 说明), 右控件列按 schema type 分发; hover/focus 高亮当前行 (focus 底色走 tool.css 变量补色, 类不存在); 手机两列各占整行 (tool.css 640 段) -->
  <div
    class="setting-row flex flex-wrap items-center gap-2 py-3 hover:bg-gray-50 dark:hover:bg-gray-800"
    :class="bordered ? 'border-b border-gray-200 dark:border-gray-700' : ''"
  >
    <div class="setting-label-slot shrink-0">
      <div class="text-sm text-gray-700 dark:text-gray-200">{{ setting.label }}</div>
      <div
        v-if="setting.description"
        class="text-xs text-gray-500 dark:text-gray-400"
      >{{ setting.description }}</div>
    </div>

    <div
      class="flex-1"
      :class="setting.type === 'textarea' ? 'setting-control--wide' : 'setting-field-slot'"
    >
      <!-- 开关: 原生 checkbox 走 app.css :root accent-color 主题色, 带状态文字 -->
      <label
        v-if="setting.type === 'toggle'"
        class="inline-flex items-center gap-2 cursor-pointer"
      >
        <input
          type="checkbox"
          class="h-4 w-4 cursor-pointer"
          :checked="value === true"
          @change="$emit('update', $event.target.checked)"
        >
        <span class="text-sm text-gray-700 dark:text-gray-200">{{ value === true ? '开启' : '关闭' }}</span>
      </label>

      <OptionSelect
        v-else-if="setting.type === 'select'"
        :options="setting.options"
        :value="value"
        @update="$emit('update', $event)"
      />

      <OptionMulti
        v-else-if="setting.type === 'multi'"
        :options="setting.options"
        :selected="Array.isArray(value) ? value : []"
        :all-label="setting.allLabel"
        @update="$emit('update', $event)"
      />

      <textarea
        v-else-if="setting.type === 'textarea'"
        class="setting-textarea w-full px-3 py-2 text-sm form-control form-input form-control-bordered"
        :value="value"
        @input="$emit('update', $event.target.value)"
      />

      <input
        v-else-if="setting.type === 'number'"
        type="number"
        class="h-8 px-3 text-sm form-control form-input form-control-bordered w-full"
        :value="value"
        @input="$emit('update', Number($event.target.value))"
      >

      <!-- 密文: type=password 只防旁窥, 值仍明文存取; autocomplete 防浏览器把它存进密码管理器 -->
      <input
        v-else-if="setting.type === 'secret'"
        type="password"
        autocomplete="new-password"
        class="h-8 px-3 text-sm form-control form-input form-control-bordered w-full"
        :value="value"
        @input="$emit('update', $event.target.value)"
      >

      <input
        v-else
        type="text"
        class="h-8 px-3 text-sm form-control form-input form-control-bordered w-full"
        :value="value"
        @input="$emit('update', $event.target.value)"
      >
    </div>
  </div>
</template>

<script setup>
import OptionMulti from './OptionMulti.vue'
import OptionSelect from './OptionSelect.vue'

defineProps({
  /** 设置项 {key, label, description, type, options, value, default} (接口下发) */
  setting: { type: Object, required: true },
  /** 当前编辑值 (Tool.vue 的 values[key]) */
  value: { type: [String, Number, Boolean, Array], default: null },
  /** 是否画行底分隔线 (最后一行不画) */
  bordered: { type: Boolean, default: true },
})

defineEmits(['update'])
</script>
