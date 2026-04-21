<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Discussions" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            ['label' => 'Discussions'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Discussion List --}}
            <div>
                <div class="d-flex items-center justify-between mb-4">
                    <h2 class="text-xs font-semibold text-gray-900">
                        Discussions
                        @if($discussions->isNotEmpty())
                            <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $discussions->count() }}</span>
                        @endif
                    </h2>
                    <button wire:click="$set('showCreateForm', true)"
                            class="inline-flex items-center gap-1 px-2.5 py-[4px] text-[11px] font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                        @svg('heroicon-o-plus', 'w-3 h-3')
                        New
                    </button>
                </div>

                {{-- Create Form --}}
                @if($showCreateForm)
                    <div class="p-4 mb-4 rounded-md border border-blue-200 bg-blue-50/30 space-y-3">
                        <x-ui-input-text wire:model="newTitle" label="Title" placeholder="Discussion title" />
                        <x-ui-input-textarea wire:model="newBody" label="Body" placeholder="Optional" rows="3" />
                        <div class="d-flex items-center gap-2">
                            <button wire:click="createDiscussion"
                                    class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                                Create
                            </button>
                            <button wire:click="$set('showCreateForm', false)"
                                    class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </div>
                @endif

                {{-- List --}}
                <div class="space-y-1.5">
                    @foreach($discussions as $discussion)
                        <button
                            wire:click="selectDiscussion({{ $discussion->id }})"
                            class="w-full text-left px-4 py-3 rounded-md border transition-all {{ $activeDiscussionId === $discussion->id ? 'border-blue-300 bg-blue-50/50 ring-1 ring-blue-100' : 'border-gray-200 hover:bg-gray-50 hover:border-gray-300' }}"
                        >
                            <div class="d-flex items-start gap-2">
                                @if($discussion->is_pinned)
                                    @svg('heroicon-s-bookmark', 'w-3 h-3 text-blue-500 flex-shrink-0 mt-0.5')
                                @else
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-0.5" viewBox="0 0 16 16" fill="currentColor"><path d="M1.75 1h8.5c.966 0 1.75.784 1.75 1.75v5.5A1.75 1.75 0 0 1 10.25 10H7.061l-2.574 2.573A1.458 1.458 0 0 1 2 11.543V10h-.25A1.75 1.75 0 0 1 0 8.25v-5.5C0 1.784.784 1 1.75 1ZM1.5 2.75v5.5c0 .138.112.25.25.25h1a.75.75 0 0 1 .75.75v2.19l2.72-2.72a.749.749 0 0 1 .53-.22h3.5a.25.25 0 0 0 .25-.25v-5.5a.25.25 0 0 0-.25-.25h-8.5a.25.25 0 0 0-.25.25Zm13 2a.25.25 0 0 0-.25-.25h-.5a.75.75 0 0 1 0-1.5h.5c.966 0 1.75.784 1.75 1.75v5.5A1.75 1.75 0 0 1 14.25 12H14v1.543a1.458 1.458 0 0 1-2.487 1.03L9.22 12.28a.749.749 0 0 1 .326-1.275.749.749 0 0 1 .734.215l2.22 2.22v-2.19a.75.75 0 0 1 .75-.75h1a.25.25 0 0 0 .25-.25Z"/></svg>
                                @endif
                                <div class="min-w-0 flex-grow-1">
                                    <h3 class="text-xs font-medium text-gray-900 truncate leading-relaxed">{{ $discussion->title }}</h3>
                                    <div class="d-flex items-center gap-1.5 mt-1 text-[11px] text-gray-500">
                                        <span>{{ $discussion->createdBy?->name }}</span>
                                        <span class="text-gray-300">&middot;</span>
                                        <span>{{ $discussion->replies_count }} {{ $discussion->replies_count === 1 ? 'reply' : 'replies' }}</span>
                                        @if($discussion->is_locked)
                                            <span class="text-gray-300">&middot;</span>
                                            @svg('heroicon-o-lock-closed', 'w-3 h-3 text-yellow-500')
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>

                @if($discussions->isEmpty() && !$showCreateForm)
                    <div class="text-center py-12">
                        <svg class="w-10 h-10 text-gray-200 mx-auto mb-4" viewBox="0 0 16 16" fill="currentColor"><path d="M1.75 1h8.5c.966 0 1.75.784 1.75 1.75v5.5A1.75 1.75 0 0 1 10.25 10H7.061l-2.574 2.573A1.458 1.458 0 0 1 2 11.543V10h-.25A1.75 1.75 0 0 1 0 8.25v-5.5C0 1.784.784 1 1.75 1ZM1.5 2.75v5.5c0 .138.112.25.25.25h1a.75.75 0 0 1 .75.75v2.19l2.72-2.72a.749.749 0 0 1 .53-.22h3.5a.25.25 0 0 0 .25-.25v-5.5a.25.25 0 0 0-.25-.25h-8.5a.25.25 0 0 0-.25.25Zm13 2a.25.25 0 0 0-.25-.25h-.5a.75.75 0 0 1 0-1.5h.5c.966 0 1.75.784 1.75 1.75v5.5A1.75 1.75 0 0 1 14.25 12H14v1.543a1.458 1.458 0 0 1-2.487 1.03L9.22 12.28a.749.749 0 0 1 .326-1.275.749.749 0 0 1 .734.215l2.22 2.22v-2.19a.75.75 0 0 1 .75-.75h1a.25.25 0 0 0 .25-.25Z"/></svg>
                        <p class="text-xs text-gray-500 mb-4">No discussions yet</p>
                        <button wire:click="$set('showCreateForm', true)"
                                class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                            @svg('heroicon-o-plus', 'w-3.5 h-3.5')
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
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
                                <h2 class="text-sm font-bold text-gray-900 mb-2">{{ $activeDiscussion->title }}</h2>
                                <div class="text-[11px] text-gray-500 d-flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 text-[10px] font-medium text-gray-600">
                                        {{ mb_strtoupper(mb_substr($activeDiscussion->createdBy?->name ?? 'U', 0, 1)) }}
                                    </span>
                                    <span class="font-medium text-gray-900">{{ $activeDiscussion->createdBy?->name }}</span>
                                    <span class="text-gray-300">&middot;</span>
                                    <span>{{ $activeDiscussion->created_at->format('M d, Y') }}</span>
                                    @if($activeDiscussion->is_pinned)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-700 text-[10px] font-medium border border-blue-100">
                                            @svg('heroicon-s-bookmark', 'w-2.5 h-2.5')
                                            Pinned
                                        </span>
                                    @endif
                                    @if($activeDiscussion->is_locked)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-yellow-50 text-yellow-700 text-[10px] font-medium border border-yellow-100">
                                            @svg('heroicon-o-lock-closed', 'w-2.5 h-2.5')
                                            Locked
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if($activeDiscussion->body)
                                <div class="px-5 py-4">
                                    <div class="prose prose-sm prose-gray max-w-none text-xs leading-relaxed text-gray-700">
                                        {!! nl2br(e($activeDiscussion->body)) !!}
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Replies --}}
                        @if($replies->isNotEmpty())
                            <div class="relative">
                                {{-- Vertical connection line --}}
                                <div class="absolute left-[22px] top-0 bottom-0 w-px bg-gray-200"></div>

                                <div class="space-y-4 relative">
                                    @foreach($replies as $reply)
                                        <div class="d-flex gap-3">
                                            <div class="flex-shrink-0 relative z-10">
                                                <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full bg-gray-100 border border-gray-200 text-[9px] font-medium text-gray-600">
                                                    {{ mb_strtoupper(mb_substr($reply->createdBy?->name ?? 'U', 0, 1)) }}
                                                </span>
                                            </div>
                                            <div class="flex-grow-1 min-w-0 rounded-md border border-gray-200 overflow-hidden">
                                                <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 d-flex items-center gap-2 text-[11px] text-gray-500">
                                                    <span class="font-semibold text-gray-900">{{ $reply->createdBy?->name }}</span>
                                                    <span class="text-gray-300">&middot;</span>
                                                    <span>{{ $reply->created_at->diffForHumans() }}</span>
                                                </div>
                                                <div class="px-4 py-3 text-xs text-gray-700 leading-relaxed">
                                                    {!! nl2br(e($reply->body)) !!}
                                                </div>

                                                {{-- Nested replies --}}
                                                @if($reply->children->isNotEmpty())
                                                    <div class="border-t border-gray-100">
                                                        @foreach($reply->children as $child)
                                                            <div class="px-4 py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                                                                <div class="text-[11px] text-gray-500 mb-1 d-flex items-center gap-1.5">
                                                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 text-[8px] font-medium text-gray-500">
                                                                        {{ mb_strtoupper(mb_substr($child->createdBy?->name ?? 'U', 0, 1)) }}
                                                                    </span>
                                                                    <span class="font-medium text-gray-700">{{ $child->createdBy?->name }}</span>
                                                                    <span class="text-gray-300">&middot;</span>
                                                                    <span>{{ $child->created_at->diffForHumans() }}</span>
                                                                </div>
                                                                <div class="text-xs text-gray-700 pl-5">
                                                                    {!! nl2br(e($child->body)) !!}
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Reply Form --}}
                        @if(!$activeDiscussion->is_locked)
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full bg-green-100 border border-green-200 text-[9px] font-medium text-green-700">+</span>
                                </div>
                                <div class="flex-grow-1 rounded-md border border-gray-200 overflow-hidden focus-within:border-blue-300 focus-within:ring-1 focus-within:ring-blue-100 transition-all">
                                    <textarea
                                        wire:model="replyBody"
                                        wire:keydown.ctrl.enter="reply"
                                        rows="3"
                                        placeholder="Write a reply..."
                                        class="w-full px-4 py-3 text-xs bg-white text-gray-900 placeholder-gray-400 focus:outline-none resize-none border-none"
                                    ></textarea>
                                    <div class="px-4 py-2 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                                        <span class="text-[10px] text-gray-400">Ctrl+Enter to submit</span>
                                        <button wire:click="reply"
                                                class="inline-flex items-center gap-1.5 px-3 py-[4px] text-[11px] font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                                            Reply
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-yellow-50 text-yellow-700 text-[11px] font-medium border border-yellow-200">
                                    @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5')
                                    This discussion is locked
                                </span>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-20">
                        <svg class="w-12 h-12 text-gray-200 mx-auto mb-5" viewBox="0 0 16 16" fill="currentColor"><path d="M1.75 1h8.5c.966 0 1.75.784 1.75 1.75v5.5A1.75 1.75 0 0 1 10.25 10H7.061l-2.574 2.573A1.458 1.458 0 0 1 2 11.543V10h-.25A1.75 1.75 0 0 1 0 8.25v-5.5C0 1.784.784 1 1.75 1ZM1.5 2.75v5.5c0 .138.112.25.25.25h1a.75.75 0 0 1 .75.75v2.19l2.72-2.72a.749.749 0 0 1 .53-.22h3.5a.25.25 0 0 0 .25-.25v-5.5a.25.25 0 0 0-.25-.25h-8.5a.25.25 0 0 0-.25.25Zm13 2a.25.25 0 0 0-.25-.25h-.5a.75.75 0 0 1 0-1.5h.5c.966 0 1.75.784 1.75 1.75v5.5A1.75 1.75 0 0 1 14.25 12H14v1.543a1.458 1.458 0 0 1-2.487 1.03L9.22 12.28a.749.749 0 0 1 .326-1.275.749.749 0 0 1 .734.215l2.22 2.22v-2.19a.75.75 0 0 1 .75-.75h1a.25.25 0 0 0 .25-.25Z"/></svg>
                        <p class="text-xs font-medium text-gray-700 mb-1">Select a discussion</p>
                        <p class="text-[11px] text-gray-500">Choose from the list or start a new one.</p>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
