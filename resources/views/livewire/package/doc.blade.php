<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $docPage->title }}" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            ['label' => 'Doku', 'href' => route('dev.packages.docs', $package)],
        ]">
            <x-slot name="left">
                {{-- Seiten-Umschalter (ersetzt die frühere Doku-Sidebar) --}}
                @php
                    $docIconMap = [
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
                @endphp
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open"
                            class="inline-flex items-center gap-1.5 max-w-[14rem] px-3 py-[5px] text-xs font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                        @svg($docIconMap[$docPage->type->value] ?? 'heroicon-o-document-text', 'w-3.5 h-3.5 text-gray-400 flex-shrink-0')
                        <span class="truncate">{{ $docPage->title }}</span>
                        @svg('heroicon-o-chevron-down', 'w-3 h-3 text-gray-400 flex-shrink-0')
                    </button>
                    <div x-show="open" x-cloak x-transition @click.outside="open = false"
                         class="absolute left-0 top-full mt-1 w-72 max-w-[calc(100vw-1rem)] max-h-[70vh] overflow-y-auto rounded-md bg-white border border-gray-200 z-50 py-1 shadow-lg">
                        @foreach($docPages as $page)
                            @php $isActive = $page->id === $docPage->id; @endphp
                            <a href="{{ route('dev.packages.docs.show', [$package, $page]) }}" wire:navigate @click="open = false"
                               class="flex items-center gap-2.5 px-4 py-2 text-xs transition-colors {{ $isActive ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                @svg($docIconMap[$page->type->value] ?? 'heroicon-o-document-text', 'w-3.5 h-3.5 flex-shrink-0 ' . ($isActive ? 'text-gray-700' : 'text-gray-400'))
                                <span class="truncate">{{ $page->title }}</span>
                                @if($page->status === 'published')
                                    <span class="ml-auto flex-shrink-0 w-1.5 h-1.5 rounded-full bg-[#238636]" title="Published"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-slot>
            <button wire:click="toggleStatus"
                    class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium rounded-md border transition-colors {{ $status === 'published' ? 'text-[#238636] bg-green-50 border-green-200 hover:bg-green-100' : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50' }}">
                @if($status === 'published')
                    @svg('heroicon-s-check-circle', 'w-3.5 h-3.5')
                    Published
                @else
                    @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                    Draft
                @endif
            </button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Revisionen" width="w-80" :defaultOpen="false" storeKey="docRevisionsOpen" side="right">
            <div class="p-5">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-4">Revision History</h3>
                <div class="space-y-2">
                    {{-- Current Version --}}
                    <div class="px-3 py-2.5 rounded-md border border-[#238636] bg-green-50">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-gray-900">Current</span>
                            <span class="text-[11px] text-gray-500">{{ $docPage->updated_at?->diffForHumans() }}</span>
                        </div>
                        @if($docPage->lastEditedBy)
                            <div class="text-[11px] text-gray-500">{{ $docPage->lastEditedBy->name }}</div>
                        @endif
                    </div>

                    @forelse($revisions as $revision)
                        <div class="px-3 py-2.5 rounded-md border border-gray-200 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-medium text-gray-900">
                                    <code class="px-1 py-px text-[10px] font-mono bg-gray-100 rounded tabular-nums">v{{ $revision->version }}</code>
                                </span>
                                <span class="text-[11px] text-gray-500">{{ $revision->created_at?->diffForHumans() }}</span>
                            </div>
                            @if($revision->createdBy)
                                <div class="text-[11px] text-gray-500">{{ $revision->createdBy->name }}</div>
                            @endif
                            @if($revision->change_summary)
                                <div class="text-[11px] text-gray-600 mt-1">{{ $revision->change_summary }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="py-6 text-center">
                            <p class="text-[11px] text-gray-500">No revisions yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-4xl mx-auto px-6 py-6">
            {{-- Page Header --}}
            <div class="mb-6">
                <div class="d-flex items-center gap-2 mb-2">
                    @php
                        $typeIcon = [
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
                        ][$docPage->type->value] ?? 'heroicon-o-document-text';
                    @endphp
                    <span class="px-2 py-0.5 text-[11px] font-medium rounded bg-gray-100 text-gray-600 border border-gray-200">
                        {{ $docPage->type->label() }}
                    </span>
                    @if($status === 'published')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded bg-green-50 text-[#238636] border border-green-200">
                            @svg('heroicon-s-check-circle', 'w-3 h-3')
                            Published
                        </span>
                    @else
                        <span class="px-2 py-0.5 text-[11px] font-medium rounded bg-yellow-50 text-yellow-700 border border-yellow-200">
                            Draft
                        </span>
                    @endif
                </div>

                <x-ui-input-text
                    name="title"
                    label=""
                    wire:model.blur="title"
                    wire:change="updateTitle"
                    class="text-xl font-semibold"
                    placeholder="Seitentitel..."
                />

                @if($docPage->type->description())
                    <p class="text-xs text-gray-500 mt-2">{{ $docPage->type->description() }}</p>
                @endif
            </div>

            {{-- Content Editor --}}
            <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                <div class="px-5 py-2.5 bg-gray-50 border-b border-gray-200 d-flex items-center justify-between">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5 text-gray-500')
                        <span class="text-[11px] font-medium text-gray-700">Markdown</span>
                    </div>
                    <div class="d-flex items-center gap-3 text-[11px] text-gray-500">
                        @if($docPage->lastEditedBy)
                            <span>Zuletzt: {{ $docPage->lastEditedBy->name }}</span>
                            <span>&middot;</span>
                        @endif
                        @if($docPage->updated_at)
                            <span>{{ $docPage->updated_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
                <div class="p-5">
                    <x-ui-input-textarea
                        name="content"
                        label=""
                        wire:model.blur="content"
                        wire:change="updateContent"
                        :placeholder="$docPage->type->defaultContent() ?: 'Inhalt hier eingeben...'"
                        rows="24"
                        class="font-mono text-sm"
                    />
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
