<?php
/**
 * Plugin Name: SWStrava Clubs
 * Plugin URI: http://swwidgets.co.nf/?page_id=23
 * Description: Display the recent activities of your Strava club in your Wordpress site.
 * Version: 1.01
 * Author: Stuart Wilson
 * Author URI: http://www.swwidgets.co.nf
 * License: A "Slug" license name e.g. GPL2
 */

//**********************************************************************************************
//*
//*  Main Widget function
//*
//**********************************************************************************************

function register_swstrava_clubs_widget() {
    register_widget('SWStrava_Clubs_Widget');
}


// Initialise settings when plugin is activated
function swstrava_activate() {
    
    add_option( 'swstrava_km_miles','Kilometres' );
    add_option( 'swstrava_anonymise', 'No' );
    add_option( 'swstrava_display_icon', 'Yes' );
    add_option( 'swstrava_strava_link', 'No' );
    add_option( 'swstrava_title', 'Our club on Strava' );
    add_option( 'swstrava_num_activities', 30 );
    add_option( 'swstrava_timezone', 'Europe/London' );
   
}

function swstrava_add_stylesheet() {
    // Respects SSL, Style.css is relative to the current file
    wp_register_style( 'prefix-style', plugins_url('sw_strava.css', __FILE__) );
    wp_enqueue_style( 'prefix-style' );
}


class SWStrava_Clubs_Widget extends WP_Widget {

      
   function SWStrava_Clubs_Widget() {
        $sws_widget_options = array('classname' => 'swstrava_clubs_widget', 'description' => __( 'Display your club\'s recent Strava activities', 'swstrava') );
        // widget code goes here
        parent::WP_Widget( false, $name = 'SWStrava Clubs', $sws_widget_options );
   }

   public function widget ($args) {


        $sws_strava_authid = get_option("swstrava_auth");
        $sws_strava_clubid = get_option("swstrava_club");
    
        extract($args);
        echo$before_widget;
        echo$before_title;
        $sws_title = sanitize_text_field( get_option( 'swstrava_title','Our Club On Strava' ) );
        echo "\n" . esc_attr( $sws_title  );   
        echo$after_title;
   
        echo "\n<div id='sws_div'>";
        if (empty ($sws_strava_authid)) {

           echo "<br>No Strava authorisation code entered<br>";    	

        } elseif (empty ($sws_strava_clubid) or $sws_strava_clubid == -1) {

          echo "<br>No Strava club found<br>"; 

        } else {
          
          $sws_json = sws_callstrava("https://www.strava.com/api/v3/clubs/" .  $sws_strava_clubid );
          
          echo "\n<table id='sws_activities'>";

     	    // Display club's logo if option selected
          if (get_option("swstrava_display_icon") == 'Yes') {
             
             echo "\n<tr><td colspan='6' class='sws_image'>";
             echo "<a href=\"http://app.strava.com/clubs/" . $sws_json["id"] . "\"><img src=\"". $sws_json["profile_medium"] . "\" title=\"" . $sws_json["name"] . "\"></a>"; 
             echo "</td></tr>";
           
         }

         // Display club's latest activities
         $sws_output = sws_callstrava("https://www.strava.com/api/v3/clubs/" . $sws_strava_clubid . "/activities");
         sws_getActivities($sws_output);
      }
      
      echo "\n</div>";
      echo$after_widget;

   } // of widget

   public function form( $instance ) {

   }



} // of widget class


//**********************************************************************************************
//*
//*  Curl functions
//*
//**********************************************************************************************

function sws_callstrava ($sws_url) {
  if (_is_curl_installed()) {
      
     $sws_access_key = get_option('swstrava_auth');
     
     $sws_params = array('per_page' => get_option( 'swstrava_num_activities','30' ));
     $sws_url .= '?' . http_build_query($sws_params);
     
     $sws_curl = curl_init();
     curl_setopt($sws_curl, CURLOPT_URL, $sws_url);
     curl_setopt($sws_curl, CURLOPT_HTTPHEADER, array( 'Authorization: Bearer ' . $sws_access_key ) );
     //curl_setopt($sws_curl, CURLOPT_VERBOSE, true);
     curl_setopt($sws_curl, CURLOPT_RETURNTRANSFER, true);
     curl_setopt($sws_curl, CURLOPT_SSL_VERIFYPEER, false); 

     $sws_output = curl_exec($sws_curl)   ;
  
     if($sws_output === false)
       {
        echo 'Curl error: ' . curl_error($sws_curl) . " * " . $sws_url . " * " . $sws_access_key . " *";
       }
       else
       {
        $sws_json = json_decode( $sws_output, true );
       }     
     curl_close ($sws_curl);

    } else {
     echo "cURL is NOT installed on this server";
   }
   
   return $sws_json;

}

