<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('phoneNumbers')]
    public function test_normalize_converts_to_local_format(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function phoneNumbers(): array
    {
        return [
            'plus40 with spaces' => ['+40 722 123 456', '0722123456'],
            '0040 prefix' => ['0040722123456', '0722123456'],
            'local with separators' => ['0722.123.456', '0722123456'],
            'already local' => ['0733111222', '0733111222'],
            'null input' => [null, null],
            'empty string' => ['', null],
        ];
    }
}
