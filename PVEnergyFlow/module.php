<?php

class PVEnergyFlow extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('PVVariable', 22209);
        $this->RegisterPropertyInteger('BatteryVariable', 18192);
        $this->RegisterPropertyInteger('GridVariable', 13288);
        $this->RegisterPropertyInteger('WallboxVariable', 31735);
        $this->RegisterPropertyInteger('BatterySOCVariable', 45769);
        $this->RegisterPropertyInteger('CarSOCVariable', 48784);
        $this->RegisterPropertyInteger('PVTotalVariable', 54550);
        $this->RegisterPropertyInteger('GridImportVariable', 30181);
        $this->RegisterPropertyInteger('GridExportVariable', 29267);
        $this->RegisterPropertyInteger('WallboxSessionVariable', 17564);
        $this->RegisterPropertyInteger('PVTodayVariable', 0);
        $this->RegisterPropertyInteger('GridImportTodayVariable', 0);
        $this->RegisterPropertyInteger('GridExportTodayVariable', 0);
        $this->RegisterPropertyInteger('WallboxPhasesVariable', 23503);
        $this->RegisterPropertyInteger('WallboxRemainingVariable', 56785);
        $this->RegisterPropertyInteger('RemainingTimeUnit', 0);
        $this->RegisterPropertyInteger('RefreshSeconds', 30);
        $this->RegisterVariableString('PVEnergyTile', 'PV Energiefluss', '~HTMLBox', 0);
        $this->RegisterTimer('Refresh', 0, 'PVFLOW_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        // Native HTML/PHP-Kachel; HTMLBox bleibt für andere Clients verfügbar.
        if (method_exists($this, 'SetVisualizationType')) {
            $this->SetVisualizationType(1);
        }
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->StartUpdating();
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === IPS_KERNELMESSAGE && $Data[0] === KR_READY) {
            $this->StartUpdating();
        }
    }

    private function StartUpdating()
    {
        // Mindestens 10 Sekunden, damit Archivabfragen nicht unnötig oft laufen.
        $this->SetTimerInterval('Refresh', max(10, $this->ReadPropertyInteger('RefreshSeconds')) * 1000);
        $this->Update();
        $this->SetStatus(102);
    }

    public function Update()
    {
        $pvTileContext = [
            'parent' => $this->InstanceID,
            'remainingFactor' => in_array($this->ReadPropertyInteger('RemainingTimeUnit'), [0, 1, 60, 3600], true) ? $this->ReadPropertyInteger('RemainingTimeUnit') : 0,
            'ids' => [
                'pv' => $this->ReadPropertyInteger('PVVariable'),
                'battery' => $this->ReadPropertyInteger('BatteryVariable'),
                'grid' => $this->ReadPropertyInteger('GridVariable'),
                'ev' => $this->ReadPropertyInteger('WallboxVariable'),
                'phases' => $this->ReadPropertyInteger('WallboxPhasesVariable'),
                'remaining' => $this->ReadPropertyInteger('WallboxRemainingVariable'),
                'soc' => $this->ReadPropertyInteger('BatterySOCVariable'),
                'car' => $this->ReadPropertyInteger('CarSOCVariable'),
                'pvTotal' => $this->ReadPropertyInteger('PVTotalVariable'),
                'gridImport' => $this->ReadPropertyInteger('GridImportVariable'),
                'gridExport' => $this->ReadPropertyInteger('GridExportVariable'),
                'evSession' => $this->ReadPropertyInteger('WallboxSessionVariable'),
                'pvToday' => $this->ReadPropertyInteger('PVTodayVariable'),
                'importToday' => $this->ReadPropertyInteger('GridImportTodayVariable'),
                'exportToday' => $this->ReadPropertyInteger('GridExportTodayVariable'),
            ],
        ];
        // Gemeinsamer Renderer; im Modulmodus wird kein Skripttimer angelegt.
        include dirname(__DIR__) . '/tile.php';
        if (method_exists($this, 'UpdateVisualizationValue')) {
            $this->UpdateVisualizationValue($html);
        }
    }

    public function GetVisualizationTile()
    {
        // Die HTMLBox enthält bereits die initiale, sicher escaped srcdoc-Ausgabe.
        $initial = GetValue($this->GetIDForIdent('PVEnergyTile'));
        return $initial . file_get_contents(__DIR__ . '/assets/native.html');
    }

    public function GetConfigurationForm()
    {
        return file_get_contents(__DIR__ . '/form.json');
    }
}
