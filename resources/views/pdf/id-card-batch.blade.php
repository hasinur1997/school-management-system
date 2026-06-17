<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Cards</title>
    @include('pdf.partials.id-card-styles')
    <style>
        /* One card per page; each wrapper fills the CR80 page so the shared card
           body keeps the single-card layout. No trailing blank page after last. */
        .card-page { height: 242.65pt; position: relative; }
    </style>
</head>
<body>
    @foreach ($cards as $card)
        <div class="card-page" @if (! $loop->last) style="page-break-after: always;" @endif>
            @include('pdf.partials.id-card-body', $card)
        </div>
    @endforeach
</body>
</html>
