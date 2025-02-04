<template>
  <div class="p-5">
    <div class="p-5 rounded-xl">
      <!-- search -->
      <div
        class="w-full relative rounded-xl flex justify-between items-center md:h-20 h-16 py-5
        focus-within:text-secondPrimary text-gray-400">
        <input
          ref="inputRef"
          v-model="form.searchable_text"
          type="text"
          name="search"
          placeholder="جستجو کنید"
          class="placeholder-gray-400 bg-transparent border-x-0 border-t-0 border-b
          border-gray-300 text-gray-800 w-full py-2 px-4 focus:outline-none focus:bg-transparent
          focus:border-secondPrimary focus:ring-0 disabled:opacity-50"
          @keydown.enter="subSearch">
        <MagnifyingGlassIcon class="absolute top-6 left-6 w-6" />
      </div>
      <!-- errors -->
      <ul
        v-if="errors?.length > 0"
        class="list-inside text-sm text-red-600 pt-2">
        <li
          v-for="(e , i) in errors"
          :key="i"
          class="text-start"
          v-text="e" />
      </ul>
      <div class="mx-auto flex flex-col">
        <div
          v-for="(item , key , i) in categorySearch"
          :key="i"
          class="w-full px-5 py-3 mb-3 bg-primary/5 border-b-2 border-gray-200 rounded-t-lg ">
          <!-- header-->
          <div
            class=" flex flex-row justify-between items-center cursor-pointer"
            @click.stop.prevent="isOpenLetter[i]=!isOpenLetter[i]">
            <div class=" flex items-center">
              <p
                class="transition-colors text-right text-primaryText select-none text-md text-primaryText cursor-pointer"
                v-text="item" />
            </div>

            <div class="flex">
              <!-- Badge -->
              <!--              <span-->
              <!--                v-if="conditionBadge(i , key)"-->
              <!--                class="justify-items-end items-center bg-green-100 text-green-800 text-xs cursor-pointer-->
              <!--                font-medium mr-2 px-2.5 py-0.5 rounded-full dark:bg-green-900 dark:text-green-300">-->
              <!--                <span class="w-2 h-2 mx-1 mr-1 bg-green-500 rounded-full" />-->
              <!--                فیلتر شده-->
              <!--              </span>-->
              <ChevronUpIcon
                v-if="isOpenLetter[i]"
                class="w-6 cursor-pointer" />
              <ChevronDownIcon
                v-else
                class="w-6 cursor-pointer" />
            </div>
          </div>

          <!-- body letter-->
          <div
            v-if="isOpenLetter[i] && key === 'optionLetter'"
            class="grid grid-cols-2 gap-5 w-full transition-all text-right text-primaryText
            font-thin text-xs lg:text-base my-6">
            <CustomInput
              v-for="(input , indexInput) in inputOptionLetter"
              :key="indexInput"
              v-model="form[input.name]"
              :input-label="input.label"
              :input-type="input.type"
              :input-name="input.name"
              class="mt-10 select-none"
              :is-search-input="true" />
          </div>

          <!-- body department-->
          <div
            v-if="isOpenLetter[i] && key === 'optionDepartment'"
            class="w-full transition-all text-right text-primaryText font-thin text-xs lg:text-base mt-6">
            <div class="flex flex-col items-start relative mt-10 cursor-pointer select-none">
              <label class="block text-base text-gray-900 w-48">واحد</label>
              <div
                class="flex flex-row justify-start gap-x-2 items-center mt-3 rounded-lg border border-gray-300 !bg-white w-full
            h-[2.5rem] py-2 px-3 focus:outline-none focus:border-none focus:ring-0 relative"
                @click.stop="toggleDropdown('departments')">
                <p class="">
                  <span class="relative mx-1 px-3 pl-8 text-sm rounded-md text-gray-900 bg-green-500/20">
                    واحد
                    <XMarkIcon
                      class="w-8 absolute top-1 px-2 left-0 cursor-pointer"
                      @click.stop="deleteDepartments(j)" />
                  </span>
                </p>
                <ChevronUpIcon
                  v-if="isOpenDepartments"
                  class="absolute left-3 w-4 text-gray-900" />
                <ChevronDownIcon
                  v-else
                  class="absolute left-3 w-4 text-gray-900" />
              </div>
              <div
                v-if="isOpenDepartments"
                class="absolute right-0 top-20 shadow-lg w-full px-5 py-3 bg-white z-10"
                @click.stop>
                <div class="flex items-center gap-x-3 py-2">
                  <input
                    :id="depart.id"
                    v-model="form.departments"
                    :value="depart.id"
                    type="checkbox"
                    class="w-6 h-6 rounded-sm focus:ring-secondPrimary">
                  <label
                    :for="depart.id"
                    class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                    v-text="depart.name" />
                </div>
              </div>
            </div>
          </div>

          <!-- body date-->
          <div
            v-if="isOpenLetter[i] && key === 'optionDate'"
            class="w-full transition-all text-right text-primaryText font-thin text-xs lg:text-base mt-6">
            <div class="flex flex-col items-start col-span-2 relative my-10">
              <label class="block text-base text-gray-900 select-none"> تاریخ</label>
              <div class="flex items-center gap-x-2 mt-3 w-full">
                <p class="text-gray-900">
                  از
                </p>
                <DatePicker
                  v-model="form.fromDate"
                  class="border border-gray-300 rounded-lg mb-2  md:mb-0 w-full bg-white text-gray-900"
                  :class="{'border border-red-500': errors.length>0}"
                  format="jYYYY-jMM-jDD"
                  display-format="jYYYY/jMM/jDD"
                  simple
                  popover />
                <p class="text-gray-900">
                  تا
                </p>
                <DatePicker
                  v-model="form.toDate"
                  class="border border-gray-300 rounded-lg mb-2 md:mb-0 w-full bg-white text-gray-900"
                  :class="{'border border-red-500': errors.length>0}"
                  format="jYYYY-jMM-jDD"
                  display-format="jYYYY/jMM/jDD"
                  simple
                  popover />
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-center gap-x-2">
          <button
            class="text-center bg-primary border-primary mt-10 text-sm border-4 text-white w-36 py-2 px-4 rounded-xl"
            @click="subSearch"
            v-text="`تایید`" />
          <Link
            :href="$route('web.user.cartable.inbox.list')"
            class="flex justify-center items-center bg-white hover:text-white hover:bg-secondPrimary border
             border-primary hover:border-none mt-10 text-sm text-primary w-36 py-2 px-4 rounded-xl">
            انصراف
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { MagnifyingGlassIcon, ChevronUpIcon, ChevronDownIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useForm } from '@inertiajs/inertia-vue3'
import { ref } from 'vue'
import CustomInput from '../../Components/CustomInput.vue'
import DatePicker from 'vue3-persian-datetime-picker'

