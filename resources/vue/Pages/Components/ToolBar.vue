<template>
  <div class="">
    <nav class="bg-white w-full rounded-2xl shadow-cardUni">
      <div class="flex justify-between p-4">
        <div class="flex justify-between w-1/2 gap-x-5">
          <!-- searches and filter -->
          <div
            v-if="isLetter"
            class="relative w-full flex justify-between items-center h-15 focus-within:text-secondPrimary">
            <input
              class="placeholder-primaryText/60 bg-transparent border-primaryText text-primaryText rounded-full
              w-full pl-5 pr-10 focus:outline-none focus:bg-transparent focus:border-secondPrimary
              focus:ring-0 disabled:opacity-50"
              type="search"
              name="search"
              autocomplete="off"
              placeholder="جستجو در این صفحه"
              @input="setTextSearch">
            <MagnifyingGlassIcon class="absolute top-2.5 right-3 w-6" />
          </div>

          <!-- icons filters -->
          <div class="flex justify-center items-center gap-x-2">
            <div
              v-for="(item, i) in secondToolbarIcon"
              :key="i"
              class="relative">
              <button
                v-if="checkHasIcon(item,$page.url)"
                type="button"
                class="w-10 p-2 text-primaryText cursor-pointer hover:hover:bg-primary/5 hover:rounded-xl"
                @mouseover="actionTooltip(item.name,true)"
                @mouseout="actionTooltip(item.name,false)"
                @click.stop.prevent="choiceFunction(item)">
                <component
                  :is="item.icon"
                  class="w-7 pointer-events-none"
                  :class="{'animate-spin': item.name === 'refresh' && isRefresh}" />
              </button>
              <!-- tooltip process-->
              <transition name="tooltip">
                <div
                  v-if="textTooltip[item.name]"
                  class="absolute w-max top-10 w-full left-0 z-10 p-5 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip">
                  <span v-text="dicTooltips(item.name)" />
                </div>
              </transition>
            </div>
          </div>
        </div>
        <!-- toolbar -->
        <div class="flex justify-center &:w-8 items-center">
          <div
            v-for="(item, i) in toolbarIcon"
            :key="i"
            class="relative">
            <button
              v-if="checkHasIcon(item,$page.url)"
              type="button"
              class="w-10 p-2 text-primaryText cursor-pointer hover:hover:bg-primary/5 hover:rounded-xl
              disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="!isActive && item?.name !== 'checkBox'"
              @mouseover="actionTooltip(item.name,true)"
              @mouseout="actionTooltip(item.name,false)"
              @click.stop.prevent="choiceFunction(item)">
              <component
                :is="conditionIcons(item)"
                class="w-7 pointer-events-none"
                :class="{'hidden' : item.name==='checkbox' && item?.active}" />
            </button>
            <!-- tooltip process-->
            <transition name="tooltip">
              <div
                v-if="textTooltip[item.name]"
                class="absolute w-max top-10 w-full left-0 z-10 p-5 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip">
                <span v-text="dicTooltips(item.name)" />
              </div>
            </transition>
          </div>
        </div>
      </div>
    </nav>
  </div>
</template>

<script setup>
import {
  DocumentMagnifyingGlassIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  AdjustmentsHorizontalIcon,
  TrashIcon,
  ArchiveBoxIcon,
  ArrowDownTrayIcon,
  DocumentDuplicateIcon,
  StopIcon as OutlineStopIcon
} from '@heroicons/vue/24/outline'

import {
  StopIcon as SolidStopIcon
} from '@heroicons/vue/24/solid'

import MoveIcon from './icon/MoveIcon.vue'
import { reactive, ref } from 'vue'

defineOptions({
  name: 'ToolBar'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  isRefresh: { type: Boolean, required: true },
  isCheckBox: { type: Boolean, required: true },
  isActive: { type: Boolean, required: true },
  isLetter: { type: Boolean, default: true }
})
const emits = defineEmits(['item-click', 'update:modelValue'])

const toolbarIcon = ref([
  {
    name: 'checkBox',
    icon: SolidStopIcon
  },
  {
    name: 'copy',
    icon: DocumentDuplicateIcon
  },
  {
    name: 'move',
    icon: MoveIcon
  },
  {
    name: 'download',
    icon: ArrowDownTrayIcon
  },
  {
    name: 'archive',
    icon: ArchiveBoxIcon
  },
  {
    name: 'delete',
    icon: TrashIcon
  }
])
const secondToolbarIcon = ref([
  {
    name: 'filter',
    icon: DocumentMagnifyingGlassIcon
  },
  {
    name: 'sort',
    icon: AdjustmentsHorizontalIcon
  },
  {
    name: 'refresh',
    icon: ArrowPathIcon
  }
])
const textTooltip = reactive({
  delete: false,
  archive: false,
  download: false,
  move: false,
  copy: false,
  reference: false,
  filter: false,
  sort: false,
  refresh: false
})
const currActiveItem = ref('')

function checkHasIcon (item, url) {
  if ((item.name === 'copy' || item.name === 'download' || item.name === 'move' || item.name === 'sort' || item.name === 'filter' || item.name === 'refresh') && url.includes('dashboard')) return item.name
  if ((item.name === 'checkBox' || item.name === 'archive' || item.name === 'delete') && (url.includes('dashboard') || url.includes('cartable'))) return item.name
}

function conditionIcons (item) {
  if (item.name === 'checkBox') return props.isCheckBox ? SolidStopIcon : OutlineStopIcon
  return item.icon
}

function setTextSearch () {
  emits('update:modelValue', event.target.value)
}

const tooltips = {
  checkBox: 'انتخاب',
  copy: 'کپی',
  move: 'انتقال',
  download: 'دانلود',
  archive: 'آرشیو',
  delete: 'حذف',
  reference: 'ارجاع',
  filter: 'جستجوی پیشرفته',
  sort: 'مرتب سازی',
  refresh: 'به‌روزرسانی'
}

function dicTooltips (name) {
  return tooltips[name] || name
}
function actionTooltip (name, isShow) {
  textTooltip[name] = isShow
}

function choiceFunction (item) {
  currActiveItem.value = item.name
  emits('item-click', currActiveItem.value)
}

</script>

<style scoped lang="scss">
.tooltip-enter-active {
  transition: all 0.5s ease-out;
}

.tooltip-leave-active {
  transition: all 0.1s cubic-bezier(1, 0.5, 0.8, 1);
}

.tooltip-enter-from,
.tooltip-leave-to {
  transform: translateY(10px);
  opacity: 0;
}
</style>
