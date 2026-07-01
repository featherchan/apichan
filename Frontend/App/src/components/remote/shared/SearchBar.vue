<template>
  <input
    v-model="searchQuery"
    @input="handleSearch"
    type="text"
    :placeholder="placeholder"
    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
  />
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  placeholder?: string
  debounce?: number
}>()

const emit = defineEmits<{
  search: [query: string]
}>()

const searchQuery = ref('')
let timeout: ReturnType<typeof setTimeout> | null = null

const handleSearch = () => {
  if (timeout) {
    clearTimeout(timeout)
  }

  timeout = setTimeout(() => {
    emit('search', searchQuery.value)
  }, props.debounce || 300)
}
</script>
