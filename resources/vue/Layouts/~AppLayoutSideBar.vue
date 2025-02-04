<template>
  <aside class="w-1/6 relative right-0 my-5">
    <div
      class="flex h-[calc(100vh-140px)] p-5 flex-col items-start px-4 rounded-xl
             shadow-cardUni bg-primary overflow-auto">
      <slot />
    </div>
  </aside>
</template>

<script setup>
// eslint-disable-next-line no-undef
defineOptions({
  name: '~AppLayoutSideBar'
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
