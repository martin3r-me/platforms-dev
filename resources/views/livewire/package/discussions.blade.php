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
                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)]">Diskussionen</h2>
                    <button wire:click="$set('showCreateForm', true)" class="p-1.5 rounded-lg hover:bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                    </button>
                </div>

                {{-- Create Form --}}
                @if($showCreateForm)
                    <div class="p-3 rounded-lg border border-[var(--ui-primary)]/40 bg-[var(--ui-muted-5)] space-y-2">
                        <x-ui-input-text wire:model="newTitle" label="Titel" placeholder="Diskussionstitel" />
                        <x-ui-input-textarea wire:model="newBody" label="Inhalt" placeholder="Optional" rows="3" />
                        <div class="d-flex items-center gap-2">
                            <x-ui-button variant="primary" size="sm" wire:click="createDiscussion">Erstellen</x-ui-button>
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showCreateForm', false)">Abbrechen</x-ui-button>
                        </div>
                    </div>
                @endif

                {{-- List --}}
                @foreach($discussions as $discussion)
                    <button
                        wire:click="selectDiscussion({{ $discussion->id }})"
                        class="w-full text-left p-3 rounded-lg border transition-colors {{ $activeDiscussionId === $discussion->id ? 'border-[var(--ui-primary)]/40 bg-[var(--ui-primary)]/5' : 'border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] hover:border-[var(--ui-primary)]/20' }}"
                    >
                        <div class="d-flex items-start gap-2">
                            @if($discussion->is_pinned)
                                @svg('heroicon-s-bookmark', 'w-3.5 h-3.5 text-[var(--ui-primary)] flex-shrink-0 mt-0.5')
                            @endif
                            <div class="min-w-0">
                                <h3 class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $discussion->title }}</h3>
                                <div class="d-flex items-center gap-2 mt-1 text-xs text-[var(--ui-muted)]">
                                    <span>{{ $discussion->createdBy?->name }}</span>
                                    <span>&middot;</span>
                                    <span>{{ $discussion->replies_count }} Antworten</span>
                                    @if($discussion->is_locked)
                                        @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                    @endif
                                </div>
                            </div>
                        </div>
                    </button>
                @endforeach

                @if($discussions->isEmpty())
                    <div class="text-center py-8">
                        <p class="text-sm text-[var(--ui-muted)]">Keine Diskussionen vorhanden.</p>
                    </div>
                @endif
            </div>

            {{-- Discussion Detail --}}
            <div class="lg:col-span-2">
                @if($activeDiscussion)
                    <div class="space-y-4">
                        {{-- Discussion Header --}}
                        <div class="p-4 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                            <h2 class="text-lg font-bold text-[var(--ui-secondary)] mb-2">{{ $activeDiscussion->title }}</h2>
                            <div class="text-xs text-[var(--ui-muted)] mb-3">
                                {{ $activeDiscussion->createdBy?->name }} &middot; {{ $activeDiscussion->created_at->format('d.m.Y H:i') }}
                                @if($activeDiscussion->is_pinned)
                                    &middot; <span class="text-[var(--ui-primary)]">Angepinnt</span>
                                @endif
                                @if($activeDiscussion->is_locked)
                                    &middot; <span class="text-[var(--ui-warning)]">Gesperrt</span>
                                @endif
                            </div>
                            @if($activeDiscussion->body)
                                <div class="prose prose-sm max-w-none text-[var(--ui-secondary)]">
                                    {!! nl2br(e($activeDiscussion->body)) !!}
                                </div>
                            @endif
                        </div>

                        {{-- Replies --}}
                        @if($replies->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($replies as $reply)
                                    <div class="p-3 rounded-lg border border-[var(--ui-border)]/40 bg-white dark:bg-transparent">
                                        <div class="text-xs text-[var(--ui-muted)] mb-1">
                                            {{ $reply->createdBy?->name }} &middot; {{ $reply->created_at->format('d.m.Y H:i') }}
                                        </div>
                                        <div class="text-sm text-[var(--ui-secondary)]">
                                            {!! nl2br(e($reply->body)) !!}
                                        </div>

                                        {{-- Nested replies --}}
                                        @if($reply->children->isNotEmpty())
                                            <div class="mt-3 ml-4 space-y-2 border-l-2 border-[var(--ui-border)]/40 pl-3">
                                                @foreach($reply->children as $child)
                                                    <div>
                                                        <div class="text-xs text-[var(--ui-muted)] mb-0.5">
                                                            {{ $child->createdBy?->name }} &middot; {{ $child->created_at->format('d.m.Y H:i') }}
                                                        </div>
                                                        <div class="text-sm text-[var(--ui-secondary)]">
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
                            <div class="d-flex items-start gap-2">
                                <div class="flex-grow-1">
                                    <textarea
                                        wire:model="replyBody"
                                        wire:keydown.ctrl.enter="reply"
                                        rows="2"
                                        placeholder="Antwort schreiben... (Ctrl+Enter zum Senden)"
                                        class="w-full px-3 py-2 text-sm rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:border-[var(--ui-primary)]/40 resize-none"
                                    ></textarea>
                                </div>
                                <x-ui-button variant="primary" size="sm" wire:click="reply">
                                    @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                                </x-ui-button>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <p class="text-sm text-[var(--ui-muted)]">Diese Diskussion ist gesperrt.</p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-16">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[var(--ui-muted-5)] mb-4">
                            @svg('heroicon-o-chat-bubble-left-right', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm text-[var(--ui-muted)]">Wähle eine Diskussion aus oder erstelle eine neue.</p>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
