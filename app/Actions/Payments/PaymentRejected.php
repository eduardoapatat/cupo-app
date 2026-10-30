<?php

namespace App\Actions\Payments;

use RuntimeException;

/**
 * A payment rule said no. The message is already translated and is shown
 * as is, for example in a toast.
 */
class PaymentRejected extends RuntimeException {}
