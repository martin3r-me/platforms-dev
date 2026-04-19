<?php

namespace Platform\Dev\Services;

use Illuminate\Support\Str;
use Platform\Dev\Enums\DocPageType;
use Platform\Dev\Models\DevDocPage;
use Platform\Dev\Models\DevDocRevision;
use Platform\Dev\Models\DevPackage;

class DevDocService
{
    public function initializeDocumentation(DevPackage $package, int $userId): void
    {
        foreach (DocPageType::predefined() as $type) {
            $page = DevDocPage::create([
                'team_id' => $package->team_id,
                'created_by_user_id' => $userId,
                'dev_package_id' => $package->id,
                'type' => $type,
                'title' => $type->defaultTitle(),
                'slug' => Str::slug($type->defaultTitle()) ?: $type->value,
                'content' => $type->defaultContent(),
                'position' => $type->position(),
                'status' => 'draft',
            ]);

            $this->createRevision($page, $userId, 'Initiale Erstellung');
        }
    }

    public function createPage(array $data, int $userId): DevDocPage
    {
        $title = trim((string) ($data['title'] ?? ''));
        $slug = $this->generateUniqueSlug($title, (int) $data['dev_package_id']);

        $page = DevDocPage::create([
            'team_id' => $data['team_id'],
            'created_by_user_id' => $userId,
            'dev_package_id' => $data['dev_package_id'],
            'type' => DocPageType::Custom,
            'title' => $title,
            'slug' => $slug,
            'content' => $data['content'] ?? null,
            'position' => 99,
            'status' => $data['status'] ?? 'draft',
        ]);

        $this->createRevision($page, $userId, 'Initiale Erstellung');

        return $page;
    }

    public function updatePage(DevDocPage $page, array $data, int $userId): DevDocPage
    {
        $payload = [];

        if (array_key_exists('title', $data) && $data['title'] !== null) {
            $payload['title'] = trim((string) $data['title']);
        }

        if (array_key_exists('content', $data) && $data['content'] !== null) {
            $payload['content'] = (string) $data['content'];
        }

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $payload['status'] = $data['status'];
        }

        if (!empty($payload)) {
            $payload['last_edited_by_user_id'] = $userId;
            $page->update($payload);
        }

