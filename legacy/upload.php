<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>BeneluxSpoor foto upload</title>
    <link href="style.css" rel="stylesheet" type="text/css" />
</head>
<body>
    <h1>BeneluxSpoor foto upload</h1>
    <?php

    include('class.upload.php');
    $year = date('Y');
    $handle = new Verot\Upload\Upload($_FILES['bnls_image']);
    $im = imagecreatefromstring(file_get_contents($handle->file_src_pathname));
    $valid = ($im !== false);
    if ($valid && $handle->uploaded) {
        imagedestroy($im);
        $handle->image_no_enlarging = true;
        $handle->image_resize = true;
        $handle->image_ratio_y = true;
        $handle->image_x = 1200;
        $handle->Process('/data/configuration/images.beneluxspoor.net/images/');

        if ($handle->processed) {
            // Upload process succesfully
            echo '<fieldset class="upload">';
            echo '  <legend>Plaatje succesvol geplaatst</legend>';
            echo '  <strong>LET OP: Dit is de link naar je plaatje:</strong>';
            echo '  <br />Plaatje: <input type="text" size="100" value="https://images.beneluxspoor.net/bnls_'.$year.'/' . $handle->file_dst_name . ' "/> ';
            echo '  <br />Direct met link in het forum plaatsen: <input type="text" size="150" value="[url=https://images.beneluxspoor.net/bnls_'.$year.'/' . $handle->file_dst_name . '][img]https://images.beneluxspoor.net/bnls_'.$year.'/' . $handle->file_dst_name . '[/img][/url]" />';
            echo '  <br /><br /><img src="https://images.beneluxspoor.net/bnls_'.$year.'/' . $handle->file_dst_name . '" />';

            $info = getimagesize($handle->file_dst_pathname);
            echo '  <p>' . $info['mime'] . ' &nbsp;-&nbsp; ' . $info[0] . ' x ' . $info[1] . ' &nbsp;-&nbsp; ' . round(filesize($handle->file_dst_pathname) / 256) / 4 . 'KB</p>';
            echo '</fieldset>';
        } else {
            // An error occured
            echo '<fieldset>';
            echo '  <legend>Bestand kon niet geplaatst worden, probeer opnieuw of meld aan de webmaster!</legend>';
            echo '  Fout: ' . $handle->error . '';
            echo '</fieldset>';
        }
    } else {
        // The upload file failed for some reason, the server didn't receive the file
        echo '<fieldset>';
        echo '  <legend>Bestand kon niet geplaatst worden, probeer opnieuw of meld aan de webmaster!</legend>';
        echo '  Fout: ' . $handle->error . '';
        echo '</fieldset>';
    }
    echo '<p><a href="index.html">Nog een plaatje uploaden</a></p>';
    ?>
</body>
</html>
