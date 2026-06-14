<?php

namespace App\Enums;

/**
 * How a payment was made. Stored as VARCHAR(20). Counter payments are `cash`
 * (Task 10.3); online checkout via SSLCommerz is `sslcommerz` (Task 10.4/10.5).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Sslcommerz = 'sslcommerz';
}
