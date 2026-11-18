<template>
  <!-- 选项多选下拉: SymbolSelect 全套交互移植 (面板顶搜索本地过滤 + 键盘↓↑EnterEsc + 点选不关面板累选 + 首项清空 + 触发器×清空), 选项来自 schema 非服务端 -->
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
    >
      <span
        class="flex-1 truncate text-sm text-left"
        :class="selected.length === 0 ? 'text-gray-500 dark:text-gray-400' : ''"
      >{{ selected.length === 0 ? allLabel : `已选 ${selected.length} 项` }}</span>
      <!-- 已选清空钮: 点 × 回全部, stop 阻断不冒泡到触发器 (不弹面板); mr-4 给右缘 chevron 让位 -->
      <svg
        v-if="selected.length > 0"
        class="w-3 h-3 shrink-0 mr-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        title="清空已选"
        @click.stop="clearAll"
      ><path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M6 18L18 6M6 6l12 12"
      /></svg>
      <!-- 右缘 chevron: Nova Select 同款实心三角 -->
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
      <input
        ref="searchEl"
        v-model="query"
        type="search"
        role="combobox"
        class="h-8 px-3 text-sm bg-white dark:bg-gray-700 rounded-t border-b border-gray-200 dark:border-gray-800 w-full outline-none"
        placeholder="搜索选项"
        @keydown="onKeydown"
      >
      <div
        role="listbox"
        aria-multiselectable="true"
        class="setting-select__list"
      >
        <button
          :id="`setting-mopt-${uid}-all`"
          type="button"
          role="option"
          :aria-selected="selected.length === 0 ? 'true' : 'false'"
          class="w-full text-left px-3 py-1.5 text-sm cursor-pointer flex items-center justify-between gap-2"
          :class="rowClass(0)"
          @click="pickAll"
        >
          {{ allLabel }}
          <svg
            v-if="selected.length === 0"
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
        <button
          v-for="(item, index) in filtered"
          :id="`setting-mopt-${uid}-${index + 1}`"
          :key="item.value"
          type="button"
          role="option"
          :aria-selected="selected.includes(item.value) ? 'true' : 'false'"
          class="w-full text-left px-3 py-1.5 text-sm cursor-pointer flex items-center justify-between gap-2"
          :class="rowClass(index + 1, item)"
          @click="toggleOne(item.value)"
        >
          {{ item.label }}
          <svg
            v-if="selected.includes(item.value)"
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
        <div
          v-if="filtered.length === 0"
          class="px-3 py-1.5 text-xs text-gray-500 dark:text-gray-400"
        >没有匹配的选项</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  /** 选项表 [{value, label}] (schema 下发) */
  options: { type: Array, required: true },
  /** 已选 value 数组 */
  selected: { type: Array, required: true },
  /** 空选时的占位文案, 也是首项 (清空语义) 的文案 */
  allLabel: { type: String, default: '不限制' },
})

const emit = defineEmits(['update'])

/** 实例级 id 前缀: 同页多个多选, 键盘滚屏按 id 找行 */
const uid = Math.random().toString(36).slice(2, 8)

const open = ref(false)
const query = ref('')
const rootEl = ref(null)
const triggerEl = ref(null)
const searchEl = ref(null)
const activeIndex = ref(-1)

/** 搜索过滤: 大小写不敏感包含匹配 (对 label), 空词全清单 */
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (q === '') {
    return props.options
  }
  return props.options.filter((item) => item.label.toLowerCase().includes(q))
})

/** 选项行形态 (AccountSelect 同款): 非首行分隔线, 选中/高亮蓝底 */
function rowClass(index, item) {
  const border = index > 0 ? 'border-t border-gray-100 dark:border-gray-700' : ''
  const on = (index === 0 && props.selected.length === 0) || (index > 0 && props.selected.includes(item?.value)) || index === activeIndex.value
  return on
    ? `${border} bg-blue-100 text-blue-700 dark:bg-primary-500 dark:text-gray-900 font-medium`.trim()
    : `${border} hover:bg-gray-100 dark:hover:bg-gray-800`.trim()
}

/** 键盘: ↓↑ 高亮, Enter 点选不关面板, Esc 清词关闭回焦; 输入法组合键跳过 */
function onKeydown(event) {
  if (event.isComposing || event.keyCode === 229) {
    return
  }
  const total = filtered.value.length + 1
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, total - 1)
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
    const index = activeIndex.value
    if (index <= 0) {
      pickAll()
      return
    }
    const item = filtered.value[index - 1]
    if (item !== undefined) {
      toggleOne(item.value)
    }
    return
  }
  if (event.key === 'Escape') {
    query.value = ''
    activeIndex.value = -1
    open.value = false
    triggerEl.value?.focus()
  }
}

/** 高亮项滚进可视区 (0 = 清空行) */
function scrollActive() {
  if (activeIndex.value < 0) {
    return
  }
  const id = activeIndex.value === 0 ? `setting-mopt-${uid}-all` : `setting-mopt-${uid}-${activeIndex.value}`
  document.getElementById(id)?.scrollIntoView({ block: 'nearest' })
}

/** 触发器点击开↔关; 打开清词 + 焦点落搜索框 (SymbolSelect 同款) */
function toggle() {
  if (open.value) {
    open.value = false
    return
  }
  query.value = ''
  activeIndex.value = -1
  open.value = true
  nextTick(() => searchEl.value?.focus())
}

/** 首项: 清空 = 全部 (关闭面板) */
function pickAll() {
  query.value = ''
  activeIndex.value = -1
  open.value = false
  emit('update', [])
}

/** 触发器 × 清空: 同 pickAll 语义但不弹面板 */
function clearAll() {
  emit('update', [])
}

/** 多选 toggle, 面板保持打开继续选 */
function toggleOne(value) {
  if (props.selected.includes(value)) {
    emit('update', props.selected.filter((item) => item !== value))
    return
  }
  emit('update', [...props.selected, value])
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
