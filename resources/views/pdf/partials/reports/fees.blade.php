{{-- Fees report: invoiced/collected/outstanding totals, by-month, by-class. --}}
<div class="summary">
    <div class="item">
        <div class="val">{{ $data['totals']['invoiced'] }}</div>
        <div class="cap">Invoiced</div>
    </div>
    <div class="item">
        <div class="val">{{ $data['totals']['collected'] }}</div>
        <div class="cap">Collected</div>
    </div>
    <div class="item">
        <div class="val">{{ $data['totals']['outstanding'] }}</div>
        <div class="cap">Outstanding</div>
    </div>
</div>

<h2>By Month</h2>
<table class="data">
    <thead>
        <tr><th>Month</th><th class="num">Invoiced</th><th class="num">Collected</th><th class="num">Outstanding</th></tr>
    </thead>
    <tbody>
        @forelse ($data['by_month'] as $row)
            <tr><td>{{ $row['month'] }}</td><td class="num">{{ $row['invoiced'] }}</td><td class="num">{{ $row['collected'] }}</td><td class="num">{{ $row['outstanding'] }}</td></tr>
        @empty
            <tr><td colspan="4" class="empty">No invoices in this period.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>By Class</h2>
<table class="data">
    <thead>
        <tr><th>Class</th><th class="num">Invoiced</th><th class="num">Collected</th></tr>
    </thead>
    <tbody>
        @forelse ($data['by_class'] as $row)
            <tr><td>{{ $row['class'] }}</td><td class="num">{{ $row['invoiced'] }}</td><td class="num">{{ $row['collected'] }}</td></tr>
        @empty
            <tr><td colspan="3" class="empty">No invoices in this period.</td></tr>
        @endforelse
    </tbody>
</table>
