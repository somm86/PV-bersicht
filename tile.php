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
    'gridImport' => ['id' => 30181, 'factor' => 1, 'label' => 'Netzbezug gesamt', 'unit' => 'kWh', 'kind' => 'dailyCounter'],
    'gridExport' => ['id' => 29267, 'factor' => 1, 'label' => 'Netzeinspeisung gesamt', 'unit' => 'kWh', 'kind' => 'dailyCounter'],
    'evSession' => ['id' => 17564, 'factor' => 1, 'label' => 'Wallbox Ladezyklus', 'unit' => 'kWh', 'kind' => 'counter'],
    'pvToday' => ['id' => 0, 'factor' => 1, 'label' => 'PV heute', 'unit' => 'kWh', 'kind' => 'counter'],
    'importToday' => ['id' => 0, 'factor' => 1, 'label' => 'Netzbezug heute', 'unit' => 'kWh', 'kind' => 'counter'],
    'exportToday' => ['id' => 0, 'factor' => 1, 'label' => 'Einspeisung heute', 'unit' => 'kWh', 'kind' => 'counter'],
    'phases' => ['id' => 23503, 'factor' => 1, 'label' => 'Ladephasen', 'unit' => '', 'chart' => false],
    'remaining' => ['id' => 56785, 'factor' => 1, 'label' => 'Restladezeit', 'unit' => '', 'chart' => false],
];
// Die Modulinstanz kann IDs übergeben; der direkte Skriptbetrieb bleibt verfügbar.
if (isset($pvTileContext)) {
    foreach ($pvTileContext['ids'] as $key => $id) {
        $config[$key]['id'] = $id;
    }
}
// Vorzeichen: Batterie positiv = LADEN; Netz positiv = BEZUG.
// Faktor -1 bzw. -1000 verwenden, wenn die Quelle andersherum zählt.
$archiveIDs = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
$archive = $archiveIDs[0] ?? 0;
$now = time();
require_once __DIR__ . '/PVEnergyFlow/ArchiveCache.php';
$parent = isset($pvTileContext) ? $pvTileContext['parent'] : $_IPS['SELF'];
$archiveInterval = isset($pvTileContext) ? ($pvTileContext['archiveInterval'] ?? 300) : 300;
// Datum/Zeitzone, Quellen und Archivinstanz ändern die Bedeutung des Caches.
$signature = hash('sha256', json_encode([1, $config, $archive, date('Y-m-d', $now), date_default_timezone_get()]));
$cacheFile = sys_get_temp_dir() . '/pv-energy-archive-' . $parent . '.json';
$previousCache = isset($pvTileContext) ? ($pvTileContext['archiveCache'] ?? []) : (is_file($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : []);
if (!is_array($previousCache)) $previousCache = [];
$archiveCache = PVEnergyArchiveCache::remember($previousCache, $signature, $now, $archiveInterval,
    static function () use ($config, $archive, $now) { return PVEnergyArchiveCache::read($config, $archive, $now); });
if (!isset($pvTileContext) && $archiveCache !== $previousCache) {
    file_put_contents($cacheFile, json_encode($archiveCache, JSON_THROW_ON_ERROR), LOCK_EX);
    chmod($cacheFile, 0600);
}
$data = ['updated' => $now, 'archiveUpdated' => $archiveCache['updated'], 'values' => []];
foreach ($config as $key => $item) {
    $entry = $item + ['value' => null, 'history' => [], 'archive' => false, 'error' => null];
    if (IPS_VariableExists($item['id'])) {
        // Diese Abfragen laufen weiterhin bei JEDER Aktualisierung.
        $raw = GetValue($item['id']);
        if ($key === 'remaining') $entry['display'] = GetValueFormatted($item['id']);
        if (is_numeric($raw)) $entry['value'] = (float)$raw * $item['factor'];
        $entry = array_replace($entry, $archiveCache['values'][$key] ?? []);
    }
    $data['values'][$key] = $entry;
}
$template = file_get_contents(__DIR__ . '/PVEnergyFlow/assets/tile.html');
$html = str_replace('/*__DATA__*/null', json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), $template);
// Ident innerhalb des ausführenden Symcon-Skripts: einmalig anlegen, danach aktualisieren.
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
