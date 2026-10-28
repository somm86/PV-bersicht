<?php
/** In Symcon: include __DIR__ . '/PV-bersicht/tile.php'; */
$config = [
    // Faktoren beziehen sich auf den ROHEN Variablenwert, nicht auf die Profilanzeige.
    // Quellen, Leistungsrichtungen und Rohwerteinheiten vom Nutzer bestätigt:
    // PV, Speicher und Netz in W; Wallbox in kW (Umrechnung mit Faktor 1000).
    'pv'      => ['id' => 22209, 'factor' => 1, 'label' => 'PV', 'unit' => 'W'],
    'battery' => ['id' => 18192, 'factor' => 1, 'label' => 'Speicher', 'unit' => 'W'],
    'grid'    => ['id' => 13288, 'factor' => 1, 'label' => 'Netz', 'unit' => 'W'],
    'ev'      => ['id' => 31735, 'factor' => 1000, 'label' => 'Wallbox', 'unit' => 'W'],
    'soc'     => ['id' => 45769, 'factor' => 1, 'label' => 'Speicherstand', 'unit' => '%'],
    'car'     => ['id' => 48784, 'factor' => 1, 'label' => 'Fahrzeugakku', 'unit' => '%'],
    'pvTotal' => ['id' => 54550, 'factor' => 1, 'label' => 'PV-Erzeugung gesamt', 'unit' => 'kWh', 'kind' => 'counter'],
    'gridImport' => ['id' => 30181, 'factor' => 1, 'label' => 'Netzbezug gesamt', 'unit' => 'kWh', 'kind' => 'counter'],
    'gridExport' => ['id' => 29267, 'factor' => 1, 'label' => 'Netzeinspeisung gesamt', 'unit' => 'kWh', 'kind' => 'counter'],
    'evSession' => ['id' => 17564, 'factor' => 1, 'label' => 'Wallbox Ladezyklus', 'unit' => 'kWh', 'kind' => 'counter'],
    'pvToday' => ['id' => 0, 'factor' => 1, 'label' => 'PV heute', 'unit' => 'kWh', 'kind' => 'counter'],
    'importToday' => ['id' => 0, 'factor' => 1, 'label' => 'Netzbezug heute', 'unit' => 'kWh', 'kind' => 'counter'],
    'exportToday' => ['id' => 0, 'factor' => 1, 'label' => 'Einspeisung heute', 'unit' => 'kWh', 'kind' => 'counter'],
    'phases' => ['id' => 23503, 'factor' => 1, 'label' => 'Ladephasen', 'unit' => '', 'chart' => false],
    'remaining' => ['id' => 56785, 'factor' => 0, 'label' => 'Restladezeit', 'unit' => 's', 'chart' => false],
];
// Die Modulinstanz kann IDs übergeben; der direkte Skriptbetrieb bleibt verfügbar.
if (isset($pvTileContext)) {
    $config['remaining']['factor'] = $pvTileContext['remainingFactor'] ?? 0;
    foreach ($pvTileContext['ids'] as $key => $id) {
        $config[$key]['id'] = $id;
    }
}
// Vorzeichen: Batterie positiv = LADEN; Netz positiv = BEZUG.
// Faktor -1 bzw. -1000 verwenden, wenn die Quelle andersherum zählt.
$archiveIDs = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
$archive = $archiveIDs[0] ?? 0;
$now = time();
$data = ['updated' => $now, 'values' => []];
foreach ($config as $key => $item) {
    $entry = $item + ['value' => null, 'history' => [], 'archive' => false, 'error' => null];
    if (IPS_VariableExists($item['id'])) {
        $raw = GetValue($item['id']);
        if (is_numeric($raw)) {
            $entry['value'] = (float)$raw * $item['factor'];
            if ($key === 'remaining') {
                $entry['raw'] = (float)$raw;
                $entry['unitKnown'] = (float)$item['factor'] !== 0.0;
                if (!$entry['unitKnown']) $entry['value'] = null;
            }
        }
        if ($archive && ($item['chart'] ?? true)) {
            try {
                $entry['archive'] = AC_GetLoggingStatus($archive, $item['id']);
                if ($entry['archive']) {
                    if (($item['kind'] ?? '') === 'counter') {
                        // Zählerstände niemals als Leistungs-/Verbrauchsmittel darstellen.
                        // Begrenztes Rohwertfenster, bei Erreichen der Grenze explizit markieren.
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
            } catch (Throwable $e) { $entry['error'] = 'Archiv derzeit nicht lesbar'; }
        }
    }
    $data['values'][$key] = $entry;
}
$template = file_get_contents(__DIR__ . '/PVEnergyFlow/assets/tile.html');
$html = str_replace('/*__DATA__*/null', json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), $template);
// Ident innerhalb des ausführenden Symcon-Skripts: einmalig anlegen, danach aktualisieren.
$parent = isset($pvTileContext) ? $pvTileContext['parent'] : $_IPS['SELF'];
$box = @IPS_GetObjectIDByIdent('PVEnergyTile', $parent);
if (!$box) {
    $box = IPS_CreateVariable(3);
    IPS_SetParent($box, $parent);
    IPS_SetIdent($box, 'PVEnergyTile');
    IPS_SetName($box, 'PV Energiefluss');
    IPS_SetVariableCustomProfile($box, '~HTMLBox');
}
SetValue($box, '<iframe title="PV Energiefluss" sandbox="allow-scripts" style="border:0;width:100%;height:100%;min-height:300px;display:block" srcdoc="' . htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></iframe>');
// Nur das dedizierte aufrufende Skript verwenden! Timer führt es alle 30 s aus.
if (!isset($pvTileContext)) {
    IPS_SetScriptTimer($parent, 30);
}
