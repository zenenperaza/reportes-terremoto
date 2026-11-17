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
            $entry = new BuzonEntry(Arr::only($request->validated(), [
                'opinion_date', 'place_name', 'full_name', 'phone', 'age', 'gender', 'disabilities',
                'afro_descendant', 'opinion', 'feedback_type', 'comment', 'improvement',
            ]));
            $entry->proyecto_id = $project->id;
            $entry->created_by = null;
            $entry->public_recipient_id = $owner->id;
            $entry->submitted_via_public_link = true;
            $entry->save();
        });

        return redirect()->away('https://asonacop.org/', 303)->withInput([]);
    }
}
