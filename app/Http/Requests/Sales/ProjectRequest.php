<?php

namespace App\Http\Requests\Sales;

use App\Enums\ProjectStatus;
use App\Http\Requests\MasterData\MasterDataRequest;
use App\Models\Project;
use Illuminate\Validation\Rule;

class ProjectRequest extends MasterDataRequest
{
    protected string $modelClass = Project::class;

    protected string $routeParameter = 'project';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $this->activeCompanyId())],
            'title' => ['required', 'string', 'max:255'],
            'site_address' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'responsible_user_id' => ['nullable', Rule::exists('company_user', 'user_id')->where('company_id', $this->activeCompanyId())],
            'commissioned_on' => ['nullable', 'date'],
            'started_on' => ['nullable', 'date'],
            'planned_finish_on' => ['nullable', 'date'],
            'finished_on' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'Kunde',
            'title' => 'Titel',
            'site_address' => 'Baustellenadresse',
            'responsible_user_id' => 'Verantwortlicher',
            'commissioned_on' => 'Beauftragt am',
            'started_on' => 'Begonnen am',
            'planned_finish_on' => 'Geplantes Ende',
            'finished_on' => 'Fertiggestellt am',
            'status' => 'Status',
            'warranty_until' => 'Gewährleistung bis',
        ];
    }
}
