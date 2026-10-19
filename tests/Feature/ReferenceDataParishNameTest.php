<?php

namespace Tests\Feature;

use App\Models\Parish;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataParishNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_catalog_import_keeps_the_canonical_name_instead_of_the_excel_serial(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $parish = Parish::where('code', 'VE010122')->with('municipality.state')->sole();
        $this->assertSame('23 de Enero', $parish->name);
        $this->assertSame('VE0101', $parish->municipality->code);
        $this->assertSame('VE01', $parish->municipality->state->code);
    }
}
