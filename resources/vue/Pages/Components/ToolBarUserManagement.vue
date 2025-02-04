<template>
  <nav class="">
    <div class="flex">
      <div class="flex justify-between w-full gap-x-5">
        <!-- icons filters -->
        <div class="flex justify-center items-center gap-x-2 hidden">
          <div
            v-for="(item, i) in secondToolbarIcon"
            :key="i"
            class="relative">
            <button
              type="button"
              class="w-10 p-2 text-primaryText cursor-pointer hover:hover:bg-primary/5 hover:rounded-xl"
              @mouseover="actionTooltip(item.name,true)"
              @mouseout="actionTooltip(item.name,false)"
              @click.stop.prevent="choiceFunction(item)">
              <component
                :is="item.icon"
                class="w-7 pointer-events-none"
                :class=" item.name === 'refresh'" />
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

        <!-- searches and filter -->
        <div class="relative w-1/2 flex justify-between items-center h-15 focus-within:text-secondPrimary">
          <input
            ref="searchRef"
            class="placeholder-primaryText/60 bg-transparent  border-primaryText text-primaryText rounded-full
              w-full pl-5 pr-10 focus:outline-none focus:bg-transparent focus:border-secondPrimary focus:ring-0 disabled:opacity-50"
            type="search"
            name="search"
            autocomplete="off"
            placeholder="جستجو کنید"
            @input="setTextSearch">
          <MagnifyingGlassIcon class="absolute top-2.5 right-3 w-6" />
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
import {
  MagnifyingGlassIcon,
  ArrowPathIcon,
  AdjustmentsHorizontalIcon
} from '@heroicons/vue/24/outline'

import { reactive, ref } from 'vue'

defineOptions({
  name: 'ToolBarUserManagement'
})

const emits = defineEmits(['item-click', 'update:modelValue'])

const secondToolbarIcon = ref([
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
  delete: false
})
const currActiveItem = ref('')

function setTextSearch () {
  emits('update:modelValue', event.target.value)
}

const tooltips = {
  checkBox: 'انتخاب',
  delete: 'حذف',
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
