<template>
  <div class="h-full w-full">
    <iframe
      v-if="externalViewerUrl"
      :src="externalViewerUrl"
      class="w-full h-full border-0"
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
