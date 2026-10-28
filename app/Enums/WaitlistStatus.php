<?php

namespace App\Enums;

/**
 * Where a place in the waitlist stands: still waiting, turned into a
 * booking when a spot freed up, or left (she left, booked it herself, or
 * the class was cancelled).
 */
enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Promoted = 'promoted';
    case Left = 'left';
}
