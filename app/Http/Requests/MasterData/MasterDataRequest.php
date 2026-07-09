<?php

namespace App\Http\Requests\MasterData;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Gemeinsame Autorisierung der Stammdaten-Formulare: Läuft vor der
 * Validierung, damit Unberechtigte 403 statt Validierungsfehler erhalten.
 */
abstract class MasterDataRequest extends FormRequest
{
    /** @var class-string<Model> */
    protected string $modelClass;

    protected string $routeParameter;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $model = $this->route($this->routeParameter);

        return $model instanceof Model
            ? $user->can('update', $model)
            : $user->can('create', $this->modelClass);
    }

    protected function activeCompanyId(): int
    {
        return app(CompanyContext::class)->requireId();
    }
}
