<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * 'input-file' input form element.
 *
 * Like: <input type="file" name="" />
 */

use \Accent\Form\Control\BaseControl;


class FileControl extends BaseControl {

    protected static $DefaultOptions= array(
        'SkipDiff'=> true,
    );

    protected $CanBeReadOnly= true;
    protected $CanBeDisabled= true;

    protected $MultipartEncoding= true;


    /**
     * Render HTML representation of current form element.
     *
     * @return string HTML
     */
    public function RenderElement() {

        // prepare most important attributes
        $Attr= $this->BuildAttributes(array('type'=>'file'));
        // 'value' attribute must be empty
        $Attr['value']= '';
        // render tag
        return $this->Form->RenderTag('input', $Attr, false);
    }



}
?>