<template>
  <div class="flex flex-col">
    <Head title="系统设置" />

    <!-- 单卡布局: 分组段选 + 行尾 恢复默认/保存 (PnlCalendar filter-bar 同布局); 卡片至少撑到视口高 (setting-card), 行区吃剩余 -->
    <div class="setting-card flex flex-col rounded-lg shadow bg-white dark:bg-gray-800">
      <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
        <!-- 顶部行只留分组段选; 动作按钮在卡底操作条 (2026-11-18 用户拍板) -->
        <div class="flex flex-wrap items-center gap-2">
          <div class="flex gap-1">
            <button
              v-for="group in groups"
              :key="group.key"
              type="button"
              class="h-8 px-3 text-sm rounded-sm cursor-pointer transition"
              :class="active === group.key ? segOn : segOff"
              @click="switchGroup(group.key)"
            >{{ group.label }}</button>
          </div>
        </div>
      </div>

      <!-- 分组清单加载失败: 卡内横条 + 重试 (PnlCalendar 同款) -->
      <div
        v-if="groupsError"
        class="mx-4 my-3 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 flex items-center gap-2"
      >
        <span
          class="flex-1 truncate text-sm text-red-700"
          :title="groupsError"
        >{{ groupsError }}</span>
        <button
          type="button"
          class="h-8 px-3 text-sm rounded-lg border border-gray-300 text-gray-600 cursor-pointer transition hover:bg-gray-100 dark:hover:bg-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
          @click="loadGroups"
        >重试</button>
      </div>

      <!-- 组数据加载失败: 只提示当前组, 重试重拉当前组 -->
      <div
        v-else-if="groupError"
        class="mx-4 my-3 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 flex items-center gap-2"
      >
        <span
          class="flex-1 truncate text-sm text-red-700"
          :title="groupError.message"
        >{{ groupError.message }}</span>
        <button
          type="button"
          class="h-8 px-3 text-sm rounded-lg border border-gray-300 text-gray-600 cursor-pointer transition hover:bg-gray-100 dark:hover:bg-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
          @click="loadGroup(groupError.key)"
        >重试</button>
      </div>

      <!-- 设置行区: 已加载组的行 (组数据按需拉, 内存保留切回不重复请求) -->
      <div
        v-else
        class="flex-1 px-4 py-3"
      >
        <SettingRow
          v-for="(setting, index) in currentSettings"
          :key="setting.key"
          :setting="setting"
          :value="values[setting.key]"
          :bordered="index < currentSettings.length - 1"
          @update="onUpdate(setting.key, $event)"
        />
        <div
          v-if="currentSettings.length === 0"
          class="text-center text-sm text-gray-500 dark:text-gray-400 py-8"
        >{{ loadingKey !== '' ? '加载中...' : (hasActive ? '该分组暂无设置' : '加载中...') }}</div>
      </div>

      <!-- 底部操作条: 提示 + 恢复默认/保存 (卡首行对称形态 border-t); 三分支外常驻, 错误态按钮也可用 -->
      <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700">
        <div class="flex flex-wrap items-center gap-2">
          <span
            class="flex-1 truncate text-sm"
            :class="hintBad ? 'text-red-700' : 'text-gray-500 dark:text-gray-400'"
          >{{ hint }}</span>

          <div class="flex gap-1 ml-auto shrink-0">
            <!-- 恢复默认/保存: Nova 原生按钮形态 (h-9 px-4 rounded shadow, 2026-11-18 用户拍板), 次要钮保持描边灰 -->
            <button
              type="button"
              class="h-9 px-4 text-sm font-bold rounded shadow border border-gray-300 text-gray-600 cursor-pointer transition hover:bg-gray-100 dark:hover:bg-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
              :disabled="busy || !hasActive"
              @click="resetDefaults"
            >恢复默认</button>
            <button
              type="button"
              class="h-9 px-4 text-sm font-bold rounded shadow focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 transition"
              :class="busy ? 'opacity-50' : 'cursor-pointer'"
              :disabled="busy || !hasActive"
              @click="save"
            >{{ busy ? '保存中...' : '保存' }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import SettingRow from './SettingRow.vue'

/** 段选与按钮形态: 盈利日历 SEG_ON/SEG_OFF 同款常量 (模板引用小驼峰, 必须对上) */
const segOn = 'bg-primary-500 text-white font-semibold'
const segOff = 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'

/** 分组页签本地记忆: 刷新后保持上次的分组 (PnlCalendar 筛选记忆同款约定), 损坏直接抛 */
const GROUP_KEY = 'vcsb-nova:system-settings:group'

const groups = ref([])
const active = ref('')
const loaded = ref({})
/** 编辑值: {设置键: 值} 全局平铺 (键全局唯一), 只含已加载组的键, 保存整包发 */
const values = ref({})
/** 每组加载时的值快照: {分组键: {设置键: 值}}, 恢复默认按 HTML reset 语义还原当前组 */
const initial = ref({})
/** 正在拉取的分组键 (空串 = 没有在途请求) */
const loadingKey = ref('')
const busy = ref(false)
const groupsError = ref('')
const groupError = ref(null)
const hint = ref('')
const hintBad = ref(false)

/** 请求序号守卫: 快速切组时后发先至, 旧响应不覆盖新组数据 */
let loadSeq = 0

/** 当前分组的设置行 */
const currentSettings = computed(() => {
  const list = loaded.value[active.value]
  return list === undefined ? [] : list
})

const hasActive = computed(() => active.value !== '')

function readRememberedGroup() {
  const raw = localStorage.getItem(GROUP_KEY)
  if (raw === null) {
    return null
  }
  const parsed = JSON.parse(raw)
  if (typeof parsed !== 'string') {
    throw new Error('系统设置分组记忆损坏: '+raw)
  }
  return parsed
}

/** 拉 分组页签 清单 (轻请求, 只含 key/label), 选中记忆组或第一组并触发组数据拉取 */
async function loadGroups() {
  groupsError.value = ''
  try {
    const res = await fetch('/nova-vendor/system-settings', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    if (!res.ok) {
      throw new Error(`分组清单加载失败: HTTP ${res.status}`)
    }
    const data = await res.json()
    groups.value = data.groups

    const remembered = readRememberedGroup()
    const hit = data.groups.some((group) => group.key === remembered)
    switchGroup(hit ? remembered : (data.groups[0] === undefined ? '' : data.groups[0].key))
  } catch (err) {
    groupsError.value = err.message
    console.error(err)
  }
}

/** 切组: 立刻换页签; 组数据没拉过才发请求 (内存保留, 切回不重复请求) */
function switchGroup(key) {
  active.value = key
  groupError.value = null
  if (key !== '') {
    localStorage.setItem(GROUP_KEY, JSON.stringify(key))
  }
  if (key !== '' && loaded.value[key] === undefined) {
    loadGroup(key)
  }
}

/** 拉单组 schema+值 (按需加载); 竞态由序号守卫挡 */
async function loadGroup(key) {
  const mine = loadSeq + 1
  loadSeq += 1
  loadingKey.value = key
  groupError.value = null
  try {
    const res = await fetch(`/nova-vendor/system-settings/${encodeURIComponent(key)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    if (!res.ok) {
      throw new Error(typeof data.message === 'string' && data.message !== '' ? data.message : `分组加载失败: HTTP ${res.status}`)
    }
    if (mine !== loadSeq) {
      return
    }

    loaded.value = { ...loaded.value, [key]: data.settings }
    const next = { ...values.value }
    const snapshot = {}
    for (const setting of data.settings) {
      next[setting.key] = setting.value
      snapshot[setting.key] = setting.value
    }
    values.value = next
    initial.value = { ...initial.value, [key]: snapshot }
    loadingKey.value = ''
  } catch (err) {
    if (mine !== loadSeq) {
      return
    }
    loadingKey.value = ''
    groupError.value = { key, message: err.message }
    console.error(err)
  }
}

/** 编辑一项: 值只在本地改, 保存时才落库 */
function onUpdate(key, value) {
  values.value = { ...values.value, [key]: value }
  hint.value = ''
  hintBad.value = false
}

/** 恢复默认 = HTML 表单 reset 语义: 还原当前组到该组加载时的值, 其他组不动 */
function resetDefaults() {
  const snapshot = initial.value[active.value]
  if (snapshot === undefined) {
    return
  }
  const next = { ...values.value }
  for (const key of Object.keys(snapshot)) {
    next[key] = snapshot[key]
  }
  values.value = next
  hint.value = '已还原本组到加载时的值, 点保存生效'
  hintBad.value = false
}

/** CSRF: Nova 同域 fetch 需要带 XSRF cookie 解出的 token 头 */
function csrfToken() {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)
  return match === null ? '' : decodeURIComponent(match[1])
}

async function save() {
  busy.value = true
  hint.value = ''
  hintBad.value = false
  try {
    const res = await fetch('/nova-vendor/system-settings/save', {
      method: 'PUT',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrfToken(),
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ values: values.value }),
    })
    const data = await res.json()
    if (!res.ok) {
      throw new Error(typeof data.message === 'string' && data.message !== '' ? data.message : `保存失败: HTTP ${res.status}`)
    }
    hint.value = `已保存 ${data.saved} 项设置`
  } catch (err) {
    hint.value = err.message
    hintBad.value = true
    console.error(err)
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  loadGroups()
})
</script>
