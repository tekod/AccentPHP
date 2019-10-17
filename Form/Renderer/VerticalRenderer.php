<?php namespace Accent\Form\Renderer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Form factory class.
 *
 */

use \Accent\Form\Renderer\BaseRenderer;


class VerticalRenderer extends BaseRenderer {


    protected $Templates= array(

        'Row'=> '
                <div id="{{Id}}_row"{{RowClass}}>
                    {{Label}}{{Markers}}
                    <div class="fInnerDiv">
                        {{PreHTML}}{{Element}}{{PostHTML}}{{Errors}}{{Description}}
                    </div>
                </div>',

        'RowForInline'=> '
                <div id="{{Id}}_row"{{RowClass}}>
                    {{Errors}}{{PreHTML}}{{Element}}&nbsp;{{Label}}{{Markers}}{{PostHTML}}{{Description}}
                </div>',

        'RowSeparator'=> '<div class="fVertSeparator"></div>',

        'RowsPack'=> '{{RowsPack}}',

        'RowWithButtons'=> '<div class="fButtonsRow">{{Buttons}}</div>',

        'Label'=> '<label for="{{Id}}">{{Label}}</label>',

        'Element'=> '{{Element}}',

        'Description'=> '<p class="fDesc">{{Description}}</p>',

        'Markers'=> '<span class="fMarkers">{{Markers}}</span>',

        'ErrorsPack'=> '<div class="fError"><ul>{{ErrorsPack}}</ul></div>',

        'ErrorLine'=> '<li>{{ErrorLine}}</li>',
    );




}



?>