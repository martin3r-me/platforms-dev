<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Diskussionen" icon="heroicon-o-chat-bubble-left-right" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            ['label' => 'Diskussionen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Discussion List --}}
            <div class="space-y-3">
                <div class="d-flex items-center justify-between mb-2">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4 text-gray-500')
                        <h2 class="text-sm font-semibold text-gray-900">Discussions</h2>
                        @if($discussions->isNotEmpty())
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">{{ $discussions->count() }}</span>
                        @endif
                    </div>
                    <button wire:click="$set('showCreateForm', true)" class="p-1.5 rounded-md bg-green-50 hover:bg-green-100 text-green-600 transition-colors border border-green-200">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                    </button>
                </div>

                {{-- Create Form --}}
                @if($showCreateForm)
                    <div class="p-3 rounded-md border border-blue-200 bg-blue-50/50 space-y-2">
                        <x-ui-input-text wire:model="newTitle" label="Titel" placeholder="Diskussionstitel" />
                        <x-ui-input-textarea wire:model="newBody" label="Inhalt" placeholder="Optional" rows="3" />
                        <div class="d-flex items-center gap-2">
                            <button wire:click="createDiscussion"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                                Erstellen
                            </button>
                            <button wire:click="$set('showCreateForm', false)"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                                Abbrechen
                            </button>
                        </div>
                    </div>
                @endif

                {{-- List --}}
                @foreach($discussions as $discussion)
                    <button
                        wire:click="selectDiscussion({{ $discussion->id }})"
                        class="w-full text-left px-4 py-3 rounded-md border transition-all duration-200 {{ $activeDiscussionId === $discussion->id ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300' }}"
                    >
                        <div class="d-flex items-start gap-2">
                            @if($discussion->is_pinned)
                                @svg('heroicon-s-bookmark', 'w-3.5 h-3.5 text-blue-500 flex-shrink-0 mt-0.5')
                            @endif
                            <div class="min-w-0">
                                <h3 class="text-sm font-medium text-gray-900 truncate">{{ $discussion->title }}</h3>
                                <div class="d-flex items-center gap-2 mt-1 text-xs text-gray-500">
                                    <span class="font-medium text-gray-700">{{ $discussion->createdBy?->name }}</span>
                                    <span>&middot;</span>
                                    <span>{{ $discussion->replies_count }} {{ $discussion->replies_count === 1 ? 'reply' : 'replies' }}</span>
                                    @if($discussion->is_locked)
                                        <span>&middot;</span>
                                        @svg('heroicon-o-lock-closed', 'w-3 h-3 text-yellow-500')
                                    @endif
                                </div>
                            </div>
                        </div>
                    </button>
                @endforeach

                @if($discussions->isEmpty())
                    <div class="text-center py-8">
                        @svg('heroicon-o-chat-bubble-left-right', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                        <p class="text-sm text-gray-500">No discussions yet.</p>
                        <button wire:click="$set('showCreateForm', true)"
                                class="inline-flex items-center gap-2 px-3 py-1.5 mt-3 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span>New discussion</span>
                        </button>
                    </div>
                @endif
            </div>

            {{-- Discussion Detail --}}
            <div class="lg:col-span-2">
                @if($activeDiscussion)
                    <div class="space-y-4">
                        {{-- Discussion Header --}}
                        <div class="p-4 rounded-md border border-gray-200 bg-white">
                            <h2 class="text-lg font-bold text-gray-900 mb-2">{{ $activeDiscussion->title }}</h2>
                            <div class="text-xs text-gray-500 mb-3 d-flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 text-[10px] font-medium text-gray-600">
                                    {{ mb_strtoupper(mb_substr($activeDiscussion->createdBy?->name ?? 'U', 0, 1)) }}
                                </span>
                                <span class="font-medium text-gray-900">{{ $activeDiscussion->createdBy?->name }}</span>
                                <span>&middot;</span>
                                <span>{{ $activeDiscussion->created_at->format('d.m.Y H:i') }}</span>
                                @if($activeDiscussion->is_pinned)
                                    <span>&middot;</span>
                                    <span class="inline-flex items-center gap-1 text-blue-600">
                                        @svg('heroicon-s-bookmark', 'w-3 h-3')
                                        Pinned
                                    </span>
                                @endif
                                @if($activeDiscussion->is_locked)
                                    <span>&middot;</span>
                                    <span class="inline-flex items-center gap-1 text-yellow-600">
                                        @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                        Locked
                                    </span>
                                @endif
                            </div>
                            @if($activeDiscussion->body)
                                <div class="prose prose-sm max-w-none text-gray-700">
                                    {!! nl2br(e($activeDiscussion->body)) !!}
                                </div>
                            @endif
                        </div>

                        {{-- Replies --}}
                        @if($replies->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($replies as $reply)
                                    <div class="p-4 rounded-md border border-gray-200 bg-white border-l-2 border-l-blue-300">
                                        <div class="text-xs text-gray-500 mb-2 d-flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 text-[10px] font-medium text-gray-600">
                                                {{ mb_strtoupper(mb_substr($reply->createdBy?->name ?? 'U', 0, 1)) }}
                                            </span>
                                            <span class="font-medium text-gray-900">{{ $reply->createdBy?->name }}</span>
                                            <span>&middot;</span>
                                            <span>{{ $reply->created_at->format('d.m.Y H:i') }}</span>
                                        </div>
                                        <div class="text-sm text-gray-700">
                                            {!! nl2br(e($reply->body)) !!}
                                        </div>

                                        {{-- Nested replies --}}
                                        @if($reply->children->isNotEmpty())
                                            <div class="mt-3 ml-4 space-y-2 border-l-2 border-gray-200 pl-3">
                                                @foreach($reply->children as $child)
                                                    <div>
                                                        <div class="text-xs text-gray-500 mb-0.5 d-flex items-center gap-2">
                                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 text-[9px] font-medium text-gray-600">
                                                                {{ mb_strtoupper(mb_substr($child->createdBy?->name ?? 'U', 0, 1)) }}
                                                            </span>
                                                            <span class="font-medium text-gray-700">{{ $child->createdBy?->name }}</span>
                                                            <span>&middot;</span>
                                                            <span>{{ $child->created_at->format('d.m.Y H:i') }}</span>
                                                        </div>
                                                        <div class="text-sm text-gray-700">
                                                            {!! nl2br(e($child->body)) !!}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Reply Form --}}
                        @if(!$activeDiscussion->is_locked)
                            <div class="rounded-md bg-gray-50 border border-gray-200 p-3">
                                <div class="d-flex items-end gap-2">
                                    <div class="flex-grow-1">
                                        <textarea
                                            wire:model="replyBody"
                                            wire:keydown.ctrl.enter="reply"
                                            rows="2"
                                            placeholder="Write a reply... (Ctrl+Enter)"
                                            class="w-full px-3 py-2 text-sm rounded-md bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none"
                                        ></textarea>
                                    </div>
                                    <button wire:click="reply"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                                        @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-yellow-50 text-yellow-700 text-sm border border-yellow-200">
                                    @svg('heroicon-o-lock-closed', 'w-4 h-4')
                                    <span>This discussion is locked.</span>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-16">
                        @svg('heroicon-o-chat-bubble-left-right', 'w-10 h-10 text-gray-300 mx-auto mb-4')
                        <p class="text-sm font-medium text-gray-900 mb-1">No discussion selected</p>
                        <p class="text-xs text-gray-500">Select a discussion or create a new one.</p>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
