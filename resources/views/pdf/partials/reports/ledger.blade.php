{{-- Income / expense report: total, by-category, series, and (consolidated) by-branch. --}}
<div class="summary">
    <div class="item">
        <div class="val">{{ $data['total'] }}</div>
        <div class="cap">Total</div>
    </div>
</div>

<h2>By Category</h2>
<table class="data">
    <thead>
        <tr><th>Category</th><th class="num">Amount</th></tr>
    </thead>
    <tbody>
        @forelse ($data['by_category'] as $row)
            <tr><td>{{ $row['category'] }}</td><td class="num">{{ $row['amount'] }}</td></tr>
        @empty
            <tr><td colspan="2" class="empty">No records in this period.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Series</h2>
<table class="data">
    <thead>
        <tr><th>Date</th><th class="num">Amount</th></tr>
    </thead>
    <tbody>
        @forelse ($data['series'] as $row)
            <tr><td>{{ $row['date'] }}</td><td class="num">{{ $row['amount'] }}</td></tr>
        @empty
            <tr><td colspan="2" class="empty">No records in this period.</td></tr>
        @endforelse
    </tbody>
</table>

@if (! empty($data['by_branch']))
    <h2>By Branch</h2>
    <table class="data">
        <thead>
            <tr><th>Branch</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($data['by_branch'] as $row)
                <tr><td>{{ $row['branch'] }}</td><td class="num">{{ $row['amount'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif
