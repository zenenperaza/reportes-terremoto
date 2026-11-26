<?php

namespace Tests\Feature;

use App\Models\{BuzonEntry, Donante, Proyecto, User};
use App\Support\PublicBuzonLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuzonFeedbackRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Proyecto $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $donor = Donante::create(['nombre' => 'Donante de prueba', 'estatus' => true]);
        $this->project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'SOL-01', 'descripcion' => 'Proyecto de prueba', 'estatus' => true]);
    }

    private function payload(bool $public = false, array $extra = []): array
    {
        return array_replace(['opinion_date' => today()->toDateString(), 'place_name' => 'Lugar de prueba',
            'opinion' => 'very_good', 'feedback_type' => 'request', 'comment' => 'Solicitud de información'],
            $public ? ['submission_token' => Str::random(64)] : ['proyecto_id' => $this->project->id], $extra);
    }

    private function publicUrl(): string
    {
        return PublicBuzonLink::url($this->project, $this->admin);
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    public function test_both_forms_offer_four_feedback_radios_and_one_conditional_contact_group(): void
    {
        $internal = $this->actingAs($this->admin)->get(route('buzon.create'))->assertOk();
        auth()->logout();
        $public = $this->get($this->publicUrl())->assertOk();
        foreach ([$internal, $public] as $page) {
            $page->assertSee('Solicitud')->assertSee('js/buzon-feedback.js')->assertSee('css/buzon-feedback.css');
            $xpath = $this->xpath($page->getContent());
            $form = '//form[@data-buzon-entry or @data-public-buzon-form]';
            $this->assertSame(4, $xpath->query($form.'//input[@type="radio" and @name="feedback_type" and @required]')->length);
            $this->assertSame(0, $xpath->query($form.'//select[@name="feedback_type"]')->length);
            $this->assertSame('age_group', $xpath->query($form.'//input[not(@type="hidden") and not(@name="website")]')->item(0)->getAttribute('name'));
            $this->assertSame(0, $xpath->query($form.'//*[@data-buzon-contact and @hidden]')->length);
            foreach (['full_name', 'phone'] as $field) {
                $this->assertSame(1, $xpath->query($form.'//input[@name="'.$field.'"]')->length);
                $this->assertSame(0, $xpath->query($form.'//input[@name="'.$field.'" and @required]')->length);
            }
        }
    }

    public function test_internal_requests_require_both_contacts_and_validate_supplied_values(): void
    {
        $this->actingAs($this->admin);
        foreach ([[], ['full_name' => '  ', 'phone' => '  '], ['full_name' => ['invalid'], 'phone' => str_repeat('1', 41)]] as $extra) {
            $this->postJson(route('buzon.store'), $this->payload(false, $extra))->assertUnprocessable()
                ->assertJsonValidationErrors(['full_name', 'phone']);
        }
        $this->assertDatabaseCount('buzon_entries', 0);
        $this->assertDatabaseCount('buzon_lists', 0);
    }

    public function test_public_requests_require_contact_and_preserve_the_same_submission_after_correction(): void
    {
        $payload = $this->payload(true);
        $this->from($this->publicUrl())->post($this->publicUrl(), $payload)->assertRedirect($this->publicUrl())
            ->assertSessionHasErrors(['full_name', 'phone'], null, 'publicBuzon')->assertSessionMissing('public_buzon_success');
        $page = $this->get($this->publicUrl())->assertOk()
            ->assertSee('El nombre es obligatorio cuando la retroalimentación es una Solicitud.')
            ->assertSee('El teléfono es obligatorio cuando la retroalimentación es una Solicitud.');
        $xpath = $this->xpath($page->getContent());
        $this->assertSame(1, $xpath->query('//input[@name="feedback_type" and @value="request" and @checked]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-buzon-contact and @hidden]')->length);
        $this->assertSame(2, $xpath->query('//*[@data-buzon-contact]//input[@required]')->length);
        $this->assertSame($payload['submission_token'], $xpath->evaluate('string(//input[@name="submission_token"]/@value)'));
        $payload += ['full_name' => 'Persona de prueba', 'phone' => '04140000000'];
        $this->post($this->publicUrl(), $payload)->assertStatus(303)->assertSessionHasNoErrors();
        $this->post($this->publicUrl(), $payload)->assertStatus(303);
        $entry = BuzonEntry::sole();
        $this->assertSame('request', $entry->feedback_type);
        $this->assertSame('Persona de prueba', $entry->full_name);
        $this->assertTrue($entry->submitted_via_public_link);
        $this->assertDatabaseCount('buzon_lists', 0);
        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_other_feedback_types_still_accept_anonymous_internal_and_public_opinions(): void
    {
        foreach (['suggestion', 'comment', 'complaint'] as $kind) {
            $this->actingAs($this->admin)->post(route('buzon.store'), $this->payload(false, ['feedback_type' => $kind]))
                ->assertSessionHasNoErrors()->assertRedirect();
            auth()->logout();
            $this->post($this->publicUrl(), $this->payload(true, ['feedback_type' => $kind]))->assertStatus(303)->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('buzon_entries', 6);
        $this->assertSame(0, BuzonEntry::whereNotNull('full_name')->orWhereNotNull('phone')->count());
    }

    public function test_requests_can_be_consulted_edited_and_filtered_and_next_form_is_clean(): void
    {
        $payload = $this->payload(false, ['full_name' => 'Persona de prueba', 'phone' => '04140000000']);
        $saved = $this->actingAs($this->admin)->post(route('buzon.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $entry = BuzonEntry::sole();
        $next = $this->get($saved->headers->get('Location'))->assertOk();
        $nextXpath = $this->xpath($next->getContent());
        $this->assertSame(0, $nextXpath->query('//*[@data-buzon-contact and @hidden]')->length);
        $this->assertSame('', $nextXpath->evaluate('string(//form[@data-buzon-entry]//input[@name="full_name"]/@value)'));
        foreach ([route('buzon.show', $entry), route('buzon.edit', $entry)] as $url) {
            $page = $this->get($url)->assertOk()->assertSee('Persona de prueba');
            $xpath = $this->xpath($page->getContent());
            $this->assertSame(1, $xpath->query('//input[@name="feedback_type" and @value="request" and @checked]')->length);
            $this->assertSame(2, $xpath->query('//*[@data-buzon-contact]//input[@required]')->length);
        }
        $this->get(route('buzon.index', ['feedback_type' => 'request']))->assertOk()->assertSee('Persona de prueba');
        $this->putJson(route('buzon.update', $entry), $this->payload(false, ['version' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors(['full_name', 'phone']);
        $this->assertSame('Persona de prueba', $entry->fresh()->full_name);
        $this->put(route('buzon.update', $entry), $payload + ['version' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('request', $entry->fresh()->feedback_type);
    }

    public function test_historical_contact_is_still_visible_and_optional_outside_requests(): void
    {
        $entry = BuzonEntry::create($this->payload(false, ['feedback_type' => 'comment', 'full_name' => 'Nombre histórico',
            'phone' => '04140000000']) + ['created_by' => $this->admin->id]);
        foreach ([route('buzon.show', $entry), route('buzon.edit', $entry)] as $url) {
            $page = $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('Nombre histórico');
            $xpath = $this->xpath($page->getContent());
            $this->assertSame(0, $xpath->query('//*[@data-buzon-contact and @hidden]')->length);
            $this->assertSame(0, $xpath->query('//*[@data-buzon-contact]//input[@required]')->length);
        }
    }

    public function test_gender_and_afro_are_optional_radios_with_no_indicado_as_default(): void
    {
        $internal = $this->actingAs($this->admin)->get(route('buzon.create'))->assertOk();
        auth()->logout();
        $public = $this->get($this->publicUrl())->assertOk();
        foreach ([$internal, $public] as $page) {
            $xpath = $this->xpath($page->getContent());
            foreach (['gender' => 4, 'afro_descendant' => 3] as $field => $count) {
                $this->assertSame(0, $xpath->query('//select[@name="'.$field.'"]')->length);
                $this->assertSame($count, $xpath->query('//input[@type="radio" and @name="'.$field.'"]')->length);
                $this->assertSame(1, $xpath->query('//input[@name="'.$field.'" and @checked and @value=""]')->length);
                $this->assertSame(0, $xpath->query('//input[@name="'.$field.'" and @required]')->length);
                $this->assertSame(1, $xpath->query('//fieldset[@data-buzon-demographic="'.$field.'"]')->length);
            }
        }
    }

    public function test_demographic_radios_preserve_saved_gender_and_afro_yes_no_and_null(): void
    {
        foreach ([['female', true, '1'], ['male', false, '0'], ['non_binary_other', null, '']] as [$gender, $afro, $expected]) {
            $entry = BuzonEntry::create($this->payload(false, ['feedback_type' => 'comment', 'gender' => $gender,
                'afro_descendant' => $afro]) + ['created_by' => $this->admin->id]);
            foreach ([route('buzon.show', $entry), route('buzon.edit', $entry)] as $url) {
                $page = $this->actingAs($this->admin)->get($url)->assertOk();
                $xpath = $this->xpath($page->getContent());
                $this->assertSame(1, $xpath->query('//input[@name="gender" and @checked and @value="'.$gender.'"]')->length);
                $this->assertSame(1, $xpath->query('//input[@name="afro_descendant" and @checked and @value="'.$expected.'"]')->length);
            }
            $this->assertSame($afro, $entry->fresh()->afro_descendant);
        }
    }

    public function test_public_validation_restores_gender_and_afro_radios_without_losing_no_value(): void
    {
        $payload = $this->payload(true, ['feedback_type' => 'comment', 'gender' => 'female', 'afro_descendant' => '0', 'place_name' => '']);
        $this->post($this->publicUrl(), $payload)->assertSessionHasErrors('place_name', null, 'publicBuzon');
        $xpath = $this->xpath($this->get($this->publicUrl())->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//input[@name="gender" and @checked and @value="female"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="afro_descendant" and @checked and @value="0"]')->length);
        $payload['place_name'] = 'Lugar corregido';
        $this->post($this->publicUrl(), $payload)->assertStatus(303)->assertSessionHasNoErrors();
        $this->assertFalse(BuzonEntry::sole()->afro_descendant);
        $this->assertSame('female', BuzonEntry::sole()->gender);
    }
}
