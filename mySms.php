<?php

include "Sms/Sms.php";

$sms = new Jsms\Sms;
$sms->delayInSeconds = 6;
print "Set device: " . $sms->setDevice('/dev/ttyUSB2') . "\n";
print "Open device: " . $sms->openDevice() . "\n";
print "Set baud rate: " . $sms->setBaudRate(115200) . "\n";
print "Sent message: " . $sms->sendSMS('+639332162333', 'I miss you.') . "\n";
// print "Sent message: " . $sms->sendSMS('+639472609815', 'I miss you.') . "\n";
// print "Sent message: " . $sms->sendSMS('+639773126821', 'Fuck up!!!') . "\n";
$sms->sendCmd("ATi\r");
print $sms->getDeviceResponse() . "\n";
print "Device closed: " . $sms->closeDevice() . "\n";