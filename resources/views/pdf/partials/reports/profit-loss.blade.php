{{-- Profit & loss: income/expense totals, net, combined series, and (consolidated) by-branch. --}}
<div class="summary">
    <div class="item">
        <div class="val">{{ $data['income_total'] }}</div>
        <div class="cap">Income</div>
    </div>
    <div class="item">
        <div class="val">{{ $data['expense_total'] }}</div>
        <div class="cap">Expense</div>
    </div>
    <div class="item">
        <div class="val">{{ $data['net'] }}</div>
        <div class="cap">Net</div>
    </div>
</div>

<h2>Series</h2>
<table class="data">
    <thead>
        <tr><th>Date</th><th class="num">Income</th><th class="num">Expense</th></tr>
    </thead>
    <tbody>
        @forelse ($data['series'] as $row)
            <tr><td>{{ $row['date'] }}</td><td class="num">{{ $row['income'] }}</td><td class="num">{{ $row['expense'] }}</td></tr>
        @empty
            <tr><td colspan="3" class="empty">No records in this period.</td></tr>
        @endforelse
    </tbody>
</table>

@if (! empty($data['by_branch']))
    <h2>By Branch</h2>
    <table class="data">
        <thead>
            <tr><th>Branch</th><th class="num">Income</th><th class="num">Expense</th><th class="num">Net</th></tr>
        </thead>
        <tbody>
            @foreach ($data['by_branch'] as $row)
                <tr><td>{{ $row['branch'] }}</td><td class="num">{{ $row['income'] }}</td><td class="num">{{ $row['expense'] }}</td><td class="num">{{ $row['net'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif
