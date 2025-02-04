<template>
  <div class="relative overflow-auto rounded-t-2xl mx-5">
    <table
      class="w-full text-center"
      :col="col"
      :rows="rows">
      <!-- header -->
      <thead class="text-base bg-white h-16 rounded-t-2xl">
        <tr class="w-full text-gray-500 mb-10 rounded-t-2xl">
          <th
            v-for="(item, i) in col"
            :key="i"
            scope="col"
            class="px-6 py-3">
            <div class="flex justify-center items-center">
              {{ item }}
            </div>
          </th>
        </tr>
      </thead>
      <!--body-->
      <tbody>
        <tr
          v-for="(user, i) in rows"
          :key="i"
          class="rounded-2xl p-5 text-base cursor-default bg-white border-t border-gray-300
                 cursor-pointer hover:bg-primaryDark/5"
          @click.prevent="openUserInfo(user)">
          <template
            v-for="(item ,key, j) in user"
            :key="j">
            <td
              v-if="key!=='id' && key!=='password' && key!=='departments'"
              class="relative font-medium text-gray-700 px-2 ">
              <p
                class="text-center cursor-pointer"
                v-text="dictionary(item)" />
            </td>
            <!-- department -->
            <td
              v-else-if="key ==='departments'"
              class="relative font-medium text-gray-700 p-2 ">
              <template
                v-for="(itemDep, c) in user.departments"
                :key="c">
                <div
                  v-if="key==='departments'"
                  class="bg-green-100/50 mx-2 px-2 inline-flex flex-wrap rounded-md"
                  v-text=" itemDep.name" />
              </template>
            </td>
          </template>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
// eslint-disable-next-line no-undef
import { Inertia } from '@inertiajs/inertia'
import { dictionary } from '../../globalFunction/dictionary.js'

defineOptions({
  name: 'Table'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  col: { type: Array, required: true },
  rows: { type: Object, required: true }
})

function openUserInfo (user) {
  Inertia.get(route('web.user.user-management.user-info', { user: user?.id }), {
    replace: true,
    preserveState: true
  })
}
</script>
