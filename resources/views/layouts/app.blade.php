<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-100 overflow-x-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>
            [x-cloak] {
                display: none !important;
            }
            @media print {
                .no-print, header, nav, aside, footer, [role="dialog"], button.no-print, [aria-modal="true"] {
                    display: none !important;
                }
                body, html {
                    background: #ffffff !important;
                    color: #000000 !important;
                    height: auto !important;
                    min-height: auto !important;
                    overflow: visible !important;
                }
                div[class*="md:pl-"], div[class*="pl-"] {
                    padding-left: 0 !important;
                    height: auto !important;
                    min-height: auto !important;
                    overflow: visible !important;
                    display: block !important;
                }
                main {
                    padding: 0 !important;
                    margin: 0 !important;
                    height: auto !important;
                    min-height: auto !important;
                    overflow: visible !important;
                    display: block !important;
                }
                .min-h-full {
                    min-height: auto !important;
                    height: auto !important;
                    overflow: visible !important;
                    display: block !important;
                }
            }
        </style>
    </head>
    @php
        $isAdvancedRouteActive = request()->routeIs([
            'admin.sectors*',
            'admin.calendar*',
            'admin.time-bank*',
            'admin.settlement-policies*',
            'admin.adjustment*',
            'admin.fiscalizacao*',
            'admin.audit*',
            'admin.users*',
            'admin.settings*',
            'admin.export-afd*',
        ]);
    @endphp
    <body class="h-full font-sans antialiased text-gray-900 bg-gray-100 overflow-x-hidden" 
          x-data="{ 
              mobileMenuOpen: false,
              sidebarCollapsed: localStorage.getItem('pf_sidebar_collapsed') === 'true',
              advancedOpen: {!! $isAdvancedRouteActive ? 'true' : "localStorage.getItem('pf_advanced_menu_open') === 'true'" !!},
              toggleSidebar() {
                  this.sidebarCollapsed = !this.sidebarCollapsed;
                  localStorage.setItem('pf_sidebar_collapsed', this.sidebarCollapsed);
              },
              toggleAdvanced() {
                  this.advancedOpen = !this.advancedOpen;
                  localStorage.setItem('pf_advanced_menu_open', this.advancedOpen);
              }
          }">
        @auth
        @php
            $currentUser = Auth::user();
            $isAdmin = $currentUser->role === App\Enums\UserRole::Admin;
            $isManager = $currentUser->role === App\Enums\UserRole::Manager;
            $isAuditor = $currentUser->role === App\Enums\UserRole::Auditor;
            $isStaff = $isAdmin || $isManager;
        @endphp
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
                            <button @click="mobileMenuOpen = false" type="button" class="-m-2.5 min-h-[44px] min-w-[44px] flex items-center justify-center p-2.5 text-indigo-200 hover:text-white">
                                <span class="sr-only">Fechar menu</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <!-- Mobile Nav Links -->
                        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-1">
                            @if($isStaff)
                                <!-- ROTINA OPERACIONAL DO RH / GESTOR (PRIORIDADE MÁXIMA) -->
                                <p class="px-3 text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-2">Rotina Operacional</p>

                                <a href="{{ route('admin.monthly-review') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition {{ request()->routeIs('admin.monthly-review*') ? 'bg-indigo-800 text-white shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Conferência Mensal
                                </a>
                                <a href="{{ route('admin.employees') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.employees*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                    Servidores
                                </a>
                                <a href="{{ route('admin.shifts') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.shifts*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    Horários & Escalas
                                </a>
                                <a href="{{ route('admin.treatment-requests') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.treatment-requests*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                                    Solicitações
                                </a>
                                <a href="{{ route('admin.reports') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.reports*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                    Relatórios de Frequência
                                </a>

                                <!-- MEU REGISTRO PESSOAL -->
                                <div class="pt-3 mt-3 border-t border-indigo-800/80 space-y-1">
                                    <p class="px-3 text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-1">Meu Registro Pessoal</p>
                                    <a href="{{ route('home') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('home') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                        Bater Ponto
                                    </a>
                                    <a href="{{ route('timesheet') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('timesheet') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                        Espelho de Ponto
                                    </a>
                                    <a href="{{ route('receipts.center') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('receipts.*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                        <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                                        Comprovantes
                                    </a>
                                </div>

                                @if($isAdmin)
                                    <!-- CONFIGURAÇÕES & APOIO (ÁREA SECUNDÁRIA EXPANSÍVEL) -->
                                    <div class="pt-3 mt-3 border-t border-indigo-800/80">
                                        <button @click="toggleAdvanced()" 
                                                id="advanced-menu-mobile-btn"
                                                type="button" 
                                                aria-controls="advanced-menu-mobile"
                                                aria-expanded="{{ $isAdvancedRouteActive ? 'true' : 'false' }}"
                                                :aria-expanded="advancedOpen ? 'true' : 'false'"
                                                class="w-full min-h-[44px] flex items-center justify-between px-3 py-2 text-[11px] font-bold uppercase tracking-wider transition rounded-lg {{ $isAdvancedRouteActive ? 'text-indigo-200 bg-indigo-950/40' : 'text-indigo-300 hover:text-white' }}">
                                            <span class="flex items-center gap-2 truncate">
                                                @if($isAdvancedRouteActive)
                                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-400 shrink-0" aria-hidden="true"></span>
                                                @endif
                                                <span>Configurações & Apoio</span>
                                            </span>
                                            <svg class="h-4 w-4 transform transition-transform shrink-0" :class="advancedOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                        </button>
                                        
                                        <div id="advanced-menu-mobile" 
                                             x-show="advancedOpen" 
                                             x-cloak 
                                             class="space-y-1 mt-1 pl-1">
                                            <a href="{{ route('admin.sectors') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.sectors*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                                Setores
                                            </a>
                                            <a href="{{ route('admin.calendar') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.calendar*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                Calendário Laboral
                                            </a>
                                            <a href="{{ route('admin.time-bank') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.time-bank*') || request()->routeIs('admin.settlement-policies*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                Banco de Horas
                                            </a>
                                            <a href="{{ route('admin.adjustment') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.adjustment*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Ajuste Manual
                                            </a>
                                            <a href="{{ route('admin.fiscalizacao') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.fiscalizacao*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                                Fiscalização MTE
                                            </a>
                                            <a href="{{ route('admin.audit') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.audit*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                                Auditoria
                                            </a>
                                            <a href="{{ route('admin.users') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.users*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                                Usuários do Sistema
                                            </a>
                                            <a href="{{ route('admin.settings') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.settings*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /></svg>
                                                Configurações
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @elseif($isAuditor)
                                <!-- PERFIL DE AUDITORIA FISCAL -->
                                <p class="px-3 text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-2">Fiscalização Trabalhista</p>
                                <a href="{{ route('admin.fiscalizacao') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.fiscalizacao*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                    Fiscalização MTE
                                </a>
                                <a href="{{ route('admin.audit') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.audit*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                    Auditoria
                                </a>
                                <a href="{{ route('admin.reports') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.reports*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                    Relatórios de Frequência
                                </a>
                            @else
                                <!-- ÁREA EXCLUSIVA DO COLABORADOR -->
                                <p class="px-3 text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-2">Menu Principal</p>
                                
                                <a href="{{ route('home') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('home') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    Bater Ponto
                                </a>
                                <a href="{{ route('timesheet') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('timesheet') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                    Espelho de Ponto
                                </a>
                                <a href="{{ route('folha-ponto') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('folha-ponto') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                    Folha de Ponto
                                </a>
                                <a href="{{ route('receipts.center') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('receipts.*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                                    Comprovantes
                                </a>
                            @endif

                            <div class="pt-3 mt-3 border-t border-indigo-800/80">
                                <a href="{{ route('help') }}" @click="mobileMenuOpen = false" class="min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('help') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-200 hover:bg-indigo-800/60 hover:text-white' }}">
                                    <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                                    Ajuda e Suporte
                                </a>
                            </div>
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
                                    <button type="submit" class="min-h-[44px] px-3.5 py-2 text-xs font-semibold text-red-200 bg-red-900/60 rounded-lg hover:bg-red-800 transition cursor-pointer">
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
                                class="p-1.5 min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg text-indigo-300 hover:text-white hover:bg-indigo-900/80 transition cursor-pointer"
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
                            @if($isStaff)
                                <!-- ROTINA OPERACIONAL DO RH / GESTOR (PRIORIDADE MÁXIMA) -->
                                <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-1.5">Rotina Operacional</p>

                                <a href="{{ route('admin.monthly-review') }}" :title="sidebarCollapsed ? 'Conferência Mensal' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-semibold transition {{ request()->routeIs('admin.monthly-review*') ? 'bg-indigo-800 text-white shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Conferência Mensal</span>
                                </a>
                                <a href="{{ route('admin.employees') }}" :title="sidebarCollapsed ? 'Servidores' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.employees*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Servidores</span>
                                </a>
                                <a href="{{ route('admin.shifts') }}" :title="sidebarCollapsed ? 'Horários & Escalas' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.shifts*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Horários & Escalas</span>
                                </a>
                                <a href="{{ route('admin.treatment-requests') }}" :title="sidebarCollapsed ? 'Solicitações' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.treatment-requests*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Solicitações</span>
                                </a>
                                <a href="{{ route('admin.reports') }}" :title="sidebarCollapsed ? 'Relatórios de Frequência' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.reports*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Relatórios</span>
                                </a>

                                <!-- MEU REGISTRO PESSOAL -->
                                <div class="pt-3 mt-3 border-t border-indigo-800/80 space-y-1">
                                    <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-1">Registro Pessoal</p>
                                    <div x-show="sidebarCollapsed" class="border-t border-indigo-800/80 my-2"></div>

                                    <a href="{{ route('home') }}" :title="sidebarCollapsed ? 'Bater Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('home') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Bater Ponto</span>
                                    </a>
                                    <a href="{{ route('timesheet') }}" :title="sidebarCollapsed ? 'Espelho de Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('timesheet') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Espelho de Ponto</span>
                                    </a>
                                    <a href="{{ route('receipts.center') }}" :title="sidebarCollapsed ? 'Comprovantes' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('receipts.*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                        <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                                        <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Comprovantes</span>
                                    </a>
                                </div>

                                @if($isAdmin)
                                    <!-- CONFIGURAÇÕES & APOIO (ÁREA SECUNDÁRIA EXPANSÍVEL) -->
                                    <div class="pt-3 mt-3 border-t border-indigo-800/80">
                                        <button @click="toggleAdvanced()" 
                                                id="advanced-menu-desktop-btn"
                                                type="button" 
                                                aria-controls="advanced-menu-desktop"
                                                aria-expanded="{{ $isAdvancedRouteActive ? 'true' : 'false' }}"
                                                :aria-expanded="advancedOpen ? 'true' : 'false'"
                                                :title="sidebarCollapsed ? 'Configurações & Apoio' : ''"
                                                :class="sidebarCollapsed ? 'justify-center px-1' : 'justify-between px-3'" 
                                                class="w-full min-h-[44px] flex items-center py-1.5 text-[11px] font-bold uppercase tracking-wider transition cursor-pointer rounded-lg {{ $isAdvancedRouteActive ? 'text-indigo-200 bg-indigo-950/40' : 'text-indigo-300 hover:text-white' }}">
                                            <span x-show="!sidebarCollapsed" class="flex items-center gap-2 truncate">
                                                @if($isAdvancedRouteActive)
                                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-400 shrink-0" aria-hidden="true"></span>
                                                @endif
                                                <span>Configurações & Apoio</span>
                                            </span>
                                            <svg class="h-4 w-4 transform transition-transform shrink-0" :class="advancedOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                        </button>
                                        
                                        <div id="advanced-menu-desktop" 
                                             x-show="advancedOpen" 
                                             x-cloak 
                                             class="space-y-1 mt-1">
                                            <a href="{{ route('admin.sectors') }}" :title="sidebarCollapsed ? 'Setores' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[40px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.sectors*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Setores</span>
                                            </a>
                                            <a href="{{ route('admin.calendar') }}" :title="sidebarCollapsed ? 'Calendário Laboral' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[40px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.calendar*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Calendário</span>
                                            </a>
                                            <a href="{{ route('admin.time-bank') }}" :title="sidebarCollapsed ? 'Banco de Horas' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[40px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.time-bank*') || request()->routeIs('admin.settlement-policies*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Banco de Horas</span>
                                            </a>
                                            <a href="{{ route('admin.adjustment') }}" :title="sidebarCollapsed ? 'Ajuste Manual' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[40px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.adjustment*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Ajuste Manual</span>
                                            </a>
                                            <a href="{{ route('admin.fiscalizacao') }}" :title="sidebarCollapsed ? 'Fiscalização MTE' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[40px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.fiscalizacao*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Fiscalização</span>
                                            </a>
                                            <a href="{{ route('admin.audit') }}" :title="sidebarCollapsed ? 'Auditoria' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[40px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.audit*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Auditoria</span>
                                            </a>
                                            <a href="{{ route('admin.users') }}" :title="sidebarCollapsed ? 'Usuários do Sistema' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[40px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.users*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Usuários</span>
                                            </a>
                                            <a href="{{ route('admin.settings') }}" :title="sidebarCollapsed ? 'Configurações' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[40px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.settings*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                                <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /></svg>
                                                <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Configurações</span>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @elseif($isAuditor)
                                <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-1.5">Fiscalização</p>
                                <a href="{{ route('admin.fiscalizacao') }}" :title="sidebarCollapsed ? 'Fiscalização MTE' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.fiscalizacao*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Fiscalização MTE</span>
                                </a>
                                <a href="{{ route('admin.audit') }}" :title="sidebarCollapsed ? 'Auditoria' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.audit*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Auditoria</span>
                                </a>
                                <a href="{{ route('admin.reports') }}" :title="sidebarCollapsed ? 'Relatórios' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.reports*') ? 'bg-indigo-800 text-white font-semibold' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Relatórios</span>
                                </a>
                            @else
                                <!-- COLABORADOR -->
                                <p x-show="!sidebarCollapsed" class="px-3 text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-1.5">Colaborador</p>
                                
                                <a href="{{ route('home') }}" :title="sidebarCollapsed ? 'Bater Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('home') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Bater Ponto</span>
                                </a>
                                <a href="{{ route('timesheet') }}" :title="sidebarCollapsed ? 'Espelho de Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('timesheet') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Espelho de Ponto</span>
                                </a>
                                <a href="{{ route('folha-ponto') }}" :title="sidebarCollapsed ? 'Folha de Ponto' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('folha-ponto') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Folha de Ponto</span>
                                </a>
                                <a href="{{ route('receipts.center') }}" :title="sidebarCollapsed ? 'Comprovantes' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('receipts.*') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-100 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Comprovantes</span>
                                </a>
                            @endif

                            <div class="pt-3 mt-3 border-t border-indigo-800/80">
                                <a href="{{ route('help') }}" :title="sidebarCollapsed ? 'Ajuda e Versões' : ''" :class="sidebarCollapsed ? 'justify-center px-2' : 'px-3'" class="min-h-[44px] flex items-center py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('help') ? 'bg-indigo-800 text-white font-semibold shadow-xs' : 'text-indigo-200 hover:text-white hover:bg-indigo-800/60' }}">
                                    <svg :class="sidebarCollapsed ? 'mr-0' : 'mr-3'" class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                                    <span x-show="!sidebarCollapsed" x-transition.opacity class="truncate">Ajuda e Versões</span>
                                </a>
                            </div>
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
                                    <button type="submit" class="min-h-[44px] min-w-[44px] flex items-center justify-center text-xs text-indigo-300 hover:text-white font-medium p-1 cursor-pointer" title="Sair do sistema">
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
                        <button @click="mobileMenuOpen = true" type="button" class="-m-2.5 min-h-[44px] min-w-[44px] flex items-center justify-center p-2.5 text-indigo-200 hover:text-white focus:outline-none cursor-pointer" aria-label="Abrir menu lateral">
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
                            <button type="submit" class="min-h-[44px] text-xs bg-indigo-950 text-indigo-200 hover:text-white px-3 py-2 rounded-lg border border-indigo-700 cursor-pointer flex items-center justify-center">
                                Sair
                            </button>
                        </form>
                    </div>
                </header>

                <!-- Page Main Content -->
                <main class="flex-1 p-2.5 sm:p-5 lg:p-6 pb-28 md:pb-8">
                    <div class="no-print">
                        <livewire:version-notifier />
                        <x-modal-feedback />
                    </div>
                    {{ $slot }}
                </main>

                <!-- Mobile Bottom Tab Bar (App-like navigation for smartphones, adapted by role) -->
                <nav class="no-print md:hidden fixed bottom-0 left-0 right-0 z-30 bg-white/95 backdrop-blur-xs border-t border-gray-200/90 shadow-lg flex items-center justify-around px-2 pt-1.5 pb-[max(0.6rem,env(safe-area-inset-bottom))]">
                    @if($isStaff)
                        <!-- SHORTCUTS OPERACIONAIS PARA RH/GESTOR -->
                        <a href="{{ route('admin.monthly-review') }}" class="min-h-[48px] min-w-[48px] flex flex-col items-center justify-center flex-1 py-1 text-xs transition rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600 {{ request()->routeIs('admin.monthly-review*') ? 'text-indigo-700 font-bold' : 'text-gray-600 hover:text-gray-900 font-medium' }}">
                            <svg class="h-5 w-5 mb-0.5 {{ request()->routeIs('admin.monthly-review*') ? 'text-emerald-600' : 'text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Conferência
                        </a>
                        <a href="{{ route('home') }}" class="min-h-[48px] min-w-[48px] flex flex-col items-center justify-center flex-1 py-1 text-xs transition rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600 {{ request()->routeIs('home') ? 'text-indigo-700 font-bold' : 'text-gray-600 hover:text-gray-900 font-medium' }}">
                            <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            Ponto
                        </a>
                        <a href="{{ route('admin.reports') }}" class="min-h-[48px] min-w-[48px] flex flex-col items-center justify-center flex-1 py-1 text-xs transition rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600 {{ request()->routeIs('admin.reports*') ? 'text-indigo-700 font-bold' : 'text-gray-600 hover:text-gray-900 font-medium' }}">
                            <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                            Relatórios
                        </a>
                        <button @click="mobileMenuOpen = true" type="button" class="min-h-[48px] min-w-[48px] flex flex-col items-center justify-center flex-1 py-1 text-xs transition rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600 text-gray-600 hover:text-gray-900 font-medium cursor-pointer">
                            <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                            Menu
                        </button>
                    @else
                        <!-- SHORTCUTS DO COLABORADOR -->
                        <a href="{{ route('home') }}" class="min-h-[48px] min-w-[48px] flex flex-col items-center justify-center flex-1 py-1 text-xs transition rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600 {{ request()->routeIs('home') ? 'text-indigo-700 font-bold' : 'text-gray-600 hover:text-gray-900 font-medium' }}">
                            <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            Ponto
                        </a>
                        <a href="{{ route('timesheet') }}" class="min-h-[48px] min-w-[48px] flex flex-col items-center justify-center flex-1 py-1 text-xs transition rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600 {{ request()->routeIs('timesheet') ? 'text-indigo-700 font-bold' : 'text-gray-600 hover:text-gray-900 font-medium' }}">
                            <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                            Espelho
                        </a>
                        <a href="{{ route('receipts.center') }}" class="min-h-[48px] min-w-[48px] flex flex-col items-center justify-center flex-1 py-1 text-xs transition rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600 {{ request()->routeIs('receipts.*') ? 'text-indigo-700 font-bold' : 'text-gray-600 hover:text-gray-900 font-medium' }}">
                            <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                            Comprovantes
                        </a>
                        <button @click="mobileMenuOpen = true" type="button" class="min-h-[48px] min-w-[48px] flex flex-col items-center justify-center flex-1 py-1 text-xs transition rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-600 text-gray-600 hover:text-gray-900 font-medium cursor-pointer">
                            <svg class="h-5 w-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                            Menu
                        </button>
                    @endif
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

