<?php

namespace Database\Seeders;

use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class ImportarRegistrosExcelSeeder extends Seeder
{
    private const FILE_NAME = 'Incluir registros a sistema.xlsx';

    /** @var array<string, int> */
    private array $stateIds = [];

    /** @var array<string, int> */
    private array $municipalityIds = [];

    /** @var array<string, int> */
    private array $parishIds = [];

    /** @var array<string, object> */
    private array $users = [];

    private string $batchKey = '';

    /** @var array<string, string>|null */
    private ?array $environmentValues = null;

    public function run(): void
    {
        $path = $this->sourcePath();
        $rows = $this->readWorkbook($path);

        $this->batchKey = substr(hash_file('sha256', $path), 0, 16);

        if ($rows === []) {
            throw new RuntimeException('El archivo de importación no contiene registros.');
        }

        $project = $this->resolveProject();
        $userMap = $this->configuredUserMap();
        $defaultUser = $this->defaultUser();
        $dryRun = $this->booleanSetting('IMPORT_REGISTROS_DRY_RUN');

        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            $catalog = $this->catalog($project->id, $rows);
            $prepared = $this->prepareRows($rows, $catalog, $userMap, $defaultUser);
        } catch (Throwable $exception) {
            if ($dryRun && DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $exception;
        }

        $this->command?->info(sprintf(
            'Prevalidación correcta: %d beneficiarios, %d registros de actividad.',
            count($prepared),
            collect($prepared)->pluck('_group_key')->unique()->count(),
        ));

        if ($dryRun) {
            DB::rollBack();
            $this->command?->warn('IMPORT_REGISTROS_DRY_RUN está activo. No se insertaron datos.');

            return;
        }

        $insertedReports = 0;
        $insertedBeneficiaries = 0;
        $skippedReports = 0;

        DB::transaction(function () use (
            $prepared,
            $project,
            &$insertedReports,
            &$insertedBeneficiaries,
            &$skippedReports,
        ): void {
            collect($prepared)
                ->groupBy('_group_key')
                ->each(function (Collection $beneficiaryRows) use (
                    $project,
                    &$insertedReports,
                    &$insertedBeneficiaries,
                    &$skippedReports,
                ): void {
                    $first = $beneficiaryRows->first();
                    $beneficiaries = $beneficiaryRows->map(fn (array $row): array => $row['beneficiary'])->values();
                    $summary = $this->summary($beneficiaries);
                    $importMarker = $this->importMarker($first['_group_key']);

                    if (DB::table('reports')->where('qualitative_notes', $importMarker)->exists()) {
                        $skippedReports++;

                        return;
                    }

                    $reportId = DB::table('reports')->insertGetId([
                        'user_id' => $first['user_id'],
                        'proyecto_id' => $project->id,
                        'indicador_proyecto_id' => $first['indicador_proyecto_id'],
                        'actividad_indicador_id' => null,
                        'report_date' => $first['report_date'],
                        'reporter_first_name' => $first['reporter_name'],
                        'reporter_last_name' => 'Importación histórica',
                        'reporter_email' => $first['reporter_email'],
                        'organization' => 'ASONACOP',
                        'other_organization' => null,
                        'state_id' => $first['state_id'],
                        'municipality_id' => $first['municipality_id'],
                        'parish_id' => $first['parish_id'],
                        'installation_type' => $first['installation_type'],
                        'place_name' => $first['place_name'],
                        'latitude' => $first['latitude'],
                        'longitude' => $first['longitude'],
                        'altitude' => null,
                        'gps_accuracy' => null,
                        'sector_id' => $first['sector_id'],
                        'activity_id' => null,
                        'activity_details' => $first['activity_details'],
                        'recurrence_status' => $summary['recurrence_status'],
                        'total_beneficiaries' => $summary['total'],
                        'beneficiary_breakdown' => json_encode($summary['breakdown'], JSON_UNESCAPED_UNICODE),
                        'people_with_disabilities' => $summary['people_with_disabilities'],
                        'indigenous_people' => $summary['indigenous_people'],
                        'pregnant_or_lactating_women' => $summary['pregnant_or_lactating_women'],
                        'qualitative_notes' => $importMarker,
                        'status' => 'submitted',
                        'reviewed_at' => null,
                        'reviewed_by' => null,
                        'created_at' => $first['created_at'],
                        'updated_at' => $first['created_at'],
                    ]);

                    DB::table('beneficiaries')->insert($beneficiaryRows->map(function (array $row) use ($reportId): array {
                        return array_merge($row['beneficiary'], [
                            'report_id' => $reportId,
                            'created_at' => $row['created_at'],
                            'updated_at' => $row['created_at'],
                        ]);
                    })->all());

                    $insertedReports++;
                    $insertedBeneficiaries += $beneficiaries->count();
                });
        });

        $this->command?->info(sprintf(
            'Importación completada: %d registros y %d beneficiarios insertados; %d registros ya existentes omitidos.',
            $insertedReports,
            $insertedBeneficiaries,
            $skippedReports,
        ));
    }

    private function sourcePath(): string
    {
        $configured = trim((string) $this->setting('IMPORT_REGISTROS_FILE', ''));
        $candidates = array_filter([
            $configured,
            database_path('seeders/data/'.self::FILE_NAME),
            storage_path('app/imports/'.self::FILE_NAME),
        ]);

        foreach ($candidates as $candidate) {
            $path = Str::startsWith($candidate, ['/', '\\']) || preg_match('/^[A-Za-z]:[\\\\\/]/', $candidate)
                ? $candidate
                : base_path($candidate);

            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException(
            'No se encontró '.self::FILE_NAME.'. Súbalo a database/seeders/data o configure IMPORT_REGISTROS_FILE.'
        );
    }

    private function resolveProject(): object
    {
        $code = trim((string) $this->setting('IMPORT_REGISTROS_PROJECT_CODE', ''));
        $query = DB::table('proyectos');

        if ($code !== '') {
            $project = $query->where('codigo', $code)->first();
            if (! $project) {
                throw new RuntimeException("No existe el proyecto con código {$code}.");
            }

            return $project;
        }

        $projects = $query->get();
        if ($projects->count() !== 1) {
            throw new RuntimeException('Configure IMPORT_REGISTROS_PROJECT_CODE porque existe más de un proyecto.');
        }

        return $projects->first();
    }

    /** @return array<string, string> */
    private function configuredUserMap(): array
    {
        $json = trim((string) $this->setting('IMPORT_REGISTROS_USER_MAP', ''));
        if ($json === '') {
            return [];
        }

        $map = json_decode($json, true);
        if (! is_array($map)) {
            throw new RuntimeException('IMPORT_REGISTROS_USER_MAP debe contener un objeto JSON válido.');
        }

        return collect($map)->mapWithKeys(fn ($email, $alias) => [
            $this->normalize((string) $alias) => trim((string) $email),
        ])->all();
    }

    private function defaultUser(): ?object
    {
        $email = trim((string) $this->setting('IMPORT_REGISTROS_DEFAULT_USER_EMAIL', ''));

        if ($email !== '') {
            $user = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->first();

            if (! $user) {
                throw new RuntimeException("El usuario predeterminado {$email} no existe o está inactivo.");
            }

            return $user;
        }

        $asonacopAdmin = DB::table('users')
            ->whereRaw('LOWER(email) = ?', ['admin@asonacop.org'])
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->first();

        if ($asonacopAdmin) {
            $this->command?->warn(
                'No se configuró IMPORT_REGISTROS_DEFAULT_USER_EMAIL; los alias sin correspondencia se asignarán a admin@asonacop.org.'
            );

            return $asonacopAdmin;
        }

        $administrators = DB::table('users')
            ->where('role', 'admin')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->get();

        if ($administrators->count() === 1) {
            $this->command?->warn(sprintf(
                'No se configuró IMPORT_REGISTROS_DEFAULT_USER_EMAIL; los alias sin correspondencia se asignarán a %s.',
                $administrators->first()->email,
            ));

            return $administrators->first();
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function catalog(int $projectId, array $rows): array
    {
        $this->stateIds = DB::table('states')->get()->mapWithKeys(fn ($row) => [$this->normalize($row->name) => $row->id])->all();
        DB::table('municipalities')->get()->each(function ($row): void {
            $this->municipalityIds[$row->state_id.'|'.$this->normalize($row->name)] = $row->id;
        });
        DB::table('parishes')->get()->each(function ($row): void {
            $this->parishIds[$row->municipality_id.'|'.$this->normalize($row->name)] = $row->id;
        });
        $this->users = DB::table('users')->whereNull('deleted_at')->get()->keyBy(fn ($row) => mb_strtolower($row->email))->all();

        $sectors = DB::table('sector_proyecto as sp')
            ->join('sectors as s', 's.id', '=', 'sp.sector_id')
            ->where('sp.proyecto_id', $projectId)
            ->select('sp.id as sector_proyecto_id', 's.id as sector_id', 's.descripcion', 's.codigo')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->normalize($row->descripcion) => $row])
            ->all();

        $this->ensureImportedIndicatorsAreAssigned($projectId, $rows, $sectors);

        $indicators = DB::table('indicador_proyecto as ip')
            ->join('indicadores as i', 'i.id', '=', 'ip.indicador_id')
            ->where('ip.proyecto_id', $projectId)
            ->whereNotNull('ip.sector_proyecto_id')
            ->select('ip.id as indicador_proyecto_id', 'ip.sector_proyecto_id', 'i.codigo')
            ->get();

        return ['sectors' => $sectors, 'indicators' => $indicators];
    }

    /**
     * Asocia al sector del proyecto los indicadores maestros usados por el Excel.
     * No crea ni modifica indicadores maestros.
     *
     * @param  array<string, object>  $sectors
     */
    private function ensureImportedIndicatorsAreAssigned(int $projectId, array $rows, array $sectors): void
    {
        if (! $this->booleanSetting('IMPORT_REGISTROS_ATTACH_INDICATORS', true)) {
            return;
        }

        collect($rows)
            ->groupBy(fn (array $row): string => $this->normalize($row['Sector programático']))
            ->each(function (Collection $sectorRows, string $sectorKey) use ($projectId, $sectors): void {
                $sector = $sectors[$sectorKey] ?? null;
                if (! $sector) {
                    return;
                }

                $sectorRows
                    ->pluck('Indicador a reportar')
                    ->map(fn (string $label): string => trim(Str::before($label, ':')))
                    ->unique()
                    ->each(function (string $code) use ($projectId, $sector): void {
                        $indicator = DB::table('indicadores')
                            ->where('codigo', $code)
                            ->orWhere('codigo', 'like', '%/'.$code)
                            ->first();

                        if (! $indicator) {
                            throw new RuntimeException("No existe el indicador maestro {$code}.");
                        }

                        DB::table('indicador_proyecto')->updateOrInsert(
                            [
                                'proyecto_id' => $projectId,
                                'indicador_id' => $indicator->id,
                            ],
                            [
                                'sector_proyecto_id' => $sector->sector_proyecto_id,
                                'estatus' => true,
                                'updated_at' => now(),
                            ],
                        );
                    });
            });
    }

    /** @return array<int, array<string, mixed>> */
    private function prepareRows(array $rows, array $catalog, array $userMap, ?object $defaultUser): array
    {
        $errors = [];
        $prepared = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            try {
                $stateId = $this->requiredLookup($this->stateIds, $row['Estado'], "Estado, fila {$line}");
                $municipalityId = $this->requiredLookup(
                    $this->municipalityIds,
                    $stateId.'|'.$this->normalize($row['Municipio']),
                    "Municipio, fila {$line}",
                    false,
                );
                $parishId = $this->requiredLookup(
                    $this->parishIds,
                    $municipalityId.'|'.$this->normalize($row['Parroquia']),
                    "Parroquia, fila {$line}",
                    false,
                );
                $sectorKey = $this->normalize($row['Sector programático']);
                $sector = $catalog['sectors'][$sectorKey] ?? null;
                if (! $sector) {
                    throw new RuntimeException("Sector no asignado al proyecto: {$row['Sector programático']}");
                }

                $indicatorCode = trim(Str::before($row['Indicador a reportar'], ':'));
                $indicator = $catalog['indicators']->first(fn ($item) => $item->sector_proyecto_id === $sector->sector_proyecto_id
                    && (Str::endsWith($item->codigo, '/'.$indicatorCode) || $item->codigo === $indicatorCode)
                );
                if (! $indicator) {
                    throw new RuntimeException("Indicador {$indicatorCode} no asignado al sector del proyecto");
                }

                $user = $this->resolveUser($row['Usuario'], $userMap, $defaultUser);
                $reportDate = $this->excelDate($row['Fecha de atención'])->format('Y-m-d');
                $createdAt = $this->excelDate($row['Fecha de inclusión'])->format('Y-m-d H:i:s');
                $reportedAt = trim($row['Fecha de reporte']) === '' ? null : $this->excelDate($row['Fecha de reporte'])->format('Y-m-d');
                $isRecurrent = $this->yes($row['Recurrente']);
                $pregnancy = $this->nullableCategory($row['Embarazada o lactante']);

                $groupParts = [
                    $user->id, $reportDate, $stateId, $municipalityId, $parishId,
                    $row['Tipo de instalación'], $row['Nombre del lugar'], $row['Latitud'], $row['Longitud'],
                    $sector->sector_id, $indicator->indicador_proyecto_id, $row['Detalles adicionales de la actividad'],
                ];

                $prepared[] = [
                    '_group_key' => hash('sha256', json_encode($groupParts, JSON_UNESCAPED_UNICODE)),
                    'user_id' => $user->id,
                    'reporter_name' => trim($row['Usuario']),
                    'reporter_email' => $user->email,
                    'indicador_proyecto_id' => $indicator->indicador_proyecto_id,
                    'report_date' => $reportDate,
                    'state_id' => $stateId,
                    'municipality_id' => $municipalityId,
                    'parish_id' => $parishId,
                    'installation_type' => trim($row['Tipo de instalación']),
                    'place_name' => trim($row['Nombre del lugar']),
                    'latitude' => $this->nullableDecimal($row['Latitud']),
                    'longitude' => $this->nullableDecimal($row['Longitud']),
                    'sector_id' => $sector->sector_id,
                    'activity_details' => $this->nullableText($row['Detalles adicionales de la actividad']),
                    'created_at' => $createdAt,
                    'beneficiary' => [
                        'full_name' => $this->nullableText($row['Nombre y apellido']),
                        'age' => (int) $row['Edad'],
                        'sex' => trim($row['Sexo']),
                        'national_id' => $this->nullableIdentifier($row['Cédula']),
                        'phone' => $this->nullableIdentifier($row['Teléfono']),
                        'disability' => $this->nullableCategory($row['Discapacidad']),
                        'ethnicity' => $this->nullableCategory($row['Indígena']),
                        'pregnant_lactating' => $pregnancy,
                        'is_recurrent' => $isRecurrent,
                        'reported' => $reportedAt !== null,
                        'reported_at' => $reportedAt,
                    ],
                ];
            } catch (RuntimeException $exception) {
                $errors[] = "Fila {$line}: {$exception->getMessage()}";
            }
        }

        if ($errors !== []) {
            throw new RuntimeException("La importación fue cancelada por errores de validación:\n".implode("\n", array_slice($errors, 0, 50)));
        }

        return $prepared;
    }

    private function resolveUser(string $alias, array $userMap, ?object $defaultUser): object
    {
        $key = $this->normalize($alias);
        $mappedEmail = $userMap[$key] ?? null;
        if ($mappedEmail) {
            $user = $this->users[mb_strtolower($mappedEmail)] ?? null;
            if (! $user) {
                throw new RuntimeException("El correo configurado para {$alias} no existe: {$mappedEmail}");
            }

            return $user;
        }

        $matches = collect($this->users)->filter(fn ($user) => $this->normalize($user->name) === $key);
        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($defaultUser) {
            return $defaultUser;
        }

        throw new RuntimeException("No se pudo asociar el usuario '{$alias}'. Configure IMPORT_REGISTROS_USER_MAP o IMPORT_REGISTROS_DEFAULT_USER_EMAIL.");
    }

    /** @return array<string, mixed> */
    private function summary(Collection $beneficiaries): array
    {
        $total = $beneficiaries->count();
        $recurrent = $beneficiaries->where('is_recurrent', true)->count();

        return [
            'total' => $total,
            'recurrence_status' => $recurrent === $total ? 'recurrente' : ($recurrent === 0 ? 'no_recurrente' : 'mixto'),
            'people_with_disabilities' => $beneficiaries->filter(fn ($row) => filled($row['disability']) && $row['disability'] !== 'Ninguna')->count(),
            'indigenous_people' => $beneficiaries->filter(fn ($row) => filled($row['ethnicity']) && $row['ethnicity'] !== 'Ninguna')->count(),
            'pregnant_or_lactating_women' => $beneficiaries->filter(fn ($row) => $row['pregnant_lactating'] === 'Sí')->count(),
            'breakdown' => [
                'source' => 'individual',
                'by_sex' => $beneficiaries->countBy('sex')->all(),
                'by_age_range' => [
                    '0_5' => $beneficiaries->whereBetween('age', [0, 5])->count(),
                    '6_11' => $beneficiaries->whereBetween('age', [6, 11])->count(),
                    '12_17' => $beneficiaries->whereBetween('age', [12, 17])->count(),
                    '18_59' => $beneficiaries->whereBetween('age', [18, 59])->count(),
                    '60_plus' => $beneficiaries->where('age', '>=', 60)->count(),
                ],
            ],
        ];
    }

    private function importMarker(string $groupKey): string
    {
        return sprintf(
            'Importado desde %s [lote:%s] [grupo:%s]',
            self::FILE_NAME,
            $this->batchKey,
            substr($groupKey, 0, 24),
        );
    }

    /** @return array<int, array<string, string>> */
    private function readWorkbook(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException("No se pudo abrir {$path}.");
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $strings = simplexml_load_string($xml);
                foreach ($strings->si as $item) {
                    $shared[] = $this->sharedString($item);
                }
            }

            $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
            $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sheet = $workbook->sheets->sheet[0];
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) $attributes['id'];
            $relationships = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
            $target = '';
            foreach ($relationships->Relationship as $relationship) {
                if ((string) $relationship['Id'] === $relationshipId) {
                    $target = (string) $relationship['Target'];
                    break;
                }
            }
            $sheetPath = Str::startsWith($target, 'xl/') ? $target : 'xl/'.ltrim($target, '/');
            $worksheet = simplexml_load_string((string) $zip->getFromName($sheetPath));
            $matrix = [];

            foreach ($worksheet->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $column = $this->columnNumber((string) $cell['r']);
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = $shared[(int) $value] ?? '';
                    } elseif ((string) $cell['t'] === 'inlineStr') {
                        $value = $this->sharedString($cell->is);
                    }
                    $values[$column] = trim($value);
                }
                $matrix[] = $values;
            }
        } finally {
            $zip->close();
        }

        $header = array_shift($matrix);
        if (! $header) {
            return [];
        }

        return collect($matrix)->filter()->map(function (array $row) use ($header): array {
            $result = [];
            foreach ($header as $column => $name) {
                $result[$name] = $row[$column] ?? '';
            }

            return $result;
        })->values()->all();
    }

    private function sharedString(SimpleXMLElement $node): string
    {
        $texts = $node->xpath('.//*[local-name()="t"]') ?: [];

        return implode('', array_map(fn ($text) => (string) $text, $texts));
    }

    private function columnNumber(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $matches);
        $number = 0;
        foreach (str_split($matches[0] ?? '') as $letter) {
            $number = ($number * 26) + ord($letter) - 64;
        }

        return $number;
    }

    private function excelDate(string $value): DateTimeImmutable
    {
        if (! is_numeric($value)) {
            $date = date_create_immutable($value);
            if (! $date) {
                throw new RuntimeException("Fecha inválida: {$value}");
            }

            return $date;
        }

        $serial = (float) $value;
        $days = (int) floor($serial);
        $seconds = (int) round(($serial - $days) * 86400);
        $timezone = new \DateTimeZone(config('app.timezone', 'UTC'));

        return (new DateTimeImmutable('1899-12-30 00:00:00', $timezone))
            ->modify("+{$days} days")
            ->modify("+{$seconds} seconds");
    }

    private function requiredLookup(array $map, string $value, string $label, bool $normalize = true): int
    {
        $key = $normalize ? $this->normalize($value) : $value;
        if (! isset($map[$key])) {
            throw new RuntimeException("{$label} no encontrado: {$value}");
        }

        return $map[$key];
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(Str::ascii(trim($value)), 'UTF-8');
    }

    private function yes(string $value): bool
    {
        return in_array($this->normalize($value), ['SI', 'SÍ', '1', 'TRUE'], true);
    }

    private function nullableCategory(string $value): ?string
    {
        $value = trim($value);

        return in_array($this->normalize($value), ['', 'N/A', 'NO APLICA'], true) ? null : ($this->yes($value) ? 'Sí' : $value);
    }

    private function nullableIdentifier(string $value): ?string
    {
        $value = trim($value);

        return in_array($this->normalize($value), ['', 'NO', 'NO TIENE', 'N/A'], true) ? null : $value;
    }

    private function nullableText(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableDecimal(string $value): ?float
    {
        $value = trim(str_replace(',', '.', $value));

        return $value === '' ? null : (float) $value;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        $runtimeValue = env($key);
        if ($runtimeValue !== null) {
            return $runtimeValue;
        }

        if ($this->environmentValues === null) {
            $this->environmentValues = [];
            $path = base_path('.env');

            foreach (is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) ?: [] : [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $this->environmentValues[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
            }
        }

        return $this->environmentValues[$key] ?? $default;
    }

    private function booleanSetting(string $key, bool $default = false): bool
    {
        $value = $this->setting($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
