<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Announcement\Models\PlatformAnnouncement;
use App\Domain\ContentOnboarding\Models\CmsPage;
use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentManagementController extends BaseApiController
{
    // ═══════════════════════════════════════════════════════════
    //  CMS Pages
    // ═══════════════════════════════════════════════════════════

    public function listPages(Request $request): JsonResponse
    {
        $query = CmsPage::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('page_type')) {
            $query->where('page_type', $request->input('page_type'));
        }

        if ($request->has('is_published')) {
            $query->where('is_published', filter_var($request->input('is_published'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('sort_order')->orderBy('title');
        $pages = $query->get();

        return $this->success([
            'pages' => $pages,
            'total' => $pages->count(),
        ]);
    }

    public function createPage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:150|unique:cms_pages,slug',
            'body' => 'nullable|string',
            'body_ar' => 'nullable|string',
            'page_type' => 'nullable|string|max:50',
            'is_published' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_title_ar' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_description_ar' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $page = CmsPage::create($data);

        return $this->created($page->fresh(), 'CMS page created');
    }

    public function showPage(string $pageId): JsonResponse
    {
        $page = CmsPage::find($pageId);
        if (!$page) {
            return $this->notFound('CMS page not found');
        }

        return $this->success($page);
    }

    public function updatePage(Request $request, string $pageId): JsonResponse
    {
        $page = CmsPage::find($pageId);
        if (!$page) {
            return $this->notFound('CMS page not found');
        }

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:150|unique:cms_pages,slug,' . $page->id,
            'body' => 'nullable|string',
            'body_ar' => 'nullable|string',
            'page_type' => 'nullable|string|max:50',
            'is_published' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_title_ar' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_description_ar' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $page->update($data);

        return $this->success($page->fresh(), 'CMS page updated');
    }

    public function destroyPage(string $pageId): JsonResponse
    {
        $page = CmsPage::find($pageId);
        if (!$page) {
            return $this->notFound('CMS page not found');
        }

        $page->delete();

        return $this->success(null, 'CMS page deleted');
    }

    public function publishPage(string $pageId): JsonResponse
    {
        $page = CmsPage::find($pageId);
        if (!$page) {
            return $this->notFound('CMS page not found');
        }

        $page->update(['is_published' => !$page->is_published]);

        return $this->success($page->fresh(), $page->fresh()->is_published ? 'Page published' : 'Page unpublished');
    }

    // ═══════════════════════════════════════════════════════════
    //  Knowledge Base Articles
    // ═══════════════════════════════════════════════════════════

    public function listArticles(Request $request): JsonResponse
    {
        $query = KnowledgeBaseArticle::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('is_published')) {
            $query->where('is_published', filter_var($request->input('is_published'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('sort_order')->orderBy('title');

        $perPage = min($request->input('per_page', 15), 100);
        $articles = $query->paginate($perPage);

        return $this->success([
            'articles' => $articles->items(),
            'total' => $articles->total(),
            'current_page' => $articles->currentPage(),
            'last_page' => $articles->lastPage(),
        ]);
    }

    public function createArticle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:knowledge_base_articles,slug',
            'body' => 'required|string',
            'body_ar' => 'required|string',
            'category' => 'nullable|string|max:50',
            'is_published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $article = KnowledgeBaseArticle::create($data);

        return $this->created($article->fresh(), 'Article created');
    }

    public function showArticle(string $articleId): JsonResponse
    {
        $article = KnowledgeBaseArticle::find($articleId);
        if (!$article) {
            return $this->notFound('Article not found');
        }

        return $this->success($article);
    }

    public function updateArticle(Request $request, string $articleId): JsonResponse
    {
        $article = KnowledgeBaseArticle::find($articleId);
        if (!$article) {
            return $this->notFound('Article not found');
        }

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'title_ar' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:knowledge_base_articles,slug,' . $article->id,
            'body' => 'sometimes|required|string',
            'body_ar' => 'sometimes|required|string',
            'category' => 'nullable|string|max:50',
            'is_published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $article->update($data);

        return $this->success($article->fresh(), 'Article updated');
    }

    public function destroyArticle(string $articleId): JsonResponse
    {
        $article = KnowledgeBaseArticle::find($articleId);
        if (!$article) {
            return $this->notFound('Article not found');
        }

        $article->delete();

        return $this->success(null, 'Article deleted');
    }

    public function publishArticle(string $articleId): JsonResponse
    {
        $article = KnowledgeBaseArticle::find($articleId);
        if (!$article) {
            return $this->notFound('Article not found');
        }

        $article->update(['is_published' => !$article->is_published]);

        return $this->success(
            $article->fresh(),
            $article->fresh()->is_published ? 'Article published' : 'Article unpublished',
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Platform Announcements
    // ═══════════════════════════════════════════════════════════

    public function listAnnouncements(Request $request): JsonResponse
    {
        $query = PlatformAnnouncement::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $query->orderByDesc('created_at');

        $perPage = min($request->input('per_page', 15), 100);
        $announcements = $query->paginate($perPage);

        return $this->success([
            'announcements' => $announcements->items(),
            'total' => $announcements->total(),
            'current_page' => $announcements->currentPage(),
            'last_page' => $announcements->lastPage(),
        ]);
    }

    public function createAnnouncement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'nullable|string|in:info,warning,maintenance,update',
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'body' => 'required|string',
            'body_ar' => 'nullable|string',
            'target_filter' => 'nullable|array',
            'display_start_at' => 'nullable|date',
            'display_end_at' => 'nullable|date|after_or_equal:display_start_at',
            'is_banner' => 'nullable|boolean',
            'send_push' => 'nullable|boolean',
            'send_email' => 'nullable|boolean',
        ]);

        $data['created_by'] = $request->user()->id;

        $announcement = PlatformAnnouncement::create($data);

        return $this->created($announcement->fresh(), 'Announcement created');
    }

    public function showAnnouncement(string $announcementId): JsonResponse
    {
        $announcement = PlatformAnnouncement::find($announcementId);
        if (!$announcement) {
            return $this->notFound('Announcement not found');
        }

        return $this->success($announcement);
    }

    public function updateAnnouncement(Request $request, string $announcementId): JsonResponse
    {
        $announcement = PlatformAnnouncement::find($announcementId);
        if (!$announcement) {
            return $this->notFound('Announcement not found');
        }

        $data = $request->validate([
            'type' => 'nullable|string|in:info,warning,maintenance,update',
            'title' => 'sometimes|required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'body' => 'sometimes|required|string',
            'body_ar' => 'nullable|string',
            'target_filter' => 'nullable|array',
            'display_start_at' => 'nullable|date',
            'display_end_at' => 'nullable|date',
            'is_banner' => 'nullable|boolean',
            'send_push' => 'nullable|boolean',
            'send_email' => 'nullable|boolean',
        ]);

        $announcement->update($data);

        return $this->success($announcement->fresh(), 'Announcement updated');
    }

    public function destroyAnnouncement(string $announcementId): JsonResponse
    {
        $announcement = PlatformAnnouncement::find($announcementId);
        if (!$announcement) {
            return $this->notFound('Announcement not found');
        }

        $announcement->delete();

        return $this->success(null, 'Announcement deleted');
    }

    // ═══════════════════════════════════════════════════════════
    //  Notification Templates
    // ═══════════════════════════════════════════════════════════

    public function listTemplates(Request $request): JsonResponse
    {
        $query = NotificationTemplate::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('event_key', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%");
            });
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('event_key')->orderBy('channel');
        $templates = $query->get();

        return $this->success([
            'templates' => $templates,
            'total' => $templates->count(),
        ]);
    }

    public function createTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_key' => 'required|string|max:100',
            'channel' => 'required|string|in:in_app,push,sms,email,whatsapp,sound',
            'title' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'body_ar' => 'nullable|string',
            'available_variables' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        // Check for duplicate event_key+channel combination
        $exists = NotificationTemplate::where('event_key', $data['event_key'])
            ->where('channel', $data['channel'])
            ->exists();

        if ($exists) {
            return $this->error('Template for this event and channel already exists', 422);
        }

        $template = NotificationTemplate::create($data);

        return $this->created($template->fresh(), 'Template created');
    }

    public function showTemplate(string $templateId): JsonResponse
    {
        $template = NotificationTemplate::find($templateId);
        if (!$template) {
            return $this->notFound('Template not found');
        }

        return $this->success($template);
    }

    public function updateTemplate(Request $request, string $templateId): JsonResponse
    {
        $template = NotificationTemplate::find($templateId);
        if (!$template) {
            return $this->notFound('Template not found');
        }

        $data = $request->validate([
            'event_key' => 'sometimes|required|string|max:100',
            'channel' => 'sometimes|required|string|in:in_app,push,sms,email,whatsapp,sound',
            'title' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'body_ar' => 'nullable|string',
            'available_variables' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        // Check for duplicate event_key+channel if either field changed
        if (isset($data['event_key']) || isset($data['channel'])) {
            $eventKey = $data['event_key'] ?? $template->event_key;
            $channel = $data['channel'] ?? $template->channel;

            $exists = NotificationTemplate::where('event_key', $eventKey)
                ->where('channel', $channel)
                ->where('id', '!=', $template->id)
                ->exists();

            if ($exists) {
                return $this->error('Template for this event and channel already exists', 422);
            }
        }

        $template->update($data);

        return $this->success($template->fresh(), 'Template updated');
    }

    public function destroyTemplate(string $templateId): JsonResponse
    {
        $template = NotificationTemplate::find($templateId);
        if (!$template) {
            return $this->notFound('Template not found');
        }

        $template->delete();

        return $this->success(null, 'Template deleted');
    }

    public function toggleTemplate(string $templateId): JsonResponse
    {
        $template = NotificationTemplate::find($templateId);
        if (!$template) {
            return $this->notFound('Template not found');
        }

        $template->update(['is_active' => !$template->is_active]);

        return $this->success(
            $template->fresh(),
            $template->fresh()->is_active ? 'Template activated' : 'Template deactivated',
        );
    }
}
