<?php

namespace App\Webpay;

use RuntimeException;

/**
 * Transbank did not answer, or answered with an error: the transaction
 * could not be created, confirmed, read or refunded.
 */
class WebpayException extends RuntimeException {}
