<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-4 sm:mb-0">
                    📝 Verse Title Manager
                </h1>

                {{-- Version Selector --}}
                @if(count($availableTranslations) > 1)
                    <div class="flex items-center space-x-3">
                        <label for="version" class="text-sm font-medium text-gray-700">
                            📖 Version:
                        </label>
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button
                                @click="open = !open"
                                class="flex items-center justify-between bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors min-w-[100px]"
                            >
                                <span class="font-medium">{{ $currentTranslation['short_name'] ?? 'Select' }}</span>
                                <svg class="w-4 h-4 ml-2 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div
                                x-show="open"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute z-50 mt-1 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-1 right-0"
                                style="display: none;"
                            >
                                @foreach($availableTranslations as $translation)
                                    <button
                                        wire:click="switchTranslation('{{ $translation['key'] }}')"
                                        @click="open = false"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 transition-colors {{ $currentTranslation && $currentTranslation['key'] === $translation['key'] ? 'bg-blue-600 text-white hover:bg-blue-700' : 'text-gray-700' }}"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="font-medium">{{ $translation['short_name'] }}</div>
                                                <div class="text-xs opacity-75">{{ $translation['name'] }}</div>
                                            </div>
                                            @if($currentTranslation && $currentTranslation['key'] === $translation['key'])
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            @endif
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <p class="text-gray-600 mb-8">
                Add custom titles to Bible verses. Select a book, chapter, and verse, then enter a title to be added to the XML file.
                @if($currentTranslation)
                    <br><span class="text-sm text-blue-600">Currently working with: {{ $currentTranslation['name'] }}</span>
                @endif
            </p>

            {{-- Message Display --}}
            @if($message)
                <div class="mb-6 p-4 rounded-lg border {{ $messageType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' }}">
                    <div class="flex items-center">
                        @if($messageType === 'success')
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        @else
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        @endif
                        {{ $message }}
                    </div>
                </div>
            @endif

            <form wire:submit.prevent="saveTitle" class="space-y-6">
                {{-- Cascading Dropdowns --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Book Selection --}}
                    <div>
                        <label for="book" class="block text-sm font-medium text-gray-700 mb-2">
                            📚 Book
                        </label>
                        <select
                            wire:model.live="selectedBook"
                            id="book"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="">Select a book...</option>
                            @foreach($books as $book)
                                <option value="{{ $book->osis_id }}">{{ $book->name }}</option>
                            @endforeach
                        </select>
                        @error('selectedBook')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Chapter Selection --}}
                    <div>
                        <label for="chapter" class="block text-sm font-medium text-gray-700 mb-2">
                            📖 Chapter
                        </label>
                        <select
                            wire:model.live="selectedChapter"
                            id="chapter"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ empty($selectedBook) ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                            {{ empty($selectedBook) ? 'disabled' : '' }}
                        >
                            <option value="">{{ empty($selectedBook) ? 'Select a book first...' : 'Select a chapter...' }}</option>
                            @foreach($chapters as $chapter)
                                <option value="{{ $chapter->chapter_number }}">Chapter {{ $chapter->chapter_number }}</option>
                            @endforeach
                        </select>
                        @error('selectedChapter')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Verse Selection --}}
                    <div>
                        <label for="verse" class="block text-sm font-medium text-gray-700 mb-2">
                            📝 Verse
                        </label>
                        <select
                            wire:model.live="selectedVerse"
                            id="verse"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ empty($selectedChapter) ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                            {{ empty($selectedChapter) ? 'disabled' : '' }}
                        >
                            <option value="">{{ empty($selectedChapter) ? 'Select a chapter first...' : 'Select a verse...' }}</option>
                            @foreach($verses as $verse)
                                <option value="{{ $verse->verse_number }}">Verse {{ $verse->verse_number }}</option>
                            @endforeach
                        </select>
                        @error('selectedVerse')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Selected Reference Display --}}
                @if($selectedBook && $selectedChapter && $selectedVerse)
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-blue-800 mb-2">Selected Reference:</h3>
                        <p class="text-blue-700">
                            {{ $this->getBookName($selectedBook) }} {{ $selectedChapter }}:{{ $selectedVerse }}
                        </p>
                    </div>
                @endif

                {{-- Title Input --}}
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                        ✏️ Verse Title
                    </label>
                    <input
                        type="text"
                        wire:model="verseTitle"
                        id="title"
                        placeholder="Enter a title for this verse..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        maxlength="255"
                    />
                    @error('verseTitle')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-gray-500 text-sm mt-1">
                        This title will be added to the XML as:
                        <code class="bg-gray-100 px-1 rounded">&lt;title type="verse" canonical="true"&gt;Your Title&lt;/title&gt;</code>
                    </p>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-wrap gap-4 pt-4">
                    <button
                        type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        {{ !$selectedBook || !$selectedChapter || !$selectedVerse || !$verseTitle ? 'disabled' : '' }}
                    >
                        💾 Save Title
                    </button>

                    @if($verseTitle && $selectedBook && $selectedChapter && $selectedVerse)
                        <button
                            type="button"
                            wire:click="removeTitle"
                            wire:confirm="Are you sure you want to remove this title?"
                            class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-6 rounded-lg transition duration-200"
                        >
                            🗑️ Remove Title
                        </button>
                    @endif

                    <button
                        type="button"
                        wire:click="$refresh"
                        class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-6 rounded-lg transition duration-200"
                    >
                        🔄 Refresh
                    </button>
                </div>
            </form>

            {{-- Instructions --}}
            <div class="mt-12 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">📋 Instructions</h3>
                <ol class="list-decimal list-inside space-y-2 text-gray-700">
                    <li>Select a book from the dropdown - this will enable the chapter dropdown</li>
                    <li>Choose a chapter - this will enable the verse dropdown</li>
                    <li>Pick a specific verse where you want to add a title</li>
                    <li>Enter your custom title in the text field</li>
                    <li>Click "Save Title" to add it to the XML file</li>
                    <li>The system will automatically create a backup of the original XML file</li>
                </ol>

                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                    <h4 class="font-medium text-yellow-800">⚠️ Important Notes:</h4>
                    <ul class="list-disc list-inside text-sm text-yellow-700 mt-2 space-y-1">
                        <li>This directly modifies XML files - backups are created automatically</li>
                        <li>If a verse already has a title, entering a new one will replace it</li>
                        <li>Use the "Remove Title" button to delete existing verse titles</li>
                        <li>Changes take effect immediately and will be visible in the Bible reader</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
