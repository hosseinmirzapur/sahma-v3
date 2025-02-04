<template>
  <div class="p-4">
    <h2 class="text-right text-xl font-bold mt-0 mb-2">
      سامانه هوشمند مدیریت اسناد
    </h2>
    <SlotTable :column="column">
      <tr
        v-for="(notif , i) in notifications"
        :key="i"
        class="cursor-default"
        :class="{'cursor-pointer': notif?.letterId}"
        @click.prevent="notif?.letterId?openReminder(notif?.letterId) : null">
        <td
          class="px-6 py-3"
          v-text="notif?.remindAt" />
        <td
          class="px-6 py-3"
          v-text="notif?.letterId" />
        <td
          class="px-6 py-3"
          v-text="notif?.description" />
        <td
          class="px-6 py-3"
          v-text="dictionary(notif?.priority)" />
      </tr>
    </SlotTable>
  </div>
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'
import SlotTable from '../../Components/SlotTable.vue'
import { dictionary } from '../../../globalFunction/dictionary.js'
import { Inertia } from '@inertiajs/inertia'

defineOptions({
  name: 'Reminder',
  layout
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  // eslint-disable-next-line vue/require-default-prop
  notifications: { type: Array, require: true }
})

const column = [
  'زمان یادآوری',
  'شماره نامه',
  'موضوع',
  'اولویت'
]

function openReminder (id) {
  Inertia.visit(route('web.user.cartable.letter.show', { letter: id }), {
    method: 'get'
  })
}
</script>