// Check if curl is installed
function _is_curl_installed() {
    if  (in_array  ('curl', get_loaded_extensions())) {
        return true;
    }
    else {
        return false;
    }
}


//**********************************************************************************************
//*
//*  Parse Strava API functions
//*
//**********************************************************************************************


function sws_getactivities($json) {

    $sws_units = get_option( 'swstrava_km_miles','Kilometres' );
    $sws_abbreviate = get_option( 'swstrava_anonymise');
    $sws_link = get_option( 'swstrava_strava_link');
    $sws_activity_url = 'http://app.strava.com/activities/';


    if ($sws_units == 'Kilometres') {
         $sws_units_disp = "Km";
    } else {
         $sws_units_disp = "Mi";
    }

    
    echo "\n<tr><th class='sws_th'>Who</th><th class='sws_th'>What</th><th class='sws_th'>When</th><th class='sws_th'>Time</th><th class='sws_th'>Dist. " . $sws_units_disp . "</th><th class='sws_th'>Kudos</th></tr>";
    foreach ($json as $key => $values) {
 
          echo "\n<tr style='border: none;'>";
                 
          switch($sws_abbreviate) {
               case 'Yes - Surname Initialised':
                    $sws_display_name = sws_abbreviate_name($values["athlete"]["firstname"], $values["athlete"]["lastname"]);
                    break;
               case 'Yes - First/Surname abbreviated':
                    $sws_display_name = sws_suboify_name($values["athlete"]["firstname"], $values["athlete"]["lastname"]);
                    break;
               case 'No':
                    $sws_display_name = $values["athlete"]["firstname"] . " " . $values["athlete"]["lastname"];
                    break;
           }

           if ($sws_units == 'Kilometres') {
          		$sws_display_distance = round($values["distance"]/1000,2);
           } else {
        		$sws_display_distance = metres_to_miles($values["distance"]);       
           }

           if ($sws_link == 'Yes') {
              echo "<td>" . sws_add_link($sws_display_name,$sws_activity_url . $values["id"] ) . "</td>";
              echo "<td title='" . $values["name"] .  "'>" . sws_add_link($values["type"],$sws_activity_url . $values["id"]) . "</td>";
              echo "<td>" .  sws_add_link(hours_since($values['start_date_local']),$sws_activity_url . $values["id"]) . "</td>";
              echo "<td>" .  sws_add_link(seconds_to_HMS($values['moving_time']),$sws_activity_url . $values["id"]) . "</td>";
              echo "<td class='sws_td'>" .  sws_add_link($sws_display_distance,$sws_activity_url . $values["id"]) . "</td>";
              echo "<td class='sws_td'>" .  sws_add_link($values["kudos_count"],$sws_activity_url . $values["id"]) . "</td>";
           } else {
              echo "<td>" . $sws_display_name . "</td>";
              echo "<td title='" . $values["name"] .  "'>" . $values["type"] . "</td>";
              echo "<td>" .  hours_since($values['start_date_local']) . "</td>";
              echo "<td>" .  seconds_to_HMS($values['moving_time']) . "</td>";
              echo "<td class='sws_td'>" .  $sws_display_distance . "</td>";
              echo "<td class='sws_td'>" .  $values["kudos_count"] . "</td>";         
           }          
           
           echo "</tr>";
           
     }
     echo "\n</table>";
    
 
}


//**********************************************************************************************
//*
//*  General functions
//*
//**********************************************************************************************


// imperial distance conversion
function metres_to_miles($distance) {
    $miles = $distance * 0.000621371192;
    $miles = round($miles, 2);
    return $miles;
}

    
// metric distance conversion
function meters_to_kilometers($distance) {

    $km = $distance * 0.001;
    $km = round($km, 1);
    return $km;
}

// convert seconds into hours, minutes, seconds
function seconds_to_HMS ($seconds) {

   return gmdate("H:i:s", $seconds);

}

