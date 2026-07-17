<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\Sector;
use App\Models\State;

class LocationController extends Controller
{
    public function municipalities(State $state)
    {
        return $state->municipalities()->orderBy('name')->get(['id', 'name']);
    }

    public function parishes(Municipality $municipality)
    {
        return $municipality->parishes()->orderBy('name')->get(['id', 'name']);
    }

    public function activities(Sector $sector)
    {
        return $sector->activities()->where('active', true)->orderBy('sort_order')->get(['id', 'code', 'title']);
    }
}
