<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Rules\EvaluationTruth;

it('evaluates ALL with four-valued logic', function (array $values, EvaluationTruth $expected): void {
    expect(EvaluationTruth::all($values))->toBe($expected);
})->with([
    'all true' => [[EvaluationTruth::TRUE, EvaluationTruth::TRUE], EvaluationTruth::TRUE],
    'false dominates unknown' => [[EvaluationTruth::FALSE, EvaluationTruth::UNKNOWN], EvaluationTruth::FALSE],
    'unknown without false' => [[EvaluationTruth::TRUE, EvaluationTruth::UNKNOWN], EvaluationTruth::UNKNOWN],
    'error blocks' => [[EvaluationTruth::FALSE, EvaluationTruth::ERROR], EvaluationTruth::ERROR],
]);

it('evaluates ANY with four-valued logic', function (array $values, EvaluationTruth $expected): void {
    expect(EvaluationTruth::any($values))->toBe($expected);
})->with([
    'one true' => [[EvaluationTruth::FALSE, EvaluationTruth::TRUE], EvaluationTruth::TRUE],
    'all false' => [[EvaluationTruth::FALSE, EvaluationTruth::FALSE], EvaluationTruth::FALSE],
    'unknown without true' => [[EvaluationTruth::FALSE, EvaluationTruth::UNKNOWN], EvaluationTruth::UNKNOWN],
    'error blocks' => [[EvaluationTruth::TRUE, EvaluationTruth::ERROR], EvaluationTruth::ERROR],
]);
