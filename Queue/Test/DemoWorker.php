<?php namespace Accent\Queue\Test;


use Accent\Queue\Worker;


class DemoWorker extends Worker {

    protected $LogPath;

    public function __construct(array $Options) {
        parent::__construct($Options);
        $this->DebugLog('', true);
    }

    public function DebugLog($Message, $Init = false) {
        if ($Init) {
            $this->LogPath = __DIR__.'/tmp/log.txt';
            @mkdir(dirname($this->LogPath));
            file_put_contents($this->LogPath, date('r')."\n--------------------------------");
            return;
        }
        file_put_contents($this->LogPath, "\n".$Message, FILE_APPEND);
    }

    protected function TooManyFails($JobRecord) {
        parent::TooManyFails($JobRecord);
        $this->DebugLog('TOOMANYFAILS #'.$JobRecord['Id']);
    }

    protected function UnhandledJob($JobRecord) {
        parent::UnhandledJob($JobRecord);
        $this->DebugLog('UNHANDLED #'.$JobRecord['Id']);
    }

    protected function ExecuteJob() {

        $Title = '#'.$this->Job->GetRecord('Id').':'.$this->Job->GetRecord('FailCount').': "'.$this->Job->GetData().'"';
        switch (parent::ExecuteJob()) {
            case false:
                $this->DebugLog("Executed $Title: release.");
                return false;
            case true:
                $this->DebugLog("Executed $Title: ok.");
                return true;
            case null:
                $this->DebugLog("Executed $Title: null.");
                return null;
        }
    }

    protected function TerminateLoop() {

        if (parent::TerminateLoop()) {
            $this->DebugLog('TERMINATE');
            return true;
        }
        return false;
    }

}
?>