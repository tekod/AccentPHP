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


class TableRenderer extends BaseRenderer {


    protected $Templates= array(

        'Row'=> '
            <tr id="{{Id}}_row"{{RowClass}}>
                <th>{{Label}}</th>
                <td>{{PreHTML}}{{Element}}{{Markers}}{{PostHTML}}{{Errors}}{{Description}}</td>
            </tr>',

        'RowForInline'=> null,

        'RowSeparator'=> '',

        'RowsPack'=> '<table>{{RowsPack}}</table>',

        'RowWithButtons'=> '<div class="fButtonsRow">{{Buttons}}</div>',

        'Label'=> '<label for="{{Id}}">{{Label}}:</label>',

        'Element'=> '{{Element}}',

        'Description'=> '<div class="fDesc">{{Description}}</div>',

        'Markers'=> '<span class="fMarkers">{{Markers}}</span>',

        'ErrorsPack'=> '<div class="fError"><ul>{{ErrorsPack}}</ul></div>',

        'ErrorLine'=> '<li>{{ErrorLine}}</li>',
    );




}



?>