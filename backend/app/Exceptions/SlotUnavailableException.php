<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The requested start time is not a free slot (any more): BR-1 / BR-2 → 409.
 * Rendered as { message } in the request language (bootstrap/app.php).
 */
class SlotUnavailableException extends HttpException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_CONFLICT, 'Slot no longer available.');
    }
}
