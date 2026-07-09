<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Schränkt jede Abfrage auf die aktive Firma ein. Ohne gesetzten Kontext
 * wird geworfen — ein mandantengebundenes Modell darf nie ungefiltert
 * oder still leer abgefragt werden.
 */
/**
 * @implements Scope<Model>
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(
            $model->qualifyColumn('company_id'),
            app(CompanyContext::class)->requireId(),
        );
    }
}
