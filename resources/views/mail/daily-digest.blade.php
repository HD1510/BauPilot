Guten Morgen!

Tägliche Zusammenfassung für {{ $company->name }} ({{ now()->timezone('Europe/Vienna')->format('d.m.Y') }}):

@foreach ($deadlines as $deadline)
{{ $deadline['overdue'] ? '⚠ ÜBERFÄLLIG' : \Carbon\CarbonImmutable::parse($deadline['due_on'])->format('d.m.Y') }} · {{ $deadline['kind_label'] }}: {{ $deadline['title'] }}@if ($deadline['subtitle']) ({{ $deadline['subtitle'] }})@endif

@endforeach

Details in BauPilot unter „Fristen".

Diese Nachricht kommt einmal täglich, wenn etwas ansteht. Abstellen: Einstellungen → Benachrichtigungen.
