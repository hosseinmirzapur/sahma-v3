<template>
  <div class="relative overflow-x-auto rounded-t-2xl mt-10 mx-5">
    <table class="w-full text-sm text-left rtl:text-right text-primaryText">
      <thead class="text-xs text-primaryText">
        <tr class="w-full text-gray-500 mb-10 rounded-t-2xl border-b border-gray-300">
          <th
            v-for="(col, i) in column"
            :key="i"
            scope="col"
            class="px-6 py-3">
            <!-- text -->
            <div v-if="!col?.type">
              {{ col }}
            </div>
            <!-- checkBox -->
            <div
              v-if="col.type==='checkbox'"
              class="flex items-center">
              <input
                id="checkbox-all-search"
                type="checkbox"
                class="w-4 h-4 text-primary bg-transparent border-gray-400 rounded focus:ring-primary focus:ring-2">
              <label
                for="checkbox-all-search"
                class="sr-only">checkbox</label>
            </div>
            <!-- icons -->
            <div v-if="col.type==='icon'">
              <component
                :is="col.icon"
                class="w-6" />
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <slot />
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineOptions({
  name: 'SlotTable'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  column: { type: Array, required: true }
})
</script>

<style scoped>

</style>
