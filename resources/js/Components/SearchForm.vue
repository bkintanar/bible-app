<template>
  <div class="flex gap-2" :class="containerClass">
    <div class="relative flex-1">
      <input
        v-model="searchQuery"
        @keydown.enter="performSearch"
        type="text"
        :placeholder="placeholder"
        :class="inputClass"
      >
      <div class="absolute left-4 top-1/2 transform -translate-y-1/2 pointer-events-none">
        <svg :class="iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>
    </div>
    <a
      v-if="showButton"
      :href="searchQuery.trim() ? `/search?q=${encodeURIComponent(searchQuery)}` : '#'"
      :class="buttonClass"
    >
      Search
    </a>
  </div>
</template>

<script>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

export default {
  name: 'SearchForm',
  props: {
    initialValue: {
      type: String,
      default: ''
    },
    variant: {
      type: String,
      default: 'default', // 'default', 'header-desktop', 'header-mobile', 'search-page'
      validator: (value) => ['default', 'header-desktop', 'header-mobile', 'search-page'].includes(value)
    },
    placeholder: {
      type: String,
      default: 'Search verses...'
    },
    showButton: {
      type: Boolean,
      default: true
    }
  },
  setup(props) {
    const searchQuery = ref(props.initialValue)

    const performSearch = () => {
      if (searchQuery.value.trim()) {
        router.visit('/search', {
          method: 'get',
          data: { q: searchQuery.value },
        })
      }
    }

    const containerClass = computed(() => {
      return props.variant === 'search-page' ? 'max-w-2xl mx-auto' : ''
    })

    const inputClass = computed(() => {
      const baseClass = "w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400"

      switch (props.variant) {
        case 'header-desktop':
          return `${baseClass} pl-10 pr-3 py-2 text-sm w-64`
        case 'header-mobile':
          return `${baseClass} pl-10 pr-4 py-3`
        case 'search-page':
          return `${baseClass} pl-10 pr-4 py-3`
        default:
          return `${baseClass} pl-10 pr-4 py-2`
      }
    })

    const iconClass = computed(() => {
      switch (props.variant) {
        case 'header-desktop':
          return "h-4 w-4 text-gray-400"
        case 'search-page':
          return "h-5 w-5 text-gray-400"
        default:
          return "h-5 w-5 text-gray-400"
      }
    })

    const buttonClass = computed(() => {
      const baseClass = "bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors whitespace-nowrap touch-friendly"
      const disabledClass = "bg-gray-400 text-white rounded-lg transition-colors whitespace-nowrap touch-friendly cursor-not-allowed"

      const activeClass = searchQuery.value.trim() ? baseClass : disabledClass

      switch (props.variant) {
        case 'header-mobile':
          return `${activeClass} px-4 py-3`
        case 'search-page':
          return `${activeClass} px-4 py-3`
        default:
          return `${activeClass} px-4 py-2`
      }
    })

    return {
      searchQuery,
      performSearch,
      containerClass,
      inputClass,
      iconClass,
      buttonClass
    }
  }
}
</script>