// Calculate the number of days and hours between the activity and current date/time
function hours_since($sws_strava_datetime) {
      
      $sws_timezone = get_option( 'swstrava_timezone');
      date_default_timezone_set($sws_timezone); 
	
      // Create two new DateTime-objects...
	$sws_current_datetime = new DateTime($sws_timezone);
      $sws_act_datetime = new DateTime($sws_strava_datetime);

	// The diff-methods returns a new DateInterval-object...
	$sws_diff = $sws_act_datetime->diff($sws_current_datetime);
	
      if ($sws_diff->days == 0) {
         $sws_ret = $sws_diff->format('%hh%im ago');
      } elseif ($sws_diff->days == 1) {
            $sws_ret = $sws_diff->format('%ad %hh ago');
         } else {
            $sws_ret = $sws_diff->format('%ad %hh ago');
         }
      return $sws_ret;
}


// Abbreviate name e.g. John Smith displayed as John S.
function sws_abbreviate_name($firstname, $secondname) {
   return ucfirst($firstname) . " " . ucfirst(substr($secondname,0,1));
}

// SuBoify name e.g. John Smith displayed as JoSm.
function sws_suboify_name($firstname, $secondname) {
   return ucfirst(substr($firstname,0,2)) .  ucfirst(substr($secondname,0,2));
}

function sws_add_link($dispstr, $url) {

   return "<a href='$url' class='sws_link' target='_blank'>" . $dispstr . "</a>";

}


function sws_create_timezone_list () {

   $timezones = DateTimeZone::listAbbreviations(DateTimeZone::ALL);

   $allzones = array();
   foreach( $timezones as $key => $zones )
   {
      foreach( $zones as $id => $zone )
            $allzones[$zone['timezone_id']] = $zone['timezone_id'];
   }
      
   $allzones = array_unique( $allzones );
   asort( $allzones );

   return $allzones;

}

//**********************************************************************************************
//*
//*  Admin page  http://kovshenin.com/2012/the-wordpress-settings-api/
//*
//**********************************************************************************************
function swstrava_admin_menu() {
    	add_options_page('SW Strava Club Options', 'SWStrava Clubs', 'manage_options', 'swstrava_slug', 'swstrava_display_settings');
}


function swstrava_admin_init() {

         if (!current_user_can('manage_options'))
         {
           wp_die( __('You do not have sufficient permissions to access this page.') );
         }
  
         register_setting( 'swstrava-settings-group', 'swstrava_auth' );
         register_setting( 'swstrava-settings-group', 'swstrava_club' );
         register_setting( 'swstrava-settings-group', 'swstrava_km_miles' );
         register_setting( 'swstrava-settings-group', 'swstrava_anonymise' );
         register_setting( 'swstrava-settings-group', 'swstrava_display_icon' );
         register_setting( 'swstrava-settings-group', 'swstrava_strava_link' );
         register_setting( 'swstrava-settings-group', 'swstrava_title' , swstrava_sanitize_textinput );
         register_setting( 'swstrava-settings-group', 'swstrava_num_activities' );
         register_setting( 'swstrava-settings-group', 'swstrava_timezone' );
      

         // Club settings setion
         add_settings_section( 's20', __('Club settings'), 's20_callback', 'swstrava_slug' );

         // Get clubs
         $clubs_json = sws_callstrava("https://www.strava.com/api/v3/athlete/clubs");
   
         // Get club and and count members
   
         foreach ($clubs_json as $key => $values) {
             if ($values['message'] == '') {  
                  $allclubs[$values["id"]] = $values["name"];
             } else {
                  $allclubs = array(-1=>'No clubs found on your Strava account');
             }
         }
            

         add_settings_field( 'f20', __('Select club to display :'), 'swstrava_dropdown_input', 'swstrava_slug', 's20', 
                             array('name' => 'swstrava_club','options'=>$allclubs )
                           );

         add_settings_field( 'f21', __('Display club\'s icon :'), 'swstrava_radio_input', 'swstrava_slug', 's20', 
                             array('name' => 'swstrava_display_icon','options'=>array('Yes', 'No'))
                           );

          // Activity display  settings setion
          add_settings_section( 's30', __('Activity display settings'), '', 'swstrava_slug' );

          add_settings_field( 'f31', __('Abbreviate names :'), 'swstrava_radio_input', 'swstrava_slug', 's30', 
                             array('name' => 'swstrava_anonymise','options'=>array('Yes - Surname Initialised','Yes - First/Surname abbreviated', 'No')  )
                           );
          add_settings_field( 'f32', __('Display miles or kilometres :'), 'swstrava_radio_input', 'swstrava_slug', 's30', 
                             array('name' => 'swstrava_km_miles','options'=>array('Miles','Kilometres')  )
                           );
          add_settings_field( 'f33', __('Link activity to Strava :'), 'swstrava_radio_input', 'swstrava_slug', 's30', 
                             array('name' => 'swstrava_strava_link','options'=>array('Yes','No')  )
                           );


          for($i = 5; $i <= 30; $i += 5) {
                  $selectable_vals[$i] = $i;
          }
          add_settings_field( 'f34', __('Number of activities displayed :'), 'swstrava_dropdown_input', 'swstrava_slug', 's30', 
                              array('name' => 'swstrava_num_activities','options'=>$selectable_vals  )
                           );


          // General settings setion
          add_settings_section( 's40', __('General settings'), '', 'swstrava_slug' );
          add_settings_field( 'f40', __('Plugin title :'), 'swstrava_text_input', 'swstrava_slug', 's40', 
                              array('name' => 'swstrava_title','value'=>esc_attr( get_option( 'swstrava_title','Our Club On Strava' ) ), size=>'30'  )
                             );

          add_settings_field( 'f41', __('Select your time zone :'), 'swstrava_dropdown_input', 'swstrava_slug', 's40', 
                             array('name' => 'swstrava_timezone','options'=>sws_create_timezone_list()  )
                             );

     

}

