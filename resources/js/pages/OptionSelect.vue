<template>
  <!-- 短选项单选下拉: TeamSelect 形态去搜索框 (schema 选项固定且短), 交互对齐 nova-tool-ui: 点触发器开↔关 + 键盘↓↑EnterEsc + 点外关 -->
  <div
    ref="rootEl"
    class="relative inline-block w-full"
  >
    <button
      ref="triggerEl"
      type="button"
      class="setting-select__trigger relative flex items-center form-control form-input form-control-bordered w-full text-left cursor-pointer"
      aria-haspopup="listbox"
      :aria-expanded="open ? 'true' : 'false'"
      @click="toggle"
      @keydown="onKeydown"
    >
      <span
        class="flex-1 truncate text-sm"
        :class="current === null ? 'text-gray-500 dark:text-gray-400' : ''"
      >{{ currentLabel }}</span>
      <!-- 右缘 chevron: absolute 无 top, 借 flex items-center 静态位置垂直居中 (Nova Select 同款实心三角) -->
      <svg
        class="absolute right-[4px] w-2 h-2 fill-current text-gray-700 pointer-events-none"
        viewBox="0 0 10 6"
        aria-hidden="true"
      >
        <path d="M0 0h10L5 6z" />
      </svg>
    </button>

    <div
      v-if="open"
      class="setting-select__panel absolute left-0 right-0 z-30 my-1 rounded-lg bg-white dark:bg-gray-900 shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden"
    >
      <div
        role="listbox"
        class="setting-select__list"
      >
        <button
          v-for="(item, index) in options"
          :id="`setting-opt-${uid}-${index}`"
          :key="item.value"
          type="button"
          role="option"
          :aria-selected="current === item.value ? 'true' : 'false'"
          class="w-full text-left px-3 py-1.5 text-sm cursor-pointer flex items-center justify-between gap-2"
          :class="rowClass(index)"
          @click="pick(item.value)"
        >
          {{ item.label }}
          <svg
            v-if="current === item.value"
            class="w-3.5 h-3.5 shrink-0"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          ><path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M4.5 12.75l6 6 9-13.5"
          /></svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  /** 选项表 [{value, label}] (schema 下发) */
  options: { type: Array, required: true },
  /** 当前值 (schema 的 value) */
  value: { type: [String, Number, Boolean], default: null },
})

const emit = defineEmits(['update'])

/** 实例级 id 前缀: 同页多个下拉, 键盘滚屏按 id 找行, 不能撞 */
const uid = Math.random().toString(36).slice(2, 8)

const open = ref(false)
const rootEl = ref(null)
const triggerEl = ref(null)
const activeIndex = ref(-1)

/** 当前值不在选项表里时回退 null 显示占位 (schema 与选项漂移时不炸, 显示置灰空) */
const current = computed(() => {
  const hit = props.options.find((item) => item.value === props.value)
  return hit === undefined ? null : props.value
})

const currentLabel = computed(() => {
  const hit = props.options.find((item) => item.value === current.value)
  return hit === undefined ? '未选择' : hit.label
})

/** 选项行形态 (AccountSelect 同款): 非首行补分隔线, 选中/高亮蓝底 */
function rowClass(index) {
  const border = index > 0 ? 'border-t border-gray-100 dark:border-gray-700' : ''
  const on = current.value === props.options[index]?.value || index === activeIndex.value
  return on
    ? `${border} bg-blue-100 text-blue-700 dark:bg-primary-500 dark:text-gray-900 font-medium`.trim()
    : `${border} hover:bg-gray-100 dark:hover:bg-gray-800`.trim()
}

/** 触发器点击开↔关 (nova-tool-ui 统一交互); 打开重置高亮 */
function toggle() {
  if (open.value) {
    open.value = false
    return
  }
  activeIndex.value = -1
  open.value = true
}

/** 键盘: ↓↑ 移高亮, Enter 选中高亮项并收起, Esc 关闭焦点回触发器; 输入法组合键跳过 */
function onKeydown(event) {
  if (event.isComposing || event.keyCode === 229) {
    return
  }
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, props.options.length - 1)
    scrollActive()
    return
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, 0)
    scrollActive()
    return
  }
  if (event.key === 'Enter') {
    event.preventDefault()
    const item = props.options[activeIndex.value]
    if (item !== undefined) {
      pick(item.value)
    }
    return
  }
  if (event.key === 'Escape') {
    open.value = false
    activeIndex.value = -1
    triggerEl.value?.focus()
  }
}

/** 高亮项滚进可视区 (0 起算, id 带实例前缀防撞) */
function scrollActive() {
  if (activeIndex.value < 0) {
    return
  }
  document.getElementById(`setting-opt-${uid}-${activeIndex.value}`)?.scrollIntoView({ block: 'nearest' })
}

/** 选中: 回填并收起 */
function pick(value) {
  open.value = false
  activeIndex.value = -1
  emit('update', value)
}

/** 点外关闭 */
function onOutside(event) {
  if (open.value && rootEl.value && !rootEl.value.contains(event.target)) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', onOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onOutside)
})
</script>
