<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class DevOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'dev.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/overview - Zeigt Uebersicht ueber das Dev-Modul (Development Hub). Packages, Boards, Issues, Diskussionen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            return ToolResult::success([
                'module' => 'dev',
                'scope' => [
                    'team_scoped' => true,
                    'team_id_source' => 'ToolContext.team bzw. team_id Parameter',
                ],
                'concepts' => [
                    'dev_packages' => [
                        'model' => 'Platform\\Dev\\Models\\DevPackage',
                        'table' => 'dev_packages',
                        'key_fields' => ['id', 'uuid', 'name', 'description', 'github_repo_full_name', 'github_repo_id', 'status', 'icon', 'order', 'team_id'],
                        'note' => 'Packages repraesentieren GitHub-Repositories/Projekte. Status: active, archived. Bei Aktivierung werden automatisch 2 Default-Boards (Features, Bugs) mit 5 Slots erstellt.',
                    ],
                    'dev_boards' => [
                        'model' => 'Platform\\Dev\\Models\\DevBoard',
                        'table' => 'dev_boards',
                        'key_fields' => ['id', 'uuid', 'dev_package_id', 'name', 'type', 'description', 'order'],
                        'note' => 'Kanban-Boards pro Package. Types: feature, bug, custom. Enthalten Slots (Spalten) und Issues.',
                    ],
                    'dev_board_slots' => [
                        'model' => 'Platform\\Dev\\Models\\DevBoardSlot',
                        'table' => 'dev_board_slots',
                        'key_fields' => ['id', 'uuid', 'dev_board_id', 'name', 'description', 'order'],
                        'note' => 'Spalten eines Boards. Default: Backlog, To Do, In Progress, Review, Done.',
                    ],
                    'dev_issues' => [
                        'model' => 'Platform\\Dev\\Models\\DevIssue',
                        'table' => 'dev_issues',
                        'key_fields' => ['id', 'uuid', 'dev_board_id', 'dev_board_slot_id', 'title', 'description', 'priority', 'status', 'labels', 'user_in_charge_id', 'is_done', 'due_date'],
                        'note' => 'Issues/Tasks auf einem Board. Priority: low, normal, high. Status: open, closed. slot_id=null bedeutet Backlog.',
                    ],
                    'dev_discussions' => [
                        'model' => 'Platform\\Dev\\Models\\DevDiscussion',
                        'table' => 'dev_discussions',
                        'key_fields' => ['id', 'uuid', 'dev_package_id', 'title', 'body', 'is_pinned', 'is_locked'],
                        'note' => 'Diskussionen zu einem Package. Koennen angepinnt und gesperrt werden.',
                    ],
                    'dev_discussion_replies' => [
                        'model' => 'Platform\\Dev\\Models\\DevDiscussionReply',
                        'table' => 'dev_discussion_replies',
                        'key_fields' => ['id', 'uuid', 'dev_discussion_id', 'parent_id', 'body'],
                        'note' => 'Antworten auf Diskussionen. Threaded (parent_id fuer verschachtelte Antworten).',
                    ],
                ],
                'relationships' => [
                    'package_has_boards' => 'DevPackage -> DevBoards (feature, bug, custom)',
                    'board_has_slots' => 'DevBoard -> DevBoardSlots (Kanban-Spalten)',
                    'board_has_issues' => 'DevBoard -> DevIssues',
                    'slot_has_issues' => 'DevBoardSlot -> DevIssues',
                    'package_has_discussions' => 'DevPackage -> DevDiscussions -> DevDiscussionReplies (threaded)',
                ],
                'related_tools' => [
                    'packages' => [
                        'list' => 'dev.packages.GET',
                        'get' => 'dev.package.GET',
                        'activate' => 'dev.packages.activate.POST',
                        'update' => 'dev.packages.PUT',
                        'deactivate' => 'dev.packages.deactivate.POST',
                    ],
                    'boards' => [
                        'list' => 'dev.boards.GET',
                        'get' => 'dev.board.GET',
                        'create' => 'dev.boards.POST',
                        'update' => 'dev.boards.PUT',
                        'delete' => 'dev.boards.DELETE',
                    ],
                    'issues' => [
                        'list' => 'dev.issues.GET',
                        'get' => 'dev.issue.GET',
                        'create' => 'dev.issues.POST',
                        'update' => 'dev.issues.PUT',
                        'delete' => 'dev.issues.DELETE',
                        'bulk_create' => 'dev.issues.bulk.POST',
                    ],
                    'discussions' => [
                        'list' => 'dev.discussions.GET',
                        'get' => 'dev.discussion.GET',
                        'create' => 'dev.discussions.POST',
                        'reply' => 'dev.discussions.reply.POST',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Dev-Uebersicht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['overview', 'help', 'dev'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
