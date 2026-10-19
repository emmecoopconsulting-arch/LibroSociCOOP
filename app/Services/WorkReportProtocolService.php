<?php

namespace App\Services;

use Illuminate\Database\Connection;

class WorkReportProtocolService
{
    public function next(Connection $connection): string
    {
        $year = now()->year;

        return $connection->transaction(function () use ($connection, $year): string {
            $counter = $connection->table('work_report_protocol_counters');
            $counter->insertOrIgnore(['year' => $year, 'last_number' => 0]);

            $number = (int) $counter->where('year', $year)->lockForUpdate()->value('last_number');

            // Continue after existing manual protocols, including shorter padding.
            if ($number === 0) {
                foreach ($connection->table('work_reports')->where('protocollo', 'like', "RI-{$year}-%")->pluck('protocollo') as $protocol) {
                    if (preg_match('/^RI-'.$year.'-([0-9]+)$/', $protocol, $matches)) {
                        $number = max($number, (int) $matches[1]);
                    }
                }
            }

            do {
                $protocol = sprintf('RI-%d-%06d', $year, ++$number);
            } while ($connection->table('work_reports')->where('protocollo', $protocol)->exists());

            $counter->where('year', $year)->update(['last_number' => $number]);

            return $protocol;
        }, attempts: 5);
    }
}
