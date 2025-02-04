<template>
  <Modal
    modal-size="large"
    :is-open="isOpen"
    title="یادآوری جدید"
    @close="close">
    <form
      class="w-full flex flex-col gap-y-5"
      @submit.prevent="subReminder">
      <!--  number letter & subject-->
      <div class="flex flex-col  items-start w-full ">
        <!--  number letter-->
        <div class="flex items-center w-full border-b border-primary/10">
          <label
            for="numberLetter"
            class="text-sm font-medium text-primaryText w-32 text-right"
            v-text="`شماره نامه`" />
          <input
            id="numberLetter"
            v-model="form.letter_id"
            type="text"
            :disabled="props.id"
            class="col-span-1 placeholder-gray-500 bg-transparent border-0 text-primary p-1
                 w-full focus:outline-none focus:bg-transparent focus:ring-0
                 disabled:opacity-50 ">
        </div>
        <!--  subject -->
        <div class="flex items-center w-full border-b border-primary/10">
          <label
            for="subject"
            class="text-sm font-medium text-primaryText w-32 text-right"
            v-text="`موضوع`" />
          <input
            id="subject"
            v-model="form.subject"
            type="text"
            :disabled="props.subject"
            class="col-span-3 placeholder-gray-500 bg-transparent border-0 text-primary
                 w-full focus:outline-none focus:bg-transparent focus:ring-0
                 disabled:opacity-50">
        </div>
      </div>

      <!-- DATE PICKER  & priority -->
      <div class="grid grid-cols-2">
        <div class="flex p-1 items-center w-full justify-between border-b border-primary/5">
          <label>تاریخ</label>
          <div class="relative pr-5">
            <DatePicker
              v-model="form.remindAt"
              placeholder="1402/09/09"
              class="rounded-lg w-full text-primaryText z-50"
              format="jYYYY-jMM-jDD"
              display-format="jYYYY/jMM/jDD"
              simple
              popover />
            <CalendarIcon class="w-6 absolute top-2 left-2 !text-primaryText" />
          </div>
        </div>
        <!-- priority-->
        <div class="flex justify-center items-center gap-x-5">
          <div
            v-for="(priority , i) in priorities "
            :key="i"
            class="flex items-center ">
            <input
              :id="priority"
              v-model="form.priority"
              class="hidden peer"
              type="radio"
              :name="priority"
              :value="priority">
            <label
              class="mt-px inline-block cursor-pointer text-sm px-4 py-2 rounded-lg"
              :class="setColorCategory(priority)"
              :for="priority"
              v-text="dictionary(priority)" />
          </div>
        </div>
      </div>

      <!--   description   -->
      <div class="border-2 border-dashed border-gray-300 bg-white rounded-lg px-2.5 py-3.5">
        <label
          for="description"
          class="w-full font-bold text-right text-primaryText">
          <p v-text="`توضیحات`" />
        </label>
        <textarea
          id="description"
          v-model="form.description"
          rows="2"
          class="w-full border-0 focus:outline-none focus:ring-0" />
      </div>
      <!--submit-->
      <div class="flex justify-center gap-x-2 mt-5">
        <button
          class="text-center bg-primary border-primary text-sm border-4 text-white w-36 py-2 px-4 rounded-xl"
          @click.prevent="subReminder"
          v-text="`ارسال`" />
        <button
          class="flex justify-center items-center bg-white hover:text-white hover:bg-secondPrimary border
             border-primary hover:border-none text-sm text-primary w-36 py-2 px-4 rounded-xl"
          @click.prevent="close">
          انصراف
        </button>
      </div>
    </form>
  </Modal>
  <Alert
    class="z-20"
    :title="AlertOption.data"
    :is-open="AlertOption.isAlert"
    :contents-list="AlertOption.dataList"
    :status="AlertOption.status"
    @close="AlertOption.isAlert=false" />
</template>

<script setup>
import Modal from './Modal.vue'
import { useForm, usePage } from '@inertiajs/inertia-vue3'
import DatePicker from 'vue3-persian-datetime-picker'
import { CalendarIcon } from '@heroicons/vue/24/outline'
import { dictionary } from '../../globalFunction/dictionary.js'
import { reactive } from 'vue'
import Alert from './Alert.vue'

defineOptions({
  name: 'ModalReminder'
})

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  id: { type: Number, default: null },
  subject: { type: String, default: null }
})

const priorities = ['NORMAL', 'IMMEDIATELY', 'INSTANT']
const form = useForm({
  letter_id: props.id || '',
  subject: props.subject || '',
  description: '',
  remindAt: null,
  priority: 'NORMAL'
})
const AlertOption = reactive({
  isAlert: false,
  dataList: [],
  status: '',
  data: ''
})
const { url } = usePage()

const setColorCategory = (item) => {
  return {
    'peer-checked:bg-blue-200 peer-checked:text-blue-700': item === 'NORMAL',
    'peer-checked:bg-red-200 peer-checked:text-red-700': item === 'SECRET' || item === 'INSTANT',
    'peer-checked:bg-orange-200 peer-checked:text-orange-700 ': item === 'CONFIDENTIAL' || item === 'IMMEDIATELY'
  }
}

// function
const emits = defineEmits(['close'])

function close (v) {
  form.reset()
  emits('close', v)
}

function setAlerts (isAlert, status, data = '', dataList = []) {
  AlertOption.status = status
  AlertOption.data = data
  AlertOption.dataList = dataList
  AlertOption.isAlert = isAlert
}

function subReminder () {
  const isNotification = url.value.includes('notification')
  form.post(route(isNotification ? 'web.user.notification.create.action' : 'web.user.cartable.submit.reminder.action', { letter: form.letter_id }), {
    replace: true,
    preserveScroll: true,
    onSuccess: () => {
      setAlerts(true, 'success', 'يادآوری یا موفقیت ثبت شد.')
      close()
    }
  })
}
</script>
