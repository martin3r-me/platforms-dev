<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $package->name }} — Docs" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            ['label' => 'Documentation'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-5xl mx-auto px-6 py-6">
            {{-- Repository Header --}}
            <div class="mb-5">
                <div class="flex items-center gap-3 mb-3">
                    <svg class="w-5 h-5 text-gray-500" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2.5A2.5 2.5 0 0 1 4.5 0h8.75a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75h-2.5a.75.75 0 0 1 0-1.5h1.75v-2h-8a1 1 0 0 0-.714 1.7.75.75 0 1 1-1.072 1.05A2.495 2.495 0 0 1 2 11.5Zm10.5-1h-8a1 1 0 0 0-1 1v6.708A2.486 2.486 0 0 1 4.5 9h8ZM5 12.25a.25.25 0 0 1 .25-.25h3.5a.25.25 0 0 1 .25.25v3.25a.25.25 0 0 1-.4.2l-1.45-1.087a.25.25 0 0 0-.3 0L5.4 15.7a.25.25 0 0 1-.4-.2Z"/></svg>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $package->name }}</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $package->status === 'active' ? 'border-gray-300 text-gray-600' : 'border-gray-200 text-gray-400' }}">
                        {{ $package->status === 'active' ? 'Public' : 'Archived' }}
                    </span>
                </div>

                {{-- Tab Navigation --}}
                <div class="border-b border-gray-200 -mx-6 px-6">
                    <nav class="flex items-center gap-1 -mb-px">
                        <a href="{{ route('dev.packages.show', $package) }}"
                           wire:navigate
                           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 transition-colors">
                            <svg class="w-4 h-4" viewBox="0 0 16 16" fill="currentColor"><path d="M0 1.75A.75.75 0 0 1 .75 1h4.253c1.227 0 2.317.59 3 1.501A3.744 3.744 0 0 1 11.006 1h4.245a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-4.507a2.25 2.25 0 0 0-1.591.659l-.622.621a.75.75 0 0 1-1.06 0l-.622-.621A2.25 2.25 0 0 0 5.258 13H.75a.75.75 0 0 1-.75-.75Zm7.251 10.324.004-5.073-.002-2.253A2.25 2.25 0 0 0 5.003 2.5H1.5v9h3.757a3.75 3.75 0 0 1 1.994.574ZM8.755 4.75l-.004 7.322a3.752 3.752 0 0 1 1.992-.572H14.5v-9h-3.495a2.25 2.25 0 0 0-2.25 2.25Z"/></svg>
                            Overview
                        </a>
                        @foreach($boards as $board)
                            <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                               wire:navigate
                               class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 transition-colors">
                                @if($board->type->value === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-4 h-4 text-red-500')
                                @elseif($board->type->value === 'feature')
                                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-blue-500')
                                @else
                                    @svg('heroicon-o-view-columns', 'w-4 h-4')
                                @endif
                                {{ $board->name }}
                                @if($board->open_issues_count > 0)
                                    <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums leading-none">{{ $board->open_issues_count }}</span>
                                @endif
                            </a>
                        @endforeach
                        <a href="{{ route('dev.packages.docs', $package) }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 border-[#f78166] text-gray-900 transition-colors">
                            @svg('heroicon-o-book-open', 'w-4 h-4')
                            Docs
                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums leading-none">{{ $docPages->count() }}</span>
                        </a>
                    </nav>
                </div>
            </div>

            {{-- Documentation Content --}}
            @if($docPages->isNotEmpty())
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-book-open', 'w-4 h-4 text-gray-500')
                        <h2 class="text-sm font-semibold text-gray-900">Documentation</h2>
                        <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $docPublishedCount }}/{{ $docPages->count() }}</span>
                    </div>
                    @php $docProgress = $docPages->count() > 0 ? round($docPublishedCount / $docPages->count() * 100) : 0; @endphp
                    <div class="flex items-center gap-3">
                        <div class="w-24 h-[6px] rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full rounded-full bg-[#238636] transition-all" style="width: {{ $docProgress }}%"></div>
                        </div>
                        <span class="text-[11px] font-semibold tabular-nums {{ $docProgress === 100 ? 'text-[#238636]' : 'text-gray-500' }}">{{ $docProgress }}%</span>
                    </div>
                </div>

                <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                    @foreach($docPages as $docPage)
                        @php
                            $iconMap = [
                                'overview' => 'heroicon-o-home',
                                'architecture' => 'heroicon-o-cube-transparent',
                                'setup' => 'heroicon-o-cog-6-tooth',
                                'api' => 'heroicon-o-code-bracket',
                                'data_model' => 'heroicon-o-circle-stack',
                                'testing' => 'heroicon-o-beaker',
                                'deployment' => 'heroicon-o-rocket-launch',
                                'changelog' => 'heroicon-o-clipboard-document-list',
                                'contributing' => 'heroicon-o-user-group',
                                'troubleshooting' => 'heroicon-o-wrench-screwdriver',
                                'custom' => 'heroicon-o-document-text',
                            ];
                            $icon = $iconMap[$docPage->type->value] ?? 'heroicon-o-document-text';
                            $isPublished = $docPage->status === 'published';
                        @endphp
                        <a href="{{ route('dev.packages.docs.show', [$package, $docPage]) }}"
                           wire:navigate
                           class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors">
                            <div class="flex-shrink-0">
                                @svg($icon, 'w-4 h-4 ' . ($isPublished ? 'text-[#238636]' : 'text-gray-400'))
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-medium text-gray-900 truncate">{{ $docPage->title }}</span>
                                    @if($isPublished)
                                        @svg('heroicon-s-check-circle', 'w-3.5 h-3.5 text-[#238636] flex-shrink-0')
                                    @else
                                        <span class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-yellow-100 text-yellow-700">Draft</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-500 mt-0.5">
                                    {{ $docPage->type->label() }}
                                    @if($docPage->revisions_count > 0)
                                        &middot; <code class="px-1 py-px text-[10px] font-mono bg-gray-100 text-gray-600 rounded tabular-nums">v{{ $docPage->revisions_count }}</code>
                                    @endif
                                    @if($docPage->lastEditedBy)
                                        &middot; {{ $docPage->lastEditedBy->name }}
                                    @endif
                                    @if($docPage->updated_at)
                                        &middot; {{ $docPage->updated_at->diffForHumans() }}
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-md border border-gray-200 p-10 text-center">
                    @svg('heroicon-o-book-open', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                    <p class="text-xs font-medium text-gray-900 mb-1">No documentation yet</p>
                    <p class="text-[11px] text-gray-500 mb-4">Initialize the standard documentation pages for this package.</p>
                    <button wire:click="initializeDocs"
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                        Initialize Documentation
                    </button>
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
