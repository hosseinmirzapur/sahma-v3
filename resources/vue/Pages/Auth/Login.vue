<template>
  <div class="">
    <p
      v-for="(err, index) in v$.$errors"
      :key="index"
      v-text="err" />
    <form @submit.prevent="submit">
      <div class="flex justify-between items-center w-full p-4">
        <ul>
          <!-- eslint-disable vue/no-v-html -->
          <li
            class="text-right ml-3 text-xs md:text-sm font-medium text-red-500"
            v-html="error" />
          <!-- eslint-enable vue/no-v-html -->
        </ul>
      </div>
      <AuthInput
        v-model="form.username"
        input-label="نام کاربری"
        input-name="username"
        input-type="text"
        maxlength="30" />

      <AuthInput
        v-model="form.password"
        input-label="رمز عبور"
        input-name="password"
        input-type="password"
        :is-show="isShow"
        maxlength="72"
        @toggle="isShow=!isShow" />

      <button
        :disabled="isLoading"
        type="submit"
        class="bg-primary rounded-full w-[100%] h-11  text-white text-lg  hover:opacity-90 mt-8"
        :class="(isLoading===true)? '!opacity-40 cursor-not-allowed' : ''">
        ادامه
      </button>
    </form>
  </div>
</template>

<script setup>
import layout from '../../Layouts/~AppLayoutAuth.vue'
import { ref } from 'vue'
import { useForm } from '@inertiajs/inertia-vue3'
import AuthInput from '../../Pages/Components/AuthInput.vue'
import { useVuelidate } from '@vuelidate/core'
import { required } from '@vuelidate/validators'

// eslint-disable-next-line no-undef
defineOptions({
  name: 'Login',
  layout
})
const error = ref('')
const isLoading = ref(false)
const isShow = ref(true)
const form = useForm({
  username: '',
  password: ''
})

const rules = {
  username: { required },
  password: { required }
}

const v$ = useVuelidate(rules, form)

function restart () {
  form.username = ''
  form.password = ''
}

function submit () {
  if (!v$.value.$pending && !v$.value.$invalid) {
    // eslint-disable-next-line no-undef
    form.post(route('web.user.login'), {
      replace: true,
      preserveScroll: true,
      onStart: () => {
        isLoading.value = true
      },
      onError: (e) => {
        error.value = ''
        for (const arrError in e) {
          if (arrError in e) {
            error.value += `${e[arrError]} <br/> `
          }
        }
      },
      onFinish: () => {
        isLoading.value = false
        restart()
      }
    })
  }
}

</script>
