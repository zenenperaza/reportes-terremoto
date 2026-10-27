<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFilterLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_beneficiary_date_pairs_stay_grouped_and_summary_immediately_precedes_indicator_picker(): void
    {
        foreach (['admin', 'coordinator', 'reporter'] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('beneficiaries.summary'))->assertOk();
            $previous = libxml_use_internal_errors(true);
            $dom = new \DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($dom);
            $form = $xpath->query('//form[@id="beneficiary-report-filters"]')->item(0);
            $groups = $xpath->query('./div[contains(@class,"beneficiary-date-range")][@role="group"]', $form);
            $this->assertCount(2, $groups);
            foreach ([['from', 'to'], ['included_from', 'included_to']] as $index => $names) {
                $inputs = $xpath->query('./label/input[@type="date"][@data-period-date]', $groups->item($index));
                $this->assertCount(2, $inputs);
                $this->assertSame($names, array_map(fn ($input) => $input->getAttribute('name'), iterator_to_array($inputs)));
                foreach ($inputs as $input) {
                    $helpId = $input->getAttribute('aria-describedby');
                    $this->assertCount(1, $xpath->query('./small[@id="'.$helpId.'"][@data-period-date-help]', $input->parentNode));
                }
            }
            $this->assertCount(1, $xpath->query('./div[contains(@class,"beneficiary-summary-filter")]//select[@name="summary_indicator_ids[]"]', $form));
            $picker = $xpath->query('./fieldset[@id="summary-indicator-picker"]', $form);
            $this->assertCount(1, $picker);
            $this->assertCount(1, $xpath->query('preceding-sibling::*[1][contains(@class,"beneficiary-summary-filter")]', $picker->item(0)));
            $this->assertCount(0, $xpath->query('preceding-sibling::div[contains(@class,"beneficiary-summary-filter")]', $groups->item(0)));
            $this->assertCount(0, $xpath->query('./br | ./label/input[@type="date"]', $form));
        }
    }

    public function test_record_views_order_reported_period_summary_then_remaining_filters_in_one_get_form(): void
    {
        foreach (['admin', 'coordinator', 'reporter'] as $role) {
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
            $summaryField = $xpath->query('./div[contains(@class,"report-filter-summary")]', $form->item(0));
            $this->assertCount(1, $summaryField);
            $this->assertCount(1, $xpath->query('.//select[@name="summary_indicator_ids[]"][@multiple]', $summaryField->item(0)));
            $this->assertCount(1, $xpath->query('./label[contains(@class,"report-filter-reported")]', $form->item(0)));
            $this->assertCount(1, $xpath->query('./div[contains(@class,"report-filter-period")]', $form->item(0)));
            $fields = $xpath->query('./*[contains(@class,"report-filter-field")]', $form->item(0));
            $names = [];
            foreach ($fields as $field) {
                $names[] = $xpath->query('.//select | .//input[not(@type="hidden")]', $field)->item(0)->getAttribute('name');
            }
            $this->assertSame(['reported', 'reporting_period[]', 'summary_indicator_ids[]', 'state_id[]', 'from', 'to', 'user_id'], $names);
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
