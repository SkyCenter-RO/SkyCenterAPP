<?php

namespace App\Actions\Scheduling;

use App\Models\User;
use App\Models\WorkShift;
use Smalot\PdfParser\Parser;

class ParseSchedulePdfAction
{
    private const MONTHS_MAP = [
        'ianuarie' => 1,
        'februarie' => 2,
        'martie' => 3,
        'aprilie' => 4,
        'mai' => 5,
        'iunie' => 6,
        'iulie' => 7,
        'august' => 8,
        'septembrie' => 9,
        'octombrie' => 10,
        'noiembrie' => 11,
        'decembrie' => 12,
    ];

    public function execute(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Fișierul nu există la calea: {$filePath}");
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        // Extract month & year from header
        if (!preg_match('/Program Ture\s*-\s*(?P<month>[a-zA-ZăâîșțĂÂÎȘȚ]+)\s+(?P<year>\d{4})/ui', $text, $matches)) {
            throw new \RuntimeException('Nu s-a putut găsi antetul cu luna și anul în PDF.');
        }

        $monthName = mb_strtolower($matches['month']);
        $year = (int) $matches['year'];

        if (!isset(self::MONTHS_MAP[$monthName])) {
            throw new \RuntimeException("Luna necunoscută: {$matches['month']}");
        }

        $monthNum = self::MONTHS_MAP[$monthName];

        $operators = User::query()->where('role', User::ROLE_OPERATOR)->get();

        $lines = explode("\n", $text);
        $importedCount = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(?P<day>\d{1,2})\s+(?P<zi>[a-zA-ZăâîșțĂÂÎȘȚ]+)\s+(?P<noapte>[a-zA-ZăâîșțĂÂÎȘȚ]+)$/ui', $line, $rowMatches)) {
                $day = (int) $rowMatches['day'];
                $dateString = sprintf('%04d-%02d-%02d', $year, $monthNum, $day);

                $ziName = trim($rowMatches['zi']);
                $noapteName = trim($rowMatches['noapte']);

                $ziUser = $operators->first(fn($u) => mb_strtolower($u->name) === mb_strtolower($ziName));
                $noapteUser = $operators->first(fn($u) => mb_strtolower($u->name) === mb_strtolower($noapteName));

                WorkShift::query()->updateOrCreate(
                    ['date' => $dateString, 'shift_type' => 'zi'],
                    [
                        'user_id' => $ziUser?->id,
                        'raw_employee_name' => $ziName
                    ]
                );

                WorkShift::query()->updateOrCreate(
                    ['date' => $dateString, 'shift_type' => 'noapte'],
                    [
                        'user_id' => $noapteUser?->id,
                        'raw_employee_name' => $noapteName
                    ]
                );

                $importedCount++;
            }
        }

        if ($importedCount === 0) {
            throw new \RuntimeException('Nu s-au găsit rânduri valide de program de tură.');
        }

        return [
            'month_name' => $matches['month'],
            'year' => $year,
            'imported_days' => $importedCount,
        ];
    }
}
