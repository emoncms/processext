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
            ),
            array(
                "name"=>_("kWh to Power (smoothed)"),
                "short"=>"kwhpwr_smooth",
                "argtype"=>ProcessArg::FEEDID,
                "function"=>"kwh_to_power_smoothed",
                "datafields"=>1,
                "unit"=>"W",
                "group"=>_("Power & Energy"),
                "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES),
                "requireredis"=>true,
                "description"=>_("<p>Convert accumulating kWh to instantaneous power, averaging over the last 10 input values. This is useful when the kWh measurement is derived from pulse counting because a short (e.g. 5s) sampling interval leads to quantisation which causes the power level to appear to oscillate between coarse-grained levels (720W for 1 Wh pulses counted for 5s). This filter delays power output for 10 samples in order to give more fine grained output. Quantisation becomes less apparent but changes of power level are delayed slightly and spread over the expanded window.</p>")
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

    //---------------------------------------------------------------------------------------
    // Delayed / smoothed kWh to power input processor by Robert Norton
    // See: https://github.com/emoncms/emoncms/pull/1686
    //---------------------------------------------------------------------------------------
    public function kwh_to_power_smoothed($feedid,$time,$value)
    {
        global $redis;
        if (!$redis) return $value; // return if redis is not available

        $power = 0;
        $queue_len = 10;
        // fetch the oldest samples in the queue
        $last_time = $redis->lIndex("process:kwhtopower_smoothed:$feedid:times",-1);
        $last_value = $redis->lIndex("process:kwhtopower_smoothed:$feedid:values",-1);
        // Push the new sample and trim to $queue_len entries (actually queue_len+1 but shrug)
        $redis->lPush("process:kwhtopower_smoothed:$feedid:times", $time);
        $redis->lTrim("process:kwhtopower_smoothed:$feedid:times", 0, $queue_len);
        $redis->lPush("process:kwhtopower_smoothed:$feedid:values", $value);
        $redis->lTrim("process:kwhtopower_smoothed:$feedid:values", 0, $queue_len);
        if (($last_time !== false) && ($last_value !== false)) { // will fail on first call
            // compute the power between now and queue_len samples ago
            $kwhinc = $value - $last_value;
            $joules = $kwhinc * 3600000.0;
            $timeelapsed = ($time - $last_time);
            if ($timeelapsed>0) {     //This only avoids a crash, it's not ideal to return "power = 0" to the next process.
                $power = $joules / $timeelapsed;
                $this->feed->insert_data($feedid, $time, $time, $power);
            } else {
                $this->log->error("Time elapsed was not greater than zero: $timeelapsed.");
            }
        }

        return $power;
    }
}
