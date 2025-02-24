<template>
  <div class="min-h-app flex needs-background">
    <div class="m-auto flex gap-20">
      <img src="/images/right-botojeghe.png" alt="" class="h-[600px] my-auto">
      <div class="flex flex-col items-center justify-center">
        <a href="https://habibifoundation.ir">
          <img
              src="/images/habibi.png"
              class="w-24"
              draggable="false"
              alt="Habibi Foundation Image"
          />
        </a>

        <h2 class="text-center text-2xl text-black flex flex-col gap-10">
          <span class="text-center text-black text-4xl">بنیاد فرهنگی امیرکبیر</span>
          <span class="text-center text-xl">
            سامانه هوشمند کتابخانه و مرکز اسناد اهدایی زنده یاد دکتر حبیبی
          </span>
        </h2>

        <div class="w-[31.125rem] m-auto rounded-2xl px-12 py-10">
          <main v-bind="$attrs">
            <slot/>
          </main>
        </div>
      </div>
      <img src="/images/left-botojeghe.png" alt="" class="h-[600px] my-auto">
    </div>
  </div>
</template>

<script setup>
// eslint-disable-next-line no-undef
defineOptions({
  name: '~AppLayoutAuth'
})
</script>

<style lang="scss" scoped>
.needs-background {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;

  &::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('/images/auth-bg.png');
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
    filter: blur(5px);
    opacity: 0.4; // Adjust opacity for faded effect
    z-index: -1;
  }
}
</style>