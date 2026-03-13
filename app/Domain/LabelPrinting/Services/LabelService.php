<?php

namespace App\Domain\LabelPrinting\Services;

use App\Domain\Auth\Models\User;
use App\Domain\LabelPrinting\Models\LabelPrintHistory;
use App\Domain\LabelPrinting\Models\LabelTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LabelService
{
    public function listTemplates(string $orgId): Collection
    {
        return LabelTemplate::where('organization_id', $orgId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getPresets(): Collection
    {
        return LabelTemplate::where('is_preset', true)->orderBy('name')->get();
    }

    public function find(string $templateId): LabelTemplate
    {
        return LabelTemplate::findOrFail($templateId);
    }

    public function create(array $data, User $actor): LabelTemplate
    {
        $data['organization_id'] = $actor->organization_id;
        $data['created_by'] = $actor->id;
        $data['sync_version'] = 1;

        // Cannot create a preset via API
        $data['is_preset'] = false;

        // If setting as default, clear other defaults
        if (!empty($data['is_default'])) {
            LabelTemplate::where('organization_id', $actor->organization_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return LabelTemplate::create($data);
    }

    public function update(LabelTemplate $template, array $data): LabelTemplate
    {
        if ($template->is_preset) {
            throw new \RuntimeException('System presets cannot be modified. Duplicate to customise.');
        }

        // If setting as default, clear other defaults
        if (!empty($data['is_default'])) {
            LabelTemplate::where('organization_id', $template->organization_id)
                ->where('is_default', true)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $data['sync_version'] = ($template->sync_version ?? 0) + 1;
        $template->update($data);
        return $template->fresh();
    }

    public function delete(LabelTemplate $template): void
    {
        if ($template->is_preset) {
            throw new \RuntimeException('System presets cannot be deleted.');
        }
        $template->delete();
    }

    public function recordPrintHistory(array $data): LabelPrintHistory
    {
        $data['printed_at'] = now();
        return LabelPrintHistory::create($data);
    }

    public function getPrintHistory(string $storeId, int $perPage = 20): LengthAwarePaginator
    {
        return LabelPrintHistory::where('store_id', $storeId)
            ->orderByDesc('printed_at')
            ->paginate($perPage);
    }
}
