<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Section --}}
        <div class="rounded-xl bg-gradient-to-br from-primary-50 to-primary-100 dark:from-primary-950 dark:to-primary-900 p-6 border border-primary-200 dark:border-primary-800">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-primary-500 dark:bg-primary-600">
                        <x-heroicon-o-sparkles class="w-7 h-7 text-white"/>
                    </div>
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-primary-900 dark:text-primary-100">
                        Model & Resource Builder
                    </h2>
                    <p class="mt-1 text-sm text-primary-700 dark:text-primary-300">
                        Generate complete models, migrations, Filament resources, factories and seeders with just a few clicks. Define your fields and let the builder handle the rest with automatic sample data generation.
                    </p>
                </div>
            </div>
        </div>

        {{-- Form Section --}}
        <form wire:submit="generate" class="space-y-6">
            {{ $this->form }}

            {{-- Action Buttons --}}
            <div class="flex items-center justify-between gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <x-heroicon-o-information-circle class="w-5 h-5"/>
                    <span>All changes will be applied immediately</span>
                </div>
                
                <div class="flex items-center gap-3">
                    <x-filament::button
                        color="gray"
                        outlined
                        size="lg"
                        tag="a"
                        href="{{ route('filament.admin.pages.dashboard') }}"
                        wire:loading.attr="disabled"
                    >
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-arrow-left class="w-4 h-4"/>
                            <span>Cancel</span>
                        </span>
                    </x-filament::button>

                    <x-filament::button 
                        type="submit"
                        size="lg"
                        wire:loading.attr="disabled"
                        class="min-w-[140px]"
                    >
                        <span class="flex items-center gap-2">
                            <x-filament::loading-indicator class="h-5 w-5" wire:loading wire:target="generate"/>
                            <x-heroicon-s-sparkles class="w-5 h-5" wire:loading.remove wire:target="generate"/>
                            <span class="font-semibold" wire:loading.remove wire:target="generate">Build</span>
                            <span class="font-semibold" wire:loading wire:target="generate">Building...</span>
                        </span>
                    </x-filament::button>
                </div>
            </div>
        </form>
</x-filament-panels::page>

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', function() {
        Livewire.on('redirect-to-dashboard', function() {
            setTimeout(function() {
                window.location.href = '{{ route("filament.admin.pages.dashboard") }}';
            }, 2000);
        });
    });
</script>
@endpush