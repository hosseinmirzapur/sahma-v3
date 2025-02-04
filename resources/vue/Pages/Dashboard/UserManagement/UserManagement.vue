<template>
  <div class="">
    <div class="flex flex-row-reverse items-center bg-white w-full rounded-2xl shadow-cardUni p-4">
      <button
        class="mx-5 w-56 h-12 text-white bg-primary rounded-xl text-xl cursor-pointer inline-flex justify-center items-center"
        @click="isOpenModal=true">
        افزودن
        <PlusSmallIcon class="w-6 h-6" />
      </button>
      <ToolBarUserManagement
        v-model.lazy="form.identifier"
        class="w-full" />
    </div>
    <Table
      v-if="users.length>0"
      :col="column"
      :rows="users"
      class="mt-10"
      @edit-user="editUserData" />
    <div
      v-else
      class="h-[calc(100vh-20rem)] flex justify-center item center">
      <div class="w-1/2 rounded-xl flex flex-col items-center gap-y-8 text-lg text-base p-5 m-auto">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="181"
          height="204"
          viewBox="0 0 181 204"
          fill="none">
          <ellipse
            opacity="0.1"
            cx="90.5"
            cy="104"
            rx="90.5"
            ry="91"
            fill="#2A3875" />
          <rect
            x="65.223"
            y="47.1755"
            width="96.4336"
            height="134.713"
            rx="7"
            transform="rotate(2.5 65.223 47.1755)"
            fill="#FAFAFA"
            stroke="#8AA6FF"
            stroke-width="2" />
          <rect
            x="9.09385"
            y="39.8598"
            width="100.434"
            height="138.713"
            rx="9"
            transform="rotate(-7.5 9.09385 39.8598)"
            fill="#FAFAFA"
            stroke="#8AA6FF"
            stroke-width="2" />
          <path
            d="M18.2126 40.1722L99.941 29.4125C104.048 28.8718 107.815 31.7627 108.356 35.8694L111.351 58.6171L14.7506 71.3348L11.7558 48.587C11.2151 44.4803 14.1059 40.7129 18.2126 40.1722Z"
            fill="#FAFAFA"
            stroke="#8AA6FF" />
          <rect
            x="129.03"
            y="31.9727"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(82.5 129.03 31.9727)"
            fill="#8AA6FF" />
          <rect
            x="91.5762"
            y="9.32617"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(-7.5 91.5762 9.32617)"
            fill="#8AA6FF" />
          <rect
            x="115.606"
            y="13.0566"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(37.5 115.606 13.0566)"
            fill="#8AA6FF" />
          <rect
            x="24.5215"
            y="76.0684"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 24.5215 76.0684)"
            fill="#EAEBF1" />
          <rect
            x="28.0908"
            y="103.178"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 28.0908 103.178)"
            fill="#EAEBF1" />
          <rect
            x="31.6602"
            y="130.287"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 31.6602 130.287)"
            fill="#EAEBF1" />
          <rect
            x="25.9492"
            y="86.9121"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 25.9492 86.9121)"
            fill="#D1D8FF" />
          <rect
            x="29.5186"
            y="114.021"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 29.5186 114.021)"
            fill="#D1D8FF" />
          <rect
            x="33.0869"
            y="141.129"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 33.0869 141.129)"
            fill="#D1D8FF" />
        </svg>
        <p class="text-center text-primary/70">
          در حال حاضر کاربری ثبت نشده است
        </p>
      </div>
    </div>
    <UserManagementModal
      :is-open="isOpenModal"
      :departments="departments"
      :edit-user="editUser"
      :is-edit="isEdit"
      @close="closeModal" />
  </div>
</template>

<script setup>
import { PlusSmallIcon } from '@heroicons/vue/24/outline'
import layout from '../../../Layouts/~AppLayout.vue'
import { reactive, ref, watch } from 'vue'
import UserManagementModal from '../../Components/UserManagementModal.vue'
import Table from '../../Components/Table.vue'
import { useForm } from '@inertiajs/inertia-vue3'
import ToolBarUserManagement from '../../Components/ToolBarUserManagement.vue'

defineOptions({
  name: 'UserManagement',
  layout
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true },
  users: { type: Array, required: true }
})

// eslint-disable-next-line vue/no-setup-props-destructure
const { departments } = props.authUser

const column = ref(['نام و نام خانوادگی', 'شماره پرسنلی', 'سمت', 'واحد', 'سطح دسترسی'])
const form = useForm({
  identifier: ''
})

const isEdit = ref(false)
const isOpenModal = ref(false)

let editUser = reactive(null)
let searchTimer = null

const editUserData = (data) => {
  const userToUpdate = props?.users.find(user => user.id === data)
  if (userToUpdate) {
    editUser = { ...userToUpdate }
    isEdit.value = true
    isOpenModal.value = true
  }
}

function closeModal () {
  isEdit.value = false
  isOpenModal.value = false
}

function persianToEnglishNumber (persianNumber) {
  const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']
  const englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']

  let englishNumber = persianNumber
  for (let i = 0; i < persianDigits.length; i++) {
    const persianDigit = persianDigits[i]
    const englishDigit = englishDigits[i]
    englishNumber = englishNumber.replace(new RegExp(persianDigit, 'g'), englishDigit)
  }

  return englishNumber
}

watch(
  () => form.identifier,
  (newValue) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      newValue = persianToEnglishNumber(newValue)
      searchUser()
    }, 1000)
  }
)

function searchUser () {
  form.post(route('web.user.user-management.search'), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat()
    }
  })
}
</script>
