<template>
  <div
    v-if="isOpen"
    class="relative z-10"
    aria-labelledby="modal-title"
    role="dialog">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-20 transition-opacity" />
    <div class="fixed inset-0 z-10 overflow-y-auto">
      <div
        class="flex m-auto  flex-wrap items-center min-h-full items-end
        justify-center p-4 text-center sm:items-center sm:p-0">
        <OnClickOutside
          :class="{'w-[30rem]' : modalSize=== 'small' ,
                   'w-[40rem]' : modalSize=== 'medium',
                   'w-[50rem]' : modalSize=== 'large'}"
          @trigger="close">
          <div
            class="relative transform overflow-visible rounded-2xl bg-white text-left
          shadow-xl transition-all sm:my-8 w-full">
            <div class="bg-white px-4 py-2 w-full!">
              <div class="flex flex-col items-center">
                <div class="bg-white w-full rounded-2xl px-5 py-5">
                  <div
                    v-if="!isDelete"
                    class="flex justify-between py-5">
                    <p
                      class="text-start
                     text-xl text-gray-800 w-full"
                      v-text="title" />
                    <XMarkIcon
                      class="w-6 h-6 cursor-pointer text-gray-800"
                      @click="close" />
                  </div>
                  <div class="flex flex-col justify-start">
                    <slot />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </OnClickOutside>
      </div>
    </div>
  </div>
</template>

<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { OnClickOutside } from '@vueuse/components'

// eslint-disable-next-line no-undef
defineOptions({
  name: 'VerifyModal'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  isOpen: { type: Boolean, default: false },
  title: { type: String, default: null },
  isDelete: { type: Boolean, required: false },
  modalSize: { type: String, default: 'small' } // small , medium , large
})

const emits = defineEmits(['close'])

function close (v) {
  emits('close', v)
}
</script>
