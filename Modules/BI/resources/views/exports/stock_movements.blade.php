<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export Mouvements de Stock</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p.meta { color: #666; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Export Mouvements de Stock</h1>
    <p class="meta">Généré le {{ now()->format('d/m/Y H:i') }}</p>

    @if (count($headings))
        <table>
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Aucune donnée disponible pour ce rapport.</p>
    @endif
</body>
</html>
