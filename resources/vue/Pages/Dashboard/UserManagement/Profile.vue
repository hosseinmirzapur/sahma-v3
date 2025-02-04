<template>
  <div class="p-5">
    <h2
      class="text-right text-xl font-bold pb-10 m-0"
      v-text="`پروفایل`" />
    <div class="grid grid-cols-2 gap-10">
      <div class="flex flex-col items-start">
        <label class="block text-base text-gray-900">نام و نام خانوادگی</label>
        <input
          class="mt-3 rounded-lg border border-gray-300 bg-gray-100 w-full h-[2.5rem] py-2 px-3"
          :value="user?.name"
          disabled>
      </div>
      <div class="flex flex-col items-start">
        <label class="block text-base text-gray-900">شماره پرسنلی</label>
        <input
          class="mt-3 rounded-lg border border-gray-300 bg-gray-100 w-full h-[2.5rem] py-2 px-3"
          :value="user?.personal_id"
          disabled>
      </div>
      <div class="flex flex-col items-start">
        <label class="block text-base text-gray-900">سمت شغلی</label>
        <input
          class="mt-3 rounded-lg border border-gray-300 bg-gray-100 w-full h-[2.5rem] py-2 px-3"
          :value="user?.role?.title"
          disabled>
      </div>
      <div class="flex flex-col items-start">
        <label class="block text-base text-gray-900">سطح</label>
        <input
          class="mt-3 rounded-lg border border-gray-300 bg-gray-100 w-full h-[2.5rem] py-2 px-3"
          :value="dictionary(user?.role?.permission)"
          disabled>
      </div>
      <div class="flex flex-row items-start gap-x-2">
        <label class="block text-base text-gray-900">واحد </label>
        <div class="flex flex-row justify-start gap-x-1 items-center">
          <p
            v-for="(department, i) in departments"
            :key="i"
            class="bg-green-100/50 mx-2 px-2  rounded-md"
            v-text="department?.name" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'

defineOptions({
  name: 'Profile',
  layout
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  user: { type: Object, required: true },
  departments: { type: Array, required: true }
})
const dictionary = (permission) => {
  if (permission?.full === 1) return 'ادمین'
  if (permission?.modify === 1) return 'کاربر سطح یک'
  if (permission?.read_only === 1) return 'کاربر سطح دو'
  return 'نامشخص'
}
</script>
