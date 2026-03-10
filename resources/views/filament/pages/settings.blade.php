<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button type="submit">
                Save Settings
            </x-filament::button>

            <x-filament::button color="gray" wire:click="rescanAll">
                Rescan All Projects
            </x-filament::button>

            <x-filament::button
                color="gray"
                tag="a"
                href="http://localhost:5173"
                target="_blank"
            >
                Open Skill Editor
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
