<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Parish;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LocationLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_coordinates_match_the_location_catalog(): void
    {
        RateLimiter::clear('nominatim-reverse-geocode');
        config(['services.nominatim.url' => 'https://nominatim.test']);
        Http::fake([
            'https://nominatim.test/reverse*' => Http::response([
                'address' => [
                    'country_code' => 've',
                    'state' => 'Estado Amazonas',
                    'county' => 'Municipio Alto Orinoco',
                    'village' => 'Huachamacare',
                ],
            ]),
        ]);

        $state = State::create(['code' => 'VEZ', 'name' => 'Amazonas']);
        $municipality = Municipality::create([
            'state_id' => $state->id,
            'code' => 'VEZ01',
            'name' => 'Autónomo Alto Orinoco',
        ]);
        $parish = Parish::create([
            'municipality_id' => $municipality->id,
            'code' => 'VEZ0101',
            'name' => 'Huachamacare',
        ]);
        $user = User::factory()->create(['role' => 'reporter']);

        $this->actingAs($user)
            ->getJson('/ubicaciones/coordenadas?latitude=3.50000&longitude=-66.90000')
            ->assertOk()
            ->assertJsonPath('location.state.id', $state->id)
            ->assertJsonPath('location.municipality.id', $municipality->id)
            ->assertJsonPath('location.parish.id', $parish->id)
            ->assertJsonPath('message', 'Ubicación identificada: Amazonas, Autónomo Alto Orinoco, Huachamacare.');

        Http::assertSentCount(1);
    }

    public function test_coordinates_outside_venezuela_are_rejected(): void
    {
        RateLimiter::clear('nominatim-reverse-geocode');
        config(['services.nominatim.url' => 'https://nominatim.test']);
        Http::fake([
            'https://nominatim.test/reverse*' => Http::response([
                'address' => ['country_code' => 'co'],
            ]),
        ]);

        $user = User::factory()->create(['role' => 'reporter']);

        $this->actingAs($user)
            ->getJson('/ubicaciones/coordenadas?latitude=4.5709&longitude=-74.2973')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Las coordenadas no corresponden al territorio venezolano. Verifique la latitud y longitud.');
    }
}
