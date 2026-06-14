@component('mail::message')
# Payment received

Dear {{ $name }},

We have received your payment. The details are below:

- **Receipt no:** {{ $receiptNo }}
- **Invoice:** {{ $invoiceNo }}
- **Amount:** {{ $amount }}

Thank you for your payment.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
