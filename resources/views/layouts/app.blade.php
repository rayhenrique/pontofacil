<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-gray-100 font-sans antialiased h-screen overflow-hidden">
        @auth
        <div class="flex h-full">
            <!-- Sidebar -->
            <div class="w-64 bg-indigo-800 text-white flex flex-col shadow-lg flex-shrink-0">
                <div class="h-16 flex items-center px-6 font-bold text-xl bg-indigo-900">
                    PontoFácil
                </div>
                
                <div class="flex-1 overflow-y-auto py-4">
                    <nav class="space-y-2 px-3">
                        <a href="{{ route('home') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                            <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            Bater Ponto
                        </a>
                        <a href="{{ route('timesheet') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                            <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                            Espelho de Ponto
                        </a>
                        <a href="{{ route('help') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                            <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                            Ajuda
                        </a>
                        
                        @if(Auth::user()->role === App\Enums\UserRole::Admin)
                            <div class="pt-4 mt-4 border-t border-indigo-700 space-y-2">
                                <p class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-2">Administração (RH)</p>
                                
                                <a href="{{ route('admin.sectors') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                                    <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12.25" /></svg>
                                    Setores
                                </a>
                                
                                <a href="{{ route('admin.employees') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                                    <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                    Funcionários
                                </a>
                                
                                <a href="{{ route('admin.users') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                                    <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                    Usuários do Sistema
                                </a>
                                
                                <a href="{{ route('admin.audit') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                                    <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                    Auditoria
                                </a>
                                
                                <a href="{{ route('admin.reports') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                                    <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                    Relatórios
                                </a>

                                <a href="{{ route('admin.adjustment') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-700 transition">
                                    <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" /></svg>
                                    Ajuste Manual
                                </a>
                            </div>
                        @endif
                    </nav>
                </div>
                
                <!-- Footer sidebar / Logout -->
                <div class="p-4 bg-indigo-900 border-t border-indigo-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center truncate">
                            <span class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="ml-2 flex-shrink-0">
                            @csrf
                            <button type="submit" class="text-xs text-indigo-300 hover:text-white font-medium" title="Sair">
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <main class="flex-1 overflow-y-auto bg-gray-100 p-6">
                    <livewire:version-notifier />
                    {{ $slot }}
                </main>
            </div>
        </div>
        @else
        <!-- Guest Content (Login) -->
        <main class="h-full overflow-y-auto bg-gray-100">
            {{ $slot }}
        </main>
        @endauth

        @livewireScripts
    </body>
</html>
