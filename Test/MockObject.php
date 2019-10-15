<?php namespace Accent\Test;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


class MockObject {


    protected $Mock_Data= array();

    protected $Mock_ErrorCallback;


    public function __construct($ErrorCallback) {

        $this->Mock_ErrorCallback= $ErrorCallback;
    }


    public function __destruct() {

        // loop on Methods and call ErrorCallback if $Times is violated
        foreach($this->Mock_Data as $Id=>$Data) {
            // don't validate times if developer alreaady know that something is wrong
            if ($Data['ErrorDispatched']) continue;
            $Name= $this->Mock_NiceName($Id);
            if ($Data['Times'] === null) continue;

            if (is_array($Data['Times'])) {
                if ($Data['Times'][0] > $Data['Count'] || $Data['Times'][1] < $Data['Count']) {
                    $this->Mock_Error(ucfirst($Name).' is called '.$Data['Count'].' times, it is not in allowed range ('.implode('..',$Data['Times']).').');
                }
            } else {
                if ($Data['Times'] <> $Data['Count']) {
                    $this->Mock_Error(ucfirst($Name).' is called '.$Data['Count'].' times, but definition require '.$Data['Times'].' times.');
                }
            }
        }
    }


    public function Mock_SetProperty($Name, $Value, $Times=null) {

        $this->Mock_SetData('P-'.$Name, array($Value), $Times);
    }


    public function Mock_SetPropertyValues($Name) {

        $this->Mock_Error('Setting multiple values for property "'.$Name.'" is not supported. It is bad idea to expect such behaivor from properties.');
    }


    public function Mock_SetMethod($Name, $Value, $Times=null) {

        $this->Mock_SetData('M-'.$Name, array($Value), $Times);
    }


    public function Mock_SetMethodValues($Name, array $Values, $Times=null) {

        $this->Mock_SetData('M-'.$Name, $Values, $Times);
    }

    protected function Mock_SetData($Id, $Values, $Times) {

        $this->Mock_Data[$Id]= array(
            'Values'=> $Values,
            'Times'=> $Times,
            'Count'=> 0,
            'ErrorDispatched'=> false,
        );
    }

    public function __get($Name) {

        $Id= 'P-'.$Name;
        if (isset($this->Mock_Data[$Id])) {
            $this->Mock_ValidateTimes($Id);
            return $this->Mock_Data[$Id]['Values'][0];
        }
        $this->Mock_Error('Property "'.$Name.'" not exist.');
        return null;
    }


    public function __set($Name, $Value) {
        $Id= 'P-'.$Name;
        if (isset($this->Mock_Data[$Id])) {
            //$this->Mock_ValidateTimes($Id);    // do not count writtings
            $this->Mock_Data[$Id]['Values']= array($Value);
        }
        $this->Mock_Error('Property "'.$Name.'" not exist.');
    }


    public function __call($Name, $Arguments) {

        $Id= 'M-'.$Name;
        if (isset($this->Mock_Data[$Id])) {
            $this->Mock_ValidateTimes($Id);
            $ValueIndex= min($this->Mock_Data[$Id]['Count'], count($this->Mock_Data[$Id]['Values'])) - 1;
            $Value= $this->Mock_Data[$Id]['Values'][$ValueIndex];
            //echo'<pre>';var_dump($Value);die();
            //return $Value($Arguments);


            return $Value instanceof \Closure
                ? call_user_func_array($Value, $Arguments)
                : $Value;
        }
        $this->Mock_Error('Method "'.$Name.'" not exist.');
        return null;
    }


    protected function Mock_ValidateTimes($Id) {

        $this->Mock_Data[$Id]['Count']++;
        if ($this->Mock_Data[$Id]['Times'] === null) {
            return;
        }
        if (is_array($this->Mock_Data[$Id]['Times'])
            && $this->Mock_Data[$Id]['Times'][1] <= $this->Mock_Data[$Id]['Count']) {
                return;
        }
        if (is_integer($this->Mock_Data[$Id]['Times'])
            && $this->Mock_Data[$Id]['Times'] <= $this->Mock_Data[$Id]['Count']) {
                return;
        }
        $this->Mock_Error('Exceded number of allowed access times to '.$this->Mock_NiceName($Id).'.');
        $this->Mock_Data[$Id]['ErrorDispatched']= true;
    }


    protected function Mock_Error($Message) {

        call_user_func($this->Mock_ErrorCallback, $Message);
    }


    protected function Mock_NiceName($Id) {

        $Name= str_replace(array('P-','M-'), array('property "','method "'), $Id);
        return $Name.'"';
    }

}

?>