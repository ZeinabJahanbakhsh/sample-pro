<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <div class="mb-4 text-sm text-gray-600 text-center">
            Hello World hellooo
        </div>
        <h2>
            {{$name}}
        </h2>

    </x-auth-card>
</x-guest-layout>
