<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $package->name }} — Docs" />
    </x-slot>

    <x-slot name="actionbar">
        <x-dev::actionbar :package="$package" />
    </x-slot>

    <x-dev::body>
        <div>
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
    </x-dev::body>
</x-ui-page>
