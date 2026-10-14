<?php

declare(strict_types=1);

namespace App\Library\Domain\Errors;

use DomainException;

final class DuplicateBookInLoan extends DomainException
{
}
