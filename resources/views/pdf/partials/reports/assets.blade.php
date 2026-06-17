{{-- Asset report: total value, count, by-status (count + value), additions in range. --}}
<div class="summary">
    <div class="item">
        <div class="val">{{ $data['total_value'] }}</div>
        <div class="cap">Total Value</div>
    </div>
    <div class="item">
        <div class="val">{{ $data['count'] }}</div>
        <div class="cap">Total Assets</div>
    </div>
    <div class="item">
        <div class="val">{{ $data['additions']['count'] }} / {{ $data['additions']['value'] }}</div>
        <div class="cap">Additions (count / value)</div>
    </div>
</div>

<h2>By Status</h2>
<table class="data">
    <thead>
        <tr><th>Status</th><th class="num">Count</th><th class="num">Value</th></tr>
    </thead>
    <tbody>
        @foreach ($data['by_status'] as $status => $row)
            <tr><td>{{ ucfirst(str_replace('_', ' ', $status)) }}</td><td class="num">{{ $row['count'] }}</td><td class="num">{{ $row['value'] }}</td></tr>
        @endforeach
    </tbody>
</table>
