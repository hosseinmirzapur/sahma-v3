<template>
  <div class="min-h-screen w-full">
    <!-- Display filename -->
    <div v-if="fileName" class="text-center text-sm text-gray-600 mb-2 p-2">
      {{ fileName }}.{{ fileExtension }}
    </div>

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
  // Add new props for filename and extension
  fileName: {
    type: String,
    default: "",
  },
  fileExtension: {
    type: String,
    default: "",
  },
});
</script>
