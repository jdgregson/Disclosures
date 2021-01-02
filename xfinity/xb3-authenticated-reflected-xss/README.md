# Xfinity Gateway XB3 - Authenticated Reflected XSS
The administrative interface of Xfinity Gateway model XB3 (and possibly others) will execute arbitrary JavaScript if sent a specially crafted POST request by an a logged in administrator.

## Details
The `/wizard_step2.php` page takes a POST parameter `userPassword` and unsafely echoes it to JavaScript on the page:

    var newPassword = '<?php if("admin" == $_SESSION["loginuser"]) echo $_POST["userPassword"]; ?>';

Arbitrary JavaScript can be executed if the following payload is sent in the `userPassword` POST parameter:

    ';}alert(1);function foo() {var foo = '

## Mitigating Factors
Users can be logged into the XB3 gateway by any website if the device is using default credentials. However, the POST request exploiting this vulnerability requires a valid CSRF protection token, so this vulnerability is not exploitable by malicious websites without an accompanying CSRF protection token leak.

## Impact
5.3 - Medium (CVSS:3.1/AV:N/AC:H/PR:H/UI:R/S:U/C:L/I:H/A:L)
