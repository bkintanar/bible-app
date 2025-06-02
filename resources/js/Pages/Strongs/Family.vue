<template>
  <BibleLayout
    :current-book="null"
    :chapter-number="null"
    :books="[]"
    :testament-books="{}"
    :current-translation="null"
    :available-translations="[]"
    :capabilities="{}"
    :hide-header-search="true"
  >
    <div class="p-4 sm:p-8">
      <div class="max-w-6xl mx-auto">
        <!-- Back Link -->
        <div class="mb-6">
          <Link :href="`/strongs/${strongsNumber}`" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to {{ strongsNumber }}
          </Link>
        </div>

        <!-- Header -->
        <div class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">🌳 Word Family Tree</h1>
          <p class="text-gray-600 dark:text-gray-400">Explore the linguistic relationships for {{ strongsNumber }}</p>
        </div>

        <!-- Root Word -->
        <div v-if="lexicon" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-8">
          <div class="text-center">
            <h2 class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">{{ strongsNumber }}</h2>
            <div class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ lexicon.original_word }}</div>
            <div class="text-gray-600 dark:text-gray-400">{{ lexicon.short_definition }}</div>
          </div>
        </div>

        <!-- Word Family -->
        <div v-if="wordFamily && Object.keys(wordFamily).length > 0" class="space-y-8">
          <!-- Root Words -->
          <div v-if="wordFamily.roots && wordFamily.roots.length > 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
              🌱 Root Words
              <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ wordFamily.roots.length }})</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <Link
                v-for="word in wordFamily.roots"
                :key="word.strongs_number"
                :href="`/strongs/${word.strongs_number}`"
                class="block p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:border-green-300 dark:hover:border-green-700 transition-colors"
              >
                <div class="font-medium text-green-600 dark:text-green-400">{{ word.strongs_number }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ word.original_word }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ word.short_definition }}</div>
              </Link>
            </div>
          </div>

          <!-- Related Words -->
          <div v-if="wordFamily.related && wordFamily.related.length > 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
              🔗 Related Words
              <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ wordFamily.related.length }})</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <Link
                v-for="word in wordFamily.related"
                :key="word.strongs_number"
                :href="`/strongs/${word.strongs_number}`"
                class="block p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:border-blue-300 dark:hover:border-blue-700 transition-colors"
              >
                <div class="font-medium text-blue-600 dark:text-blue-400">{{ word.strongs_number }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ word.original_word }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ word.short_definition }}</div>
              </Link>
            </div>
          </div>

          <!-- Derivatives -->
          <div v-if="wordFamily.derivatives && wordFamily.derivatives.length > 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
              🌿 Derivative Words
              <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ wordFamily.derivatives.length }})</span>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <Link
                v-for="word in wordFamily.derivatives"
                :key="word.strongs_number"
                :href="`/strongs/${word.strongs_number}`"
                class="block p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:border-purple-300 dark:hover:border-purple-700 transition-colors"
              >
                <div class="font-medium text-purple-600 dark:text-purple-400">{{ word.strongs_number }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ word.original_word }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ word.short_definition }}</div>
              </Link>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
          <div class="text-gray-500 dark:text-gray-400">
            <svg class="mx-auto h-16 w-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            <h3 class="text-xl font-medium mb-2">No Family Relationships Found</h3>
            <p class="max-w-md mx-auto">
              No linguistic relationships have been identified for this Strong's number yet.
            </p>
          </div>
        </div>
      </div>
    </div>
  </BibleLayout>
</template>

<script>
import BibleLayout from '@/Layouts/BibleLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
  components: {
    BibleLayout,
    Link,
  },
  props: {
    wordFamily: Object,
    lexicon: Object,
    strongsNumber: String,
  },
}
</script>