defineOptions({
  name: 'SearchLetters'
})

const errors = ref([])
const categorySearch = {
  optionLetter: 'بر اساس اطلاعات نامه',
  optionDepartment: 'بر اساس واحد‌ها',
  optionDate: 'بر اساس تاریخ',
  optionAttach: 'بر اساس پیوست '
}
const inputOptionLetter = ref([
  {
    label: 'ارجاع ‌دهنده',
    type: 'text',
    name: 'referrer'
  },
  {
    label: 'گیرنده',
    type: 'text',
    name: 'receiver'
  },
  {
    label: 'شماره نامه',
    type: 'text',
    name: 'id'
  },
  {
    label: 'موضوع',
    type: 'text',
    name: 'subject'
  }
])

const isOpenLetter = ref([])
const isOpenFileStatus = ref(false)
const isOpenAdminStatus = ref(false)
const isOpenFileType = ref(false)
const isOpenDepartments = ref(false)
const isOpenExtensions = ref(false)

const form = useForm({
  fileStatus: 'all',
  fileType: 'all',
  fileExtension: [],
  departments: [],
  toDate: '',
  fromDate: '',
  referrer: '',
  id: '',
  receiver: '',
  subject: '',
  searchable_text: '',
  adminType: 'all',
  adminIdentifier: ''
})

// const conditionBadge = (i, key) => {
//   switch (key) {
//     case 'optionLetter' :
//       return form.fileType !== 'all' || form.fileStatus !== 'all' || form.fileExtension?.length > 0 || form.fileName
//     case 'optionDepartment' :
//       return form.departments?.length > 0
//     case 'optionDate' :
//       return form.toDate || form.fromDate
//     case 'optionAttach' :
//       return form.toDate || form.fromDate
//   }
// }

const dropdownStates = {
  fileStatus: isOpenFileStatus,
  fileType: isOpenFileType,
  format: isOpenExtensions,
  departments: isOpenDepartments,
  adminStatus: isOpenAdminStatus
}

const toggleDropdown = (selectedDropdown) => {
  for (const dropdown in dropdownStates) {
    if (dropdown === selectedDropdown) {
      dropdownStates[dropdown].value = !dropdownStates[dropdown].value
    } else {
      dropdownStates[dropdown].value = false
    }
  }
}

function deleteDepartments (index) {
  form.departments.splice(index, 1)
}

function subSearch () {
  console.log('search')
}
</script>
