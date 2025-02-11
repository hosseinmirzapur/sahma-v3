<template>
  <aside class="relative pt-5 right-0 px-5 bg-transparent">
    <div class="flex items-center h-[96vh] flex-col items-start px-4 rounded-xl  bg-transparent">
      <!-- logo irpardaz-->
      <div class="">
        <Link :href="$route('web.user.dashboard.index')">
          <svg
            class="w-20 h-20"
            width="57"
            height="84"
            viewBox="0 0 252 361"
            fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <path
              d="M116.787 112.343C127.754 100.943 116.279 74.0714 71.4908 2.14534C68.1887 1.26972 67.4042 2.1825 67.6764 5.94525C111.595 96.8114 94.3751 106.073 6.16836 65.3188C2.32253 64.585 1.38603 65.3092 2.35391 69.1187C57.9695 100.869 98.6687 126.592 116.787 112.343Z"
              fill="#353DBC" />
            <path
              d="M133.165 114.35C144.609 125.274 171.583 113.843 243.784 69.2259C244.663 65.9363 243.747 65.1548 239.97 65.426C148.756 109.177 139.458 92.0229 180.369 4.15247C181.106 0.321298 180.379 -0.611634 176.554 0.35256C144.683 55.7561 118.861 96.3002 133.165 114.35Z"
              fill="#EE412A" />
            <path
              d="M131.213 128.657C120.246 140.057 131.721 166.929 176.509 238.855C179.811 239.73 180.596 238.817 180.324 235.055C136.405 144.189 153.625 134.927 241.832 175.681C245.677 176.415 246.614 175.691 245.646 171.881C190.031 140.131 149.331 114.408 131.213 128.657Z"
              fill="#353DBC" />
            <path
              d="M116.08 126.65C104.637 115.726 77.6625 127.157 5.46121 171.774C4.58224 175.064 5.49851 175.845 9.27566 175.574C100.49 131.823 109.787 148.977 68.8764 236.848C68.1399 240.679 68.8668 241.612 72.6909 240.647C104.563 185.244 130.384 144.7 116.08 126.65Z"
              fill="#353DBC" />
            <rect
              x="1"
              y="271"
              width="10"
              height="10"
              fill="#353DBC" />
            <rect
              x="112"
              y="351"
              width="10"
              height="10"
              fill="#353DBC" />
            <path
              d="M247 271V326.5M230.5 289C230.5 318 229.5 324.625 202 324.625C174.5 324.625 173.5 312.5 173.5 289V346M194 342.5H222.5M156 271V326.5M37 290.5C57.5 290.5 67 291.5 67 307.5C66.75 323.5 56.5 324.5 37 324.5M22.5 271V326.5M5.5 289V346.5M101.5 342.5H130M140.5 289C140.5 318 139.5 324.625 112 324.625C84.5 324.625 83.5 312.5 83.5 289V346"
              stroke="#353DBC"
              stroke-width="10"
              stroke-linejoin="round" />
          </svg>
        </link>
      </div>

      <div class="flex flex-col justify-center h-full gap-y-8">
        <!-- List -->
        <template
          v-for="(item , i) in options"
          :key="i">
          <Link
            class="relative"
            :class="{'pointer-events-none opacity-50':item?.name==='گزارش' && (authUser?.isUser|| authUser?.isReadOnly)}"
            :href="$route(item?.route)"
            @mouseover="isTooltip[i] = true"
            @mouseout="isTooltip[i] =false">
            <component
              :is="item.icon"
              :class="{'!bg-primary !text-white' : $page.url.includes(item.slug)|| $page.url.includes(item?.slug2) || $page.url.includes(item?.slug3)}"
              class="w-14 p-2 rounded-md shadow-cardUni pointer-events-none hover:shadow-btnUni relative" />
            <transition name="tooltip">
              <div
                v-if="isTooltip[i]"
                class="absolute bottom-0 right-16 z-10 px-5 py-2 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip">
                {{ item?.name }}
              </div>
            </transition>
          </Link>
        </template>
      </div>

      <!-- logo uni-->
      <Link :href="$route('web.user.dashboard.index')">
        <img
          class="mx-auto w-16 hidden"
          src="/images/uni-logo.png"
          alt="logo uni">
        <p class="w-full text-sm text-center text-gray-600 my-3">
          version 2.0
        </p>
      </link>
    </div>
  </aside>
</template>

<script setup>
import { FolderIcon, UserIcon, ArrowTrendingUpIcon, Squares2X2Icon, BriefcaseIcon } from '@heroicons/vue/24/outline'
import { Link } from '@inertiajs/inertia-vue3'
import { ref } from 'vue'
// eslint-disable-next-line no-undef
defineOptions({
  name: 'SideBarMenu'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true }
})
const options = [
  {
    slug: '/cartable',
    icon: BriefcaseIcon,
    name: 'کارتابل',
    route: 'web.user.cartable.inbox.list'
  },
  {
    slug: '/dashboard',
    icon: FolderIcon,
    name: 'مدیریت اسناد',
    route: 'web.user.dashboard.index'
  },
  {
    slug: '/notification',
    icon: Squares2X2Icon,
    name: 'داشبورد',
    route: 'web.user.notification.index'
  },
  {
    slug: 'profile',
    slug2: 'user-management',
    slug3: 'department',
    icon: UserIcon,
    name: 'پروفایل',
    route: 'web.user.profile.show'
  },
  {
    slug: '/report',
    icon: ArrowTrendingUpIcon,
    name: 'گزارش',
    route: 'web.user.report.users'
  }
]
const isTooltip = ref([])
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
  transform: translateX(10px);
  opacity: 0;
}
</style>
