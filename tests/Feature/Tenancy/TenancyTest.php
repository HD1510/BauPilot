<?php

use App\Enums\CompanyRole;
use App\Exceptions\MissingCompanyContext;
use App\Models\Company;
use App\Models\CostType;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;

function companyContext(): CompanyContext
{
    return app(CompanyContext::class);
}

beforeEach(function () {
    $this->companyA = Company::factory()->create(['name' => 'Firma A']);
    $this->companyB = Company::factory()->create(['name' => 'Firma B']);
    $this->costTypeA = CostType::factory()->create(['company_id' => $this->companyA->id, 'name' => 'Material A']);
    $this->costTypeB = CostType::factory()->create(['company_id' => $this->companyB->id, 'name' => 'Material B']);
});

afterEach(function () {
    companyContext()->clear();
});

test('abfragen sehen nur datensätze der aktiven firma', function () {
    companyContext()->set($this->companyA);

    expect(CostType::query()->pluck('name')->all())->toBe(['Material A']);
});

test('ein datensatz der anderen firma ist auch per direkter id nicht erreichbar', function () {
    companyContext()->set($this->companyA);

    expect(CostType::query()->find($this->costTypeB->id))->toBeNull();
});

test('beim anlegen wird company_id automatisch aus dem kontext befüllt', function () {
    companyContext()->set($this->companyA);

    $costType = CostType::create(['name' => 'Lohn']);

    expect($costType->company_id)->toBe($this->companyA->id);
});

test('company_id kann nicht per mass assignment auf eine fremde firma gesetzt werden', function () {
    companyContext()->set($this->companyA);

    $costType = CostType::create(['name' => 'Fremd', 'company_id' => $this->companyB->id]);

    expect($costType->company_id)->toBe($this->companyA->id);
});

test('abfragen ohne kontext werfen statt still leer oder ungefiltert zu liefern', function () {
    companyContext()->clear();

    CostType::query()->count();
})->throws(MissingCompanyContext::class);

test('anlegen ohne kontext wirft', function () {
    companyContext()->clear();

    CostType::create(['name' => 'Ohne Kontext']);
})->throws(MissingCompanyContext::class);

test('runFor führt im kontext der firma aus und stellt den vorherigen kontext wieder her', function () {
    companyContext()->set($this->companyA);

    $namesInB = companyContext()->runFor($this->companyB, fn () => CostType::query()->pluck('name')->all());

    expect($namesInB)->toBe(['Material B'])
        ->and(companyContext()->currentId())->toBe($this->companyA->id);
});

test('ein job ohne expliziten kontext schlägt fehl', function () {
    companyContext()->clear();

    Bus::dispatchSync(new class
    {
        use Dispatchable;

        public function handle(): void
        {
            CostType::query()->count();
        }
    });
})->throws(MissingCompanyContext::class);

test('ein job mit runFor arbeitet genau in seiner firma', function () {
    companyContext()->clear();

    $job = new class($this->companyB->id)
    {
        use Dispatchable;

        /** @var list<string> */
        public array $names = [];

        public function __construct(public int $companyId) {}

        public function handle(CompanyContext $context): void
        {
            $company = Company::query()->findOrFail($this->companyId);
            $this->names = $context->runFor($company, fn () => CostType::query()->pluck('name')->all());
        }
    };

    Bus::dispatchSync($job);

    expect($job->names)->toBe(['Material B'])
        ->and(companyContext()->current())->toBeNull();
});

test('benutzer der firma a kann firma b nicht sehen oder bearbeiten', function () {
    $user = User::factory()->create();
    $this->companyA->users()->attach($user->id, ['role' => CompanyRole::Admin->value]);

    $this->actingAs($user)->get("/companies/{$this->companyB->id}/edit")->assertForbidden();
    $this->actingAs($user)->patch("/companies/{$this->companyB->id}", [])->assertForbidden();
    $this->actingAs($user)->patch("/companies/{$this->companyB->id}/archive")->assertForbidden();
    $this->actingAs($user)->post("/companies/{$this->companyB->id}/members", [
        'email' => $user->email, 'role' => 'site',
    ])->assertForbidden();
});

test('firmenwechsel ist nur mit company_user-eintrag möglich', function () {
    $user = User::factory()->create();
    $this->companyA->users()->attach($user->id, ['role' => CompanyRole::Office->value]);

    $this->actingAs($user)
        ->post("/companies/{$this->companyB->id}/switch")
        ->assertForbidden();

    $this->actingAs($user)
        ->post("/companies/{$this->companyA->id}/switch")
        ->assertRedirect(route('dashboard'));

    expect(session('active_company_id'))->toBe($this->companyA->id);
});

test('die middleware wählt automatisch die erste firma des benutzers', function () {
    $user = User::factory()->create();
    $this->companyB->users()->attach($user->id, ['role' => CompanyRole::Office->value]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('tenancy.activeCompany.id', $this->companyB->id)
            ->where('tenancy.activeCompany.short_code', $this->companyB->short_code),
        );
});

test('eine veraltete session-firma ohne mitgliedschaft wird verworfen', function () {
    $user = User::factory()->create();
    $this->companyA->users()->attach($user->id, ['role' => CompanyRole::Office->value]);

    $this->actingAs($user)
        ->withSession(['active_company_id' => $this->companyB->id])
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('tenancy.activeCompany.id', $this->companyA->id),
        );
});
