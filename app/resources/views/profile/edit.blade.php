<x-app-layout title="Perfil">
    <div class="max-w-lg mx-auto">
        <x-page-header title="Perfil" :back="route('settings')" />

        <div class="space-y-4">
            <x-card class="p-6">
                @include('profile.partials.update-profile-information-form')
            </x-card>

            <x-card class="p-6">
                @include('profile.partials.update-password-form')
            </x-card>

            <x-card class="p-6 !border-red-200 dark:!border-red-500/30">
                @include('profile.partials.delete-user-form')
            </x-card>
        </div>
    </div>
</x-app-layout>
