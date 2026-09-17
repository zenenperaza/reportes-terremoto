<?php

namespace App\Http\Controllers;

use App\Models\CaseAttachment;
use App\Models\CaseRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaseAttachmentController extends Controller
{
    public function store(Request $request, CaseRecord $caseRecord)
    {
        Gate::authorize('update', $caseRecord);
        $category = $request->validate(['category' => ['required', Rule::in(['photo', 'audio', 'document'])]])['category'];
        $extensions = match ($category) {
            'photo' => 'png,jpg,jpeg,gif', 'audio' => 'mp3,m4a',
            default => 'pdf,txt,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
        };
        $rules = ['required', 'file', 'max:15360', 'mimes:'.$extensions, 'extensions:'.$extensions];
        if ($category === 'photo') {
            $rules[] = 'image';
        }
        $request->validate(['file' => $rules]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $caseRecord, $category, &$path): void {
                $case = CaseRecord::whereKey($caseRecord->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $case);
                if ($case->attachments()->count() >= 100) {
                    throw ValidationException::withMessages(['file' => 'El expediente alcanzó el límite de 100 archivos.']);
                }
                $file = $request->file('file');
                $path = $file->store('case-attachments/'.$case->reference, 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['file' => 'No fue posible guardar el archivo.']);
                }
                $case->attachments()->create(['uploaded_by' => $request->user()->id, 'category' => $category, 'path' => $path, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                $case->events()->create(['user_id' => $request->user()->id, 'user_name' => $request->user()->name, 'action' => 'uploaded', 'changed_fields' => []]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('cases.show', $caseRecord)->with('success', 'Archivo guardado en almacenamiento privado.');
    }

    public function download(Request $request, CaseRecord $caseRecord, CaseAttachment $attachment)
    {
        Gate::authorize('view', $caseRecord);
        abort_unless($attachment->case_record_id === $caseRecord->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        $caseRecord->events()->create(['user_id' => $request->user()->id, 'user_name' => $request->user()->name, 'action' => 'downloaded', 'changed_fields' => []]);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }
}
