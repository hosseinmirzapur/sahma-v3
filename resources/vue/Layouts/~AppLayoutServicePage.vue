<template>
  <div class="mx-5">
    <main
      class="relative top-[5rem] h-[calc(100vh-6.25rem)] overflow-y-auto w-full mt-5"
      v-bind="$attrs"
    >
      <section>
        <slot />
      </section>
    </main>
  </div>
</template>

<script setup>
// eslint-disable-next-line no-undef
defineOptions({
  name: "~AppLayoutServicePage",
});

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true },
});
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
