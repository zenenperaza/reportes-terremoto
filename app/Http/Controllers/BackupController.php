<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(): View
    {
        $disk = Storage::disk('local');
        $backups = collect($disk->files(DatabaseBackupService::DIRECTORY))
            ->filter(fn (string $path): bool => $this->validPath($path))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => $disk->size($path),
                'modified_at' => $disk->lastModified($path),
            ])
            ->sortByDesc('modified_at')
            ->values();

        return view('backups.index', compact('backups'));
    }

    public function store(DatabaseBackupService $backupService): RedirectResponse
    {
        try {
            $filename = $backupService->create();

            return redirect()->route('backups.index')->with('success', "Respaldo {$filename} generado correctamente.");
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('backups.index')->with('error', 'No fue posible generar el respaldo: '.$exception->getMessage());
        }
    }

    public function download(string $backup): StreamedResponse
    {
        $path = $this->path($backup);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $backup, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(string $backup): RedirectResponse
    {
        $path = $this->path($backup);
        abort_unless(Storage::disk('local')->exists($path), 404);
        Storage::disk('local')->delete($path);

        return redirect()->route('backups.index')->with('success', "Respaldo {$backup} eliminado correctamente.");
    }

    private function path(string $backup): string
    {
        abort_unless(preg_match('/^asonacop-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}-[a-f0-9]{6}\.sql\.gz$/', $backup) === 1, 404);

        return DatabaseBackupService::DIRECTORY.'/'.$backup;
    }

    private function validPath(string $path): bool
    {
        return preg_match('/^backups\/asonacop-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}-[a-f0-9]{6}\.sql\.gz$/', str_replace('\\', '/', $path)) === 1;
    }
}
