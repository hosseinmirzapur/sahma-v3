<template>
  <h2
    class="text-right mt-5 mx-7 text-xl font-medium pb-10"
    v-text="title " />
</template>

<script setup>
// eslint-disable-next-line no-undef
defineOptions({
  name: 'TitleTopDashboard'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  title: { type: String, required: true }
})
</script>
