<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAnnouncementsAccess();

        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');

        $announcements = Announcement::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('badge_text', 'like', "%{$search}%")
                        ->orWhere('link_label', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($statusFilter === 'live', fn ($q) => $q->activeNow())
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Announcement::query()->count(),
            'active' => Announcement::query()->where('is_active', true)->count(),
            'live_now' => Announcement::query()->activeNow()->count(),
            'inactive' => Announcement::query()->where('is_active', false)->count(),
        ];

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'stats' => $stats,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAnnouncementsAccess();

        $validated = $this->validateAnnouncement($request);

        /** @var Announcement $announcement */
        $announcement = DB::transaction(function () use ($validated, $request): Announcement {
            $linkUrl = Announcement::sanitizeLinkUrl($validated['link_url'] ?? null);

            $announcement = Announcement::query()->create([
                'title' => trim((string) $validated['title']),
                'badge_text' => isset($validated['badge_text']) && trim((string) $validated['badge_text']) !== ''
                    ? trim((string) $validated['badge_text'])
                    : null,
                'link_label' => isset($validated['link_label']) && trim((string) $validated['link_label']) !== ''
                    ? trim((string) $validated['link_label'])
                    : null,
                'link_url' => $linkUrl,
                'icon' => isset($validated['icon']) && trim((string) $validated['icon']) !== ''
                    ? trim((string) $validated['icon'])
                    : null,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => $request->boolean('is_active', true),
                'starts_at' => ! empty($validated['starts_at']) ? Carbon::parse((string) $validated['starts_at']) : null,
                'ends_at' => ! empty($validated['ends_at']) ? Carbon::parse((string) $validated['ends_at']) : null,
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'announcement_created',
                'info',
                "Anuncio «{$announcement->title}» creado en el Topbar.",
                ['announcement_id' => $announcement->id],
            );

            return $announcement;
        });

        return redirect()
            ->route('admin.announcements.index')
            ->with('admin_success', "Anuncio «{$announcement->title}» creado correctamente.");
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeAnnouncementsAccess();

        $validated = $this->validateAnnouncement($request);

        DB::transaction(function () use ($announcement, $validated, $request): void {
            /** @var Announcement $locked */
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            $linkUrl = Announcement::sanitizeLinkUrl($validated['link_url'] ?? null);

            $locked->update([
                'title' => trim((string) $validated['title']),
                'badge_text' => isset($validated['badge_text']) && trim((string) $validated['badge_text']) !== ''
                    ? trim((string) $validated['badge_text'])
                    : null,
                'link_label' => isset($validated['link_label']) && trim((string) $validated['link_label']) !== ''
                    ? trim((string) $validated['link_label'])
                    : null,
                'link_url' => $linkUrl,
                'icon' => isset($validated['icon']) && trim((string) $validated['icon']) !== ''
                    ? trim((string) $validated['icon'])
                    : null,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => $request->boolean('is_active'),
                'starts_at' => ! empty($validated['starts_at']) ? Carbon::parse((string) $validated['starts_at']) : null,
                'ends_at' => ! empty($validated['ends_at']) ? Carbon::parse((string) $validated['ends_at']) : null,
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'announcement_updated',
                'info',
                "Anuncio «{$locked->title}» actualizado.",
                ['announcement_id' => $locked->id],
            );
        });

        return redirect()
            ->route('admin.announcements.index')
            ->with('admin_success', 'Anuncio actualizado correctamente.');
    }

    public function toggle(Announcement $announcement): RedirectResponse
    {
        $this->authorizeAnnouncementsAccess();

        $newState = DB::transaction(function () use ($announcement): bool {
            /** @var Announcement $locked */
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            $target = ! (bool) $locked->is_active;

            $locked->update(['is_active' => $target]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'announcement_toggled',
                'info',
                "Anuncio «{$locked->title}» " . ($target ? 'activado' : 'desactivado') . '.',
                ['announcement_id' => $locked->id, 'is_active' => $target],
            );

            return $target;
        });

        return redirect()
            ->route('admin.announcements.index')
            ->with('admin_success', 'Anuncio ' . ($newState ? 'activado' : 'desactivado') . ' correctamente.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorizeAnnouncementsAccess();

        $title = $announcement->title;
        $id = $announcement->id;

        DB::transaction(function () use ($announcement, $title, $id): void {
            $announcement->delete();

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'announcement_deleted',
                'warning',
                "Anuncio «{$title}» eliminado.",
                ['announcement_id' => $id],
            );
        });

        return redirect()
            ->route('admin.announcements.index')
            ->with('admin_success', "Anuncio «{$title}» eliminado correctamente.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAnnouncement(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:200'],
            'badge_text' => ['nullable', 'string', 'max:60'],
            'link_label' => ['nullable', 'string', 'max:80'],
            'link_url' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $raw = trim((string) $value);
                    if ($raw !== '' && Announcement::sanitizeLinkUrl($raw) === null) {
                        $fail('El enlace debe ser una ruta interna (/catalogo) o una URL http(s):// segura.');
                    }
                },
            ],
            'icon' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => [
                'nullable',
                'date',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $startsAt = $request->input('starts_at');
                    if ($startsAt !== null && trim((string) $startsAt) !== '' && $value !== null && trim((string) $value) !== '') {
                        try {
                            if (Carbon::parse((string) $value)->lt(Carbon::parse((string) $startsAt))) {
                                $fail('La fecha de finalización debe ser igual o posterior al inicio.');
                            }
                        } catch (\Throwable) {
                            // Handled by 'date' rule
                        }
                    }
                },
            ],
        ], [
            'title.required' => 'Ingresa el mensaje del anuncio.',
            'title.min' => 'El mensaje del anuncio debe tener al menos 4 caracteres.',
        ]);
    }

    private function authorizeAnnouncementsAccess(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && (bool) $user->is_active && $user->can('announcements.manage'), 403);
    }
}