        return $page->fresh();
    }

    public function applyContentOp(string $content, string $op, array $arguments): array
    {
        return match ($op) {
            'append' => $this->appendContent($content, (string) ($arguments['text'] ?? '')),
            'prepend' => $this->prependContent($content, (string) ($arguments['text'] ?? '')),
            'replace_exact' => $this->replaceExact($content, $arguments['old'] ?? null, $arguments['new'] ?? null),
            'upsert_heading' => $this->upsertHeading(
                $content,
                $arguments['heading'] ?? null,
                (string) ($arguments['text'] ?? ''),
                (int) ($arguments['level'] ?? 2),
                (string) ($arguments['mode'] ?? 'append'),
            ),
            'replace_between' => $this->replaceBetween(
                $content,
                $arguments['start'] ?? null,
                $arguments['end'] ?? null,
                (string) ($arguments['text'] ?? ''),
            ),
            default => ['success' => false, 'error' => 'Unbekannte op: ' . $op],
        };
    }

    public function deletePage(DevDocPage $page): void
    {
        if ($page->type !== DocPageType::Custom) {
            throw new \RuntimeException('Nur Custom-Seiten koennen geloescht werden.');
        }

        $page->delete();
    }

    public function createRevision(DevDocPage $page, int $userId, ?string $changeSummary = null): DevDocRevision
    {
        $latestVersion = $page->revisions()->max('version') ?? 0;

        return DevDocRevision::create([
            'dev_doc_page_id' => $page->id,
            'version' => $latestVersion + 1,
            'title' => $page->title,
            'content' => $page->content,
            'change_summary' => $changeSummary,
            'created_by_user_id' => $userId,
        ]);
    }

    public function restoreRevision(DevDocPage $page, DevDocRevision $revision, int $userId): DevDocPage
    {
        $page->update([
            'title' => $revision->title,
            'content' => $revision->content,
            'last_edited_by_user_id' => $userId,
        ]);

        $this->createRevision($page, $userId, "Wiederhergestellt von Version {$revision->version}");

        return $page->fresh();
    }

    // --- Private helpers ---

    protected function generateUniqueSlug(string $title, int $packageId, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        if ($baseSlug === '') {
            $baseSlug = 'page';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $query = DevDocPage::where('dev_package_id', $packageId)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    protected function appendContent(string $content, string $text): array
    {
        $text = rtrim($text);
        if ($text === '') {
            return ['success' => false, 'error' => 'text ist erforderlich fuer append.'];
        }
        $content = rtrim($content);
        $out = $content === '' ? $text : ($content . "\n\n" . $text);
        return ['success' => true, 'content' => $out];
    }

    protected function prependContent(string $content, string $text): array
    {
        $text = rtrim($text);
        if ($text === '') {
            return ['success' => false, 'error' => 'text ist erforderlich fuer prepend.'];
        }
        $content = ltrim($content);
        $out = $content === '' ? $text : ($text . "\n\n" . $content);
        return ['success' => true, 'content' => $out];
    }

    protected function replaceExact(string $content, mixed $old, mixed $new): array
    {
        if ($old === null || $new === null) {
            return ['success' => false, 'error' => 'old und new sind erforderlich fuer replace_exact.'];
        }
        $old = (string) $old;
        $new = (string) $new;

        if ($old === '') {
            return ['success' => false, 'error' => 'old darf nicht leer sein.'];
        }

        $count = substr_count($content, $old);
        if ($count === 0) {
            return ['success' => false, 'error' => 'Der zu ersetzende Block (old) wurde nicht gefunden.'];
        }
        if ($count > 1) {
            return ['success' => false, 'error' => 'Der zu ersetzende Block (old) ist nicht eindeutig (kommt mehrfach vor).'];
        }

        return ['success' => true, 'content' => str_replace($old, $new, $content)];
    }

    protected function upsertHeading(string $content, mixed $heading, string $text, int $level, string $mode): array
    {
        if ($heading === null) {
            return ['success' => false, 'error' => 'heading ist erforderlich fuer upsert_heading.'];
        }
        $heading = trim((string) $heading);
        if ($heading === '') {
            return ['success' => false, 'error' => 'heading darf nicht leer sein.'];
        }
        $text = rtrim($text);
        if ($text === '') {
            return ['success' => false, 'error' => 'text ist erforderlich fuer upsert_heading.'];
        }
        if ($level < 1 || $level > 6) $level = 2;
        $mode = $mode === 'replace' ? 'replace' : 'append';

        $hashes = str_repeat('#', $level);
        $needle = $hashes . ' ' . $heading;

        $pos = strpos($content, $needle);
        if ($pos === false) {
            $out = rtrim($content);
            $block = $needle . "\n\n" . $text;
            $out = $out === '' ? $block : ($out . "\n\n" . $block);
            return ['success' => true, 'content' => $out];
        }

        $afterHeadingPos = $pos + strlen($needle);
        $rest = substr($content, $afterHeadingPos);

        $pattern = '/\n#{1,' . $level . '}\s+/';
        if (preg_match($pattern, $rest, $m, PREG_OFFSET_CAPTURE)) {
            $nextRel = $m[0][1];
            $section = substr($content, $afterHeadingPos, $nextRel);
            $tail = substr($content, $afterHeadingPos + $nextRel);
        } else {
            $section = substr($content, $afterHeadingPos);
            $tail = '';
        }

        if ($mode === 'replace') {
            $newSection = "\n\n" . $text . "\n";
        } else {
            $trimmed = rtrim($section);
            $newSection = ($trimmed === '' ? "\n\n" . $text . "\n" : $trimmed . "\n\n" . $text . "\n");
        }

        $out = substr($content, 0, $afterHeadingPos) . $newSection . ltrim($tail, "\n");
        return ['success' => true, 'content' => $out];
    }

    protected function replaceBetween(string $content, mixed $start, mixed $end, string $text): array
    {
        if ($start === null || $end === null) {
            return ['success' => false, 'error' => 'start und end sind erforderlich fuer replace_between.'];
        }
        $start = (string) $start;
        $end = (string) $end;

        if ($start === '' || $end === '') {
            return ['success' => false, 'error' => 'start und end duerfen nicht leer sein.'];
        }

        $startPos = strpos($content, $start);
        if ($startPos === false) {
            return ['success' => false, 'error' => 'start-Marker nicht gefunden.'];
        }

        $searchFrom = $startPos + strlen($start);
        $endPos = strpos($content, $end, $searchFrom);
        if ($endPos === false) {
            return ['success' => false, 'error' => 'end-Marker nicht gefunden.'];
        }

        $out = substr($content, 0, $startPos) . $start . $text . $end . substr($content, $endPos + strlen($end));
        return ['success' => true, 'content' => $out];
    }
}
