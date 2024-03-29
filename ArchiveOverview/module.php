<?php

// Klassendefinition
class ArchiveOverview extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","ArchiveOverview");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("ArchiveId",0);
		$this->RegisterPropertyInteger("SourceVariable",0);
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
				
		// Default Actions
		$this->EnableAction("Status");
		
		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'ARCHIVEOV_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {

		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		$this->RegisterReference($this->ReadPropertyInteger("ArchiveId"));
		$this->RegisterReference($this->ReadPropertyInteger("SourceVariable"));

		// Create variables only after basic configuration is done
		if ( ($this->ReadPropertyInteger('SourceVariable') != 0) && ($this->ReadPropertyInteger('ArchiveId') != 0) ) {

			// Set Variables for counter type variables
			if (AC_GetAggregationType($this->ReadPropertyInteger('ArchiveId'), $this->ReadPropertyInteger('SourceVariable')) == 1) {

				// Create variables for counter aggregation types
				$this->RegisterVariableFloat("CountHourly","Count This Hour","");
				$this->RegisterVariableFloat("CountDaily","Count Today","");
				$this->RegisterVariableFloat("CountWeekly","Count This Week","");
				$this->RegisterVariableFloat("CountMonthly","Count This Month","");
				$this->RegisterVariableFloat("CountYearly","Count This Year","");
			}
			else {

				// Create variables for regular aggregation types
				$this->RegisterVariableFloat("DailyAvg","Today - Average","");
				$this->RegisterVariableFloat("DailyMin","Today - Minimum","");
				$this->RegisterVariableInteger("DailyMinTime","Today - Minimum - Timestamp","~UnixTimestamp");
				$this->RegisterVariableFloat("DailyMax","Today - Maximum","");
				$this->RegisterVariableInteger("DailyMaxTime","Today - Maximum - Timestamp","~UnixTimestamp");

				$this->RegisterVariableFloat("WeeklyAvg","This Week - Average","");
				$this->RegisterVariableFloat("WeeklyMin","This Week - Minimum","");
				$this->RegisterVariableInteger("WeeklyMinTime","This Week - Minimum - Timestamp","~UnixTimestamp");
				$this->RegisterVariableFloat("WeeklyMax","This Week - Maximum","");
				$this->RegisterVariableInteger("WeeklyMaxTime","This Week - Maximum - Timestamp","~UnixTimestamp");

				$this->RegisterVariableFloat("MonthlyAvg","This Month - Average","");
				$this->RegisterVariableFloat("MonthlyMin","This Month - Minimum","");
				$this->RegisterVariableInteger("MonthlyMinTime","This Month - Minimum - Timestamp","~UnixTimestamp");
				$this->RegisterVariableFloat("MonthlyMax","This Month - Maximum","");
				$this->RegisterVariableInteger("MonthlyMaxTime","This Month - Maximum - Timestamp","~UnixTimestamp");

				$this->RegisterVariableFloat("YearlyAvg","This Year - Average","");
				$this->RegisterVariableFloat("YearlyMin","This Year - Minimum","");
				$this->RegisterVariableInteger("YearlyMinTime","This Year - Minimum - Timestamp","~UnixTimestamp");
				$this->RegisterVariableFloat("YearlyMax","This Year - Maximum","");
				$this->RegisterVariableInteger("YearlyMaxTime","This Year - Maximum - Timestamp","~UnixTimestamp");
			}
		}
		
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}


	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array(
								"type" => "ExpansionPanel", 
								"caption" => "General Settings",
								"expanded" => true,
								"items" => Array(
										Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval")
									)
								);
								
		$form['elements'][] = Array(
								"type" => "ExpansionPanel", 
								"caption" => "Global Settings",
								"expanded" => true,
								"items" => Array(
										Array("type" => "SelectModule", "name" => "ArchiveId", "caption" => "Select Archive instance", "moduleID" => "{43192F0B-135B-4CE7-A0A7-1475603F3060}"),
										Array("type" => "SelectVariable", "name" => "SourceVariable", "caption" => "Source Variable")
									)
								);
										
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'ARCHIVEOV_RefreshInformation($id);');
		
		// Return the completed form
		return json_encode($form);

	}
	
	public function RefreshInformation() {

		// Do nothing if status is off
		if (! GetValue($this->GetIDForIdent("Status"))) {
			
			return;
		}
		
		$this->LogMessage("Refresh in Progress", KL_DEBUG);
		
		if (AC_GetAggregationType($this->ReadPropertyInteger('ArchiveId'), $this->ReadPropertyInteger('SourceVariable')) == 1) {

			$this->RefreshInformationCounter();
		}
		else {

			$this->RefreshInformationStandard();
		}
	}

	protected function RefreshInformationCounter() {

		SetValue($this->GetIDForIdent('CountHourly'), $this->getAggregatedData("last hour", 0, "Avg"));
		SetValue($this->GetIDForIdent('CountDaily'), $this->getAggregatedData("today 00:00", 1, "Avg"));
		SetValue($this->GetIDForIdent('CountWeekly'), $this->getAggregatedData("Monday this week 00:00", 2, "Avg"));
		SetValue($this->GetIDForIdent('CountMonthly'), $this->getAggregatedData("first day of this month 00:00", 3, "Avg"));
		SetValue($this->GetIDForIdent('CountYearly'), $this->getAggregatedData("first day of January", 4, "Avg"));
	}

	protected function RefreshInformationStandard() {

		$dailyData = $this->getAggregatedDataSet("today 00:00", 1);
		$this->updateDataBulk('Daily', $dailyData);

		$weeklyData = $this->getAggregatedDataSet("Monday this week 00:00", 2);
		$this->updateDataBulk('Weekly', $weeklyData);

		$monthlyData = $this->getAggregatedDataSet("first day of this month 00:00", 3);
		$this->updateDataBulk('Monthly', $monthlyData);

		$yearlyData = $this->getAggregatedDataSet("first day of January", 4);
		$this->updateDataBulk('Yearly', $yearlyData);
	}

	protected function updateDataBulk($baseIdent, $dataSet) {

		if ($dataSet) {
			
			SetValue($this->GetIDForIdent($baseIdent . 'Avg'), $dataSet['Avg']);
			SetValue($this->GetIDForIdent($baseIdent . 'Min'), $dataSet['Min']);
			SetValue($this->GetIDForIdent($baseIdent . 'MinTime'), $dataSet['MinTime']);
			SetValue($this->GetIDForIdent($baseIdent . 'Max'), $dataSet['Max']);
			SetValue($this->GetIDForIdent($baseIdent . 'MaxTime'), $dataSet['MaxTime']);
		}
		else {
			SetValue($this->GetIDForIdent($baseIdent . 'Avg'), null);
			SetValue($this->GetIDForIdent($baseIdent . 'Min'), null);
			SetValue($this->GetIDForIdent($baseIdent . 'MinTime'), null);
			SetValue($this->GetIDForIdent($baseIdent . 'Max'), null);
			SetValue($this->GetIDForIdent($baseIdent . 'MaxTime'), null);
		}
	}

	protected function getAggregatedData($prompt, $aggregationLevel, $function) {

		$tsEnd = time();
		$tsStart = strtotime($prompt);
		$this->LogMessage("Prompt: $prompt / Timestamp: $tsStart", KL_DEBUG);

		$data = AC_GetAggregatedValues($this->ReadPropertyInteger('ArchiveId'), $this->ReadPropertyInteger('SourceVariable'), $aggregationLevel, $tsStart, $tsEnd, 0);

		if (count($data) > 0) {

			return $data[0][$function];
		}
		else {

			return 0;
		}
	}

	protected function getAggregatedDataSet($prompt, $aggregationLevel) {

		$tsEnd = time();
		$tsStart = strtotime($prompt);
		$this->LogMessage("Prompt: $prompt / Timestamp: $tsStart", KL_DEBUG);

		$data = AC_GetAggregatedValues($this->ReadPropertyInteger('ArchiveId'), $this->ReadPropertyInteger('SourceVariable'), $aggregationLevel, $tsStart, $tsEnd, 0);

		if (count($data) > 0) {

			return $data[0];
		}
		else {

			return false;
		}
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				// Initialize an immediate refresh if turned on
				if ($Value) {
					
					$this->RefreshInformation();
				}
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(";",$Data), KL_DEBUG);
	}

}
