<template>
  <div class="">
    <button
      class="mt-7 mx-5 w-56 h-12 text-white bg-primary rounded-xl text-xl cursor-pointer inline-flex justify-center items-center"
      @click="isOpenModal=true">
      افزودن
      <PlusSmallIcon class="w-6 h-6" />
    </button>

    <div
      v-if="departments.length > 0"
      class="relative overflow-auto rounded-t-2xl mx-5">
      <table class="w-full text-center">
        <!--      header -->
        <thead class="text-base bg-white h-16 rounded-t-2xl">
          <tr class="w-full text-gray-500 mb-10 rounded-t-2xl">
            <th
              v-for="(item, i) in column"
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
            v-for="(dep, i) in departments"
            :key="i"
            class="rounded-2xl p-5 text-base cursor-default bg-white border-t border-gray-300">
            <td class="relative font-medium text-gray-700 px-2 ">
              <span>{{ i+1 }}</span>
            </td>
            <td class="relative font-medium text-gray-700 px-2 ">
              <span>{{ dep?.name }}</span>
            </td>
            <!--actions-->
            <td class="px-6 py-4 gap-x-3 font-medium whitespace-nowrap">
              <button
                class="border border-primary text-primary text-center h-10 w-36 rounded-xl mx-2"
                @click="openModalEdit(dep)">
                اعمال تغییرات
              </button>

              <button
                class="text-white bg-primary text-center h-10 w-36 rounded-xl hidden"
                @click="openModalDelete(dep)">
                پاک کردن
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

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
          در حال حاضر واحدی ثبت نشده است
        </p>
      </div>
    </div>

    <!-- delete modal-->
    <Modal
      :is-open="isOpenModalDelete"
      :is-delete="true">
      <p class="text-center font-medium text-lg">
        آیا از پاک کردن واحد {{ currDep.name }}  مطمئن هستید؟
      </p>
      <hr class="mt-5">
      <div class="flex justify-center gap-x-5 mt-5">
        <button
          class="flex-shrink-0 bg-primary hover:bg-primaryDark hover:border-primaryDark text-sm
              text-white w-36 py-2 px-4 rounded-xl"
          type="submit"
          @click="deleteDepartment()"
          v-text="`تایید`" />
        <button
          class="flex-shrink-0 border border-primary text-primary
              text-sm w-36 py-2 px-4 rounded-xl hover:border-primaryDark hover:text-primaryDark"
          type="submit"
          @click="isOpenModalDelete=false"
          v-text="`انصراف`" />
      </div>
    </Modal>

    <!-- edite modal-->
    <DepartmentModal
      :is-open="isOpenModal"
      :edit-department="currDep"
      :is-edit="isEdit"
      @close="closeModal" />
  </div>
</template>

<script setup>
import { PlusSmallIcon } from '@heroicons/vue/24/outline'
import layout from '../../../Layouts/~AppLayout.vue'
import { ref } from 'vue'
import DepartmentModal from '../../Components/DepartmentModal.vue'
import { Inertia } from '@inertiajs/inertia'
import Modal from '../../Components/Modal.vue'
import { useForm } from '@inertiajs/inertia-vue3'

defineOptions({
  name: 'Department',
  layout
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true },
  departments: { type: Array, required: true }
})
const isOpenModal = ref(false)
const isOpenModalDelete = ref(false)
const column = ref(['شماره', 'نام واحد', ''])
const isEdit = ref(false)
const currDep = useForm({
  id: null,
  name: null
})

function setCurrDep (id, name) {
  currDep.id = id
  currDep.name = name
}

const openModalEdit = (dep) => {
  isEdit.value = true
  isOpenModal.value = true
  setCurrDep(dep.id, dep.name)
}

function openModalDelete (dep) {
  isEdit.value = false
  isOpenModalDelete.value = true
  setCurrDep(dep.id, dep.name)
}

function deleteDepartment () {
  Inertia.visit(route('web.user.department.delete', { department: currDep.id }), {
    method: 'post',
    replace: true,
    preserveState: true, // Add preserveState option
    onSuccess: () => {
      isOpenModalDelete.value = false
      currDep.reset()
    }
  })
}

function closeModal () {
  isEdit.value = false
  isOpenModal.value = false
  currDep.reset()
}
</script>
