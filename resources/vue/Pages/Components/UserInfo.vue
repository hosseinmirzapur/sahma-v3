<template>
  <div class="px-12">
    <div class="flex justify-end gap-x-5 mt-10">
      <button
        class="border border-primary text-primary text-center h-10 w-36 rounded-xl mx-2"
        @click="editeUserInfo(userInfo)">
        ویرایش اطلاعات
      </button>

      <button
        class="text-white bg-primary text-center h-10 w-36 rounded-xl"
        @click="setCurrUser(userInfo)">
        پاک کردن کاربر
      </button>
    </div>
    <div class="relative border border-gray-900 rounded-2xl mt-12 py-10 px-12">
      <div class="absolute -top-[1.9rem] right-6 text-xl bg-white px-5 py-4">
        اطلاعات کاربر
      </div>
      <div class="grid grid-cols-2 gap-y-6 w-full">
        <div class="flex items-center gap-x-4">
          <p v-text="`نام`" />
          <p v-text="userInfo?.name" />
        </div>
        <div class="flex items-center gap-x-4">
          <p v-text="` شماره پرسنلی`" />
          <p v-text="userInfo?.personalId" />
        </div>
        <div class="flex items-center gap-x-4">
          <p v-text="`واحد ها`" />
          <p
            v-for="dep in userInfo?.departments"
            :key="dep.id"
            class="p-1"
            v-text="dep.name" />
        </div>
        <div class="flex items-center gap-x-4">
          <p v-text="`سمت شغلی`" />
          <p v-text="userInfo?.roleTitle" />
        </div>
        <div class="flex items-center gap-x-4">
          <p v-text="`سطح دسترسی`" />
          <p v-text="dictionary(userInfo?.permission)" />
        </div>
      </div>
    </div>
    <div class="relative grid grid-cols-1 border border-gray-900 rounded-2xl mt-12 py-10 px-12">
      <div class="absolute -top-[1.9rem] right-6 text-xl bg-white px-5 py-4">
        تاریخچه عملیات
      </div>
      <ol
        v-if="activities.length>0"
        class="relative border-r border-gray-200 dark:border-gray-700">
        <li
          v-for="(active,i) in activities"
          :key="i"
          class="mb-10 ml-4">
          <div class="absolute w-3 h-3 bg-gray-900 rounded-full mt-1.5 -right-1.5 border border-gray-900" />
          <time
            class="mb-1 text-sm font-normal leading-none text-gray-900 mr-5"
            v-text="active?.created_at" />
          <p
            class="mb-4 text-base font-normal text-gray-900 mr-5 text-right"
            v-text="active?.description" />
        </li>
      </ol>
      <div
        v-else
        class="text-center font-bold">
        تاریخچه ای وجود ندارد
      </div>
    </div>
  </div>

  <UserManagementModal
    :is-open="isOpenModalEdit"
    :departments="authUser?.departments"
    :edit-user="currUser"
    :is-edit="isEdit"
    @close="closeModal" />

  <!--modal delete-->
  <Modal
    :is-open="isOpenModalDelete"
    :is-delete="true">
    <p class="text-center font-medium text-lg">
      آیا از پاک کردن کاربر {{ currUser.name }} مطمئن هستید؟
    </p>
    <hr class="mt-5">
    <div class="flex justify-center gap-x-5 mt-5">
      <button
        class="flex-shrink-0 bg-primary hover:bg-primaryDark hover:border-primaryDark text-sm
              text-white w-36 py-2 px-4 rounded-xl"
        type="submit"
        @click="deleteUser(currUser.id)"
        v-text="`تایید`" />
      <button
        class="flex-shrink-0 border border-primary text-primary
              text-sm w-36 py-2 px-4 rounded-xl hover:border-primaryDark hover:text-primaryDark"
        type="submit"
        @click="isOpenModalDelete=false"
        v-text="`انصراف`" />
    </div>
  </Modal>
</template>

<script setup>
import UserManagementModal from '../Components/UserManagementModal.vue'
import Modal from './Modal.vue'
import layout from '../../Layouts/~AppLayout.vue'
import { Inertia } from '@inertiajs/inertia'
import { reactive, ref } from 'vue'
import { dictionary } from '../../globalFunction/dictionary.js'

defineOptions({
  name: 'UserInfo',
  layout
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  userInfo: { type: Object, required: true },
  activities: { type: Array, required: true },
  authUser: { type: Object, required: true }
})
const isOpenModalDelete = ref(false)
const isOpenModalEdit = ref(false)
const currUser = reactive({
  id: '',
  name: '',
  roleTitle: '',
  departments: [],
  permission: '',
  personalId: null
})
const isEdit = ref(false)

function closeModal () {
  isEdit.value = false
  isOpenModalDelete.value = false
  isOpenModalEdit.value = false
}

function editeUserInfo (user) {
  isOpenModalEdit.value = true
  isEdit.value = true
  currUser.id = user.id
  currUser.name = user.name
  currUser.personalId = user.personalId
  currUser.permission = user.permission
  currUser.roleTitle = user.roleTitle
  currUser.departments = user.departments
}

function setCurrUser (user) {
  currUser.name = user.name
  currUser.id = user.id
  isOpenModalDelete.value = true
}

function deleteUser (id) {
  Inertia.visit(route('web.user.user-management.delete-user', { user: id }), {
    method: 'post',
    replace: true,
    preserveState: true, // Add preserveState option
    onSuccess: () => {
      isOpenModalDelete.value = false
    }
  })
}
</script>