function s10_callback() {
    echo _e('Enter you Strava access token here.');
}

function s20_callback() {
    echo _e('Select the club you want to display on the widget');
}

function swstrava_display_settings() {
?>
    <div class="wrap">
        <div id="icon-options-general" class="icon32">
           <br> 
        </div>
        <h2>SWStrava Club Options</h2>
        <form action="options.php" method="POST"> 
          <?php
              settings_fields('swstrava-settings-group' );
              $access_key = get_option('swstrava_auth');

              if (empty($access_key)) {
                  $hide_settings_class = "display:none;";
              } else {
                  $hide_settings_class = "display:block;";
              }    
              swstrava_auth_form($access_key);
              echo "<div style='" . $hide_settings_class . "'>";
              do_settings_sections( 'swstrava_slug' );
              echo "</div>";            
              submit_button(); 
           ?> 
        </form>        
    </div>
    <?php
   
}



function swstrava_text_input( $args ) {
    $name = esc_attr( $args['name'] );
    $value = esc_attr( $args['value'] );
    $size = esc_attr( $args['size'] );
    echo "<input type='text' name='$name' value='$value' size='$size' />";
}

function swstrava_radio_input( $args ) {
    $name = esc_attr( $args['name'] );
    $options = $args['options'];
    foreach ($options as $opt) {
       echo "<input type='radio' name='$name' value='$opt'";
       if (get_option($name) == $opt) {
          echo ' checked ';
       }
       echo ">&nbsp;&nbsp;$opt&nbsp;&nbsp;";
    }
}



function swstrava_dropdown_input( $args ) {
    $name = esc_attr( $args['name'] );
    $options = $args['options'];
    echo "<select name='$name'>";
    echo "<option value='-1'>Please select.......</option>";
    foreach ($options as $id => $clubname) {
       echo "<option value='$id'";
       if (get_option($name) == $id) {
          echo ' selected ';
       }
       echo ">$clubname</option>";
    }
    echo "</select>";
}

function swstrava_auth_form ($access_key) {

    echo "<h3>Strava Authorisation</h3>";
    echo "Enter your Strava access token here.";
    echo "<table class=\"form-table\">";
    echo "<tr valign=\"top\"><th scope=\"row\">Your Strava Access Token :</th>";
    echo "<td><input type='text' name='swstrava_auth' value='" . $access_key. "' size='60'/></td>";
    echo "</tr></table>";

}

function swstrava_plugin_action_links($links, $file) {
    static $this_plugin;
 
    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }
 
    // check to make sure we are on the correct plugin
    if ($file == $this_plugin) {
        // the anchor tag and href to the URL we want. For a "Settings" link, this needs to be the url of your settings page
        $settings_link = '<a href="options-general.php?page=swstrava_slug">Settings</a>';
        // add the link to the list
        array_unshift($links, $settings_link);
    }
 
    return $links;
}

function swstrava_sanitize_textinput( $input ) {
     return sanitize_text_field($input);
}


//**********************************************************************************************
//*
//*  Initialise
//*
//**********************************************************************************************
add_action('widgets_init', 'register_swstrava_clubs_widget');
add_filter('plugin_action_links', 'swstrava_plugin_action_links', 10, 2);
add_action('admin_menu', 'swstrava_admin_menu');
add_action( 'admin_init', 'swstrava_admin_init' );
register_activation_hook( __FILE__, 'swstrava_activate' );
add_action( 'wp_enqueue_scripts', 'swstrava_add_stylesheet' );


?>