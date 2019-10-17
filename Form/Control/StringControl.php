<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * 'string' control, for simply displaying data as string, non-interactive.
 */

use \Accent\Form\Control\BaseControl;


class StringControl extends BaseControl {

    protected static $DefaultOptions= array(
    );

    protected $HasValue= false;



    /**
     * Render HTML representation of current form element.
     *
     * @return string HTML
     */
    public function RenderElement() {

        // simply return escaped value
        return $this->RenderValue();
    }

}
?>