<?php

namespace App\Console\Commands;

use App\Models\CompanyProfile;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Services\Documents\DocumentNumberPattern;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JsonException;
use Throwable;

class InitialDataApply extends Command
{
    protected $signature = 'office:initial-data:apply {manifest : Approved JSON manifest path} {--apply : Persist after validation}';

    protected $description = 'Validate and apply dual-approved Office initial data and cutover sequences';

    public function handle(): int
    {
        try {
            $manifest = $this->readManifest((string) $this->argument('manifest'));
            $this->validateManifest($manifest);
            $this->validateAgainstDatabase($manifest);

            if (! $this->option('apply')) {
                $this->info('[PASS] Initial-data manifest dry-run passed. No data changed.');

                return self::SUCCESS;
            }

            DB::transaction(fn () => $this->applyManifest($manifest), 3);
            $this->info('[PASS] Initial data and cutover sequences applied.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('[FAIL] '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function readManifest(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException('Manifest tidak ditemukan.');
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new \RuntimeException('Manifest bukan JSON valid.');
        }

        if (! is_array($data)) {
            throw new \RuntimeException('Manifest harus berupa object JSON.');
        }

        return $data;
    }

    private function validateManifest(array $manifest): void
    {
        $validator = Validator::make($manifest, [
            'manifest_version' => ['required', 'integer', 'in:1'],
            'source_reference' => ['required', 'string', 'max:255'],
            'approvals.process_owner' => ['required', 'string', 'max:255'],
            'approvals.administrator' => ['required', 'string', 'max:255', 'different:approvals.process_owner'],
            'company_profile.company_code' => ['required', 'string', 'max:50'],
            'company_profile.legal_name' => ['required', 'string', 'max:255'],
            'company_profile.display_name' => ['required', 'string', 'max:255'],
            'company_profile.address_lines' => ['required', 'array', 'min:1'],
            'company_profile.city' => ['required', 'string', 'max:150'],
            'company_profile.postal_code' => ['required', 'string', 'max:20'],
            'company_profile.country' => ['required', 'string', 'size:2'],
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'document_types.*.name' => ['required', 'string', 'max:150'],
            'document_types.*.number_pattern' => ['required', 'string', 'max:255'],
            'document_types.*.approval_mode' => ['required', 'in:direct,maker_checker'],
            'templates' => ['required', 'array', 'min:1'],
            'templates.*.type' => ['required', 'string', 'max:50'],
            'templates.*.version' => ['required', 'integer', 'min:1'],
            'templates.*.name' => ['required', 'string', 'max:255'],
            'templates.*.settings.columns' => ['required', 'array', 'min:1'],
            'sequences' => ['required', 'array'],
            'sequences.*.document_type_code' => ['required', 'string'],
            'sequences.*.period_year' => ['required', 'integer', 'between:1,9999'],
            'sequences.*.last_value' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new \RuntimeException($validator->errors()->first());
        }

        foreach (['source_reference', 'approvals.process_owner', 'approvals.administrator', 'company_profile.legal_name'] as $key) {
            if (str_starts_with((string) data_get($manifest, $key), 'replace-with-')) {
                throw new \RuntimeException("Placeholder {$key} wajib diganti dengan nilai terverifikasi.");
            }
        }

        $patterns = app(DocumentNumberPattern::class);
        foreach ($manifest['document_types'] as $definition) {
            $segments = $patterns->fromPattern($definition['number_pattern']);
            if ($patterns->toPattern($patterns->validateSegments($segments)) !== $definition['number_pattern']) {
                throw new \RuntimeException("Pola tipe {$definition['code']} tidak valid.");
            }
        }
    }

    private function validateAgainstDatabase(array $manifest): void
    {
        $types = collect($manifest['document_types'])->keyBy('code');

        foreach ($manifest['sequences'] as $sequence) {
            if (! $types->has($sequence['document_type_code'])) {
                throw new \RuntimeException('Sequence merujuk tipe yang tidak ada di manifest.');
            }

            $type = DocumentType::query()->where('code', $sequence['document_type_code'])->first();
            if (! $type) {
                continue;
            }

            $definition = $types->get($sequence['document_type_code']);
            if ($type->documents()->exists() && $type->number_pattern !== $definition['number_pattern']) {
                throw new \RuntimeException("Pola tipe {$type->code} yang sudah digunakan tidak boleh ditimpa.");
            }

            $maximumIssued = (int) $type->documents()->where('period_year', $sequence['period_year'])->max('sequence_value');
            $current = (int) $type->sequences()->where('period_year', $sequence['period_year'])->value('last_value');
            if ($sequence['last_value'] < max($maximumIssued, $current)) {
                throw new \RuntimeException("Sequence {$type->code}/{$sequence['period_year']} tidak boleh diturunkan.");
            }
        }
    }

    private function applyManifest(array $manifest): void
    {
        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $profileData = $manifest['company_profile'];
        $profile = CompanyProfile::query()->updateOrCreate(
            ['company_code' => $profileData['company_code']],
            $profileData + ['is_active' => true],
        );

        foreach ($manifest['document_types'] as $definition) {
            DocumentType::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition + ['reset_period' => 'yearly', 'is_active' => true],
            );
        }

        foreach ($manifest['templates'] as $template) {
            DocumentTemplate::query()->updateOrCreate(
                ['type' => $template['type'], 'version' => $template['version']],
                $template + ['company_profile_id' => $profile->getKey(), 'is_active' => true],
            );
        }

        foreach ($manifest['sequences'] as $sequence) {
            $type = DocumentType::query()->where('code', $sequence['document_type_code'])->sole();
            DocumentSequence::query()->updateOrCreate(
                ['document_type_id' => $type->getKey(), 'period_year' => $sequence['period_year']],
                ['last_value' => $sequence['last_value']],
            );
        }
    }
}
