<template>
  <div class="">
    <label
      :for="inputName"
      class="text-lg"
      v-text="inputLabel" />
    <div class="relative">
      <input
        :id="inputName"
        :type="isShow ? 'password' : 'text'"
        :name="inputName"
        :value="modelValue"
        :maxlength="maxlength"
        class="tracking-wider text-center text-md block px-3 py-2  rounded-lg w-full placeholder-gray-300 my-3
                     border-gray-300 focus:outline-none focus:bg-transparent focus:border-primary focus:ring-0"
        @input="handlerModel">
      <div
        v-if="inputType==='password'"
        class="absolute inset-y-0 h-10 w-10 left-0 pl-3 flex items-center text-sm leading-5">
        <EyeIcon
          class="w-6 h-6"
          :class="{ 'hidden': !isShow, 'block': isShow }"
          @click="toggleShowPass" />

        <EyeSlashIcon
          class="w-6 h-6"
          :class="{ 'block': !isShow, 'hidden': isShow }"
          @click="toggleShowPass" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
// eslint-disable-next-line no-undef
defineOptions({
  name: 'AuthInput'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  inputType: { type: String, required: true },
  inputName: { type: String, required: true },
  inputLabel: { type: String, required: true },
  isShow: { type: Boolean, required: false },
  modelValue: { type: String, required: true },
  maxlength: { type: Number, required: true }
})

const emit = defineEmits(['update:modelValue', 'toggle'])
function handlerModel () {
  emit('update:modelValue', event.target.value)
}
function toggleShowPass () {
  emit('toggle', props.isShow)
}
</script>
