<?php
// Ausführbarer Regressionstest ohne Symcon: nur die verwendeten APIs simulieren.
require_once dirname(__DIR__) . '/ArchiveCache.php';
date_default_timezone_set('Europe/Vienna');
$queries = 0;
$failArchive = false;
$variables = [1 => 100, 2 => 12, 3 => 1000, 4 => 3];
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function IPS_VariableExists($id) { return array_key_exists($id, $GLOBALS['variables']); }
function AC_GetLoggingStatus($archive, $id) { $GLOBALS['queries']++; return true; }
function AC_GetAggregationType($archive, $id) { $GLOBALS['queries']++; return 1; }
function AC_GetAggregatedValues($archive, $id, $level, $start, $end, $limit) {
    $GLOBALS['queries']++;
    if ($GLOBALS['failArchive']) throw new RuntimeException('simulierter Archivfehler');
    return [['TimeStamp' => $end - 3600, 'Avg' => 4], ['TimeStamp' => $start, 'Avg' => 2]];
}
function AC_GetLoggedValues($archive, $id, $start, $end, $limit) {
    $GLOBALS['queries']++;
    return [['TimeStamp' => $end, 'Value' => 12], ['TimeStamp' => $start, 'Value' => 10]];
}
$config = [
    'power' => ['id' => 1, 'factor' => 1],
    'counter' => ['id' => 2, 'factor' => 1, 'kind' => 'counter'],
    'daily' => ['id' => 3, 'factor' => 1, 'kind' => 'dailyCounter'],
    'status' => ['id' => 4, 'factor' => 1, 'chart' => false],
    'missing' => ['id' => 0, 'factor' => 1],
];
$now = 1793188800;
$reader = static function () use ($config, $now) { return PVEnergyArchiveCache::read($config, 100, $now); };
$cache = PVEnergyArchiveCache::remember([], 'sources-A', $now, 300, $reader);
check($queries === 7, 'erster Lauf muss Archivdaten laden');
check(count($cache['values']['power']['history']) === 2, 'Leistungshistorie fehlt');
check($cache['values']['counter']['history'][0]['v'] === 10, 'Rohzähler müssen sortiert bleiben');
check(count($cache['values']['daily']['days']) === 7, 'Tagesbalken müssen sieben Tage behalten');
check(!$cache['values']['status']['archive'], 'Statuswerte dürfen kein Archiv lesen');
for ($elapsed = 30; $elapsed < 300; $elapsed += 30) {
    $cache = PVEnergyArchiveCache::remember($cache, 'sources-A', $now + $elapsed, 300, $reader);
}
check($queries === 7, 'neun Livezyklen dürfen keine zusätzlichen Archivabfragen auslösen');
check(count($cache['values']['counter']['history']) === 2, 'Cachetreffer müssen Diagrammdaten behalten');
$cache = PVEnergyArchiveCache::remember($cache, 'sources-A', $now + 300, 300, $reader);
check($queries === 14, 'nach exakt 300 Sekunden muss neu geladen werden');
$cache = PVEnergyArchiveCache::remember($cache, 'sources-B', $now + 301, 300, $reader);
check($queries === 21, 'neue Quellen/Tagessignatur müssen sofort neu geladen werden');
$cache = PVEnergyArchiveCache::remember($cache, 'sources-B', $now + 300, 300, $reader);
check($queries === 28, 'Cache aus der Zukunft darf nicht weiterverwendet werden');
$cache = PVEnergyArchiveCache::remember([], 'sources-B', $now + 302, 300, $reader);
check($queries === 35, 'leerer Cache (manuelle Aktualisierung) muss neu laden');
$failArchive = true;
$errors = PVEnergyArchiveCache::remember([], 'errors', $now, 300, $reader);
$failedQueries = $queries;
check($errors['values']['power']['error'] === 'Archiv derzeit nicht lesbar', 'Archivfehler muss sichtbar sein');
PVEnergyArchiveCache::remember($errors, 'errors', $now + 30, 300, $reader);
check($queries === $failedQueries, 'Fehler dürfen keine Wiederholung alle 30 Sekunden auslösen');
$failArchive = false;

// Den tatsächlichen Renderer wiederholt mit demselben Instanzcache ausführen.
function IPS_GetInstanceListByModuleID($module) { return [100]; }
function GetValue($id) { return $GLOBALS['variables'][$id]; }
function GetValueFormatted($id) { return (string)GetValue($id); }
function IPS_GetObjectIDByIdent($ident, $parent) { return 999; }
function SetValue($id, $value) { $GLOBALS['rendered'] = $value; }
function renderTile(array $previous) {
    $pvTileContext = ['parent' => 900, 'archiveInterval' => 300, 'archiveCache' => $previous, 'ids' => []];
    include dirname(__DIR__, 2) . '/tile.php';
    return [$archiveCache, $data];
}
$variables = [22209 => 3741, 18192 => 0, 13288 => -3201, 31735 => 0, 45769 => 89, 48784 => 61, 23503 => 0, 56785 => 90];
[$firstCache, $firstData] = renderTile([]);
$before = $queries;
$variables[22209] = 4200;
$variables[13288] = -3500;
[$secondCache, $secondData] = renderTile($firstCache);
check($queries === $before, 'zweites tatsächliches Rendern darf das Archiv nicht abfragen');
check($secondData['values']['pv']['value'] === 4200.0, 'PV-Livewert muss trotz Archivcache aktuell sein');
check($secondData['values']['grid']['value'] === -3500.0, 'Netz-Livewert muss aktuell sein');
check($secondData['values']['pv']['history'] === $firstData['values']['pv']['history'], 'Historie muss erhalten bleiben');
check($secondData['archiveUpdated'] === $firstData['archiveUpdated'], 'Archivzeitpunkt muss stabil bleiben');
check(strpos($GLOBALS['rendered'], 'iframe') !== false, 'HTMLBox muss weiterhin gerendert werden');
echo "PASS: Erstladung, neun Cachetreffer ohne Archivaufrufe, 300-s-Grenze, Signaturwechsel, Zeitrücksprung, manuelles Neuladen, Fehlercache und aktuelle Livewerte im echten Renderer\n";
