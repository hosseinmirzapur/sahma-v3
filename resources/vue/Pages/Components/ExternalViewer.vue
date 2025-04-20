<template>
  <div class="min-h-screen w-full">
    <iframe
      v-if="externalViewerUrl"
      :src="externalViewerUrl"
      class="w-full min-h-screen border-0"
      sandbox="allow-scripts allow-same-origin allow-popups allow-forms"
    />
    <div v-else class="flex items-center justify-center h-full">
      <p>این فایل قابل نمایش نیست</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  externalViewerUrl: {
    type: String,
    required: true,
  },
});
</script>
