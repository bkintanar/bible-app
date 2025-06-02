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
        <!-- Header -->
        <div class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">🔤 Strong's Concordance</h1>
          <p class="text-gray-600 dark:text-gray-400">Explore Hebrew and Greek words with detailed definitions, etymology, and Biblical usage</p>
        </div>

        <!-- Search Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-8">
          <form @submit.prevent="performSearch" class="space-y-4">
            <div>
              <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Search Strong's Numbers or Words
              </label>
              <div class="relative">
                <input
                  id="search"
                  v-model="searchForm.query"
                  type="text"
                  placeholder="Search by Strong's number (e.g., G2316), Hebrew/Greek word, or English definition..."
                  class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg pl-12 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
                  <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Language</label>
                <select id="language" v-model="searchForm.language" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2">
                  <option value="">All Languages</option>
                  <option v-for="lang in languages" :key="lang" :value="lang">{{ lang }}</option>
                </select>
              </div>
              <div>
                <label for="part-of-speech" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Part of Speech</label>
                <select id="part-of-speech" v-model="searchForm.partOfSpeech" class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2">
                  <option value="">All Parts of Speech</option>
                  <option v-for="pos in partsOfSpeech" :key="pos" :value="pos">{{ pos }}</option>
                </select>
              </div>
              <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                  Search
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Results -->
        <div v-if="searchQuery">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              Search Results for "{{ searchQuery }}"
            </h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">
              {{ lexiconEntries.length }} result{{ lexiconEntries.length !== 1 ? 's' : '' }}
            </span>
          </div>

          <div v-if="lexiconEntries.length > 0" class="space-y-4">
            <div
              v-for="entry in lexiconEntries"
              :key="entry.strongs_number"
              class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-3 mb-3">
                    <Link
                      :href="`/strongs/${entry.strongs_number}`"
                      class="text-lg font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                    >
                      {{ entry.strongs_number }}
                    </Link>
                    <div class="flex items-center gap-2">
                      <span v-if="entry.language" class="px-2 py-1 text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full">
                        {{ entry.language }}
                      </span>
                      <span v-if="entry.part_of_speech" class="px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full">
                        {{ entry.part_of_speech }}
                      </span>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <div v-if="entry.original_word" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                      {{ entry.original_word }}
                      <span v-if="entry.transliteration" class="text-sm font-normal text-gray-600 dark:text-gray-400 ml-2">
                        ({{ entry.transliteration }})
                      </span>
                    </div>
                    <div v-if="entry.short_definition" class="text-gray-700 dark:text-gray-300">
                      {{ entry.short_definition }}
                    </div>
                    <div v-if="entry.occurrence_count" class="text-sm text-gray-500 dark:text-gray-400">
                      Occurs {{ entry.occurrence_count }} time{{ entry.occurrence_count !== 1 ? 's' : '' }} in Scripture
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-12">
            <div class="text-gray-500 dark:text-gray-400">
              <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <h3 class="text-lg font-medium mb-2">No results found</h3>
              <p>Try searching with different keywords or check your spelling.</p>
            </div>
          </div>
        </div>

        <!-- Default State -->
        <div v-else class="text-center py-12">
          <div class="text-gray-500 dark:text-gray-400">
            <svg class="mx-auto h-16 w-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <h3 class="text-xl font-medium mb-2">Search Strong's Concordance</h3>
            <p class="max-w-md mx-auto">
              Search by Strong's number (e.g., G2316, H430), original Hebrew/Greek words, or English definitions.
            </p>
          </div>
        </div>
      </div>
    </div>
  </BibleLayout>
</template>

<script>
import BibleLayout from '@/Layouts/BibleLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

export default {
  components: {
    BibleLayout,
    Link,
  },
  props: {
    lexiconEntries: Array,
    searchQuery: String,
    language: String,
    partOfSpeech: String,
    languages: Array,
    partsOfSpeech: Array,
    limit: Number,
  },
  setup(props) {
    const searchForm = ref({
      query: props.searchQuery || '',
      language: props.language || '',
      partOfSpeech: props.partOfSpeech || '',
    })

    const performSearch = () => {
      const params = new URLSearchParams()
      if (searchForm.value.query) params.append('q', searchForm.value.query)
      if (searchForm.value.language) params.append('language', searchForm.value.language)
      if (searchForm.value.partOfSpeech) params.append('part_of_speech', searchForm.value.partOfSpeech)

      router.visit(`/strongs?${params.toString()}`)
    }

    return {
      searchForm,
      performSearch,
    }
  },
}
</script>
