<template>
  <header class="relative right-0 left-0 pt-5 bottom-auto w-full bg-transparent">
    <div class="w-full flex justify-between items-center md:h-20 h-16 p-5 rounded-xl shadow-cardUni bg-primary">
      <!-- logout -->
      <span
        class="absolute right-[45%] opacity-0"
        v-text="signFrontendTeam" />
      <div class="relative w-full flex justify-start">
        <button
          type="button"
          @click.stop.prevent="openDropDownProfile">
          <EllipsisVerticalIcon class="w-7 text-gray-300 pointer-events-none" />
        </button>
        <transition name="tooltip">
          <div
            v-if="dropDownProfile"
            class="absolute flex flex-col w-max top-10 w-full right-0 z-10 p-5 text-sm font-medium text-primary transition-opacity
            duration-300 bg-white rounded-md tooltip gap-y-2">
            <div class="flex flex-col items-center gap-3.5">
              <UserCircleIcon class="w-8" />
              <p
                class="text-primaryText text-base"
                v-text="authUser?.name" />
              <Link
                :href="$route('web.user.logout')"
                method="post"
                type="button"
                class="flex items-center w-full justify-center cursor-pointer text-red-600 hover:text-red-800">
                خروج
              </Link>
            </div>
          </div>
        </transition>
      </div>

      <div class="flex justify-end gap-x-5">
        <!-- short keys -->
        <div class="relative flex items-center">
          <button
            type="button"
            @mouseover="textTooltip=true"
            @mouseout="textTooltip=false">
            <InformationCircleIcon class="w-7 text-gray-300 pointer-events-none" />
          </button>
          <!-- tooltip -->
          <transition name="tooltip">
            <div
              v-if="textTooltip"
              class="absolute flex flex-col w-max top-10 w-full left-0 z-10 p-5 text-sm font-medium text-primary transition-opacity
            duration-300 bg-white rounded-md tooltip gap-y-2">
              <p
                class="text-primaryText text-base font-bold mb-3"
                v-text="` کلید میانبر`" />
              <div
                v-for="(shortKey ,indexKey ) in shortKeys"
                :key="indexKey"
                class="grid grid-cols-2 gap-x-3">
                <span
                  class="text-right"
                  v-text="shortKey.text" />
                <span
                  class="text-left"
                  v-text="shortKey.keys" />
              </div>
            </div>
          </transition>
        </div>

        <!-- forward -->
        <div class="relative flex items-center">
          <button
            type="button"
            @mouseover="textTooltipForward=true"
            @mouseout="textTooltipForward=false"
            @click.stop.prevent="navigateInHistoryPage('forward')">
            <ArrowSmallRightIcon class="w-7 text-gray-300 pointer-events-none" />
          </button>
          <!-- tooltip -->
          <transition name="tooltip">
            <div
              v-if="textTooltipForward"
              class="absolute flex flex-col w-max top-10 w-full left-0 z-10 p-5 text-sm font-medium text-primary transition-opacity
            duration-300 bg-white rounded-md tooltip gap-y-2">
              <p
                class="text-primaryText text-base font-bold mb-3"
                v-text="`بعدی`" />
            </div>
          </transition>
        </div>

        <!-- back -->
        <div class="relative flex items-center">
          <button
            type="button"
            @mouseover="textTooltipBack=true"
            @mouseout="textTooltipBack=false"
            @click.stop.prevent="navigateInHistoryPage('back')">
            <ArrowSmallLeftIcon class="w-7 text-gray-300 pointer-events-none" />
          </button>
          <!-- tooltip -->
          <transition name="tooltip">
            <div
              v-if="textTooltipBack"
              class="absolute flex flex-col w-max top-10 w-full left-0 z-10 p-5 text-sm font-medium text-primary transition-opacity
            duration-300 bg-white rounded-md tooltip gap-y-2">
              <p
                class="text-primaryText text-base font-bold mb-3"
                v-text="`قبلی`" />
            </div>
          </transition>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import {
  InformationCircleIcon,
  EllipsisVerticalIcon,
  ArrowSmallLeftIcon,
  ArrowSmallRightIcon
} from '@heroicons/vue/24/outline'
import { UserCircleIcon } from '@heroicons/vue/24/solid'
import { Link } from '@inertiajs/inertia-vue3'
import { onBeforeUnmount, onMounted, ref } from 'vue'

// eslint-disable-next-line no-undef
defineOptions({
  name: 'HeaderDashboard'
})

const shortKeys = [
  {
    text: 'انتخاب',
    keys: 'Ctrl'
  },
  {
    text: 'انتخاب همه',
    keys: 'Shift + a'
  },
  {
    text: 'کپی',
    keys: 'Shift + c'
  },
  {
    text: 'انتقال',
    keys: 'Shift + m'
  },
  {
    text: 'آرشیو',
    keys: 'Shift + r'
  },
  {
    text: 'حذف',
    keys: 'Delete'
  }
]

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, default: null }
})
const signFrontendTeam = 'ashkan sabbaghi - yosra feili (1402/09/09)'

const textTooltip = ref(false)
const textTooltipBack = ref(false)
const textTooltipForward = ref(false)
const dropDownProfile = ref(false)

// functions
function navigateInHistoryPage (type) {
  window.history[type]()
}

function openDropDownProfile () {
  dropDownProfile.value = !dropDownProfile.value
}

function closeDropdown () {
  dropDownProfile.value = false
}

onMounted(() => {
  // Attach the event listener
  window.addEventListener('click', closeDropdown)
})

// Cleanup when the component is unmounted
onBeforeUnmount(() => {
  window.removeEventListener('click', closeDropdown)
})
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
