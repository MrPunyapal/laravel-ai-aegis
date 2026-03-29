<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="AI Aegis Security" title="Real-time AI security telemetry">
        <x-slot:icon>
            <x-pulse::icons.shield />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        <div class="grid grid-cols-3 gap-3 text-gray-900 dark:text-gray-100">
            <div class="flex flex-col items-center justify-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <span class="text-2xl font-bold text-red-600 dark:text-red-400">
                    {{ number_format($blockedInjections->count()) }}
                </span>
                <span class="text-xs text-red-500 dark:text-red-400 mt-1">
                    Blocked Injections
                </span>
            </div>

            <div class="flex flex-col items-center justify-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ number_format($pseudonymizationVolume->count()) }}
                </span>
                <span class="text-xs text-blue-500 dark:text-blue-400 mt-1">
                    PII Tokens Replaced
                </span>
            </div>

            <div class="flex flex-col items-center justify-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                    ${{ number_format($computeSaved->sum(), 2) }}
                </span>
                <span class="text-xs text-green-500 dark:text-green-400 mt-1">
                    Compute Capital Saved
                </span>
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
