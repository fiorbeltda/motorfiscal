<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

enum IssWithholdingDecision: string
{
    case WITHHOLD = 'WITHHOLD';
    case DO_NOT_WITHHOLD = 'DO_NOT_WITHHOLD';
    case WITHHOLDING_PROHIBITED = 'WITHHOLDING_PROHIBITED';
    case NEEDS_MUNICIPAL_RULE = 'NEEDS_MUNICIPAL_RULE';
    case MANUAL_REVIEW = 'MANUAL_REVIEW';
}
