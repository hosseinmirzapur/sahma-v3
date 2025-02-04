<template>
  <div class="flex">
    <AppSideBarMenu :auth-user="authUser" />
    <div class="m-auto w-full ml-5">
      <HeaderDashboard :auth-user="authUser" />
      <div class="flex">
        <!-- sidebar dynamic -->
        <SideBarDashboard
          v-if="isSideBar('dashboard',$page.url)"
          :auth-user="authUser"
          :folders="authUser?.folders"
          :departments="authUser?.departments" />
        <SideBarReport
          v-if="isSideBar('report',$page.url)"
          :departmants="authUser.departments" />
        <SideBarUM
          v-if="isSideBar('userManagement',$page.url)"
          :departmants="authUser.departments"
          :auth-user="authUser" />
        <SideBarInbox v-if="isSideBar('cartable',$page.url)" />
        <SideBarReminder v-if="isSideBar('notification',$page.url)" />
        <!-- end sidebar dynamic -->
        <main
          class="h-[calc(100vh-140px)] w-5/6 overflow-y-auto bg-white relative shadow-cardUni rounded-xl my-5 mr-5"
          v-bind="$attrs">
          <template v-if="$page.url.includes('dashboard')">
            <section @contextmenu.prevent>
              <slot />
            </section>
          </template>
          <template v-else>
            <section>
              <slot />
            </section>
          </template>
        </main>
      </div>
    </div>
  </div>
</template>

<script setup>
import HeaderDashboard from '../Pages/Components/~AppHeaderDashboard.vue'
import SideBarDashboard from '../Pages/Components/~AppSideBarDashboard.vue'
import SideBarReport from '../Pages/Components/~AppSideBarReport.vue'
import SideBarUM from '../Pages/Components/~AppSideBarUM.vue'
import SideBarInbox from '../Pages/Components/~AppSideBarInbox.vue'
import SideBarReminder from '../Pages/Components/~AppSideBarReminder.vue'
import AppSideBarMenu from '../Pages/Components/~AppSideBarMenu.vue'

// eslint-disable-next-line no-undef
defineOptions({
  name: '~AppLayout'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true }
})

function isSideBar (section, url) {
  switch (section) {
    case 'dashboard':
      return url.includes('dashboard')
    case 'userManagement':
      return url.includes('profile') || url.includes('user-management') || url.includes('department')
    case 'report':
      return url.includes('report')
    case 'cartable':
      return url.includes('cartable')
    case 'notification':
      return url.includes('notification')
  }
}

</script>

<style lang="scss">
h1,
h2,
h3,
h4,
h5,
h6,
p,
span {
  cursor: default;
}
</style>
