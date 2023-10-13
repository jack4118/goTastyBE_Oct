<?php
$dialingArea = '60'; // The country code
$phone = '0167169981'; // The phone number

// Remove leading zeros
$phone = ltrim($phone, '0');

// Check if country code is present
if (substr($phone, 0, strlen($dialingArea)) === $dialingArea) {
    // Remove country code
    $phone = substr($phone, strlen($dialingArea));
}

echo $dialingArea . $phone;
?>