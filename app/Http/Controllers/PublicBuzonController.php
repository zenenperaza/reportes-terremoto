<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePublicBuzonEntryRequest;
use App\Models\{BuzonEntry, Proyecto, SystemSetting, User};
use App\Support\PublicBuzonLink;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PublicBuzonController extends Controller
{
    public function show(Proyecto $proyecto, string $codigo, User $recipient)
    {
        if (session('public_buzon_success') === ['project_id' => $proyecto->id, 'recipient_id' => $recipient->id]) {
            session()->forget('public_buzon_success');
            return view('buzon.public.thanks');
        }
        return view('buzon.public.form', [
            'project' => $proyecto, 'publicUrl' => PublicBuzonLink::url($proyecto, $recipient),
        ]);
    }

    public function store(SavePublicBuzonEntryRequest $request, Proyecto $proyecto, string $codigo, User $recipient)
    {
        DB::transaction(function () use ($request, $proyecto, $recipient): void {
            $project = Proyecto::whereKey($proyecto->id)->lockForUpdate()->firstOrFail();
            $owner = User::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            abort_unless(PublicBuzonLink::available($project, $owner), 404);
            abort_if(SystemSetting::maintenanceEnabled(), 503);
            // Serialize submissions for this project and persist a unique intent key.
            // A replay is successful but never creates another opinion, even without JavaScript.
            $submissionKey = hash('sha256', $project->id.'|'.$owner->id.'|'.$request->validated('submission_token'));
            if (BuzonEntry::where('public_submission_key', $submissionKey)->exists()) return;
            $entry = new BuzonEntry(Arr::only($request->validated(), [
                'opinion_date', 'place_name', 'full_name', 'phone', 'age', 'age_group', 'gender', 'disabilities',
                'afro_descendant', 'opinion', 'feedback_type', 'comment', 'improvement',
            ]));
            $entry->proyecto_id = $project->id;
            $entry->created_by = null;
            $entry->public_recipient_id = $owner->id;
            $entry->submitted_via_public_link = true;
            $entry->public_submission_key = $submissionKey;
            $entry->save();
        });

        return redirect()->to(PublicBuzonLink::url($proyecto, $recipient), 303)->withInput([])
            ->with('public_buzon_success', ['project_id' => $proyecto->id, 'recipient_id' => $recipient->id]);
    }
}
