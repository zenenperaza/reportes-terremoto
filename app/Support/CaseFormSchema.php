<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class CaseFormSchema
{
    public static function fieldsRules(string $prefix, array $fields, bool $supervisor = false): array
    {
        $rules = [$prefix => ['nullable', 'array:'.implode(',', array_keys($fields))]];
        foreach ($fields as $key => $field) {
            $path = $prefix.'.'.$key;
            if (($field['supervisor'] ?? false) && ! $supervisor) {
                $rules[$path] = ['prohibited'];

                continue;
            }
            $rules[$path] = match ($field['type']) {
                'select', 'radio' => ['nullable', Rule::in(array_keys($field['options']))],
                'multi' => ['nullable', 'array', 'max:30'],
                'past_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
                'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01'],
                'time' => ['nullable', 'date_format:H:i'],
                'number' => ['nullable', 'integer', 'between:0,130'],
                'textarea' => ['nullable', 'string', 'max:10000'],
                default => ['nullable', 'string', 'max:250'],
            };
            if ($field['type'] === 'multi') {
                $rules[$path.'.*'] = ['required', Rule::in(array_keys($field['options'])), 'distinct'];
            }
        }

        return $rules;
    }

    public static function rules(bool $supervisor): array
    {
        $sections = config('case-forms.sections');
        $rules = ['form_data' => ['nullable', 'array:'.implode(',', array_keys($sections))]];
        foreach ($sections as $id => $section) {
            $fields = $section['fields'] ?? [];
            $keys = array_keys($fields);
            if (isset($section['repeat'])) {
                $keys[] = 'rows';
            }
            $rules += self::fieldsRules('form_data.'.$id, $fields, $supervisor);
            $rules['form_data.'.$id] = ['nullable', 'array:'.implode(',', $keys)];
            if (isset($section['repeat'])) {
                $rules['form_data.'.$id.'.rows'] = ['nullable', 'array', 'max:50'];
                $rules += self::fieldsRules('form_data.'.$id.'.rows.*', $section['repeat']['fields'], $supervisor);
            }
        }

        return $rules;
    }

    public static function normalize(array $data): array
    {
        foreach (config('case-forms.sections') as $id => $section) {
            if (isset($section['repeat']) && array_key_exists($id, $data) && is_array($data[$id])) {
                if (empty($data[$id]['rows'])) {
                    $data[$id]['rows'] = [];
                }
            }
        }

        return $data;
    }

    public static function merge(array $previous, array $submitted, bool $supervisor): array
    {
        foreach ($submitted as $id => $section) {
            $section = $section ?? [];
            foreach (config('case-forms.sections.'.$id.'.fields', []) as $key => $field) {
                if (($field['supervisor'] ?? false) && ! $supervisor && array_key_exists($key, $previous[$id] ?? [])) {
                    $section[$key] = $previous[$id][$key];
                }
            }
            if (isset($section['rows'])) {
                $section['rows'] = array_values(array_filter($section['rows'], fn ($row) => collect($row)->contains(fn ($value) => filled($value))));
            }
            $previous[$id] = $section;
        }

        return $previous;
    }
}
