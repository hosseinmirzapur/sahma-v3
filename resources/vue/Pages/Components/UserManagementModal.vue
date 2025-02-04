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
          shadow-xl transition-all sm:my-8 w-full sm:max-w-[60rem]">
          <div class="bg-white px-4 py-12 w-full">
            <div class="flex flex-col items-center">
              <div class="bg-white w-full rounded-2xl px-5">
                <ul class="list-inside text-sm text-red-600 my-1">
                  <li
                    v-for="(e , i) in errors"
                    :key="i"
                    class="text-start"
                    v-text="e" />
                </ul>
                <div class="flex justify-around py-5">
                  <p
                    class="text-start text-xl text-gray-800 w-full"
                    v-text="`اطلاعات کاربر`" />
                  <XMarkIcon
                    class="w-6 h-6 cursor-pointer text-gray-800"
                    @click="resetAction()" />
                </div>
                <div class="grid grid-cols-2 gap-10">
                  <CustomInput
                    v-for="(user, i) in users"
                    :key="i"
                    v-model="form[user.name]"
                    :input-label="user.label"
                    :input-type="user.type"
                    :input-name="user.name"
                    :is-error="errors" />
                  <div class="flex flex-col items-start relative">
                    <label class="block text-base text-gray-900">واحد</label>
                    <div
                      id="dropdown"
                      class="flex flex-row justify-start gap-x-2 items-center mt-3 rounded-lg border border-gray-300
                      w-full h-[2.5rem] py-2 px-3 focus:outline-none focus:border-none focus:ring-0 relative"
                      @click.stop.prevent="isOpenDepartments=!isOpenDepartments">
                      <p
                        v-for="(item, j) in form.departments"
                        :key="j"
                        class="bg-green-100/50 mx-2 px-2  rounded-md">
                        <span>{{ departments.find(v => v.id === item)?.name + '  ' }}</span>
                      </p>
                      <ChevronDownIcon class="absolute left-3 w-4" />
                    </div>
                    <div
                      v-if="isOpenDepartments"
                      class="absolute right-0 top-20 shadow-lg w-full px-5 py-3 bg-white"
                      @click.stop>
                      <div
                        v-for="(option, i) in departments"
                        :key="i"
                        class="flex items-center gap-x-3 py-2">
                        <input
                          :id="option.id"
                          v-model="form.departments"
                          :value="option.id"
                          type="checkbox"
                          class="w-6 h-6 rounded-sm focus:ring-secondPrimary">
                        <label
                          :for="option.id"
                          class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                          v-text="option.name" />
                      </div>
                      <!--                      <div class="flex justify-center">-->
                      <!--                        <button-->
                      <!--                          class="bg-primary hover:bg-primaryDark shadow-btnUni text-sm text-white w-32 py-2 px-4 rounded-xl"-->
                      <!--                          @click="isOpenDepartments=false"-->
                      <!--                          v-text="`تایید`" />-->
                      <!--                      </div>-->
                    </div>
                  </div>
                  <div class="flex flex-col items-start">
                    <label
                      for="select"
                      class="block text-base text-gray-900">سطح دسترسی</label>
                    <select
                      id="select"
                      v-model="form.permission"
                      class="border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-secondPrimary
                        focus:border-secondPrimary block w-full py-2 px-8 mt-3">
                      <option
                        v-for="(option, i) in options"
                        :key="i"
                        :value="option.value"
                        selected
                        v-text="option.name" />
                    </select>
                  </div>
                </div>
                <div class="flex justify-center gap-x-5 mt-10">
                  <button
                    v-if="isEdit"
                    class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark shadow-btnUni
                      text-sm border-4 text-white w-full py-2 px-4 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing"
                    @click.prevent="sunEditUserManagement(editUser.id)"
                    v-text="`ویرایش`" />
                  <button
                    v-else
                    class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark shadow-btnUni
                      text-sm border-4 text-white w-full py-2 px-4 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing"
                    @click.prevent="sunCreateUserManagement()"
                    v-text="`ایجاد`" />
                </div>
              </div>
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
import { XMarkIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import CustomInput from './CustomInput.vue'
import { onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useForm } from '@inertiajs/inertia-vue3'
import Alert from './Alert.vue'

defineOptions({
  name: 'UserManagementModal'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  isOpen: { type: Boolean, default: false },
  isEdit: { type: Boolean, default: false },
  departments: { type: Array, required: true },
  editUser: { type: Object, default: null }
})
const emits = defineEmits(['close'])
const users = ref([
  {
    label: 'نام و نام خانوادگی',
    type: 'text',
    name: 'name'
  },
  {
    label: 'شماره پرسنلی',
    type: 'number',
    name: 'personalId'
  },
  {
    label: 'سمت شغلی',
    type: 'text',
    name: 'roleTitle'
  },
  {
    label: 'رمز عبور',
    type: 'password',
    name: 'password'
  },
  {
    label: 'تکرار رمز عبور',
    type: 'password',
    name: 'password_confirmation'
  }
])
const options = ref([
  { name: 'ادمین', value: 'full' },
  { name: 'کاربر سطح یک', value: 'read_only' },
  { name: 'کاربر سطح دو', value: 'modify' }
])
const form = useForm({
  name: '',
  personalId: '',
  roleTitle: '',
  departments: [],
  password: '',
  password_confirmation: '',
  permission: ''
})

function closeDropdown () {
  isOpenDepartments.value = false
}

// Attach the event listener
window.addEventListener('click', closeDropdown)

// Cleanup when the component is unmounted
onBeforeUnmount(() => {
  window.removeEventListener('click', closeDropdown)
})

const isOpenDepartments = ref(false)
const errors = ref([])
const AlertOption = reactive({
  isAlert: false,
  dataList: [],
  status: '',
  data: ''
})

watch(() => props.isEdit, () => {
  setForm(props.editUser?.name, props.editUser?.personalId, props.editUser?.roleTitle,
    props.editUser?.departments, props.editUser?.permission, props.editUser?.id)
})

function setForm (name, personalId, roleTitle, departments, permission, password, id) {
  form.name = name
  form.personalId = personalId
  form.roleTitle = roleTitle
  form.permission = permission
  form.departments = departments.map(v => v.id)
  form.id = id
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
  close()
  form.reset()
}

function sunEditUserManagement (id) {
  form.post(route('web.user.user-management.edit-user', { user: id }), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat()
    },
    onSuccess: () => {
      setAlerts(true, 'success', ' اطلاعات کاربر مورد نظر ویرایش شد.')
      resetAction()
    }
  })
}

function sunCreateUserManagement () {
  form.post(route('web.user.user-management.create-user'), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      console.log(e)
      errors.value = Object.values(e).flat()
    },
    onSuccess: () => {
      setAlerts(true, 'success', 'کاربر مورد نظر ایجاد شد.')
      resetAction()
    }
  })
}
</script>
