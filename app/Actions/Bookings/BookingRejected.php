<?php

namespace App\Actions\Bookings;

use RuntimeException;

/**
 * A booking rule said no. The message is already translated and is shown
 * as is, for example in a toast.
 */
class BookingRejected extends RuntimeException {}
