<?php

/* System requirements
	At least PHP 5.3.
*/

namespace Jsms;

const DEVICE_NOT_SET = 0;
const DEVICE_IS_SET = 1;
const DEVICE_IS_OPEN = 2;

class Sms {

	private $_os = "";
	private $_deviceState = DEVICE_NOT_SET;
	private $_device = null;
	private $_handle = null;

	public $delayInSeconds = 7;

	public function __construct() {
		setlocale(LC_ALL, "en_US");

		$systemName = php_uname();

		if (substr($systemName, 0, 5) == "Linux")
			$this->_os = "linux";
		else
			exit("This was designed for Linux just yet.\n");
	}

	private function _executeCommand($cmd, &$output = null) {
		$descriptorspec = array(
			0 => array('pipe', 'r'), // 0 is STDIN for process
			1 => array('pipe', 'w'), // 1 is STDOUT for process
			2 => array('pipe', 'r') // 2 is STDERR for process
		);

		$proc = proc_open($cmd, $descriptorspec, $pipes);
		$ret = stream_get_contents($pipes[1]);
		if (isset($pipes[2]))
        	$err = stream_get_contents($pipes[2]);

		fclose($pipes[0]);
		fclose($pipes[1]);
		if (isset($pipes[2]))
			fclose($pipes[2]);

		if (func_num_args() == 2) $output = array($ret, $err);

		if (proc_close($proc) != -1)
			return true;	// success
		else
			return false;
	}

	private function _checkDeviceState() {
		if ($this->_deviceState !== DEVICE_IS_OPEN) {
			trigger_error("The device should be opened.\n", E_USER_WARNING);
			return false;
		}

		return true;
	}

	public function setDevice($device) {
		if ($this->_deviceState == DEVICE_NOT_SET) {
			if ($this->_os == "linux") {

				if ($this->_executeCommand("stty -F $device") == true) {
					$this->_device = $device;
					$this->_deviceState = DEVICE_IS_SET;
					return true;
				}
			} else {
				exit("This was designed for Linux just yet.\n");
			}
		} else {
			trigger_error("Cannot locate device.\n", E_USER_WARNING);
			return false;
		}
	}

	public function openDevice($mode = "r+") {
		if ($this->_deviceState === DEVICE_IS_OPEN) {
			trigger_error("The device is already opened.\n", E_USER_NOTICE);
			return true;
        }

		if ($this->_deviceState === DEVICE_NOT_SET) {
			trigger_error("The device must be set before opening.", E_USER_WARNING);
			return false;
		}

		$this->_handle = @fopen($this->_device, $mode);

		if ($this->_handle !== false) {
			stream_set_timeout($this->_handle, 20);
			stream_set_blocking($this->_handle, false);
			$this->_deviceState = DEVICE_IS_OPEN;
			return true;
        }

        $this->_handle = null;
        // trigger_error("The device cannot be opened.", E_USER_WARNING);
        throw new Exception("The device cannot be opened.", 1);
        

        return false;
	}

	public function setBaudRate($baudRate) {
		if ($this->_deviceState === DEVICE_NOT_SET) {
            trigger_error("Cannot set the device baud rate. Device may be not set.", E_USER_WARNING);
            return false;
        }

        $validBauds = array (
            110    => 11,
            150    => 15,
            300    => 30,
            600    => 60,
            1200   => 12,
            2400   => 24,
            4800   => 48,
            9600   => 96,
            19200  => 19,
            38400  => 38400,
            57600  => 57600,
            115200 => 115200
        );

        if (isset($validBauds[$baudRate])) {
        	if ($this->_os === "linux")
        		$ret = $this->_executeCommand("stty -F $this->_device $baudRate", $output);

        	if ($ret === false) {
                trigger_error("Unable to set baud rate: " . $out[1], E_USER_WARNING);
                return false;
            }

            return true;
        }
	}

	private function matchBiddenResponses($deviceResponse) {
		$forbiddenResponses = array ('BOOT','RSSI','MODE','SIMST','SRVST');

		foreach($forbiddenResponses as $forbiddenResponse) {
			preg_match("/$forbiddenResponse/", $deviceResponse, $match);
			if ($match)
				return false;
			else
				return true;
		}
	}

	public function getDeviceResponse() {
		sleep($this->delayInSeconds);	// I noticed this device has to wait for a couple of seconds.

		$response = "";  $i = 0;

		do {
			$response .= fread($this->_handle, 128);
		} while (($i += 128) == strlen($response) && $this->matchBiddenResponses($response));

		return trim($response);	// we trim here because there are other characters in the response other than Alphabet like \n.
	}

	public function sendCmd($cmd) {
		$cmd .= "\r";

		if (fwrite($this->_handle, $cmd))
			return true;
		else
			return false;
	}

	public function sendSMS($number, $message) {
		if (!$this->_checkDeviceState())
			return false;

		if ($this->sendCmd("AT+CMGF=1"))
			if (preg_match("/OK/", $this->getDeviceResponse()))
				if ($this->sendCmd("AT+CMGS=\"$number\""))
					if (preg_match("/>/", $this->getDeviceResponse()))
						if ($this->sendCmd("$message".chr(26)))
							if (preg_match("/\+CMGS:\s+\d+[\r?\n]*OK/", $this->getDeviceResponse()))
								return true;

		return false;
	}

	public function closeDevice() {
		if ($this->_deviceState !== DEVICE_IS_OPEN)
			return true;

		if (fclose($this->_handle)) {
			$this->_deviceState = DEVICE_IS_SET;
			$this->_handle = null;

            return true;
		}

		trigger_error("Cannot close the device.\n", E_USER_ERROR);
        return false;
	}
}