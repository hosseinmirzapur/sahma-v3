<template>
  <div class="flex flex-col gap-3">
    <label
      class="flex items-center"
      :for="inputName">
      <span
        class="text-gray-900 text-base"
        v-text="inputLabel" />
    </label>

    <div class="relative flex gap-2 items-center justify-normal align-middle">
      <input
        :id="inputName"
        ref="inputRef"
        :type="inputType==='number' ? 'text' : show ? 'text' : inputType"
        :name="inputName"
        :value="modelValue"
        :autocomplete="autocomplete"
        class="rounded-lg border border-gray-300 w-full py-2 px-4 bg-white focus:outline-none focus:bg-transparent
        focus:border-secondPrimary focus:ring-1 disabled:opacity-50"
        :class="{isSearchInput : '!bg-primary/5'}"
        :disabled="disabled"
        required
        @input="handlerModel">
      <div class="grid place-items-center h-full">
        <EyeIcon
          v-if="inputType === 'password' && !show"
          class="hover:bg-blue-100 text-black/80 p-2 rounded-2xl transition-all size-10 cursor-pointer"
          @click="show = !show" />
        <EyeSlashIcon
          v-if="inputType === 'password' && show"
          class="hover:bg-blue-100 text-black/80 p-2 rounded-2xl transition-all size-10 cursor-pointer"
          @click="show = !show" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { digitsArToEn, digitsFaToAr, hasPersian } from '@persian-tools/persian-tools'
import { EyeSlashIcon, EyeIcon } from '@heroicons/vue/24/outline'

// eslint-disable-next-line no-undef
defineOptions({
  name: 'CustomInput'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  inputType: { type: String, required: true },
  inputName: { type: String, required: true },
  inputLabel: { type: String, required: true },
  modelValue: { type: String, required: true },
  disabled: { type: Boolean, default: false },
  isSelectInput: { type: Boolean, default: false },
  isSearchInput: { type: Boolean, default: false },
  autocomplete: { type: String, default: 'off' }
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const emit = defineEmits(['update:modelValue', 'input-ref', 'custom-ref'])
const inputRef = ref(null)

const show = ref(false)

function handlerModel () {
  if (props.inputType === 'number') event.target.value = validNumber(event.target.value)
  if (props.inputType === 'password') event.target.value = validPassword(event.target.value)
  emit('update:modelValue', event.target.value)
}

const validNumber = (value) => (convertNumFarToEng(value.replace(/[^0-9۰-۹٠-٩]/g, '')))

const convertNumFarToEng = (numMix) => (digitsArToEn(digitsFaToAr(numMix)))

const validPassword = (value) => (hasPersian(value) ? '' : value)

</script>
