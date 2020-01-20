<?php namespace Accent\Queue\Test;


class DemoProcessor {


    public function HandleTestWork($Event) {

        $Job= $Event->GetJob();
        $Worker= $Event->GetWorker();
        $JobRecord= $Event->GetRecord();

        // this method must not be called for other job names
        if ($JobRecord['JobName'] !== 'TestWork') {
            $Worker->Log('Job error: HandleTestWork called to execute '.serialize($JobRecord));
            return;
        }
        // payload 'x' must be successfully executed everytime
        if ($JobRecord['JobData'] === 'x') {
            $Job->SetHandled();
            return;
        }
        // payload 'y' must fail only first time
        if ($JobRecord['JobData'] === 'y') {
            $Job->SetHandled();
            if (intval($JobRecord['FailCount']) === 0) {
                $Job->SetReleased();
            }
            return;
        }
        // payload 'z' must fail everytime
        if ($JobRecord['JobData'] === 'z') {
            $Job->SetReleased();
            return;
        }
        // payload 't' will set condition for terminating loop (existence of log2 file)
        if ($JobRecord['JobData'] === 't') {
            file_put_contents(__DIR__.'/tmp/log2.txt', '');
            $Job->SetHandled();
            return;
        }
        $Worker->Log('Job: unknown payload.');
    }

    public function HandleWildcard($Event) {

        switch ($Event->GetRecord('JobName')) {
            //case 'UnusedName' : return $this->UnusedExecutioner($Para);
            case 'SomeWeirdName': return $this->WeirdJobExecutioner($Event->GetJob());
        }
        return null;
    }

    public function OnLoop($Event) {

        if (is_file(__DIR__.'/tmp/log2.txt')) {
            return true;     // condition for terminating loop meet
        }
        return false;
    }

    protected function WeirdJobExecutioner($Job) {
        //....  some work
        $Job->SetHandled();
    }
}

?>