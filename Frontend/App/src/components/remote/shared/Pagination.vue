<template>
  <div class="flex items-center justify-between p-3 bg-gray-800 rounded-lg">
    <span class="text-sm text-gray-300">
      Page {{ currentPage }} of {{ totalPages }} ({{ total }} total)
    </span>
    <div class="flex gap-2">
      <button
        @click="previousPage"
        :disabled="!hasPreviousPage"
        class="px-3 py-1 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed rounded transition-colors"
      >
        Previous
      </button>
      <button
        @click="nextPage"
        :disabled="!hasNextPage"
        class="px-3 py-1 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed rounded transition-colors"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  currentPage: number
  totalPages: number
  total: number
  hasPreviousPage: boolean
  hasNextPage: boolean
}>()

const emit = defineEmits<{
  previousPage: []
  nextPage: []
}>()

const previousPage = () => emit('previousPage')
const nextPage = () => emit('nextPage')
</script>
