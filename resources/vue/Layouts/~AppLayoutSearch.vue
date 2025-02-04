<template>
  <div class="flex">
    <AppSideBarMenu />
    <div class="flex w-full">
      <main
        class="h-[calc(100vh-1px)] w-full overflow-y-auto bg-white relative shadow-cardUni rounded-xl"
        v-bind="$attrs">
        <section>
          <slot />
        </section>
      </main>
    </div>
  </div>
</template>

<script setup>
import AppSideBarMenu from '../Pages/Components/~AppSideBarMenu.vue'

// eslint-disable-next-line no-undef
defineOptions({
  name: '~AppLayout'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true }
})

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
