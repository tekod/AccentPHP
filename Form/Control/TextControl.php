<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * 'input-text' input form element.
 *
 * Like: <input type="text" name="" value="" />
 */

use \Accent\Form\Control\BaseControl;


class TextControl extends BaseControl {

    protected static $DefaultOptions= array(
    );

    protected $CanBeReadOnly= true;
    protected $CanBeDisabled= true;

    /**
	 * Create a new form element
     */
    /*
	public function __construct($Options=array()) {

        parent::__construct($Options);

	}*/




}
?>