<?php

namespace App\Actions\CashRegisters;

use RuntimeException;

/**
 * A cash register rule said no. The message is already translated and is
 * shown as is, for example in a toast.
 */
class CashRegisterRejected extends RuntimeException {}
