<template>
  <div
    v-show="isOpen"
    class="relative z-10"
    aria-labelledby="modal-title"
    role="dialog">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-20 transition-opacity" />
    <div class="fixed inset-0 z-10 overflow-y-auto">
      <div
        class="flex m-auto w-full flex-wrap items-center min-h-full items-end
        justify-center p-4 text-center sm:items-center sm:p-0">
        <div
          class="relative transform overflow-auto rounded-2xl bg-white text-left
          shadow-xl transition-all sm:my-8 w-full max-w-[30rem]">
          <div class="bg-white px-4 py-12 w-full">
            <div class="flex flex-col items-center">
              <form
                class="bg-white w-full rounded-2xl px-5"
                @submit.prevent="isEdit ? subEdit() :sunCreate()">
                <ul class="list-inside text-sm text-red-600 my-1">
                  <li
                    v-for="(e , i) in errors"
                    :key="i"
                    class="text-start"
                    v-text="getErrorText(e)" />
                </ul>
                <div class="flex justify-around py-5">
                  <p
                    class="text-start text-xl text-gray-800 w-full"
                    v-text="`ایجاد واحد`" />
                  <XMarkIcon
                    class="w-6 h-6 cursor-pointer text-gray-800"
                    @click="resetAction()" />
                </div>
                <div class="grid grid-cols-1 gap-10">
                  <CustomInput
                    v-model="form.departmentName"
                    input-label="نام واحد را وارد کنید"
                    input-type="text"
                    input-name="department"
                    :is-error="errors" />
                </div>
                <div class="flex justify-center gap-x-5 mt-10">
                  <button
                    v-if="isEdit"
                    class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark shadow-btnUni
                      text-sm border-4 text-white w-full py-2 px-4 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing"
                    type="submit"
                    v-text="`ویرایش`" />
                  <button
                    v-else
                    class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark shadow-btnUni
                      text-sm border-4 text-white w-full py-2 px-4 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing"
                    type="submit"
                    v-text="`ایجاد`" />
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <Alert
    class="z-20"
    :title="AlertOption.data"
    :is-open="AlertOption.isAlert"
    :contents-list="AlertOption.dataList"
    :status="AlertOption.status"
    @close="AlertOption.isAlert=false" />
</template>

<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'
import CustomInput from './CustomInput.vue'
import { reactive, ref, watchEffect } from 'vue'
import { useForm } from '@inertiajs/inertia-vue3'
import Alert from './Alert.vue'

defineOptions({
  name: 'DepartmentModal'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  isOpen: { type: Boolean, default: false },
  isEdit: { type: Boolean, default: false },
  editDepartment: { type: Object, default: null }
})
const emits = defineEmits(['close'])
const form = useForm({
  departmentName: ''
})

watchEffect(() => {
  form.departmentName = props.editDepartment.name
})

const errors = ref([])
const AlertOption = reactive({
  isAlert: false,
  dataList: [],
  status: '',
  data: ''
})
const getErrorText = (error) => {
  switch (error) {
    case 'The department name field is required.':
      return 'واحد را وارد کنید'
    default:
      return error
  }
}

function close (v) {
  emits('close', v)
}

function setAlerts (isAlert, status, data = '', dataList = []) {
  AlertOption.status = status
  AlertOption.data = data
  AlertOption.dataList = dataList
  AlertOption.isAlert = isAlert
}

function resetAction () {
  form.reset()
  close()
}

function subEdit () {
  form.post(route('web.user.department.edit', { department: props.editDepartment?.id }), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat()
    },
    onSuccess: () => {
      setAlerts(true, 'success', ' اطلاعات واحد مورد نظر ویرایش شد.')
      resetAction()
    }
  })
}

function sunCreate () {
  form.post(route('web.user.department.create'), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      console.log(e)
      errors.value = Object.values(e).flat()
    },
    onSuccess: () => {
      setAlerts(true, 'success', 'واحد مورد نظر ایجاد شد.')
      resetAction()
    }
  })
}
</script>
