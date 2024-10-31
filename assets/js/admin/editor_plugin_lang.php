<?php

$strings = 'tinyMCE.addI18n({' . _WP_Editors::$mce_locale . ':{
    resaline:{
    	insert: "' . esc_js( __( 'Insert', 'resaline' ) ) . '",
        add_calendar: "' . esc_js( __( 'Insert calendar', 'resaline' ) ) . '"
    },
    resaline_datas:{
    	nb_calendars: '. get_option('resaline_nb_calendars') ;

    	for ($i = 0; $i < get_option('resaline_nb_calendars'); $i++) {
		    $cal = unserialize(get_option('resaline_calendar_'.$i));
		    $strings .= ', calendar_'.$i.': "'.$cal->id.';'. esc_js($cal->name) .';'. esc_js($cal->file) .'"' ;
		}

$strings .= '   }
}})';
