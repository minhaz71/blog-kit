<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\BackupResource;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Http\Request;

/**
 * Streams a backup archive to the browser over a plain authenticated GET.
 *
 * The Filament table action used to return `response()->download()` from
 * inside a Livewire action — Livewire inlines that as a base64 blob in its
 * JSON response, which balloons memory and times out for a real store's
 * multi-hundred-MB archive (the "spinner rotates forever, nothing downloads"
 * bug). A normal request streams the file directly (Symfony
 * BinaryFileResponse / range support) with no size ceiling.
 */
class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, Backup $backup)
    {
        // Same gate as the Backups screen (access_backups / manage settings);
        // a logged-in customer without it gets 403.
        abort_unless(BackupResource::canAccess(), 403);

        $path = storage_path('app/'.$backup->path);
        abort_unless($backup->status === 'completed' && is_file($path), 404);

        @set_time_limit(0);

        return response()->download($path, basename((string) $backup->path));
    }
}
