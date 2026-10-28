<?php

/** Nur Archivdaten speichern; Livewerte werden bei jedem Lauf neu gelesen. */
class PVEnergyArchiveCache
{
    public static function remember(array $previous, string $signature, int $now, int $interval, callable $read): array
    {
        if (($previous['signature'] ?? null) === $signature
            && isset($previous['updated'], $previous['values'])
            && is_int($previous['updated']) && is_array($previous['values'])
            && $previous['updated'] <= $now
            && $now - $previous['updated'] < max(60, $interval)) {
            return $previous;
        }
        return ['signature' => $signature, 'updated' => $now, 'values' => $read()];
    }

    public static function read(array $config, int $archive, int $now): array
    {
        $values = [];
        foreach ($config as $key => $item) {
            $entry = ['history' => [], 'archive' => false, 'error' => null];
            if (!$archive || !($item['chart'] ?? true) || !IPS_VariableExists($item['id'])) {
                $values[$key] = $entry;
                continue;
            }
            try {
                $entry['archive'] = AC_GetLoggingStatus($archive, $item['id']);
                if ($entry['archive']) {
                    if (($item['kind'] ?? '') === 'dailyCounter') {
                        $today = (new DateTimeImmutable())->setTimestamp($now)->setTime(0, 0);
                        $start = $today->modify('-6 days');
                        $entry['days'] = [];
                        for ($day = $start; $day <= $today; $day = $day->modify('+1 day')) {
                            $entry['days'][] = ['day' => $day->format('Y-m-d'), 'label' => $day->format('d.m.'), 'today' => $day == $today];
                        }
                        if (AC_GetAggregationType($archive, $item['id']) !== 1) {
                            $entry['error'] = 'Diese Variable muss im Archive Control als Zähler archiviert sein. Danach ggf. neu aggregieren.';
                        } else {
                            $rows = AC_GetAggregatedValues($archive, $item['id'], 1, $start->getTimestamp(), $now, 0);
                            if (!is_array($rows)) throw new RuntimeException('Archivdaten fehlen');
                            foreach ($rows as $row) $entry['history'][] = ['t' => $row['TimeStamp'], 'day' => date('Y-m-d', $row['TimeStamp']), 'v' => $row['Avg'] * $item['factor']];
                        }
                    } elseif (($item['kind'] ?? '') === 'counter') {
                        $rows = AC_GetLoggedValues($archive, $item['id'], $now - 86400, $now, 2000);
                        if (!is_array($rows)) throw new RuntimeException('Archivdaten fehlen');
                        $entry['limited'] = count($rows) >= 2000;
                        foreach ($rows as $row) $entry['history'][] = ['t' => $row['TimeStamp'], 'v' => $row['Value'] * $item['factor']];
                    } else {
                        $rows = AC_GetAggregatedValues($archive, $item['id'], 0, $now - 86400, $now, 0);
                        if (!is_array($rows)) throw new RuntimeException('Archivdaten fehlen');
                        foreach ($rows as $row) $entry['history'][] = ['t' => $row['TimeStamp'], 'v' => $row['Avg'] * $item['factor']];
                    }
                    usort($entry['history'], static function ($a, $b) { return $a['t'] <=> $b['t']; });
                }
            } catch (Throwable $e) {
                $entry['error'] = 'Archiv derzeit nicht lesbar';
            }
            $values[$key] = $entry;
        }
        return $values;
    }
}
