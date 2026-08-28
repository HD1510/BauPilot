<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Einteilung {{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; font-size: 12px; color: #1e293b; padding: 16px; }
        h1 { font-size: 20px; margin-bottom: 2px; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 14px; }
        .toolbar { position: fixed; top: 12px; right: 16px; }
        .toolbar button { font-size: 14px; padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #0f172a; color: #fff; cursor: pointer; }
        table.week { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.week td, table.week th { border: 1px solid #cbd5e1; vertical-align: top; padding: 5px; }
        th.day { background: #f1f5f9; font-size: 12px; text-align: left; }
        .card { border: 1px solid #cbd5e1; border-left-width: 4px; border-radius: 4px; padding: 5px 6px; margin-bottom: 6px; break-inside: avoid; }
        .card .label { font-size: 12px; font-weight: 600; margin-bottom: 3px; }
        .person, .vehicle { display: inline-block; background: rgba(255, 255, 255, 0.75); border: 1px solid #cbd5e1; border-radius: 8px; padding: 1px 6px; margin: 1px 2px 1px 0; font-size: 11px; }
        .vehicle { border-color: #94a3b8; }
        .notes { font-size: 10.5px; color: #475569; margin-top: 3px; }
        .empty { color: #94a3b8; }
        @page { size: A4 landscape; margin: 10mm; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Drucken / Als PDF speichern</button>
    </div>

    <h1>Einteilung {{ $title }}</h1>
    <p class="subtitle">{{ $company }} — {{ $range }}</p>

    <table class="week">
        <thead>
            <tr>
                @foreach ($days as $day)
                    <th class="day">{{ $day['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach ($days as $day)
                    <td>
                        @forelse ($day['assignments'] as $assignment)
                            <div class="card" style="border-left-color: {{ $assignment['border'] }}; background: {{ $assignment['background'] }};">
                                <div class="label">{{ $assignment['label'] }}</div>
                                @foreach ($assignment['employees'] as $employee)
                                    <span class="person">{{ $employee }}</span>
                                @endforeach
                                @foreach ($assignment['vehicles'] as $vehicle)
                                    <span class="vehicle">&#128663; {{ $vehicle }}</span>
                                @endforeach
                                @if ($assignment['notes'] !== null)
                                    <div class="notes">{{ $assignment['notes'] }}</div>
                                @endif
                            </div>
                        @empty
                            <span class="empty">—</span>
                        @endforelse
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
