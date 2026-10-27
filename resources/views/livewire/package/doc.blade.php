<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $docPage->title }}" />
    </x-slot>

    <x-slot name="actionbar">
        <x-dev::actionbar :package="$package" :crumbs="[['label' => $docPage->title]]">
            <x-slot:more>
                @if($status === 'published')
                    <x-dev::menu-item icon="heroicon-o-pencil" wire:click="toggleStatus">Zurück auf Entwurf</x-dev::menu-item>
                @else
                    <x-dev::menu-item icon="heroicon-o-check-circle" wire:click="toggleStatus">Veröffentlichen</x-dev::menu-item>
                @endif
            </x-slot:more>
        </x-dev::actionbar>
    </x-slot>

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

    <x-dev::body>
        {{-- Doku-Seiten (vorher als linke Sidebar) --}}
        <nav class="-mx-3 sm:-mx-6 -mt-4 sm:-mt-6 px-3 sm:px-6 border-b border-gray-200 bg-white flex items-stretch gap-1 overflow-x-auto [scrollbar-width:none]" aria-label="Doku-Seiten">
            @foreach($docPages as $page)
                @php $isActive = $page->id === $docPage->id; @endphp
                <a href="{{ route('dev.packages.docs.show', [$package, $page]) }}" wire:navigate
                   class="relative shrink-0 inline-flex items-center gap-1.5 px-2.5 py-2.5 text-xs whitespace-nowrap transition-colors {{ $isActive ? 'text-gray-900 font-semibold' : 'text-gray-500 hover:text-gray-900' }}">
                    @svg($docIconMap[$page->type->value] ?? 'heroicon-o-document-text', 'w-3.5 h-3.5 shrink-0 ' . ($isActive ? 'text-gray-700' : 'text-gray-400'))
                    <span>{{ $page->title }}</span>
                    @if($page->status === 'published')
                        <span class="w-1.5 h-1.5 rounded-full bg-[#238636]" title="Published"></span>
                    @endif
                    <span class="absolute inset-x-2 bottom-0 h-0.5 rounded-t-full {{ $isActive ? 'bg-[#238636]' : 'bg-transparent' }}"></span>
                </a>
            @endforeach
        </nav>

        <div>
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
                        <button type="button" wire:click="toggleStatus" title="Zurück auf Entwurf"
                                class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded bg-green-50 hover:bg-green-100 text-[#238636] border border-green-200 transition-colors">
                            @svg('heroicon-s-check-circle', 'w-3 h-3')
                            Published
                        </button>
                    @else
                        <button type="button" wire:click="toggleStatus" title="Veröffentlichen"
                                class="px-2 py-0.5 text-[11px] font-medium rounded bg-yellow-50 hover:bg-yellow-100 text-yellow-700 border border-yellow-200 transition-colors">
                            Draft
                        </button>
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
    </x-dev::body>
</x-ui-page>
