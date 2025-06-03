@props(['availableTranslations' => [], 'currentTranslation' => null, 'position' => 'right'])

@if(!empty($availableTranslations) && count($availableTranslations) > 1)
    <div class="relative" x-data="{ open: false }" @click.away="open = false">
        <!-- Simple Dropdown Button -->
        <button @click="open = !open"
                class="flex items-center justify-between bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors min-w-[80px]">
            <span class="font-medium">{{ $currentTranslation['short_name'] ?? 'Select' }}</span>
            <svg class="w-4 h-4 ml-2 transition-transform duration-200"
                 :class="{ 'rotate-180': open }"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <!-- Simple Dropdown Menu -->
        <div x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="absolute z-50 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 py-1 {{ $position === 'left' ? 'right-0' : 'left-0' }}"
             style="display: none;">

            @foreach($availableTranslations as $translation)
                <button wire:click="switchTranslation('{{ $translation['key'] }}')"
                        @click="open = false"
                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors {{ $currentTranslation && $currentTranslation['key'] === $translation['key'] ? 'bg-blue-600 dark:bg-blue-600 text-white dark:text-white' : 'text-gray-700 dark:text-gray-200' }}">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ $translation['short_name'] }}</span>
                        @if($currentTranslation && $currentTranslation['key'] === $translation['key'])
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </div>
@elseif($currentTranslation)
    <!-- Single translation display -->
    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $currentTranslation['short_name'] }}</span>
    </div>
@endif
