<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>
            @media print {
                .no-print, header, nav, aside, footer, [role="dialog"], button.no-print {
                    display: none !important;
                }
                body, html {
                    background: #ffffff !important;
                    color: #000000 !important;
                }
                div[class*="md:pl-"], div[class*="pl-"] {
                    padding-left: 0 !important;
                }
                main {
                    padding: 0 !important;
                    margin: 0 !important;
                }
            }
        </style>
    </head>
    <body class="h-full font-sans antialiased text-gray-900 bg-gray-100" 
          x-data="{ 
              mobileMenuOpen: false,
              sidebarCollapsed: localStorage.getItem('pf_sidebar_collapsed') === 'true',
              toggleSidebar() {
                  this.sidebarCollapsed = !this.sidebarCollapsed;
                  localStorage.setItem('pf_sidebar_collapsed', this.sidebarCollapsed);
              }
          }">
        @auth
        <div class="min-h-full">
            <!-- Mobile Off-Canvas Drawer (Slide-over menu) -->
            <div x-show="mobileMenuOpen" class="no-print relative z-50 md:hidden" role="dialog" aria-modal="true" style="display: none;">
                <!-- Backdrop -->
                <div x-show="mobileMenuOpen" 
                     x-transition:enter="transition-opacity ease-linear duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-linear duration-300"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="mobileMenuOpen = false"
                     class="fixed inset-0 bg-gray-900/80 backdrop-blur-xs"></div>

                <!-- Slide-over Panel -->
                <div class="fixed inset-0 flex">
                    <div x-show="mobileMenuOpen"
                         x-transition:enter="transition ease-in-out duration-300 transform"
                         x-transition:enter-start="-translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transition ease-in-out duration-300 transform"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="-translate-x-full"
                         class="relative mr-16 flex w-full max-w-xs flex-1 flex-col bg-indigo-900 text-white shadow-2xl">
                        
                        <!-- Close button -->
                        <div class="flex h-16 items-center justify-between px-6 bg-indigo-950">
                            <span class="text-xl font-bold tracking-tight text-white flex items-center gap-2">
                                <svg class="h-6 w-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                PontoFácil
                            </span>
                            <button @click="mobileMenuOpen = false" type="button" class="-m-2.5 p-2.5 text-indigo-200 hover:text-white">
                                <span class="sr-only">Fechar menu</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <!-- Mobile Nav Links -->
                        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
                            <a href="{{ route('home') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                Bater Ponto
                            </a>
                            <a href="{{ route('timesheet') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                Espelho de Ponto
                            </a>
                            <a href="{{ route('folha-ponto') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                Folha de Ponto
                            </a>
                            <a href="{{ route('receipts.center') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                                Comprovantes
                            </a>
                            <a href="{{ route('help') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                                Ajuda e Versões
                            </a>

                            @if(Auth::user()->role === App\Enums\UserRole::Admin)
                                <div class="pt-4 mt-4 border-t border-indigo-800 space-y-1">
                                    <p class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-2">Administração (RH)</p>
                                    
                                    <a href="{{ route('admin.sectors') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12.25" /></svg>
                                        Setores
                                    </a>
                                    <a href="{{ route('admin.employees') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                        Funcionários
                                    </a>
                                    <a href="{{ route('admin.users') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                        Usuários do Sistema
                                    </a>
                                    <a href="{{ route('admin.audit') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                        Auditoria
                                    </a>
                                    <a href="{{ route('admin.reports') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                        Relatórios
                                    </a>
                                    <a href="{{ route('admin.time-bank') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                        Banco de Horas
                                    </a>
                                    <a href="{{ route('admin.fiscalizacao') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                        Fiscalização MTE
                                    </a>
                                    <a href="{{ route('admin.treatment-requests') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                                        Tratamento de Ponto
                                    </a>
                                    <a href="{{ route('admin.adjustment') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" /></svg>
                                        Ajuste Manual
                                    </a>
                                    <a href="{{ route('admin.calendar') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" /></svg>
                                        Calendário Laboral
                                    </a>
                                    <a href="{{ route('admin.settings') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                        Configurações
                                    </a>
                                </div>
                            @elseif(Auth::user()->role === App\Enums\UserRole::Manager)
                                <div class="pt-4 mt-4 border-t border-indigo-800 space-y-1">
                                    <p class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-2">Gestão de Equipe</p>
                                    
                                    <a href="{{ route('admin.employees') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                        Funcionários
                                    </a>
                                </div>
                            @elseif(Auth::user()->role === App\Enums\UserRole::Auditor)
                                <div class="pt-4 mt-4 border-t border-indigo-800 space-y-1">
                                    <p class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-2">Fiscalização Trabalhista</p>
                                    
                                    <a href="{{ route('admin.fiscalizacao') }}" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-3 rounded-lg text-base font-medium text-indigo-100 hover:bg-indigo-800 hover:text-white transition">
                                        <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                        Fiscalização MTE
                                    </a>
                                </div>
                            @endif
                        </div>

                        <!-- Drawer Footer (User & Logout) -->
                        <div class="p-4 bg-indigo-950 border-t border-indigo-800">
                            <div class="flex items-center justify-between">
                                <div class="truncate">
                                    <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                                    <p class="text-xs text-indigo-300 truncate">{{ Auth::user()->email }} • {{ Auth::user()->role->label() }}</p>
                                </div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-red-200 bg-red-900/60 rounded-md hover:bg-red-800 transition">
                                        Sair
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop & Tablet Sidebar (md:flex) -->
            <aside class="no-print hidden md:fixed md:inset-y-0 md:flex md:flex-col shadow-xl z-20 transition-all duration-300"
                   :class="sidebarCollapsed ? 'md:w-20' : 'md:w-64'">
                <div class="flex flex-col flex-grow bg-indigo-900 overflow-y-auto">
                    <!-- Brand & Toggle Button -->
                    <div class="flex items-center h-16 flex-shrink-0 px-4 bg-indigo-950 text-white font-bold text-xl justify-between transition-all">
                        <a href="{{ route('home') }}" class="flex items-center gap-2 overflow-hidden">
                            <svg class="h-6 w-6 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">PontoFácil</span>
                        </a>
                        <!-- Botão de Toggle do Menu (Otimizado para Tablets) -->
                        <button @click="toggleSidebar()" 
                                type="button" 
                                class="p-1.5 rounded-lg text-indigo-300 hover:text-white hover:bg-indigo-900/80 transition cursor-pointer"
                                :title="sidebarCollapsed ? 'Expandir menu lateral' : 'Recolher menu lateral (modo tablet)'">
                            <svg x-show="!sidebarCollapsed" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                            </svg>
                            <svg x-show="sidebarCollapsed" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>

                    <!-- Desktop Nav -->
                    <div class="flex-grow flex flex-col justify-between">
                        <nav class="flex-1 px-3 py-4 space-y-1">
                            <a href="{{ route('home') }}" :title="sidebarCollapsed ? 'Bater Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2.5 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Bater Ponto</span>
                            </a>
                            <a href="{{ route('timesheet') }}" :title="sidebarCollapsed ? 'Espelho de Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2.5 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Espelho de Ponto</span>
                            </a>
                            <a href="{{ route('folha-ponto') }}" :title="sidebarCollapsed ? 'Folha de Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2.5 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Folha de Ponto</span>
                            </a>
                            <a href="{{ route('receipts.center') }}" :title="sidebarCollapsed ? 'Comprovantes' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2.5 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Comprovantes</span>
                            </a>
                            <a href="{{ route('help') }}" :title="sidebarCollapsed ? 'Ajuda e Versões' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2.5 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Ajuda e Versões</span>
                            </a>

                            @if(Auth::user()->role === App\Enums\UserRole::Admin)
                                <div class="pt-4 mt-4 border-t border-indigo-800 space-y-1">
                                    <p x-show="!sidebarCollapsed" class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-2">Administração (RH)</p>
                                    <div x-show="sidebarCollapsed" class="border-t border-indigo-800 my-2"></div>
                                    
                                    <a href="{{ route('admin.sectors') }}" :title="sidebarCollapsed ? 'Setores' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12.25" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Setores</span>
                                    </a>
                                    <a href="{{ route('admin.employees') }}" :title="sidebarCollapsed ? 'Funcionários' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Funcionários</span>
                                    </a>
                                    <a href="{{ route('admin.users') }}" :title="sidebarCollapsed ? 'Usuários do Sistema' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Usuários do Sistema</span>
                                    </a>
                                    <a href="{{ route('admin.audit') }}" :title="sidebarCollapsed ? 'Auditoria' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Auditoria</span>
                                    </a>
                                    <a href="{{ route('admin.reports') }}" :title="sidebarCollapsed ? 'Relatórios' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Relatórios</span>
                                    </a>
                                    <a href="{{ route('admin.time-bank') }}" :title="sidebarCollapsed ? 'Banco de Horas' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Banco de Horas</span>
                                    </a>
                                    <a href="{{ route('admin.fiscalizacao') }}" :title="sidebarCollapsed ? 'Fiscalização MTE' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Fiscalização MTE</span>
                                    </a>
                                    <a href="{{ route('admin.treatment-requests') }}" :title="sidebarCollapsed ? 'Tratamento de Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Tratamento de Ponto</span>
                                    </a>
                                    <a href="{{ route('admin.adjustment') }}" :title="sidebarCollapsed ? 'Ajuste Manual' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Ajuste Manual</span>
                                    </a>
                                    <a href="{{ route('admin.calendar') }}" :title="sidebarCollapsed ? 'Calendário Laboral' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Calendário Laboral</span>
                                    </a>
                                    <a href="{{ route('admin.settings') }}" :title="sidebarCollapsed ? 'Configurações' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Configurações</span>
                                    </a>
                                </div>
                            @elseif(Auth::user()->role === App\Enums\UserRole::Manager)
                                <div class="pt-4 mt-4 border-t border-indigo-800 space-y-1">
                                    <p x-show="!sidebarCollapsed" class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-2">Gestão de Equipe</p>
                                    <div x-show="sidebarCollapsed" class="border-t border-indigo-800 my-2"></div>
                                    
                                    <a href="{{ route('admin.employees') }}" :title="sidebarCollapsed ? 'Funcionários' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Funcionários</span>
                                    </a>
                                </div>
                            @elseif(Auth::user()->role === App\Enums\UserRole::Auditor)
                                <div class="pt-4 mt-4 border-t border-indigo-800 space-y-1">
                                    <p x-show="!sidebarCollapsed" class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-2">Fiscalização Trabalhista</p>
                                    <div x-show="sidebarCollapsed" class="border-t border-indigo-800 my-2"></div>
                                    
                                    <a href="{{ route('admin.fiscalizacao') }}" :title="sidebarCollapsed ? 'Fiscalização MTE' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="flex items-center py-2 rounded-lg text-sm font-medium text-indigo-100 hover:text-white hover:bg-indigo-800 transition">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Fiscalização MTE</span>
                                    </a>
                                </div>
                            @endif
                        </nav>

                        <!-- Desktop Sidebar Footer -->
                        <div class="p-3 bg-indigo-950 border-t border-indigo-800">
                            <div class="flex items-center" :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
                                <div x-show="!sidebarCollapsed" class="truncate">
                                    <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                                    <p class="text-xs text-indigo-300 truncate">{{ Auth::user()->role->label() }}</p>
                                </div>
                                <form method="POST" action="{{ route('logout') }}" :class="sidebarCollapsed ? '' : 'ml-2'" class="flex-shrink-0">
                                    @csrf
                                    <button type="submit" class="text-xs text-indigo-300 hover:text-white font-medium p-1 cursor-pointer" title="Sair do sistema">
                                        <svg x-show="sidebarCollapsed" class="w-5 h-5 text-red-300 hover:text-red-100" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                        </svg>
                                        <span x-show="!sidebarCollapsed">Sair</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Content Area (with responsive padding for sidebar) -->
            <div class="flex flex-col flex-1 min-h-screen transition-all duration-300"
                 :class="sidebarCollapsed ? 'md:pl-20' : 'md:pl-64'">
                <!-- Mobile Top Navigation Header -->
                <header class="no-print sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-indigo-800 bg-indigo-900 px-4 text-white shadow-sm md:hidden">
                    <div class="flex items-center gap-3">
                        <button @click="mobileMenuOpen = true" type="button" class="-m-2.5 p-2.5 text-indigo-200 hover:text-white focus:outline-none cursor-pointer" aria-label="Abrir menu lateral">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </button>
                        <span class="text-lg font-bold tracking-tight text-white flex items-center gap-1.5">
                            <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            PontoFácil
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="text-xs text-indigo-200 font-medium max-w-[120px] truncate">{{ Auth::user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-xs bg-indigo-950 text-indigo-200 hover:text-white px-2.5 py-1 rounded border border-indigo-700 cursor-pointer">
                                Sair
                            </button>
                        </form>
                    </div>
                </header>

                <!-- Page Main Content -->
                <main class="flex-1 p-3 sm:p-6 lg:p-8 pb-24 md:pb-8">
                    <div class="no-print">
                        <livewire:version-notifier />
                        <x-modal-feedback />
                    </div>
                    {{ $slot }}
                </main>

                <!-- Mobile Bottom Tab Bar (App-like navigation for smartphones) -->
                <nav class="no-print md:hidden fixed bottom-0 left-0 right-0 z-30 bg-white border-t border-gray-200 shadow-lg flex items-center justify-around py-2 px-2">
                    <a href="{{ route('home') }}" class="flex flex-col items-center justify-center flex-1 py-1 text-xs font-medium {{ request()->routeIs('home') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-900' }}">
                        <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        Ponto
                    </a>
                    <a href="{{ route('timesheet') }}" class="flex flex-col items-center justify-center flex-1 py-1 text-xs font-medium {{ request()->routeIs('timesheet') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-900' }}">
                        <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                        Espelho
                    </a>
                    <a href="{{ route('help') }}" class="flex flex-col items-center justify-center flex-1 py-1 text-xs font-medium {{ request()->routeIs('help') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-900' }}">
                        <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                        Ajuda
                    </a>
                    <button @click="mobileMenuOpen = true" type="button" class="flex flex-col items-center justify-center flex-1 py-1 text-xs font-medium text-gray-500 hover:text-gray-900">
                        <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        Menu
                    </button>
                </nav>
            </div>
        </div>
        @else
        <!-- Guest Content (Login) -->
        <main class="min-h-screen bg-gray-50 flex flex-col justify-center">
            {{ $slot }}
        </main>
        @endauth

        @livewireScripts
    </body>
</html>

