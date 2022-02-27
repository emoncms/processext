<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
 ---------------------------------------------------------------------
 Emoncms - open source energy visualisation
 Part of the OpenEnergyMonitor project: http://openenergymonitor.org
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Extended Processlist Module
class processext_ProcessList
{
    private $log;
    private $schedule;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent)
    {
        $this->log = new EmonLogger(__FILE__);
    }

    public function process_list() {
        
        $list = array(
           array(
              "name"=>_("pow10 input"),
              "short"=>"s inp",
              "argtype"=>ProcessArg::INPUTID,
              "function"=>"pow10_input",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Input"),
              "description"=>_("<p>Scales the current value by ten to the power of the other input.</p>")
           ),
           array(
              "name"=>_("value and scale"),
              "short"=>"v+s",
              "argtype"=>ProcessArg::NONE,
              "function"=>"value_and_scale",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Calibration"),
              "description"=>_("<p>Unpack combined value and scale factor, applying the scale factor.</p>")
           )
        );
        return $list;
    }
    // / Below are functions of this module processlist, same name must exist on process_list()
    
    //---------------------------------------------------------------------------------------
    // Combines a value and scale input, multiplies the value by 10^scale
    //
    // These split value and scale inputs are output from the Modbus interface of solar edge
    // inverters.
    //---------------------------------------------------------------------------------------
    public function value_and_scale($arg, $time, $value) 
    {
        $scale = $value & 0xFFFF; // Right 16 bits are the scale
        if($scale >= 0x8000) { // Decode twos complement if negative
          $scale = -(($scale ^ 0xFFFF)+1);		
        }

        $val = $value >> 16; // Left 16 bits are the value

        // Apply scale factor to the value
        return $val * pow(10, $scale);
    }
    
    //---------------------------------------------------------------------------------------
    // Scales current value by 10 to the power of another input.
    //---------------------------------------------------------------------------------------
    public function pow10_input($id, $time, $value)
    {
        $input_value = $this->input->get_last_value($id);
        return $value * pow(10, $input_value);
    }
}
