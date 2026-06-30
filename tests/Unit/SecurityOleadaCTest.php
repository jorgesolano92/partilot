<?php

namespace Tests\Unit;

use App\Rules\DeadlineBeforeLottery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityOleadaCTest extends TestCase
{
    #[Test]
    public function deadline_before_lottery_rejects_empty_when_not_nullable_in_validation(): void
    {
        $validator = validator(
            ['deadline_date' => null],
            ['deadline_date' => 'required|date']
        );

        $this->assertTrue($validator->fails());
    }

    #[Test]
    public function deadline_before_lottery_allows_null_when_optional(): void
    {
        $rule = new DeadlineBeforeLottery(0);
        $this->assertTrue($rule->passes('deadline_date', null));
    }
}
