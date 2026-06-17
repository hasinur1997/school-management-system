<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ strip_tags($title) }}</title>
    <style>
        @page { margin: 36px 48px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 12px; }
        .header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 20px; }
        .filters { font-size: 11px; color: #555; margin-bottom: 18px; }
        .filters span { margin-right: 16px; }
        .filters .label { color: #888; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.data th, table.data td { padding: 5px 6px; text-align: left; border-bottom: 1px solid #eee; }
        table.data th { background: #f4f4f4; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.data td.num, table.data th.num { text-align: right; }
        .summary { margin-bottom: 6px; }
        .summary .item { display: inline-block; margin-right: 28px; }
        .summary .item .val { font-size: 16px; font-weight: bold; }
        .summary .item .cap { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .empty { color: #999; font-style: italic; font-size: 11px; }
        .footer { margin-top: 28px; font-size: 10px; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{!! $title !!}</h1>
    </div>

    <div class="filters">
        <span><span class="label">Period:</span> {{ ucfirst($filters['period']) }}</span>
        <span><span class="label">From:</span> {{ $filters['from'] }}</span>
        <span><span class="label">To:</span> {{ $filters['to'] }}</span>
        <span><span class="label">Branch:</span> {{ $filters['branch'] }}</span>
    </div>

    @include("pdf.partials.reports.{$partial}", ['data' => $data])

    <div class="footer">This is a computer-generated report.</div>
</body>
</html>
