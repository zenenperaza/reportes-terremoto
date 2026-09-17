<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseAttachment;
use App\Models\CaseRecord;
use App\Models\FamilyRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CaseFamiliesAndFormsTest extends TestCase
{
    use RefreshDatabase;

    private function worker(array $extra = []): User
    {
        $user = User::factory()->create(['role' => 'reporter', 'is_active' => true]);
        $user->givePermissionTo(array_merge(['ver casos', 'crear casos', 'editar casos', 'ver historial de casos'], $extra));

        return $user;
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['full_name' => 'Persona de prueba', 'registered_on' => today()->toDateString(), 'case_type' => 'general', 'sex' => 'not_specified', 'risk_level' => 'pending', 'consent_status' => 'pending', 'share_services' => '0', 'share_reports' => '0', 'form_complete' => '1'], $extra);
    }

    private function record(User $user, array $extra = []): CaseRecord
    {
        $data = $this->payload($extra);
        unset($data['form_complete']);

        return CaseRecord::create($data + ['reference' => 'CS-'.Str::ulid(), 'assigned_to' => $user->id, 'created_by' => $user->id]);
    }

    private function family(User $user, array $extra = []): FamilyRecord
    {
        return FamilyRecord::create($extra + ['reference' => 'FA-'.Str::ulid(), 'name' => 'Familia de prueba', 'registered_on' => today(), 'assigned_to' => $user->id]);
    }

    private function htmlPayload(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $pairs = [];
        foreach ($xpath->query('//*[@id="case-editor"]//*[self::input or self::select or self::textarea][@name and not(@disabled) and not(ancestor::template)]') as $control) {
            if (in_array($control->getAttribute('type'), ['radio', 'checkbox']) && ! $control->hasAttribute('checked')) {
                continue;
            }
            $name = $control->getAttribute('name');
            if ($control->tagName === 'select') {
                $selected = $xpath->query('.//option[@selected]', $control);
                if (! $selected->length && ! $control->hasAttribute('multiple')) {
                    $selected = $xpath->query('.//option[1]', $control);
                }
                foreach ($selected as $option) {
                    $pairs[] = urlencode($name).'='.urlencode($option->getAttribute('value'));
                }
            } else {
                $pairs[] = urlencode($name).'='.urlencode($control->tagName === 'textarea' ? $control->textContent : $control->getAttribute('value'));
            }
        }
        parse_str(implode('&', $pairs), $payload);

        return $payload;
    }

    public function test_actual_rendered_case_and_family_forms_submit_and_edit_successfully(): void
    {
        foreach ([$this->worker(), $this->worker(['supervisar casos'])] as $user) {
            $response = $this->actingAs($user)->get(route('cases.create'))->assertOk();
            $payload = $this->htmlPayload($response->getContent());
            $payload['full_name'] = 'Prueba de formulario real';
            $this->post(route('cases.store'), $payload)->assertSessionHasNoErrors();
            $case = CaseRecord::latest('id')->firstOrFail();
            $response = $this->get(route('cases.edit', $case))->assertOk();
            $edit = $this->htmlPayload($response->getContent());
            $edit['full_name'] = 'Formulario actualizado';
            $this->put(route('cases.update', $case), $edit)->assertSessionHasNoErrors();
            $this->assertSame('Formulario actualizado', $case->fresh()->full_name);
            $response = $this->get(route('families.create'))->assertOk();
            $familyPayload = $this->htmlPayload($response->getContent());
            $familyPayload['name'] = 'Familia desde formulario';
            $this->post(route('families.store'), $familyPayload)->assertSessionHasNoErrors();
        }
    }

    public function test_family_registration_members_encryption_and_case_association(): void
    {
        $owner = $this->worker();
        $this->actingAs($owner)->get(route('families.create'))->assertOk()->assertSee('Integrantes de la familia');
        $this->post(route('families.store'), [
            'name' => 'Familia local', 'registered_on' => today()->toDateString(), 'restricted' => '0', 'form_complete' => '1',
            'details' => ['address' => 'Dirección reservada'], 'members' => [['name' => 'Integrante reservado', 'age' => 8, 'role' => 'Hijo']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $family = FamilyRecord::firstOrFail();
        $this->assertSame('Integrante reservado', $family->members[0]['name']);
        $this->assertStringNotContainsString('Integrante reservado', DB::table('family_records')->value('members'));
        $this->assertNull(AuditLog::where('route_name', 'families.store')->value('request_payload'));
        $this->post(route('cases.store'), $this->payload(['family_record_id' => $family->id]))->assertSessionHasNoErrors();
        $case = CaseRecord::firstOrFail();
        $this->assertSame($family->id, $case->family_record_id);
        $this->get(route('families.show', $family))->assertOk()->assertSee($case->reference)->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('cases.show', $case))->assertOk()->assertSee('Integrante reservado');
        $this->get(route('cases.edit', $case))->assertOk()->assertSee('form_data[notes][rows]', false)->assertSee('family_record_id');
        $this->assertDatabaseHas('family_events', ['family_record_id' => $family->id, 'action' => 'viewed']);
    }

    public function test_family_does_not_expose_other_assignees_or_vbg_cases(): void
    {
        $owner = $this->worker();
        $peer = $this->worker();
        $vbgOwner = $this->worker(['gestionar casos vbg']);
        $family = $this->family($owner);
        $visible = $this->record($owner, ['family_record_id' => $family->id]);
        $hidden = $this->record($peer, ['full_name' => 'Nombre de otro responsable', 'family_record_id' => $family->id]);
        $sensitive = $this->record($vbgOwner, ['case_type' => 'vbg', 'full_name' => 'Nombre confidencial VBG', 'family_record_id' => $family->id]);
        $this->actingAs($owner)->get(route('families.show', $family))->assertOk()->assertSee($visible->reference)->assertDontSee($hidden->reference)->assertDontSee($sensitive->reference)->assertDontSee('Nombre confidencial VBG');
        $this->actingAs($peer)->get(route('families.show', $family))->assertForbidden();
        $this->get(route('families.edit', $family))->assertForbidden();
        $this->get(route('cases.show', $hidden))->assertOk()->assertDontSee('Familia de prueba');
        $this->post(route('cases.store'), $this->payload(['family_record_id' => $family->id]))->assertSessionHasErrors('family_record_id');
        $restricted = $this->family($vbgOwner, ['restricted' => true, 'name' => 'Familia reservada']);
        $supervisor = $this->worker(['supervisar casos']);
        $this->actingAs($supervisor)->get(route('families.index'))->assertOk()->assertDontSee('Familia reservada');
        $this->get(route('families.show', $restricted))->assertForbidden();
    }

    public function test_repeatable_forms_round_trip_clear_rows_and_reject_unknown_fields(): void
    {
        $owner = $this->worker();
        $data = ['notes' => ['rows' => [['date' => today()->toDateString(), 'subject' => 'Seguimiento', 'notes' => 'Contenido privado']]],
            'protection' => ['concerns' => ['neglect', 'separated']],
            'plan' => ['started_on' => today()->toDateString(), 'rows' => [['service' => 'Apoyo', 'due_date' => today()->addDays(30)->toDateString()]]]];
        $this->actingAs($owner)->post(route('cases.store'), $this->payload(['form_data' => $data]))->assertSessionHasNoErrors();
        $case = CaseRecord::firstOrFail();
        $this->assertSame('Contenido privado', data_get($case->form_data, 'notes.rows.0.notes'));
        $this->assertSame(3, $case->workflow_stage);
        $this->assertStringNotContainsString('Contenido privado', DB::table('case_records')->value('form_data'));
        $this->get(route('cases.edit', $case))->assertOk()->assertSee('Contenido privado');
        $this->put(route('cases.update', $case), $this->payload(['version' => 1, 'form_data' => ['notes' => ['rows' => '']]]))->assertSessionHasNoErrors();
        $this->assertSame([], data_get($case->fresh()->form_data, 'notes.rows'));
        $this->assertSame('Apoyo', data_get($case->fresh()->form_data, 'plan.rows.0.service'));
        $this->put(route('cases.update', $case), $this->payload(['version' => 2, 'form_data' => ['notes' => ['rows' => [['unknown' => 'injection']]]]]))->assertSessionHasErrors('form_data.notes.rows.0');
        $this->put(route('cases.update', $case), $this->payload(['version' => 2, 'form_data' => ['notes' => ['rows' => []]], 'form_complete' => null]))->assertSessionHasErrors('form_data');
        $this->assertSame(2, $case->fresh()->version);
    }

    public function test_all_dynamic_fields_are_validated_and_render_without_losing_values(): void
    {
        $supervisor = $this->worker(['supervisar casos']);
        $data = [];
        $value = fn ($field) => match ($field['type']) {
            'select', 'radio' => array_key_first($field['options']), 'multi' => [array_key_first($field['options'])],
            'date', 'past_date' => today()->toDateString(), 'time' => '10:30', 'number' => 25, default => 'Texto de prueba',
        };
        foreach (config('case-forms.sections') as $id => $section) {
            foreach ($section['fields'] ?? [] as $key => $field) {
                $data[$id][$key] = $value($field);
            }
            foreach ($section['repeat']['fields'] ?? [] as $key => $field) {
                $data[$id]['rows'][0][$key] = $value($field);
            }
        }
        $this->actingAs($supervisor)->post(route('cases.store'), $this->payload(['form_data' => $data]))->assertSessionHasNoErrors();
        $case = CaseRecord::firstOrFail();
        $this->assertEquals($data, $case->form_data);
        $this->get(route('cases.show', $case))->assertOk()->assertSee('Acuerdos de cuidado')->assertSee('Historial de cambios')->assertSee('Registro de accesos');
        $this->get(route('cases.edit', $case))->assertOk()->assertSee('form_data[services][rows][0][service_type]', false);
    }

    public function test_approval_and_closure_require_supervision_and_preserve_existing_approvals(): void
    {
        $owner = $this->worker();
        $case = $this->record($owner, ['form_data' => ['plan' => ['approved' => 'yes', 'approved_date' => today()->toDateString(), 'approval_status' => 'approved']]]);
        $this->actingAs($owner)->put(route('cases.update', $case), $this->payload(['version' => 1, 'form_data' => ['plan' => ['approved' => 'yes']]]))->assertSessionHasErrors('form_data.plan.approved');
        $this->put(route('cases.update', $case), $this->payload(['version' => 1, 'status' => 'closed']))->assertSessionHasErrors('status');
        $this->put(route('cases.update', $case), $this->payload(['version' => 1, 'form_data' => ['plan' => ['started_on' => today()->toDateString()]]]))->assertSessionHasNoErrors();
        $this->assertSame('approved', data_get($case->fresh()->form_data, 'plan.approval_status'));
        $supervisor = $this->worker(['supervisar casos']);
        $this->actingAs($supervisor)->put(route('cases.update', $case), $this->payload(['version' => 2, 'status' => 'closed', 'form_data' => ['closure' => ['reason' => 'formal', 'date' => today()->toDateString()]]]))->assertSessionHasNoErrors();
        $this->assertSame(6, $case->fresh()->workflow_stage);
    }

    public function test_family_validation_and_concurrency(): void
    {
        $owner = $this->worker();
        $family = $this->family($owner);
        $payload = ['name' => 'Familia editada', 'registered_on' => today()->toDateString(), 'restricted' => '0', 'form_complete' => '1', 'version' => 1];
        $this->actingAs($owner)->put(route('families.update', $family), $payload + ['members' => [['name' => null]]])->assertSessionHasErrors('members.0.name');
        $this->put(route('families.update', $family), $payload + ['members' => [['name' => 'Integrante', 'birth_date' => today()->addDay()->toDateString()]]])->assertSessionHasErrors('members.0.birth_date');
        $this->put(route('families.update', $family), $payload)->assertSessionHasNoErrors();
        $this->put(route('families.update', $family), $payload)->assertSessionHasErrors('version');
        $this->assertSame(2, $family->fresh()->version);
    }

    public function test_attachments_are_private_scoped_and_audited(): void
    {
        Storage::fake('local');
        $owner = $this->worker();
        $peer = $this->worker();
        $case = $this->record($owner);
        $other = $this->record($owner);
        $this->actingAs($owner)->post(route('cases.attachments.store', $case), ['category' => 'document', 'file' => UploadedFile::fake()->createWithContent('informe.txt', 'Contenido privado')])->assertSessionHasNoErrors();
        $attachment = CaseAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('cases.attachments.download', [$case, $attachment]))->assertOk()->assertDownload('informe.txt')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('case_events', ['case_record_id' => $case->id, 'action' => 'downloaded']);
        $this->get(route('cases.attachments.download', [$other, $attachment]))->assertNotFound();
        $this->actingAs($peer)->get(route('cases.attachments.download', [$case, $attachment]))->assertForbidden();
        $this->post(route('cases.attachments.store', $case), ['category' => 'document', 'file' => UploadedFile::fake()->createWithContent('test.txt', 'text')])->assertForbidden();
        $this->actingAs($owner)->post(route('cases.attachments.store', $case), ['category' => 'photo', 'file' => UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;')->mimeType('text/x-php')])->assertSessionHasErrors('file');
        $this->assertSame(1, CaseAttachment::count());
    }
}
