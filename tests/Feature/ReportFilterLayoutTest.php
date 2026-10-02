<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFilterLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_record_views_keep_filters_in_two_desktop_rows_and_one_get_form(): void
    {
        foreach (['admin', 'reporter'] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('reports.index'))->assertOk();
            $previous = libxml_use_internal_errors(true);
            $dom = new \DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($dom);
            $form = $xpath->query('//form[contains(@class,"report-list-filters")]');
            $this->assertCount(1, $form);
            $this->assertSame('get', $form->item(0)->getAttribute('method'));
            $this->assertCount(3, $xpath->query('./*[contains(@class,"report-filter-top")]', $form->item(0)));
            $this->assertCount(2, $xpath->query('./label[contains(@class,"report-filter-date")]', $form->item(0)));
            foreach (['reported', 'state_id[]', 'user_id', 'reporting_period[]'] as $name) {
                $this->assertCount(1, $xpath->query('.//select[@name="'.$name.'"]', $form->item(0)));
            }
            $this->assertCount(1, $xpath->query('.//select[@id="reporting-period"][@multiple]', $form->item(0)));
            $this->assertCount(1, $xpath->query('.//select[@id="records-state-filter"][@multiple]', $form->item(0)));
            $this->assertCount(1, $xpath->query('.//input[@type="hidden"][@name="reporting_period[]"]', $form->item(0)));
            $this->assertCount(1, $xpath->query('.//button[@type="submit"]', $form->item(0)));
        }
    }
}
