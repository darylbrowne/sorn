<?php
class PHPMP3
{
    private $str;
    private $time;
    private $frames;
    private $binaryTable;
    public function __construct($path = '')
    {
        $this->binaryTable = array();
        for ($i = 0; $i < 256; $i ++) {
            $this->binaryTable[chr($i)] = sprintf('%08b', $i);
        }
        if ($path != '') {
            $this->str = file_get_contents($path);
        }
    }
    private function setStr($str)
    {
        $this->str = $str;
    }
    public function getStart()
    {
        $currentStrPos = - 1;
        while (true) {
            $currentStrPos = strpos($this->str, chr(255), $currentStrPos + 1);
            if ($currentStrPos === false) {
                return 0;
            }
            $str    = substr($this->str, $currentStrPos, 4);
            $strlen = strlen($str);
            $parts  = array();
            for ($i = 0; $i < $strlen; $i ++) {
                $parts[] = $this->binaryTable[$str[$i]];
            }
            if ($this->doFrameStuff($parts) === false) {
                continue;
            }
            return $currentStrPos;
        }
    }
    public function setFileInfoExact()
    {
        $maxStrLen     = strlen($this->str);
        $currentStrPos = $this->getStart();
        $framesCount = 0;
        $time        = 0;
        while ($currentStrPos < $maxStrLen) {
            $str    = substr($this->str, $currentStrPos, 4);
            $strlen = strlen($str);
            $parts  = array();
            for ($i = 0; $i < $strlen; $i ++) {
                $parts[] = $this->binaryTable[$str[$i]];
            }
            if ($parts[0] != '11111111') {
                if (($maxStrLen - 128) > $currentStrPos) {
                    return false;
                } else {
                    $this->time   = $time;
                    $this->frames = $framesCount;
                    return true;
                }
            }
            $a = $this->doFrameStuff($parts);
            $currentStrPos += $a[0];
            $time += $a[1];
            $framesCount ++;
        }
        $this->time   = $time;
        $this->frames = $framesCount;
        return true;
    }
    public function extract($start, $length)
    {
        $maxStrLen     = strlen($this->str);
        $currentStrPos = $this->getStart();
        $framesCount   = 0;
        $time          = 0;
        $startCount    = - 1;
        $endCount      = - 1;
        while ($currentStrPos < $maxStrLen) {
            if ($startCount == - 1 && $time >= $start) {
                $startCount = $currentStrPos;
            }
            if ($endCount == - 1 && $time >= ($start + $length)) {
                $endCount = $currentStrPos - $startCount;
            }
            $str    = substr($this->str, $currentStrPos, 4);
            $strlen = strlen($str);
            $parts  = array();
            for ($i = 0; $i < $strlen; $i ++) {
                $parts[] = $this->binaryTable[$str[$i]];
            }
            if ($parts[0] == '11111111') {
                $a = $this->doFrameStuff($parts);
                $currentStrPos += $a[0];
                $time += $a[1];
                $framesCount ++;
            } else {
                break;
            }
        }
        $mp3 = new static();
        if ($endCount == - 1) {
            $endCount = $maxStrLen - $startCount;
        }
        if ($startCount != - 1 && $endCount != - 1) {
            $mp3->setStr(substr($this->str, $startCount, $endCount));
        }
        return $mp3;
    }
    private function doFrameStuff($parts)
    {
        $seconds = 0;
        $errors  = array();
        switch (substr($parts[1], 3, 2)) {
            case '01':
                $errors[] = 'Reserved audio version';
                break;
            case '00':
                $audio = 2.5;
                break;
            case '10':
                $audio = 2;
                break;
            case '11':
                $audio = 1;
                break;
        }
        switch (substr($parts[1], 5, 2)) {
            case '01':
                $layer = 3;
                break;
            case '00':
                $errors[] = 'Reserved layer';
                break;
            case '10':
                $layer = 2;
                break;
            case '11':
                $layer = 1;
                break;
        }
        $bitFlag  = substr($parts[2], 0, 4);
        $bitArray = array(
            '0000' => array(0, 0, 0, 0, 0),
            '0001' => array(32, 32, 32, 32, 8),
            '0010' => array(64, 48, 40, 48, 16),
            '0011' => array(96, 56, 48, 56, 24),
            '0100' => array(128, 64, 56, 64, 32),
            '0101' => array(160, 80, 64, 80, 40),
            '0110' => array(192, 96, 80, 96, 48),
            '0111' => array(224, 112, 96, 112, 56),
            '1000' => array(256, 128, 112, 128, 64),
            '1001' => array(288, 160, 128, 144, 80),
            '1010' => array(320, 192, 160, 160, 96),
            '1011' => array(352, 224, 192, 176, 112),
            '1100' => array(384, 256, 224, 192, 128),
            '1101' => array(416, 320, 256, 224, 144),
            '1110' => array(448, 384, 320, 256, 160),
            '1111' => array(- 1, - 1, - 1, - 1, - 1)
        );
        $bitPart  = $bitArray[$bitFlag];
        $bitArrayNumber = null;
        if ($audio == 1) {
            switch ($layer) {
                case 1:
                    $bitArrayNumber = 0;
                    break;
                case 2:
                    $bitArrayNumber = 1;
                    break;
                case 3:
                    $bitArrayNumber = 2;
                    break;
            }
        } else {
            switch ($layer) {
                case 1:
                    $bitArrayNumber = 3;
                    break;
                case 2:
                    $bitArrayNumber = 4;
                    break;
                case 3:
                    $bitArrayNumber = 4;
                    break;
            }
        }
        $bitRate = $bitPart[$bitArrayNumber];
        if ($bitRate <= 0) {
            return false;
        }
        $frequencies = array(
            1   => array(
                '00' => 44100,
                '01' => 48000,
                '10' => 32000,
                '11' => 'reserved'
            ),
            2   => array(
                '00' => 44100,
                '01' => 48000,
                '10' => 32000,
                '11' => 'reserved'
            ),
            2.5 => array(
                '00' => 44100,
                '01' => 48000,
                '10' => 32000,
                '11' => 'reserved'
            )
        );
        $freq        = $frequencies[$audio][substr($parts[2], 4, 2)];
        $frameLength = 0;
        $padding = substr($parts[2], 6, 1);
        if ($layer == 3 || $layer == 2) {
            $frameLength = 144 * $bitRate * 1000 / $freq + $padding;
        }
        $frameLength = floor($frameLength);
        if ($frameLength == 0) {
            return false;
        }
        $seconds += $frameLength * 8 / ($bitRate * 1000);
        return array($frameLength, $seconds);
    }
    public function save($path)
    {
        $fp           = fopen($path, 'w');
        $bytesWritten = fwrite($fp, $this->str);
        fclose($fp);
        return $bytesWritten == strlen($this->str);
    }
}
$path = 'uploads/ssc.mp3';
$mp3 = new PHPMP3($path);
$mp3_1 = $mp3->extract(0,3);
$mp3_1->save('welcome.mp3');
$day = date('w');
$CLIP_DICT = array(
	//GENERAL PHRASES & TILE 3
	array(
	"welcome" => "0,5",
	"updates" => "5,10",
	"menu" => "10,15",
	"thanks" => "15,20",
	"later" => "20,25",
	"comeback" => "25,30", 
	"feedback" => "30,35", 
	"goodbye" => "35,40",
	"why" => "40",
	"3" => "40"),
	//TILE ONE BY DAY OF WEEK, 0 = SUNDAY, 6 = SATURDAY
	array(
	"0" => "0,5",
	"1" => "5,10",
	"2" => "10,15",
	"3" => "15,20",
	"4" => "20,25", 
	"5" => "25,30", 
	"6" => "30,35",
	"7" => "35"),
	//TILE TWO BY DAY OF WEEK, 0 = SUNDAY, 6 = SATURDAY
	array(
	"0" => "0,5",
	"1" => "5,10",
	"2" => "10,15",
	"3" => "15,20",
	"4" => "20,25", 
	"5" => "25,30", 
	"6" => "30,35",
	"7" => "35")
);
function get_url($url) { 
	$ch = curl_init(); 
	curl_setopt ($ch, CURLOPT_URL, $url); 
	curl_setopt ($ch, CURLOPT_HEADER, 0); 
	ob_start(); 
	curl_exec ($ch); 
	curl_close ($ch); 
	$string = ob_get_contents(); 
	ob_end_clean(); 
	return $string; 
} 
function get_audio($sheetID) {
	$filter = "Direct link to download an audio file of this message: ";
	$content = get_url("https://docs.google.com/spreadsheets/u/4/d/e/". $sheetID ."/pubhtml?gid=0&single=true"); 
	$result = explode($filter, $content);
	preg_match_all('!(https?://\S+)(\s)!', $result[1], $matches);
	$all_urls = $matches[0];
	$target_urls = explode('<br><br>', $all_urls[0]);
	$target_url = $target_urls[0];
	define(URL, $target_url);
	$f = fopen('output.mp3', 'w');
	$ch = curl_init(URL);
	curl_setopt_array($ch, array(
	  CURLOPT_CONNECTTIMEOUT => 600,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_FILE => $f,
	));
	curl_exec($ch);
	//curl_close($handle);
	fclose($f);
}
get_audio("2PACX-1vS3tJ42lgppNzgO4IWdVFe7rE7LEY9jzKWQpx465E1dZVP7cCRgN1hwJ-I2XxlJVcd7QcUa-GfX5RdY");
$ga_vertical = "ssc.php";
$absPath = explode("/",$_SERVER['SCRIPT_NAME']);
$arrPath = explode(".",end($absPath));
$ga_asset_file_root = $arrPath[0];	
$ga_csv = $ga_asset_file_root . ".csv";
function getRequestHeaders() {
    $headers = array();
    foreach($_SERVER as $key => $value) {
        if (substr($key, 0, 5) <> 'HTTP_') {
            continue;
        }
        $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
        $headers[$header] = $value;
    }
    return $headers;
}
function getSpeakpipeFiles() {
	//$file = fopen('https://docs.google.com/spreadsheets/d/15-nIodSYKg7Gag4lXYL7LuKyWkabAlS-AGaiH3zUxig/edit?output=csv', 'r');
	//while (($line = fgetcsv($file)) !== FALSE) {
	//   print_r($line);
	//}
	//fclose($file);	
}
getSpeakpipeFiles();
$headers = getRequestHeaders();
$payload = '';
foreach ($headers as $header => $value) {
    $payload .= "$header: $value\n";
    if ($header == 'X-Real-Ip') {$subject = 'Voice Destination [' . $ga_asset_file_root . '] request from ' . $value;}
}
#mail("daryl@voicengage.co, derek@voicengage.co",$subject,$payload);
if(!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on") {
    header("Location: https://02b7cf4.netsolhost.com/voicedestination/" . $_SERVER["REQUEST_URI"], true, 301);
    exit;
}
/*
echo('$ga_asset_file_root : ' . $ga_asset_file_root . '<br />');
echo('$_SERVER["SCRIPT_FILENAME"] : ' . $_SERVER["SCRIPT_FILENAME"] . '<br />');
echo('$_SERVER["REQUEST_URI"] : ' . $_SERVER["REQUEST_URI"] . '<br />');
echo('$_SERVER["QUERY_STRING"] : ' . $_SERVER['QUERY_STRING'] . '<br />');
echo('$_SERVER["REQUEST_METHOD"] : ' . $_SERVER['REQUEST_METHOD'] . '<br />');
echo('$_SERVER["PHP_SELF"] : ' . $_SERVER["PHP_SELF"] . '<br />');
echo('$_SERVER["HTTPS"] : ' . $_SERVER["HTTPS"] . '<br />');
*/
	if ($_SERVER['QUERY_STRING'] && stristr($_SERVER['QUERY_STRING'], '%7C')) {
		if (!file_exists($ga_csv)) {
		    $fp = fopen($ga_csv, 'wb');
		    fputcsv($fp , array ('Timestamp', 'User', 'Turn', 'Current', 'Previous', 'Request', 'Type'));
		    fclose($fp);
		}
		$querystring = str_replace("qs=t%3D", "", $_SERVER['QUERY_STRING']);
		$pieces = explode("%7C", $querystring);
		array_unshift($pieces, time());
	    $fp = fopen($ga_csv, 'a');
	    fputcsv($fp, $pieces);
	    fclose($fp);
	}
	$ga_client_id = $_POST["ga_client_id"] ? $_POST["ga_client_id"] : $ga_tiles[0]['client_id'];
	$ga_owners = $_POST["ga_owners"] ? $_POST["ga_owners"] : $ga_tiles[0]['owners'];
	/*$ga_client_id = $ga_tiles[0]['client_id'];
	$ga_owners = $ga_tiles[0]['owners'];*/
	
	$pieces = explode("%7C", $_SERVER['QUERY_STRING']);
	#$prefix = pathinfo(basename($_SERVER['PHP_SELF']), PATHINFO_FILENAME);
	$prefix = pathinfo(basename($_SERVER['PHP_SELF']), PATHINFO_FILENAME);	

if (strpos($_SERVER['QUERY_STRING'], 'audio') || strpos($_SERVER['REQUEST_URI'], 'audio')){
	
	$clip = ($pieces[4] == "1" || $pieces[4] == "2" ) ? $pieces[4] . "_" . $day : $pieces[4];
	$fullAudio = 'uploads/' . $ga_asset_file_root . "_" . $clip;
	header("Content-type: audio/mp3");
	header("Location: " . $fullAudio . ".mp3");
} else if (strpos($_SERVER['QUERY_STRING'], 'icon') || strpos($_SERVER['REQUEST_URI'], 'icon')){
	switch ($pieces[4]) {
	    case "1":
	        $imgURL = 'uploads/' . $ga_asset_file_root . '_1.jpg';
	        break;
	    case "2":
	        $imgURL = 'uploads/' . $ga_asset_file_root . '_2.jpg';
	        break;
	    case "3":
			$imgOBJ = imagecreatefromjpeg("uploads/" . $ga_asset_file_root . ".jpg");
	        $imgURL = "uploads/" . $ga_asset_file_root . "_3.jpg";
	        break;
	    default:
	        $imgURL = "uploads/" . $ga_asset_file_root . ".jpg";
	}
	
	header("Content-type: image/jpg");
	//if($pieces[4] == "3" && $imgOBJ && imagefilter($imgOBJ, IMG_FILTER_GRAYSCALE))
	//{
	//    imagejpeg($imgOBJ);
	//	imagedestroy($imgOBJ);
	//}
	//else
	//{
		header("Location: " . $imgURL);
	//}
	
} else if (isset($_POST) && $_SERVER['REQUEST_METHOD'] == "POST") {
	if ($_POST["ga_submit"] == "create" && $_POST["ga_new_title"]) {
		$o_root = $ga_asset_file_root . '.php';
		$n_name = $str = strtolower(preg_replace( '/[^a-z0-9 ]/i', '', $_POST["ga_new_title"])) . "_";
		$n_prefix = uniqid($n_name);		
		$n_root = $n_prefix . ".php";
		if (!copy($o_root, $n_root)) {
		    echo "failed to copy $file...\n";
		} else {
			$o_prefix = 'uploads/hal_';
			$n_prefix = 'uploads/' . $n_prefix . '_';
			$images = array(
				"0",
				"1",
				"2",
				"3"
			);
			$sounds = array(
				"welcome", 
				"comeback", 
				"feedback", 
				"goodbye", 
				"later", 
				"menu",
				"thanks",
				"1",
				"2",
				"3",
				"1_0",
				"1_1",
				"1_2",
				"1_3",
				"1_4",
				"1_5",
				"1_6"
			);
			foreach ($images as $image) {
				$o_image = $o_prefix . $image . ".jpg";
				$n_image = $n_prefix . $image . ".jpg";
				if (!copy($o_image, $n_image)) {
				    echo "failed to copy";
				}
			}
			foreach ($sounds as $sound) {
				$o_sound = $o_prefix . $sound . ".mp3";
				$n_sound = $n_prefix . $sound . ".mp3";
				if (!copy($o_sound, $n_sound)) {
				    echo "failed to copy";
				}
			}
			chmod($n_root,0777);			
			header("Location: https://02b7cf4.netsolhost.com/voicedestination/" . $n_root, true, 301);		
		}	
	} else {
		$path = "uploads/";
		$valid_formats1 = array("mp3", "ogg", "flac");
		for ($x = 0; $x <= 3; $x++) {
			$lookFor = 'audio_' . $x;
			$option = 'option_' . $x;
		    $audio = $_FILES[$lookFor]['name'];
		    $size = $_FILES[$lookFor]['size'];
		    if(strlen($audio)) {
		        list($raw, $ext) = explode(".", $audio);
		        if(in_array($ext,$valid_formats1)) {
		        	$actual_audio_name = $raw.".".$ext;
			        $new_audio_name = $ga_asset_file_root . "_raw." . $ext;
					$tmp = $_FILES[$lookFor]['tmp_name'];
					if(move_uploaded_file($tmp, $path.$new_audio_name)) {
						$audError = $lookFor . " audio success";
						if (!empty($_POST[$option])) {
							foreach($_POST[$option] as $selected){
								copy($path.$new_audio_name,$path.$ga_asset_file_root . "_" . $selected . "." . $ext);
							}
						}
					} else {
						$audError = $option . " audio upload failed";              
					}
		        } else {
						$audError = "wrong file type (audio " . $option . " upload error)";                          	
		        }
		        echo($audError);
		    }		
		}

		for ($x = 0; $x <= 3; $x++) {
			$imageName = "image_" . $x;
			$target_file = $path . basename($_FILES[$imageName]["name"]);
			$uploadOk = 1;
			$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
			$IMAGE_HEIGHT = 1080;
			$IMAGE_WIDTH = 1920;
			if(isset($_FILES[$imageName]["name"]) && !empty($_FILES[$imageName]["name"])) {
				echo($imageName . ' : ' . ($_FILES[$imageName]["name"]) . '...<br />');
				if($imageFileType != "jpg" && $imageFileType != "jpeg") {
				  $imgError = "Sorry, only JPG files are allowed.";
				  $uploadOk = 0;
				}     
				$check = getimagesize($_FILES[$imageName]["tmp_name"]);
				
				if($check !== false) {
					$imgError = "File is an image - " . $check["mime"] . ".";
					$uploadOk = 1;
				} else {
					$imgError = "File is not an image.";
					$uploadOk = 0;
				}
				if ($uploadOk == 0) {
					$imgError = "Sorry, image " . $x . " was not uploaded.";
				} else {
					$tmp_file_name = $_FILES[$imageName]['tmp_name'];
					$ext = strtolower(pathinfo($_FILES[$imageName]['name'], PATHINFO_EXTENSION));
					$actual_file_name = $path . basename($_FILES[$imageName]['name'], "." . $ext) . ".jpg";
					if(getimagesize($tmp_file_name)){
						$image_array = getimagesize($tmp_file_name);
						$mime_type = $image_array['mime'];
						list($width_orig, $height_orig) = getimagesize($tmp_file_name);
						if($mime_type == "image/jpeg"){
							if($image_p = imagecreatetruecolor($IMAGE_WIDTH, $IMAGE_HEIGHT)){
								if($image = imagecreatefromjpeg($tmp_file_name)){
									$exif=exif_read_data($_FILES[$imageName]['tmp_name'],'IFD0');
									if($exif['Orientation']==3 || $exif['Orientation']==6){
										//ini_set('memory_limit', '256M');
										$image=imagerotate($image,270,0);
										$width_orig = $image_array[1];
										$height_orig = $image_array[0];
									}
									$old_x          =   imageSX($image);
									$old_y          =   imageSY($image);
									$old_ratio		= 	$old_x/$old_y;
									$new_ratio		= 	$IMAGE_WIDTH/$IMAGE_HEIGHT;
									if($old_ratio == $new_ratio) 
									{
										$ga_width    =   $IMAGE_WIDTH;
										$ga_height    =   $IMAGE_HEIGHT;
									}
									if($old_ratio > $new_ratio) 
									{
										$ga_width    =   $IMAGE_WIDTH;
										$ga_height    =   $old_y*($IMAGE_HEIGHT/$old_x);
									}
									if($old_ratio < $new_ratio) 
									{
										$ga_width    =   $old_x*($IMAGE_WIDTH/$old_y);
										$ga_height    =   $IMAGE_HEIGHT;
									}
									$width_new = $height_orig * ($IMAGE_WIDTH / $IMAGE_HEIGHT);
									$height_new = $width_orig * ($IMAGE_HEIGHT / $IMAGE_WIDTH);
									if($width_new > $width_orig){
										$width = $width_orig;
										$height = $height_new;
										$w_point = 0;
										$h_point = (($height_orig - $height_new) / 2);
									} else {
										$width = $width_new;
										$height = $height_orig;
										$w_point = (($width_orig - $width_new) / 2);
										$h_point = 0;
									}
									if(imagecopyresampled($image_p, $image, 0, 0, $w_point, $h_point, $IMAGE_WIDTH, $IMAGE_HEIGHT, $width, $height)){
											if(imagejpeg($image_p, $actual_file_name, 100)){
												imagedestroy($image_p);
												imagedestroy($image);
												$filename = $path . $ga_asset_file_root . "_" . $x . ".jpg";
												
												if(rename($actual_file_name, $filename)){
													switch ($x) {
													    case 0:
															$ga_option_0_image = $filename;
													        break;
													    case 1:
															$ga_option_1_image = $filename;
													        break;
													    case 2:
															$ga_option_2_image = $filename;
													        break;
													    case 3:
															$ga_option_3_image = $filename;
													        break;
													}											
												} else {
													switch ($x) {
													    case 0:
															$ga_option_0_image = "https://image.cnbcfm.com/api/v1/image/106161538-1570071966284gettyimages-1178179427.jpeg";
													        break;
													    case 1:
															$ga_option_1_image = "https://image.cnbcfm.com/api/v1/image/106161538-1570071966284gettyimages-1178179427.jpeg";
													        break;
													    case 2:
															$ga_option_2_image = "https://image.cnbcfm.com/api/v1/image/106161538-1570071966284gettyimages-1178179427.jpeg";
													        break;
													    case 3:
															$ga_option_3_image = "https://image.cnbcfm.com/api/v1/image/106161538-1570071966284gettyimages-1178179427.jpeg";
													        break;
													}
												}
											}
									} else {
										imagedestroy($image);
										imagedestroy($image_p);
										$imgError = "Image Processing Error";
									}
								} else {
									imagedestroy($image_p);
									$imgError = "Image Processing Error";
								}
							} else {
								$imgError = "Image Processing Error. Please try again later.";
							}
						} else {
							$imgError = "Only JPEG, PNG and GIF images are allowed.";
						}
					} else {
						$imgError = "Bad image format";
					} 
				}
				echo($imgError);
			}
		}
		
		$tileSlot = '/^\$ga_tiles = array((.*)$/m';
		$newTile = '';
		for ($t = 0; $t <= 10; $t++) {
			if($_POST["ga_option_" . $t . "_title"] || $_POST["ga_option_" . $t . "_subtitle"] || $_POST["ga_option_" . $t . "_description"] || $_POST["ga_option_" . $t . "_url"] || $_POST["ga_option_" . $t . "_text"]) {
				$addendum = $t == 0 ? ',"owners" => "' . $_POST["ga_owners"] . '","client_id" => "' . $_POST["ga_client_id"] . '"' : '';
				$newTile .= 'array("title" => "' . $_POST["ga_option_" . $t . "_title"] . '", "subtitle" => "' . $_POST["ga_option_" . $t . "_subtitle"] . '", "description" => "' . $_POST["ga_option_" . $t . "_description"] . '","text" => "' . $_POST["ga_option_" . $t . "_text"] . '","url" => "' . $_POST["ga_option_" . $t . "_url"] . '" . $addendum),';
			}
		}
		
		$newTile = '$ga_tiles = array(' . $newTile . ');';		
		$content = file_get_contents($ga_asset_file_root . '.php'); 
		$arr = explode("\n", $content);
		array_shift($arr);
		array_shift($arr);
		$newcontent = "<?php\n" . $newTile . "\n" . implode("\n", $arr);
		file_put_contents($ga_asset_file_root . '.php', $newcontent);
		header("Location: https://02b7cf4.netsolhost.com/voicedestination/" . $ga_asset_file_root . '.php#admin', true, 301);		
	}
} else {
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">	
	<meta name="generator" content="766f6963656e67616765">
	<?="<meta name='distribution' content='766f69636564657374696e6174696f6e0a'>"?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">	
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">  	
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	<link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
	<style type="text/css">
	:root {
	  --xxlarge: 1.7em;
	  --xlarge: 1.5em;
	  --large: 1.25em;
	  --medium: 1.2em;
	  --color1: #1b458b;
	  --color2: #0a0;
	  --color3: #f80;
	  --color4: #08f;
	  --color5: #a04;
	  --color6: #ffd700;
	}
	body {
		color: black;
		background: white; 
		font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
		margin: auto;
		font-size: 4vw;
	}/*
	details:first-child > summary::before {
	    background-image: url('https://02b7cf4.netsolhost.com/voicedestination/uploads/ga.png');
	    background-size: 2.25em auto;
	    display: inline-block;
		width: 2.25em;
		height: 2.25em;
		margin: 0 1em 0 0;
	    content:"";
	}*/
	details:not(:first-child) .btn-url {
	  display: none;
	}
	details:not(:first-child) .yt.url {
	  display: none;
	}
	details:first-child > summary {
	  list-style: none;
	}
	details:first-child > summary::marker,
	details:first-child h4.card-subtitle {
	  display: none;
	}	
	details:first-child > summary > input.h3.form-control.form-control-lg {
		font-size: var(--medium);
		font-weight: bold;
		background-image: url('https://02b7cf4.netsolhost.com/voicedestination/uploads/ssc-logo-sm.png');
  background-repeat: no-repeat;
  background-position: left;		
	    background-size: 1.5em auto;
	    padding-left: 2em;
	}
	details:first-child .hero:hover .overlay {
	  display: block;
	  background: rgba(0, 0, 0, .3);
	}
	details:first-child .hero:hover .overlay label {
	  opacity: 1;
	}	
	details:last-child img.ga_image {
	  -webkit-filter: grayscale(100%); /* Safari 6.0 - 9.0 */
	  filter: grayscale(100%);		
	}
	details:not(:first-child) .overlay, 
	details:not(:first-child) .overlay label {
		display: none;
	}
	form {
		margin-block-end: 20em;
	}
	h1 {
		font-size: 2em;
		padding: .25em;
	}
	h2, h4, legend, summary {
		padding: .25em 0;
	}
	h2, a.btn.btn-warning.cta-0 {
		font-size: var(--xxlarge);
	}	
	h4,
	a.btn,
	input.form-control.form-control-lg, 
	select.form-control.form-control-lg, 
	input.form-control-file.form-control-lg 
	{
		font-size: var(--xlarge);
	}
	hr {
		border-top: 1em solid lightgray;
	}
	audio, .teleprompter {
		width: 100%;
		height: 3em;
		margin: 1em auto;
	}	
	legend, 
	summary,
	label.btn-image,
	textarea.p,
	textarea.form-control-file.form-control-lg,
	input.h3.form-control.form-control-lg
	{
		font-size: var(--large);
	}
	input.h4.form-control.form-control-lg {
		font-size: var(--medium);
	}
	p, label, li:not(.nav-item), option, a.btn.btn-primary {
		font-size: 1em;
	}
	label.btn
	{
		height: calc(2.25em);
		font-size: 2em;
		padding: .5em .75em;
		margin: .25em;
	}	
	label.btn-image 	
	{
		height: calc(2.25em);
		margin: .25em 0 0 .25em;
		padding: .4em .5em 0;
		border-radius: 50%;
		background: gray;
		text-align: center;
		color: white;
		border: none;
		transition: all 0.2s;
	}
	a.btn,
	input.form-control.form-control-lg, 
	select.form-control.form-control-lg, 
	input.form-control-file.form-control-lg {
		height: calc(2.5em);
		padding: .25em .5em;
		line-height: 2em;
		border-radius: .25em;
		margin: .25em;
		width: 95%;
	}
	a.btn.btn-primary {
		background-color: #3380b6;
		border-color: #3380b6;
		color: white;
	}
	a.btn.btn-primary:hover {
		color: lightgray;
	}
	input.form-control.form-control-lg, 
	select.form-control.form-control-lg, 
	input.form-control-file.form-control-lg,
	textarea {
		background: none;
		color: black;
		/*margin-bottom: 5em;*/
	}
	input.h3.form-control.form-control-lg,
	textarea.form-control-file.form-control-lg,
	textarea.p	
	 {
		margin: .25em .25em 1em .25em;		
	}
	select#sound {
		width: 98%;
		margin-bottom: 1em;
	} 
	select.form-control.form-control-lg.tribe {
	    display: inline-block!important;
	    width: 83%;		
	}
	textarea.h1 {
		height: calc(5em);
		width: 100%;		
		padding: .25em .5em;
		font-size: 2em;
		line-height: 2.5em;
		border: none;
	}
	textarea.p {
		height: calc(5em);
		width: 100%;
		padding: .25em .5em;
		line-height: 1.75em;
		border: none;
	}
	textarea.form-control-file.form-control-lg {
		height: calc(6em);
		padding: .25em .5em;
		line-height: 2em;
		border: none;
	}
	input.h3.form-control.form-control-lg {
		border: none;
		width: 85%;	
		display: inline;
		padding: 0;
		margin: 0;
	}
	input.h4.form-control.form-control-lg {
		border: none;			
		width: 85%;	
		display: inline;
	}
	nav .fa {
		font-size: 2em;
	}
	nav.navbar-dark.bg-dark {
		padding-top: 1em;
		height: 4em;
		display: flex;
		align-items: center;
		vertical-align: middle;
	}
	nav button {
	  display: flex;
	  justify-content: center;
	  position: absolute;
	  width: 100%;
	  left: 0;
	  top: 0;
	  margin-top: 1em;
	}
 	textarea, input {
		background: transparent;
	}	
	.navbar-dark.bg-dark, 
	.navbar-dark.bg-dark .btn-dark
	{
		background-color: #333!important;
		border: none;
	}
	.mt-5 {
		margin-top: 1.25em;
	}
	.icon {
		width: 2.25em;
		height: auto;
		margin: 0 1em 0 0;
	}
	.playback, .record {
		display: none;
	}
	.teleprompter {
		text-align: center;
		vertical-align: center;
		width: 100%;	
		padding: .5em 0;
	}
	.cueCards {
		border: none;
		color: white;
		font-weight: bold;
		background: none;
		font-size: 1rem;
		text-align: center;	
	}
	.overlay label {
	  position: relative;
	  top: 40%;
	  width: 2.25em;
	  margin: auto;
	  transition: opacity 0.5s ease;
	  opacity: 0;
	}
	.addTile, .addTile:active, .addTile:visited, .addTile:hover {
		color: white;
		display: block;
		margin: 1em 0;
	}
	label.btn:not(.active):not(.toggle-on):not(.toggle-off) {
		color: lightgray;
		background-color: black;
		border-color: gray;	 	
	}
	fieldset.recordings,
	figcaption,
	label[for*="alt"],
	label[for*="-image-upload"],
	input[id*="alt"],
	input[for*="-image-upload"],
	input[type="file"][class="audio"],
	input[type="file"][class="image"],
	.hidden
	{
		display: none;
	}
	div.hero {
		position: relative;
		margin: 0 -15px 2px;
	}
	.ga_image {
	    max-height: 33%;
	    width: 100%;		
	}
	img.ga_image:before {
	    content: ' ';
	    display: block;
	    position: absolute;
	    height: 100%;
	    width: 100%;
	    background-image: url(https://02b7cf4.netsolhost.com/voicedestination/uploads/sta_1.jpg);
		background-position: center; /* Center the image */
		background-repeat: no-repeat; /* Do not repeat the image */
		background-size: cover; /* Resize the background image to cover the entire container */	    
	}
	input:invalid,
	textarea:invalid,
	select:invalid {
	  color: #b94a48;
	  border-color: #ee5f5b;
	  &:focus {
	    border-color: darken(#ee5f5b, 10%);
	    .box-shadow(0 0 6px lighten(#ee5f5b, 20%));    
	  }
	}	
	#ga_assets {
		display: none;
		margin: 2% -5%;
	}
	.overlay {
	/*input[type="file"][class="image"] {*/
	  position: absolute;
	  top: 0;
	  left: 0;
	  width: 100%;
	  height: 100%;
	  background: rgba(0, 0, 0, 0);
	  transition: background 0.5s ease;
	  text-align: center;
	}
	.card-body {
		padding: 0;
	}
	.card {
		background: transparent;
		border: none;
	}
	.campaignDay.input-group-text {
		text-align: right;
		width: 12em;
	}
	#sun {
		background-color: #8dd3c7;
	}
	#mon {
		background-color: #ffffb3;		
	}
	#tue {
		background-color: #bebada;				
	}
	#wed {
		background-color: #fb8072;
	}
	#thu {
		background-color: #80b1d3;
	}
	#fri {
		background-color: #fdb462;
	}
	#sat {
		background-color: #b3de69;
	}
	
	#body input:focus, textarea:focus, .btn:focus, button:focus, button.btn-dark.upload:focus, summary, summary:focus,
	button.btn-dark.upload:focus-visible, button.btn-dark.upload:active, button.btn-dark.upload:hover, button.btn-dark.upload:visited 
	{
	  /*box-shadow: 0 1px 1px rgba(0, 0, 0, 0.075) inset, 0 0 8px rgba(52, 127, 182, 0.6);*/
	  outline: 0;
	  outline-color: white;
      box-shadow: none;
	}
	.toast {
		background: green;
		display: none;
	    position: fixed;
	    top: 0;
	    left: 0;
	    width: 100%;
	    max-width: 100%;
	    min-width: 100%;
	    z-index: 10;
	}
	.toast-header img {
		height: 50px;
		width: auto;
	}
	@media (min-width: 768px) {
		#body {
			width: 767px;
			font-size: 14px;
			margin: auto;
		}
	}
	.btn-dark {
	    color: #fff;
	    background-color: black;
	    border-color: black;
	}	
	table.dataTable tbody tr {
	    background-color: #333;
	}	
	.dataTables_wrapper .dataTables_filter input,
	.dataTables_wrapper .dataTables_length select {
	    background-color: #999;
	}
	.dataTables_wrapper {
		margin-top: 5em;
	}
	.loading {
	  font-size: 1em;
	}
	.loading:after {
	  overflow: hidden;
	  display: inline-block;
	  vertical-align: bottom;
	  -webkit-animation: ellipsis steps(4,end) 900ms ;      
	  animation: ellipsis steps(4,end) 900ms ;
	  content: "\2026"; /* ascii code for the ellipsis character */
	  width: 0px;
	}
	@keyframes ellipsis {
	  to {
	    width: 1.25em;    
	  }
	}
	@-webkit-keyframes ellipsis {
	  to {
	    width: 1.25em;    
	  }
	}
	.modal, .modal input.form-control.form-control-lg {
		color: black;
	}
	.fa:hover {
		cursor: pointer;
	}
	.required label:after {
	  content:"*";
	  color:red;
	}
/*--------------------------------------------------------------------------------*/
.accordion > input[type="checkbox"] {
  position: absolute;
  left: -100vw;
}
.accordion .collapsed {
  overflow-y: hidden;
  height: 0;
  transition: height 0.3s ease;
}
.accordion > input[type="checkbox"]:checked ~ .collapsed {
  height: auto;
  overflow: visible;
}
.accordion label {
  display: block;
}
.accordion > input[type="checkbox"]:checked ~ .collapsed {
  padding: 15px;
}
.accordion label {
  cursor: pointer;
}
fieldset.accordion {
	display: none;
}
/*--------------------------------------------------------------------------------*/
#admin {
	/*display: none;*/
}
.col-userid {
    max-width: 6em;
    overflow: auto;
}
.col-smalls {
    max-width: 3em;
    overflow: auto;
}
    .barContainer {
    	margin-top: 2em;
      display: block;
    }
    .pieLegend {
    	width: 66%;	
      height: 150px;
      position: relative;
      display: inline-block;
      vertical-align: top;
    }
	.pieLegend ul {
	  list-style: none;
	}
	.pieLegend ul li::before {
	  content: "O";
	  display: inline-block;
	  width: 1em;
	  height: 1em;
	  margin-right: 5px;
	  font-size: 1.5em;
	  line-height: 1em;
	  font-weight: bolder;
	}
	.pieLegend ul li:first-child::before {
	  color: var(--color1);
	}
	.pieLegend ul li:nth-child(2)::before {
	  color: var(--color2);
	}
	.pieLegend ul li:nth-child(3)::before {
	  color: var(--color3);
	}
	.pieLegend ul li:nth-child(4)::before {
	  color: var(--color4);
	}
	.pieLegend ul li:nth-child(5)::before {
	  color: var(--color5);
	}
	.pieLegend ul li:nth-child(6)::before {
	  color: var(--color6);
	}

    .pieContainer {
    	width: 33%;	
      height: 150px;
      position: relative;
      display: inline-block;
    }
    
    .pieBackground {
      position: absolute;
      width: 150px;
      height: 150px;
      border-radius: 100%;
      box-shadow: 0px 0px 8px rgba(0,0,0,0.5);
    } 
    
    .pie {
      transition: all 1s;
      position: absolute;
      width: 150px;
      height: 150px;
      border-radius: 100%;
      clip: rect(0px, 75px, 150px, 0px);
    }
    
    .hold {
      position: absolute;
      width: 150px;
      height: 150px;
      border-radius: 100%;
      clip: rect(0px, 150px, 150px, 75px);
    }
    
    #pieSlice1 .pie {
      background-color: var(--color1);
      transform:rotate(30deg);
    }
    
    #pieSlice2 {
      transform: rotate(30deg);
    }
    
    #pieSlice2 .pie {
      background-color: var(--color2);
      transform: rotate(60deg);
    }
    
    #pieSlice3 {
      transform: rotate(90deg);
    }
    
    #pieSlice3 .pie {
      background-color: var(--color3);
      transform: rotate(120deg);
    }
    
    #pieSlice4 {
      transform: rotate(210deg);
    }
    
    #pieSlice4 .pie {
      background-color: var(--color4);
      transform: rotate(10deg);
    }
    
    #pieSlice5 {
      transform: rotate(220deg);
    }
    
    #pieSlice5 .pie {
      background-color: var(--color5);
      transform: rotate(70deg);
    }
    
    #pieSlice6 {
      transform: rotate(290deg);
    }
    
    #pieSlice6 .pie {
      background-color: var(--color6);
      transform: rotate(70deg);
    }
    
    .innerCircle {
      position: absolute;
      width: 120px;
      height: 120px;
      background-color: #444;
      border-radius: 100%;
      top: 15px;
      left: 15px; 
      box-shadow: 0px 0px 8px rgba(0,0,0,0.5) inset;
      color: white;
    }
    .innerCircle .content {
      position: absolute;
      display: block;
      width: 120px;
      top: 30px;
      left: 0;
      text-align: center;
      font-size: 14px;
    }
.graph-container {
  display: flex;
  margin: 0;
  padding: .5em;
  max-width:100%;
}
.graph-container > p {
  margin:0 20px 0 0;
  padding: .25em 1em;
  max-width: 50%;
  justify-content:center;
}
.bar-graph {
  height: 2em;
  position: relative;
  width: 10em;
}
.bar-graph .graph {
  height: 2em;
  padding: 0;
  margin: 0;
  width:100%;
  display: table;
}
.bar-graph .graph p{
  position: relative;
  text-align:center;
  color:white;
  margin-top:0;
  display: table-cell;
  vertical-align: middle;
}
.bar-graph {
	color: black;
}
.bar-graph .graph-1 {
	width: 30%;
	color: black;
	background-color: var(--color1);
}
.bar-graph .graph-2 {
	width: 100%;
	background-color: var(--color2);
}
.bar-graph .graph-3 {
	width: 75%;
	background-color: var(--color3);
}
.bar-graph .graph-4 {
	width: 80%;
	background-color: var(--color4);
}
.bar-graph .graph-5 {
	width: 60%;
	background-color: var(--color5);
}
.bar-graph .graph-6 {
	width: 40%;
	background-color: var(--color6);
}
.mt-5em {
	margin-top: 5em;
} 
.mt-2em {
	margin-top: 2em;
} 
.dev {
	display: none;
}
.sound.container {
	display: none;
	margin: .5em auto;
	padding: 1em;
	border-radius: 2em;
	background: repeating-linear-gradient(
	  45deg,
	  #ef734c,
	  #ef734c 10px,
	  #e65021 10px,
	  #e65021 20px
	);
}
#teleprompter{
	margin-left: .25em;	
}
#tiles details:first-child .dev.btn-url {
	display: block;
}
#generalPhrases, #questionsOfTheDay, #dailyDoses {
	display: none;
}
.quotes { 
	position:relative; 
	max-width:80%; 
	list-style-type:none; 
	text-align:center; 
    margin: 0 auto 0 12%;
    padding:0; 
}
.quotes li { 
	position:absolute; 
	left:0; 
	right:0; 
	text-align:center; 
	padding:1em; 
	border-radius:0.25em; 
	background-color: #fff;
	background-color: rgba(255,255,255,0.5);
	opacity:0;
	font-size: 80%;
	font-weight: bold;
}
#generalPhrases.quotes li:nth-child(20),
#questionsOfTheDay.quotes li:nth-child(16),
#dailyDoses.quotes li:nth-child(16)
 {
	opacity: 1;
}
.quotes li:last-child { position:relative }
.quotes li:after { /* quote triangle */
	position:absolute; 
	content:""; 
	display:block; 
	width:0; 
	height:0; 
	top:1.75em; 
	left:-0.75em;
	border-top:0.75em solid transparent;
	border-bottom:0.75em solid transparent;
	border-right:0.75em solid rgba(255,255,255,0.5);
	}
#generalPhrases.quotes li:nth-child(1) { -webkit-animation:slowquote 5s 0s; animation:slowquote 5s 0s}
#generalPhrases.quotes li:nth-child(2) { -webkit-animation:medquote 5s 5s; animation:medquote 5s 5s   }
#generalPhrases.quotes li:nth-child(3) { -webkit-animation:quote 5s 10s ; animation:quote 5s 10s  }
#generalPhrases.quotes li:nth-child(4) { -webkit-animation:quote 5s 15s ; animation:quote 5s 15s  }
#generalPhrases.quotes li:nth-child(5) { -webkit-animation:quote 5s 20s ; animation:quote 5s 20s  }
#generalPhrases.quotes li:nth-child(6) { -webkit-animation:quote 5s 25s  ; animation:quote 5s 25s   }
#generalPhrases.quotes li:nth-child(7) { -webkit-animation:quote 5s 30s  ; animation:quote 5s 30s   }
#generalPhrases.quotes li:nth-child(8) { -webkit-animation:quote 5s 35s ; animation:quote 5s 35s  }
#generalPhrases.quotes li:nth-child(9) { -webkit-animation:quote 5s 40s ; animation:quote 5s 40s  }
#generalPhrases.quotes li:nth-child(10) { -webkit-animation:quote 5s 45s ; animation:quote 5s 45s  }
#generalPhrases.quotes li:nth-child(11) { -webkit-animation:quote 5s 50s  ; animation:quote 5s 50s   }
#generalPhrases.quotes li:nth-child(12) { -webkit-animation:quote 5s 55s  ; animation:quote 5s 55s   }
#generalPhrases.quotes li:nth-child(13) { -webkit-animation:quote 5s 60s ; animation:quote 5s 60s  }
#generalPhrases.quotes li:nth-child(14) { -webkit-animation:quote 5s 65s ; animation:quote 5s 65s  }
#generalPhrases.quotes li:nth-child(15) { -webkit-animation:quote 5s 70s ; animation:quote 5s 70s  }
#generalPhrases.quotes li:nth-child(16) { -webkit-animation:quote 5s 75s  ; animation:quote 5s 75s   }
#generalPhrases.quotes li:nth-child(17) { -webkit-animation:quote 5s 80s  ; animation:quote 5s 80s   }
#generalPhrases.quotes li:nth-child(18) { -webkit-animation:quote 5s 85s  ; animation:quote 5s 85s   }
#generalPhrases.quotes li:nth-child(19) { -webkit-animation:quote 20s 90s  ; animation:quote 20s 90s   }
#generalPhrases.quotes li:nth-child(20) { -webkit-animation:endquote 110s 0s  ; animation:endquote 110s 0s   }
 
#questionsOfTheDay.quotes li:nth-child(1) { -webkit-animation:slowquote 5s 0s; animation:slowquote 5s 0s}
#questionsOfTheDay.quotes li:nth-child(2) { -webkit-animation:medquote 5s 5s; animation:medquote 5s 5s   }
#questionsOfTheDay.quotes li:nth-child(3) { -webkit-animation:quote 10s 10s ; animation:quote 10s 10s  }
#questionsOfTheDay.quotes li:nth-child(4) { -webkit-animation:quote 5s 20s ; animation:quote 5s 20s  }
#questionsOfTheDay.quotes li:nth-child(5) { -webkit-animation:quote 10s 25s ; animation:quote 10s 25s  }
#questionsOfTheDay.quotes li:nth-child(6) { -webkit-animation:quote 5s 35s  ; animation:quote 5s 35s   }
#questionsOfTheDay.quotes li:nth-child(7) { -webkit-animation:quote 10s 40s  ; animation:quote 10s 40s   }
#questionsOfTheDay.quotes li:nth-child(8) { -webkit-animation:quote 5s 50s ; animation:quote 5s 50s  }
#questionsOfTheDay.quotes li:nth-child(9) { -webkit-animation:quote 10s 55s ; animation:quote 10s 55s  }
#questionsOfTheDay.quotes li:nth-child(10) { -webkit-animation:quote 5s 65s ; animation:quote 5s 65s  }
#questionsOfTheDay.quotes li:nth-child(11) { -webkit-animation:quote 10s 70s  ; animation:quote 10s 70s   }
#questionsOfTheDay.quotes li:nth-child(12) { -webkit-animation:quote 5s 80s  ; animation:quote 5s 80s   }
#questionsOfTheDay.quotes li:nth-child(13) { -webkit-animation:quote 10s 85s ; animation:quote 10s 85s  }
#questionsOfTheDay.quotes li:nth-child(14) { -webkit-animation:quote 5s 95s ; animation:quote 5s 95s  }
#questionsOfTheDay.quotes li:nth-child(15) { -webkit-animation:quote 10s 100s ; animation:quote 10s 100s  }
#questionsOfTheDay.quotes li:nth-child(16) { -webkit-animation:endquote 110s 0s  ; animation:endquote 110s 0s   }
 
#dailyDoses.quotes li:nth-child(1) { -webkit-animation:slowquote 5s 0s; animation:slowquote 5s 0s}
#dailyDoses.quotes li:nth-child(2) { -webkit-animation:medquote 5s 5s; animation:medquote 5s 5s   }
#dailyDoses.quotes li:nth-child(3) { -webkit-animation:quote 15s 10s ; animation:quote 15s 10s  }
#dailyDoses.quotes li:nth-child(4) { -webkit-animation:quote 5s 25s ; animation:quote 5s 25s  }
#dailyDoses.quotes li:nth-child(5) { -webkit-animation:quote 15s 30s ; animation:quote 15s 30s  }
#dailyDoses.quotes li:nth-child(6) { -webkit-animation:quote 5s 45s  ; animation:quote 5s 45s   }
#dailyDoses.quotes li:nth-child(7) { -webkit-animation:quote 15s 50s  ; animation:quote 15s 50s   }
#dailyDoses.quotes li:nth-child(8) { -webkit-animation:quote 5s 65s ; animation:quote 5s 65s  }
#dailyDoses.quotes li:nth-child(9) { -webkit-animation:quote 15s 70s ; animation:quote 15s 70s  }
#dailyDoses.quotes li:nth-child(10) { -webkit-animation:quote 5s 85s ; animation:quote 5s 85s  }
#dailyDoses.quotes li:nth-child(11) { -webkit-animation:quote 15s 90s  ; animation:quote 15s 90s   }
#dailyDoses.quotes li:nth-child(12) { -webkit-animation:quote 5s 105s  ; animation:quote 5s 105s   }
#dailyDoses.quotes li:nth-child(13) { -webkit-animation:quote 15s 110s ; animation:quote 15s 110s  }
#dailyDoses.quotes li:nth-child(14) { -webkit-animation:quote 5s 125s ; animation:quote 5s 125s  }
#dailyDoses.quotes li:nth-child(15) { -webkit-animation:quote 15s 130s ; animation:quote 15s 130s  }
#dailyDoses.quotes li:nth-child(16) { -webkit-animation:endquote 146s 0s  ; animation:endquote 146s 0s   }
 
.quotes:after {
	position:absolute; 
	display:block; 
	width:0; 
	height:0; 
    bottom: 1em;
    left: -.5em;
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    content: "\f007";
    text-indent: -1em;
    font-size: 1.5em;
}
@-webkit-keyframes quote {
	0%   { opacity:0 }
	15%   { opacity:1 }
	90%  { opacity:1 }
	95%  { opacity:0 }
	}
@keyframes quote {
	0%   { opacity:0 }
	15%   { opacity:1 }
	90%  { opacity:1 }
	95%  { opacity:0 }
	}
@-webkit-keyframes slowquote {
	0%   { opacity:0 }
	20%   { opacity:0 }
	50%   { opacity:1 }
	85%  { opacity:1 }
	95%  { opacity:0 }
	}
@keyframes slowquote {
	0%   { opacity:0 }
	20%   { opacity:0 }
	50%   { opacity:1 }
	85%  { opacity:1 }
	95%  { opacity:0 }
	}
@-webkit-keyframes medquote {
	0%   { opacity:0 }
	15%   { opacity:0 }
	30%   { opacity:1 }
	85%  { opacity:1 }
	95%  { opacity:0 }
	}
@keyframes medquote {
	0%   { opacity:0 }
	15%   { opacity:0 }
	30%   { opacity:1 }
	85%  { opacity:1 }
	95%  { opacity:0 }
	}
@-webkit-keyframes endquote {
	0%   { opacity:0 }
	99%  { opacity:0 }
	100% { opacity:1 } 
	}
@keyframes endquote { 
	0%   { opacity:0 }
	99%  { opacity:0 }
	100% { opacity:1 } 
	}
#video {
	width: 100%;
	height: 29em;
	display: none;
}
	</style>
</head>
<body id="body">
	<a name="top"></a>
	<nav class="navbar navbar-dark bg-dark fixed-top admin">
		<!-- if not from right domain, email us - server-side-->
		<button class="btn-dark upload" type="button" id="startOrContinue"><i class="fa fa-podcast"></i></button>
	</nav>
	<iframe id="ga_assets" name="ga_assets" src="<?=$_SERVER['PHP_SELF']?>#iframe"></iframe>
	<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-autohide="true" data-delay="5000" data-animation="true">
		<div class="toast-header">
			<img src="https://02b7cf4.netsolhost.com/voicedestination/uploads/ssc-logo-sm.png.png" alt="...">
			<strong class="mr-auto loading">Autosaving Changes</strong>
			<input class="autosave-btn" type="checkbox" checked data-toggle="toggle" data-on="Autosave On" data-off="Autosave Off" data-onstyle="success" data-offstyle="danger" data-size="mini"data-width="200" data-height="45">
			<button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
	</div>
	<form enctype="multipart/form-data" id="form" action="<?=$_SERVER['PHP_SELF']?>" method="post" target="ga_assets">
		<input class="hidden" type="text" id="ga_submit" name="ga_submit" value="publish" />
		<datalist id="tribes">
			<option value="Tribe 1">
			<option value="Tribe 2">
			<option value="Gold Circle">
			<option value="Bronze Members">
			<option value="Platinum Club">
		</datalist>		
		<datalist id="invocations">
			<option value="Ask [...] about Acne">
			<option value="Ask [...] about Allergies">
			<option value="Ask [...] about Anxiety">
			<option value="Ask [...] about Apnea">
			<option value="Ask [...] about Arthritis">
			<option value="Ask [...] about Asthma">
			<option value="Ask [...] about Bronchitis">
			<option value="Ask [...] about Colds & Flu">
			<option value="Ask [...] about COPD">
			<option value="Ask [...] about Cystic Fibrosis">
			<option value="Ask [...] about Ear Infections">
			<option value="Ask [...] about Emphysema">
			<option value="Ask [...] about Eczema">
			<option value="Ask [...] about Fatigue">
			<option value="Ask [...] about Halotherapy">
			<option value="Ask [...] about Psoriasis">
			<option value="Ask [...] about Salt Therapy">
			<option value="Ask [...] about Sinus Infections">
			<option value="Ask [...] about Sinusitis">
			<option value="Ask [...] about Snoring">
			<option value="Ask [...] about Stress">
		</datalist>			
		<datalist id="tile1">
			<option value="Question of the Day">
			<option value="Talk to Us">
			<option value="Tell Us More">
			<option value="Today's Big Question">
		</datalist>			
		<datalist id="tile2">
			<option value="Daily Dose">
			<option value="Today's Update">
			<option value="What's New Today?">
			<option value="Today's News">
			<option value="Today's Latest">
			<option value="For Today">
		</datalist>			
		<datalist id="tile3">
			<option value="Our Background">
			<option value="Our Story">
			<option value="Our Why">
			<option value="Why We Did This">
		</datalist>			
		<datalist id="tile0">
		</datalist>		
		<iframe id="video" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
		<template id="tile">
			<details class="multiTribe site">
				<summary><input type="text" class="h3 title form-control form-control-lg" placeholder="Title" onkeyup="updateTitle();" list="tile0" autocomplete="off" /></summary>
				<div class="hero">
					<img class="card-img-top ga_image">
					<div class="overlay admin">
						<label class="btn-image multiTribe" for="image_<?=$i?>"><i class="fa fa-camera"></i></label>
						<input type="file" class="image" accept="image/*" capture="camera" onchange="document.form.submit();">
					</div>
				</div>
				<div class="card">
					<div class="card-body">
						<h4 class="card-subtitle"><input type="text" class="h4 subtitle form-control form-control-lg" placeholder="Subtitle" /></h4>
						<fieldset class="accordion">
						  <input type="checkbox" name="collapse" class="handlee">
						  <h2 class="handle">
						    <label class="handler"><a class="collapser text-read btn btn-primary">Button Text</a></label>
						  </h2>
						  <div class="collapsed admin">
							<input type="text" class="updateButton text-write form-control form-control-lg" placeholder="Button Text" />
							<input type="text" class="btn-url form-control form-control-lg" placeholder="https://..." />
							<input type="text" placeholder="YouTube embed url" class="yt url form-control form-control-lg" />
						  </div>
						</fieldset>
						<p class="card-text"><textarea class="p description" placeholder="Description"></textarea></p>						
					</div>
				</div>			
			</details>
		</template>		
		<div id="contents" class="container-fluid mt-2em">
			<div id="tiles"></div>
			<hr />
			<a class="dev addTile" href="javascript:addTile();"><i class="fa fa-plus"></i> Add Tile</a>
			<details id="terms" name="#terms">
				<summary>Terms of Service</summary>
				<div>
					<h1>Terms of Service for the "<span class="ga_option_0_title"></span>" Google Action</h1>
					<p>This Terms of Service notice discloses the terms of service for the <em  class="ga_option_0_title"></em> Google Action.</p>
					<p>Our Responsibilities. We are responsible for:</p>
					<p>(a) Customer service and claims, and communications and reporting among the individuals and entities involved in providing our Services;</p>
					<p>(b) Protecting any and all personally identifiable information (PII) you share with us;</p>
					<p>(c) Safeguarding of accounts, usernames, and passwords;</p>
					<p>(d) All necessary rights to grant the licenses in this Agreement and to provide our Services through Actions on Google;</p>
					<p>(e) Providing accurate information. All information, authorizations, and Settings we provide are complete, correct, and current;</p>
					<p>(f) Avoiding deceptive practices. We do not engage in deceptive, misleading, and/or unethical practices in connection with our Services or their promotion and will make no false or misleading representations with regard to Google or its products or services;</p>
					<p>(g) Compliance with Laws. We comply with all applicable laws, rules, and regulations in connection with Actions on Google;</p>
					<p>(h) Authorization to Act. We are authorized to act on behalf of, have bound to these Terms, and will be liable under these Terms for, each individual or entity involved in our Services.</p>
					<p>If you feel that we are not abiding by this privacy policy, you should contact us immediately via telephone at +16177856994 or via email at support@voicengage.co.</p>
				</div>
			</details>
			<details id="policy" name="#policy">
				<summary>Privacy Policy</summary>
				<div>
					<h1>Privacy Notice for the "<span class="ga_option_0_title"></span>" Google Action</h1>
					<p>This privacy notice discloses the privacy practices for the <em class="ga_option_0_title"></em> Google Action.</p>
					<p>This privacy notice applies solely to information collected by this Google Assistant Action. It will notify you of the following:</p>
					<ul>
						<li>What personally identifiable information is collected from you through the Action, how it is used and with whom it may be shared.</li>
						<li>What choices are available to you regarding the use of your data.</li>
						<li>The security procedures in place to protect the misuse of your information.</li>
						<li>How you can correct any inaccuracies in the information.</li>
					</ul>
					<h2>Information Collection, Use, and Sharing</h2>
					<p>We are the sole owners of the information collected on this Action.</p>
					<p>We only have access to/collect information that you voluntarily give us via email or other direct contact from you.</p>
					<p>We will not sell or rent this information to anyone.</p>
					<p>We will use your information to respond to you, regarding the reason you contacted us. We will not share your information with any third party outside of our organization, other than as necessary to fulfill your request, e.g. to ship an order.</p>
					<p>Unless you ask us not to, we may contact you via email in the future to tell you about specials, new products or services, or changes to this privacy policy.</p>
					<h2>Your Access to and Control Over Information</h2>
					<p>You may opt out of any future contacts from us at any time.</p>
					<p>You can do the following at any time by contacting us via support@voicengage.co or +16177856994:</p>
					<ul>
						<li>See what data we have about you, if any.</li>
						<li>Change/correct any data we have about you.</li>
						<li>Have us delete any data we have about you.</li>
						<li>Express any concern you have about our use of your data.</li>
					</ul>
					<h2>Security</h2>
					<p>We take precautions to protect your information. When you submit sensitive information via the Action, your information is protected both online and offline.</p>
					<p>Wherever we collect sensitive information (such as credit card data), that information is encrypted and transmitted to us in a secure way. You can verify this by looking for a lock icon in the address bar and looking for "https" at the beginning of the address of the Web page.</p>
					<p>While we use encryption to protect sensitive information transmitted online, we also protect your information offline. Only employees who need the information to perform a specific job (for example, billing or customer service) are granted access to personally identifiable information. The computers/servers in which we store personally identifiable information are kept in a secure environment.</p>
					<p>If you feel that we are not abiding by this privacy policy, you should contact us immediately via telephone at +16177856994 or via email at support@voicengage.co.</p>
				</div>
			</details>
			<details id="reports" name="#reports" class="admin">
				<summary>Reports</summary>
			    <div class="mt-5">
				    <div class="pieContainer">
				      <div class="pieBackground"></div>
				      <div id="pieSlice1" class="hold"><div class="pie"></div></div>
				      <div id="pieSlice2" class="hold"><div class="pie"></div></div>
				      <div id="pieSlice3" class="hold"><div class="pie"></div></div>
				      <div id="pieSlice4" class="hold"><div class="pie"></div></div>
				      <div id="pieSlice5" class="hold"><div class="pie"></div></div>
				      <div id="pieSlice6" class="hold"><div class="pie"></div></div>
				      <div class="innerCircle"><div class="content"><b>Time Spent</b><br />by<br />Section</div></div>
				    </div>
				    <div class="pieLegend">
				    	<ul>
				    		<li>Welcome</li>
				    		<li>Tile #1</li>
				    		<li>Tile #2</li>
				    		<li>Tile #3</li>
				    		<li>Comeback</li>
				    		<li>Feedback</li>
				    	</ul>
				    </div>
				    <div class="barContainer">
						<div class="graph-container">
						  <p>Welcome</p>
						  <div class="bar-graph">
						    <div class="graph-1">
						       <p>&nbsp;</p>
						    </div>
						   </div>
						</div>
						<div class="graph-container">
						  <p>Tile #1</p>
						  <div class="bar-graph">
						      <div class="graph-2">
						       <p>&nbsp;</p>
						      </div>
						  </div>
						</div>
						<div class="graph-container">
						  <p>Tile #2</p>
						  <div class="bar-graph">
						    <div class="graph-3">
						       <p>&nbsp;</p>
						    </div>
						   </div>
						</div>
						<div class="graph-container">
						  <p>Tile #3</p>
						  <div class="bar-graph">
						      <div class="graph-4">
						       <p>&nbsp;</p>
						      </div>
						  </div>
						</div>
						<div class="graph-container">
						  <p>Comeback</p>
						  <div class="bar-graph">
						    <div class="graph-5">
						       <p>&nbsp;</p>
						    </div>
						   </div>
						</div>
						<div class="graph-container">
						  <p>Feedback</p>
						  <div class="bar-graph">
						      <div class="graph-6">
						       <p>&nbsp;</p>
						      </div>
						  </div>
						</div>
					</div>
				</div>
			<?	
				//if( isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe' ) {
					$row = 1;
					if (($handle = fopen($ga_csv, "r")) !== FALSE) {
					   
					    echo '<table id="csv" class="table table-dark table-striped table-hover">';
					   
					    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
					        $num = count($data);
					        if ($row == 1) {
					            echo '<thead><tr>';
					        }else{
					            echo '<tr>';
					        }
					       
					        for ($c=0; $c < $num; $c++) {
					            //echo $data[$c] . "<br />\n";
					            if(empty($data[$c])) {
					            } else {
									
									$array = explode(',', $data[$c]); 
					            	$value = $data[$c];
									
									foreach($array as $value) {
							            if ($row == 1) {
							                echo '<th>'.$value.'</th>';
							            }else{
							                echo '<td>'.urldecode(str_replace("qs=t%3D", "", $value)).'</td>';
							            }
									}
					            }
					        }
					       
					        if ($row == 1) {
					            echo '</tr></thead><tbody>';
					        }else{
					            echo '</tr>';
					        }
					        $row++;
					    }
					   
					    echo '</tbody></table>';
					    fclose($handle);
					}
				//}
			?>
			</details>
		</div>
		<input type="hidden" id="ga_audio_filename" name="ga_audio_filename" />
		<input type="hidden" id="audio_file_prefix" name="ga_audio_file_prefix" />
		<input type="hidden" id="audio_file_suffix" name="ga_audio_file_suffix" />
	<div class="modal fade" id="publishNew" tabindex="-1" role="dialog" aria-labelledby="publishNewTitle" aria-hidden="true">
	  <div class="modal-dialog modal-dialog-centered" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h5 class="step1 admin modal-title">What's Your Name or Brand?</h5>
	        <h5 id="tileModalTitle" class="modal-title"></h5>
			<div class="modal-title step2 admin input-group mb-3">
			  <input id="ga_new_title" name="ga_new_title" value="<?=$ga_new_title?>" placeholder="Voice Destination Title" onkeyup="syncTitle();" list="tile0" autocomplete="off" type="text" class="form-control ga_option_0_title" aria-label="Voice Destination Title" required>
			  <input id="ga_file_prefix" name="ga_file_prefix" value="<?=$ga_asset_file_root?>" type="hidden">
			  <div class="input-group-append">
			    <button class="btn btn-secondary" type="button" id="refresh-title"><i class="fa fa-refresh" id="title-refresh"></i></button>
			  </div>
			</div>
	        <button type="button" class="close resetModal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	      </div>
	      <div class="modal-body">
			<select id="sound" class="step2 modal-title helpers form-control form-control-lg multiTribe admin">
				<option value="">Play / Record</option>
			    <optgroup  value="play" label="Play">
					<option value="play_0">General Phrases</option>
					<option value="play_1">Questions of the Day</option>
					<option value="play_2">Daily Doses</option>
			    </optgroup>
			    <optgroup value="record" label="Record">
					<option value="record_0">General Phrases</option>
					<option value="record_1">Questions of the Day</option>
					<option value="record_2">Daily Doses</option>
			    </optgroup>
			</select>
			<div class="sound container">
				<audio id="audio_url" class="playback" controls src="">Your browser does not support the <code>audio</code> element.</audio>
				<ul id="generalPhrases">
					<li>Ready to Say General Phrases?</li>
					<li>Press 'Start Recording' Below</li>
					<li>[Your Hello / Welcome / Greeting]</li>
					<li>[Silent Pause]</li>
					<li>"Can I send you daily updates?"</li>
					<li>[Silent Pause]</li>
					<li>"Please Choose or say one of these."</li>
					<li>[Silent Pause]</li>
					<li>"Thank you for doing that."</li>
					<li>[Silent Pause]</li>
					<li>"Alright, maybe next time."</li>
					<li>[Silent Pause]</li>
					<li>"Sorry, I didn't catch that."</li>
					<li>[Silent Pause]</li>
					<li>"Any feedback before you go?"</li>
					<li>[Silent Pause]</li>
					<li>"Thanks & See You Tomorrow!"</li>
					<li>[Silent Pause]</li>
					<li>[Your Story in 20 seconds]</li>
					<li>Hit 'Stop' then 'Full Name' & Paste</li>
				</ul>
				<ul id="questionsOfTheDay">
					<li>Ask Your Questions of the Day...</li>
					<li>Hit 'Start Recording' Below</li>
					<li>[Sunday's <span id="question_0">10-Second Question</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Monday's <span id="question_1">10-Second Question</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Tuesday's <span id="question_2">10-Second Question</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Wednesday's <span id="question_3">10-Second Question</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Thursday's <span id="question_4">10-Second Question</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Friday's <span id="question_5">10-Second Question</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Saturday's <span id="question_6">10-Second Question</span>]</li>
					<li>Hit 'Stop' then 'Full Name' & Paste</li>
				</ul>
				<ul id="dailyDoses">
					<li>Ready to do this week's Daily Doses?</li>
					<li>Hit 'Start Recording' Below</li>
					<li>[Sunday's <span id="dose_0">15-Second Daily Dose</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Monday's <span id="dose_1">15-Second Daily Dose</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Tuesday's <span id="dose_2">15-Second Daily Dose</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Wednesday's <span id="dose_3">15-Second Daily Dose</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Thursday's <span id="dose_4">15-Second Daily Dose</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Friday's <span id="dose_5">15-Second Daily Dose</span>]</li>
					<li>[Silent Pause]</li>
					<li>[Saturday's <span id="dose_6">15-Second Daily Dose</span>]</li>
					<li>Hit 'Stop' then 'Full Name' & Paste</li>
				</ul>
			</div>
			<iframe class="record" id="speakpipe" src="https://www.speakpipe.com/widget/inline/q1ch7tmaprw0uthdfeiikqoq8wv9nsfh" allow="microphone" width="100%" height="200" frameborder="0"></iframe>
			<script async src="https://www.speakpipe.com/widget/loader.js" charset="utf-8"></script>
	      	<p class="required admin">
				<div class="step1 input-group mb-3">
				  <input id="ga_brand_name_1" name="ga_brand_name_1" type="text" class="brand form-control ga_option_0_subtitle" placeholder="[Your Name / Brand / Show / Station]" onchange="updateOptions();" aria-label="Personal / Corporate Brand Name" required autocomplete="off" />
				  <div class="input-group-append">
				    <button class="step1 btn btn-secondary" type="button" id="submit-brand-1"><i class="fa fa-arrow-right" id="brand-submit-1"></i></button>
				  </div>
				</div>
			</p>
			<div id="settings" class="admin hidden">
		      	<label for="ga_brand_name_2">Personal / Corporate Brand Name</label> <small class="step1"><em>(e.g. Jane Doe)</em></small>
				<div class="input-group mb-3">
				  <input id="ga_brand_name_2" name="ga_brand_name_2" type="text" class="form-control ga_option_0_subtitle" placeholder="[Your Brand Name]" onchange="updateOptions();" aria-label="Personal / Corporate Brand Name" required autocomplete="off" />
				  <div class="input-group-append">
				    <button class="step2 btn btn-secondary" type="button" id="refresh-brand-2"><i class="fa fa-refresh" id="brand-refresh-2"></i></button>
				  </div>
				</div>
		      	<label>Daily Campaigns <small>(e.g. ACME | https://pay.com/tuesday-slot)</small></label>
				<div class="input-group mb-3">
					<div class="input-group-prepend">
					  <div id="sun" class="input-group-text campaignDay">Sunday's Campaign</div>
					</div>
					<input type="text" class="form-control campaign" id="campaign_0" placeholder="Sunday's Campaign" value="<?=$ga_tiles[0]['campaign'][0]['title']?>">
					<div class="input-group-append">
						<button class="ad btn btn-secondary" data-url="<?=$ga_tiles[0]['campaign'][0]['url']?>" type="button" id="buy_btn_0"><i class="fa fa-credit-card" id="buy_0"></i></button>
					</div>
				</div>
				<div class="input-group mb-3">
					<div class="input-group-prepend">
					  <div id="mon" class="input-group-text campaignDay">Monday's Campaign</div>
					</div>
					<input type="text" class="form-control campaign" id="campaign_1" placeholder="Monday's Campaign" value="<?=$ga_tiles[0]['campaign'][1]['title']?>">
					<div class="input-group-append">
						<button class="ad btn btn-secondary" data-url="<?=$ga_tiles[0]['campaign'][1]['url']?>" type="button" id="buy_btn_1"><i class="fa fa-credit-card" id="buy_1"></i></button>
					</div>
				</div>
				<div class="input-group mb-3">
					<div class="input-group-prepend">
					  <div id="tue" class="input-group-text campaignDay">Tuesday's Campaign</div>
					</div>
					<input type="text" class="form-control campaign" id="campaign_2" placeholder="Tuesday's Campaign" value="<?=$ga_tiles[0]['campaign'][2]['title']?>">
					<div class="input-group-append">
						<button class="ad btn btn-secondary" data-url="<?=$ga_tiles[0]['campaign'][2]['url']?>" type="button" id="buy_btn_2"><i class="fa fa-credit-card" id="buy_2"></i></button>
					</div>
				</div>
				<div class="input-group mb-3">
					<div class="input-group-prepend">
					  <div id="wed" class="input-group-text campaignDay">Wednesday's Campaign</div>
					</div>
					<input type="text" class="form-control campaign" id="campaign_3" placeholder="Wednesday's Campaign" value="<?=$ga_tiles[0]['campaign'][3]['title']?>">
					<div class="input-group-append">
						<button class="ad btn btn-secondary" data-url="<?=$ga_tiles[0]['campaign'][3]['url']?>" type="button" id="buy_btn_3"><i class="fa fa-credit-card" id="buy_3"></i></button>
					</div>
				</div>
				<div class="input-group mb-3">
					<div class="input-group-prepend">
					  <div id="thu" class="input-group-text campaignDay">Thursday's Campaign</div>
					</div>
					<input type="text" class="form-control campaign" id="campaign_4" placeholder="Thursday's Campaign" value="<?=$ga_tiles[0]['campaign'][4]['title']?>">
					<div class="input-group-append">
						<button data-buylink="https://checkout.square.site/merchant/329YQMM4263NV/checkout/3W3Z3FXOY4SP37NP45N7NCRB" class="ad btn btn-secondary" data-url="<?=$ga_tiles[0]['campaign'][4]['url']?>" type="button" id="buy_btn_4"><i class="fa fa-credit-card" id="buy_4"></i></button>
					</div>
				</div>
				<div class="input-group mb-3">
					<div class="input-group-prepend">
					  <div id="fri" class="input-group-text campaignDay">Friday's Campaign</div>
					</div>
					<input type="text" class="form-control campaign" id="campaign_5" placeholder="Friday's Campaign" value="<?=$ga_tiles[0]['campaign'][5]['title']?>">
					<div class="input-group-append">
						<button class="ad btn btn-secondary" data-url="<?=$ga_tiles[0]['campaign'][5]['url']?>" type="button" id="buy_btn_5"><i class="fa fa-credit-card" id="buy_5"></i></button>
					</div>
				</div>
				<div class="input-group mb-3">
					<div class="input-group-prepend">
					  <div id="sat" class="input-group-text campaignDay">Saturday's Campaign</div>
					</div>
					<input type="text" class="form-control campaign" id="campaign_6" placeholder="Saturday's Campaign" value="<?=$ga_tiles[0]['campaign'][6]['title']?>">
					<div class="input-group-append">
						<button class="ad btn btn-secondary" data-url="<?=$ga_tiles[0]['campaign'][6]['url']?>" type="button" id="buy_btn_6"><i class="fa fa-credit-card" id="buy_6"></i></button>
					</div>
				</div>
		      	<label class="step2" for="ga_owners">Owners</label> <small class="step2"><em>(e.g. sue@main.net, bob@acme.com)</em></small>
				<div class="step2 input-group mb-3">
				  <input id="ga_owners" name="ga_owners" value="<?=$ga_owners?>" placeholder="Voice Destination Owners" type="text" class="form-control" aria-label="Voice Destination Owners">
				  <div class="input-group-append">
				    <button class="btn btn-secondary" type="button" id="refresh-owners"><i class="fa fa-refresh" id="owners-refresh"></i></button>
				  </div>
				</div>
		      	<label class="dev" for="ga_client_id">Google Client Id</label>  <small class="dev">Google Actions IDE</small>
				<div class="dev input-group mb-3">
				  <input id="ga_client_id" name="ga_client_id" placeholder="Google Client ID" type="text" class="form-control" aria-label="Google Client ID" value="<?=$ga_client_id?>">
				  <div class="input-group-append">
				    <button class="btn btn-secondary" type="button" id="refresh-id"><i class="fa fa-refresh" id="id-refresh"></i></button>
				  </div>
				</div>
		      	<p class="step2">
		      		<em>For current Voice Destinations, image & audio updates are available in 15 minutes. Text changes & new projects go live in 72 hours.</em>
		      	</p>
				<div class="dev text-right">
					<small>
						<a id="copyUrl" href="javascript:copyUrl();">URL</a> | <a id="getWebhook" href="javascript:getWebhook();">Code</a> | <a href="https://domains.google.com/" target="_new">Domain</a> | <a href="https://search.google.com/search-console/" target="_new">Ownership</a> | <a id="copyPhrases" href="javascript:copyPhrases();">Phrases</a>
					</small>
				</div>				
			</div>
			<input id="ga_client_url" name="ga_client_url" placeholder="Voice Destination URL" type="text" class="hidden" aria-label="Voice Destination URL" value="<?=$_SERVER['PHP_SELF']?>">
			<textarea id="webhook" class="hidden">
const {
  conversation,
  Card,
  Collection,
  Simple,
  List,
  Media,
  Image,
  Table,
	Suggestion  
} = require('@assistant/conversation');
const functions = require('firebase-functions');
const destUrl = `https://02b7cf4.netsolhost.com/voicedestination/<?=$ga_asset_file_root?>.php`;
const app = conversation({
  clientId: 'GOOGLECLIENTID',
  debug: true
});
const ASSISTANT_LOGO_IMAGE = new Image({
  url: 'https://developers.google.com/assistant/assistant_96.png',
  alt: 'Google Assistant logo',
});
var OPTION = [];
OPTIONBLOCK
function url(conv, option, fileType) {
  const token = (conv.user.params.tokenPayload && conv.user.params.tokenPayload.email) ? conv.user.params.tokenPayload.email : 'xxxxxx';
  const turn = conv.session.params.turn ?  conv.session.params.turn : 0;
  const strOption = option ?  option : 'idk';
  const feedback = conv.session.params.feedback ?  conv.session.params.feedback : 'none';
  const comeback = conv.session.params.comeback ?  conv.session.params.comeback : 'none';
  let urlReq = new URL(destUrl);
  const qs = 't=' + token + '|' + turn + '|' + feedback + '|' + comeback + '|' + strOption + '|' + fileType;
  urlReq.searchParams.set('qs', qs);  
  const encodedUrl = encodeURI(urlReq);  
  return urlReq;
}
// List
app.handle('list', (conv) => {
  conv.add(`<speak><audio src="${url(conv,'menu','audio')}">Here are your options:</audio></speak>`);
  // Override prompt_option Type with display
  // information for List items.
  conv.session.typeOverrides = [{
    name: 'prompt_option',
    mode: 'TYPE_REPLACE',
    synonym: {
      entries: [
      	ENTRIESBLOCK
      ],
    },
  }];
  conv.add(new List({
    title: GA_TITLE,
    items: [
    	LISTBLOCK
    ],
  }));
  SUGGESTIONBLOCK
  conv.add(new Suggestion({'title': 'Get Daily'}));
});
// Option
app.handle('option', (conv) => {
	showOption(conv);
});
app.handle('sked', (conv) => {
	conv.scene.next.name = 'Daily_AccountLinked_DailyUpdates';
});
app.handle('hi', (conv) => {
	conv.add(`<speak><audio src="${url(conv,'welcome','audio')}">Sign up!</audio></speak>`);
});
app.handle('nav', (conv) => {
	conv.add(`<speak><audio src="${url(conv,'menu','audio')}">Menu</audio></speak>`);
});
app.handle('thx', (conv) => {
  const name = (conv.user.params.tokenPayload && conv.user.params.tokenPayload.given_name) ? `, ${conv.user.params.tokenPayload.given_name}.` : `.`;
	conv.add(`<speak><audio src="${url(conv,'thanks','audio')}">Thanks${name}</audio></speak>`);
});
app.handle('ok', (conv) => {
  const name = (conv.user.params.tokenPayload && conv.user.params.tokenPayload.given_name) ? `, ${conv.user.params.tokenPayload.given_name}.` : `.`;
	conv.add(`<speak><audio src="${url(conv,'later','audio')}">Got it${name}. Maybe another time.</audio></speak>`);
});
app.handle('ask', (conv) => {
	let strAudio;
  if (conv.session.params.comeback) {
    strAudio = 'feedback';
  } else {
    strAudio = 'comeback';
    conv.session.params.comeback = conv.request.intent.query;    
  }
  conv.add(`<speak><audio src="${url(conv,strAudio,'audio')}">Sorry, say that again?</audio></speak>`);
});
app.handle('bye', (conv) => {
  conv.session.params.feedback = conv.request.intent.query;
  const name = (conv.user.params.tokenPayload && conv.user.params.tokenPayload.given_name) ? `, ${conv.user.params.tokenPayload.given_name}.` : `.`;
	conv.add(`<speak><audio src="${url(conv,'goodbye','audio')}">Goodbye${name}</audio></speak>`);
});
function showOption(conv){
  let intOption, selectedOption, scene_text, intro_text;
  selectedOption = conv.session.params.prompt_option ? conv.session.params.prompt_option.toLowerCase().replace(/_/g, ' #') : conv.intent.name;
  intOption = parseInt(selectedOption.slice(-1));
  scene_text = (intOption > -1) ? OPTION[intOption].DESCRIPTION : `Try saying, HELPBLOCK. And if you get lost, just say, "Menu" to see all your options in one place.`;
  intro_text = (intOption > -1) ? OPTION[intOption].BUTTON_NAME : GA_TITLE;
  conv.add(`<speak><audio src="${url(conv,(intOption+1),'audio')}">${OPTION[intOption].SUBTITLE}</audio></speak>`);
  conv.add(new Card({
    'title': OPTION[intOption].TITLE,
    'text': scene_text,
    'image': new Image({
            url: url(conv, (intOption+1), 'icon'),
            alt: OPTION[intOption].TITLE
          }),
    'button': {
      'name': intro_text,
      'open': {
              'url': url(conv, intOption, 'url')
            }
    }      
  }));    
  SUGGESTIONBLOCK
  conv.add(new Suggestion({'title': 'Get Daily'}));
}
// Media
app.handle('media', (conv) => {
  conv.add('This is a media response');
  conv.add(new Media({
    mediaObjects: [
      {
        name: 'Media name',
        description: 'Media description',
        url: 'https://actions.google.com/sounds/v1/cartoon/cartoon_boing.ogg',
        image: {
          large: ASSISTANT_LOGO_IMAGE,
        }
      }
    ],
    mediaType: 'AUDIO',
    optionalMediaControls: ['PAUSED', 'STOPPED']
  }));
});
// Media Status
app.handle('media_status', (conv) => {
  const mediaStatus = conv.intent.params.MEDIA_STATUS.resolved;
  switch(mediaStatus) {
    case 'FINISHED':
      conv.add('Media has finished playing.');
      break;
    case 'FAILED':
      conv.add('Media has failed.');
      break;
    case 'PAUSED' || 'STOPPED':
      conv.add(new Media({
        mediaType: 'MEDIA_STATUS_ACK'
      }));
      break;
    default:
      conv.add('Unknown media status received.');
  }
});
exports.ActionsOnGoogleFulfillment = functions.https.onRequest(app);
				</textarea>
	      </div>
	      <div class="modal-footer step2 admin">
	        <button type="button" class="btn btn-secondary resetModal"><i class="fa fa-close"></i> Close</button>
	        <button type="button" class="btn btn-secondary" id="toggleSettings"><i class="fa fa-cog"></i> ...</button>
	        <button type="button" class="btn btn-info autosave" id="saveThings"><i class="fa fa-save"></i> <span id="saveText">Save</span></button>
	        <button type="button" class="btn btn-primary publish" id="pushOrClone"><i class="fa fa-upload"></i> <span id="pushText">Push</span></button>
	        <button type="button" class="btn btn-success new" id="createNew"><i class="fa fa-copy"></i> New</button>
	      </div>
	    </div>
	  </div>
	</div>
	</form>
<script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	<script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
	<script type="text/javascript">
	    var tiles = document.querySelector("#tiles");
	    var template = document.querySelector('#tile');
		let ga_tiles = [];
		let objCampaign = [];
		<?
			for ($j=0; $j<count($ga_tiles[0]["campaign"]); $j++) {
		?>
				objCampaign.push({
					'title' : `<?=$ga_tiles[0]["campaign"][$j]["title"]?>`,
					'url' : `<?=$ga_tiles[0]["campaign"][$j]["url"]?>`
				});
		<? 		
			}
			for ($i=0; $i<count($ga_tiles); $i++) {
		?>
			ga_tiles.push({
				'title' : `<?=$ga_tiles[$i]["title"]?>`,
				'subtitle' : `<?=$ga_tiles[$i]["subtitle"]?>`,
				'description' : `<?=$ga_tiles[$i]["description"]?>`,
				'text' : `<?=$ga_tiles[$i]["text"]?>`,
				'url' : `<?=$ga_tiles[$i]["url"]?>`,
				<? if ($i == 0) { ?>
				'video' : `<?=$ga_tiles[$i]["video"]?>`,
				'owners' : `<?=$ga_tiles[$i]["owners"]?>`,
				'client_id' : `<?=$ga_tiles[$i]["client_id"]?>`,
				'campaign' : objCampaign
				<?
					} 
				?>
			});
		<?	}?>
		function addTile() {
			let newTile = ga_tiles.length;
			ga_tiles.push({
				'title' : '',
				'subtitle' : '',
				'description' : '',
				'text' : '',
				'url' : ''
			});
			makeTile(ga_tiles[newTile],newTile);
			bind();
		}
		function bind() {
			document.querySelectorAll('#startOrContinue').forEach(item => {
			  item.addEventListener('click', event => {
			    startOrContinue(event);
			  })
			});
			document.querySelectorAll('.resetModal').forEach(item => {
			  item.addEventListener('click', event => {
			    resetModal();
			  })
			});
			document.querySelectorAll('.tileCTA').forEach(item => {
			  item.addEventListener('click', event => {
			    tileCTA(event);
			  })
			});
		}
		function init() {
			const hash = window.location.hash;
			ga_tiles.forEach((thisTile, i) => {
				makeTile(thisTile, i);
			});
	        if (hash === '#sales') {
				$('.user').hide();
			} else if (hash === '#reports') {
				$('details:not(#reports)').hide();
				$('#reports').attr('open','open');
				$('.admin').detach();
			} else if (hash === '#admin' || hash === '#new' || location.search.indexOf('qs=') > -1) {
				$('#contents').removeClass('mt-2em');
				$('#contents').addClass('mt-5em');
				$('.admin').show();
				$('.multiTribe').show();
				//$('.site').hide();
			} else if (hash === '#dev') {
				$('#contents').addClass('mt-5em');
				$('#contents').removeClass('mt-2em');
				$('.dev').removeClass('dev');
				$('.admin').show();
				$('.multiTribe').show();
			} else {
				$("#video").attr("src", $('#ga_video_0').val());				
				$('#hero_0').replaceWith($('#video'));
				$('.site,#video').show();
				<?
					for ($i=0; $i<count($ga_tiles); $i++) {
				?>
				//$('#ga_option_<?=$i?>_button').attr("href", $('#ga_option_<?=$i?>_url').val()).removeAttr('data-toggle');
				$('#ga_option_<?=$i?>_button').removeAttr('data-toggle');
				$('#ga_option_<?=$i?>_button').addClass( "tileCTA" );
				$('#ga_option_<?=$i?>_button').data( "tile", "<?=$i?>" );
				$('#ga_option_<?=$i?>_button').data( "url", ga_tiles[<?=$i?>].url );
				<?}?>
				$('#form input, #form select, #form textarea').attr('readonly', 'readonly');				
				$('.admin').detach();
		        if (hash === '#policy') {document.getElementById('policy').open = true;}
		        if (hash === '#terms') {document.getElementById('terms').open = true;}
                var link = $('<a>');
                link.attr('href', ga_tiles[0].url);
                link.attr('title', ga_tiles[0].url);
                link.text(ga_tiles[0].text);
                link.addClass('btn btn-warning cta-0');
                $(link).insertBefore('hr');
				$('details:first').find('fieldset').remove();                
			}
			if (hash !== '#iframe') {
				console.dir(ga_tiles);
				document.getElementsByTagName("details")[0].open = true;
				$('#ga_new_title').val(ga_tiles[0].title);
				$('.ga_option_0_title').text(ga_tiles[0].title);
				$('.ga_option_0_subtitle').val(ga_tiles[0].subtitle);	
				$('#ga_owners').val(ga_tiles[0].owners);
				$('.overlay.admin').not(':first').remove();
				bind();
				startOrContinue();
				updateOptions();
			}
		}
		function makeTile(thisTile, i) {
		    let tile = template.content.cloneNode(true);
		    let handler = tile.querySelector('.handler');
		    let handlee = tile.querySelector('.handlee');
		    let hero = tile.querySelector('.hero');
		    let tileTitle = tile.querySelector('.title');
		    let tileImage = tile.querySelector('.ga_image');
		    let imgInput = tile.querySelector('.image');
		    let imgUpload = tile.querySelector('.btn-image');
		    let tileSubtitle = tile.querySelector('.subtitle');
		    let tileDescription = tile.querySelector('.description');
		    let tileButton = tile.querySelectorAll('.text-read');
		    let tileText = tile.querySelectorAll('.text-write');
		    let tileUrl = tile.querySelector('.btn-url');
		    let tileVideo = tile.querySelector('.yt');
		    let tileAudio = tile.querySelector('.audio');
		    handlee.id = 'handle' + i;
		    handler.setAttribute('for', handlee.id);
		    hero.id = 'hero_' + i;
		    imgInput.id = 'image_' + i;
		    imgUpload.setAttribute('for', 'image_' + i);
		    tileTitle.id = 'ga_option_' + i + '_title';
		    tileTitle.name = tileTitle.id;
		    tileTitle.value = thisTile['title'];
			let strImage, d = new Date();
			let n = d.getDay();
			switch(i) {
			  case 1:
				strImage = 'uploads/<?=$ga_asset_file_root?>_1.jpg';
			    break;
			  case 2:
				strImage = 'uploads/<?=$ga_asset_file_root?>_2.jpg';
			    break;
			  default:
				strImage = 'uploads/<?=$ga_asset_file_root?>.jpg';
			}			
		    tileVideo.id = 'ga_video_' + i;
		    tileVideo.name = tileVideo.id;
		    tileVideo.value = thisTile['video'] ? thisTile['video'] : '';
		    tileUrl.id = 'ga_url_' + i;
		    tileUrl.name = tileUrl.id;
		    tileUrl.value = thisTile['url'] ? thisTile['url'] : '';
		    tileImage.src = strImage;
		    tileSubtitle.id = 'ga_option_' + i + '_subtitle';
		    tileSubtitle.name = tileSubtitle.id;
		    tileSubtitle.value = thisTile['subtitle'];
		    tileDescription.id = 'ga_option_' + i + '_description';
		    tileDescription.name = tileDescription.id;
		    tileDescription.value = thisTile['description'];
		    tileButton[0].id = 'ga_option_' + i + '_button';
		    tileButton[0].name = tileButton[0].id;
		    tileButton[0].textContent = thisTile['text'];
		    //tileText[0].href = '#collapse_button_' + i;
		    //tileButton[0].setAttribute('aria-controls', tileText[0].href);
		    tileText[0].id = 'ga_option_' + i + '_text';
		    tileText[0].name = tileText[0].id;
		    tileText[0].value = thisTile['text'];
		    tileText[0].dataset.tile = i;

		    tiles.appendChild(tile);
		}
		function playAudio(indexTile, arrShow) {
			let arrClips = [], audioURL, cuepoints;
			<?
				for ($i=0; $i<count($CLIP_DICT); $i++) {
					echo "arrClips[$i] = {};\n";
					foreach ($CLIP_DICT[$i] as $key => $value) {
					    echo "arrClips[$i]['$key'] = '$value';\n";
					}
				}
			?>
			switch(indexTile) {
			  case 1:
			  	cuepoints = arrClips[indexTile][<?=$day?>];
				audioURL = 'https://02b7cf4.netsolhost.com/voicedestination/uploads/<?=$ga_asset_file_root?>_1_<?=$day?>.mp3';
				break;
			  case 2:
			  	cuepoints = arrClips[indexTile][<?=$day?>];
				audioURL = 'https://02b7cf4.netsolhost.com/voicedestination/uploads/<?=$ga_asset_file_root?>_2_<?=$day?>.mp3';
				break;
			  default:
			  	cuepoints = arrClips[0]["3"];
				audioURL = 'https://02b7cf4.netsolhost.com/voicedestination/uploads/<?=$ga_asset_file_root?>_3_<?=$day?>.mp3';
			}
			let audio = document.getElementById('audio_url');
			document.getElementById('audio_url').pause();
			$('#tileModalTitle').text(ga_tiles[indexTile].title).show();
			$('.record').hide();
			$('.playback').hide();						
			$('#settings').hide();
			$(arrShow).show();
			audio.setAttribute("src", audioURL);
			audio.load();
			document.getElementById('audio_url').play();
			$('#publishNew').modal('show');
		}
		function resetModal() {
			document.getElementById('audio_url').pause();					
			$('#publishNew').modal('hide');
			$('#settings').hide();
			$('.sound.container').hide();
			$('.record').hide();
			$('#tileModalTitle').text('').hide();			
			$('.playback').hide();	
			$('#sound').prop('selectedIndex', 0);
		}
		function startOrContinue(event){
			if (event) {
				resetModal();
				$('.step1').hide();
				$('.step2').show();
				$('#publishNew').modal('show');
			} else if(!$('#ga_brand_name_1').val()) {
				$('#publishNew').modal({ backdrop: 'static', keyboard: true });				
				$('.step1').show();
				$('.step2').hide();
			} else {
				$('.step1').hide();
				$('.step2').hide();
			}
		}
		function syncTitle() {
			$('#ga_option_0_title').val($('#ga_new_title').val());
			$('.ga_option_0_title').text($('#ga_new_title').val());
		}
		function tileCTA(event){
			let tileIndex = parseInt($(event.target).data('tile'));
			let tileHref = $(event.target).data('url');
			switch(tileIndex) {
			  case 0:
			  	window.location.replace(tileHref);
			    break;
			  case 1:
				playAudio(tileIndex,'#speakpipe, .sound.container, .playback');
				break;
			  default:
			  	playAudio(tileIndex,'.sound.container, .playback');				
			}
		}
		function updateOptions() {
			let $options = $("#invocations > option").clone();
		    $options.each(function(i){$(this).val($(this).val().replace("[...]", $('#ga_brand_name_1').val()));});
			$('#tile0').html($options);			
		}
		function updateTitle() {
			$('.ga_option_0_title').text($('#ga_option_0_title').val());
			$('.ga_option_0_title').val($('#ga_option_0_title').val());			
		}
		function writeWebhook(){
			let optionBlock = [], 
				entriesBlock = [], 
				listBlock = [],
				suggestionBlock = [],
				helpBlock = [],
				ordinals = ['Zeroth', 'First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth', 'Seventh', 'Eighth', 'Ninth', 'Tenth', 'Eleventh', 'Twelfth', 'Thirteenth', 'Fourteenth', 'Fifteenth', 'Sixteenth', 'Seventeenth', 'Eighteenth', 'Nineteenth'],
				deca = ['Twent', 'Thirt', 'Fort', 'Fift', 'Sixt', 'Sevent', 'Eight', 'Ninet'];
			function stringifyNumber(n) {
			  if (n < 20) return ordinals[n];
			  if (n%10 === 0) return deca[Math.floor(n/10)-2] + 'ieth';
			  return deca[Math.floor(n/10)-2] + 'y-' + ordinals[n%10];
			}
			let ga_blocks = ga_tiles.slice(1);
			ga_blocks.forEach((thisTile, i) => {
				optionBlock.push(`\t\tOPTION[${i}] = {
									  'TITLE': \`${thisTile.title}\`, 
									  'SUBTITLE': \`${thisTile.subtitle}\`, 
									  'DESCRIPTION': \`${thisTile.description}\`, 
									  'BUTTON_NAME': \`${thisTile.text}\`
									};`);
				entriesBlock.push(`{
							          name: 'ITEM_${i}',
							          synonyms: ['Item ${i}', '${stringifyNumber(parseInt(i))} item'],
							          display: {
							            title: OPTION[${i}].TITLE,
							            description: OPTION[${i}].DESCRIPTION,
							            image: new Image({url: url(conv, ${(i+1)}, 'icon'),
							            	alt: OPTION[${i}].TITLE}),
							          },
							        }`);
			    listBlock.push(`{key: 'ITEM_${i}'}`);
				suggestionBlock.unshift(`conv.add(new Suggestion({'title': OPTION[${i}].SUBTITLE}));`);
				helpBlock.push(`"\${OPTION[${i}].SUBTITLE}"`);
			});
			helpBlock.push(`"Menu"`);
			suggestionBlock.push(`conv.add(new Suggestion({'title': 'Menu'}));`);

			const OPTIONBLOCK = optionBlock.join('\n');
			const ENTRIESBLOCK = entriesBlock.join(',\n');
			const LISTBLOCK = listBlock.join(',\n');
			const SUGGESTIONBLOCK = suggestionBlock.join('\n\t');
			const HELPBLOCK = helpBlock.join(' or ');
			let base = document.getElementById("webhook").value;
			base = base.replace('OPTIONBLOCK', OPTIONBLOCK);
			base = base.replace('ENTRIESBLOCK', ENTRIESBLOCK);
			base = base.replace('LISTBLOCK', LISTBLOCK);
			base = base.replaceAll('SUGGESTIONBLOCK', SUGGESTIONBLOCK);
			base = base.replaceAll('GA_TITLE', `"${ga_tiles[0].title}"`);
			base = base.replace('HELPBLOCK', HELPBLOCK);
			base = base.trim();
			document.getElementById("webhook").value = base;
		}
		$(document).ready(function() {
			$('.autosave').on('click',function() {
				$('.toast').toast('show');
				document.getElementById("form").target = "_self";
				document.getElementById('form').submit();
			});
			$('#sound').change(function() {
				let selected = $('#sound option:selected');
				let selectedGroup = selected.closest('optgroup').attr('value');
			    let selectedIndex = parseInt(document.getElementById('sound').selectedIndex);
			    let selectedTitle = selected.text();
			    let selectedValue = selected.val();
			    let selectedFile = (selectedValue.split('_'))[1];
				$("#ga_audio_filename").val('<?=$ga_asset_file_root?>_' + selectedFile);
				$('#generalPhrases, #questionsOfTheDay, #dailyDoses').hide();
				$('#generalPhrases, #questionsOfTheDay, #dailyDoses').removeClass('quotes');
				for (let i = 0; i < 7; i++) {
					thisQuestion = ga_tiles[0].campaign[i].title;
					$('#question_' + i).text(`  :10 Question w / ${ga_tiles[0].campaign[i].title}`);
					$('#dose_' + i).text(` :15 Update w / ${ga_tiles[0].campaign[i].title}`);
				}
				$('.sound.container').fadeIn();
				if (selectedGroup === 'record') {
					document.getElementById('audio_url').pause();					
					$('.playback').fadeOut(function(){$('.record').fadeIn();});
			        switch (selectedValue) {
			            case 'record_1':
			            	$('#questionsOfTheDay').addClass('quotes');
			            	$('#questionsOfTheDay').show();
			                break;
			            case 'record_2':
							$('#dailyDoses').addClass('quotes');
							$('#dailyDoses').show();
			                break;
			            default:
							$('#generalPhrases').addClass('quotes');
							$('#generalPhrases').show();
			        }
					const textToCopy = document.getElementById("ga_audio_filename").value;
					navigator.clipboard.writeText(textToCopy)
					  .then(() => {
					  	//$('#teleprompter').html('2. Paste in \'Your Name\' Below.');
					  })
					  .catch((error) => {
					  	//$('#teleprompter').html('Copy Name Failed. Try Again.');
					  });
				} else {
					var audioURL = 'https://02b7cf4.netsolhost.com/voicedestination/uploads/<?=$ga_asset_file_root?>_1.mp3';
					var audio = document.getElementById('audio_url');
					audio.setAttribute("src", audioURL);
					audio.load();
					document.getElementById('audio_url').play();
					$('.record').fadeOut(function(){$('.playback').fadeIn();});
				}
			});			
			$('#ga_assets').on('load',function() {
				$.each($(".ga_image"), function() {
					var imgsrc = $(this).attr("src");
					$(this).attr("src", imgsrc + "?timestamp=" + new Date().getTime());
				});				
			});
			$('#ga_new_title').on('keyup',function() {
				if ($("#ga_new_title").val() !== ga_tiles[0].client_id){
					$("#ga_client_id").val("");
					$("#pushText").html("Clone");
				} else {
					$("#ga_client_id").val(ga_tiles[0].client_id);
					$("#pushText").html("Push");
				}
			});
			$('.updateButton').on('keyup',function(event) {
				let buttonId = '#ga_option_' + event.target.dataset.tile + '_button';
				$(buttonId).text(event.target.value);
			});
			$('#ga_brand_name_1').on('keyup',function() {$("#ga_brand_name_2").val($(this).val());});
			$('#ga_brand_name_2').on('keyup',function() {$("#ga_brand_name_1").val($(this).val());});
			$('.brand').on('blur',function() {updateOptions();});
			$('.campaign').on('blur',function() {
				let arrCampaign = ($(this).val()).split('|');
				let campaignName = arrCampaign[0];
				let campaignLink = (arrCampaign[1]).trim();
				$(this).val(campaignName);
				if(campaignLink){$(this).closest('.input-group').find('.ad').data('url',campaignLink);}
			});
			$('#getWebhook').on('click',function() {
				writeWebhook();
				const textToCopy = document.getElementById("webhook").value;
				let webhook = textToCopy.replace("GOOGLECLIENTID", $('#ga_client_id').val());
				navigator.clipboard.writeText(webhook)
				  .then(() => {
				  	$('#copyUrl').html('URL');
				  	$('#copyPhrases').html('Phrases');
				  	$('#getWebhook').html('<em>Copied Code</em>');
				  })
				  .catch((error) => {
				  	$('#copyUrl').html('URL');
				  	$('#copyPhrases').html('Phrases');
				  	$('#getWebhook').html('Copy Failed');
				  })
			});
			$('#copyPhrases').on('click',function() {
				const ordinal = ['First','Second','Third','Fourth','Fifth','Sixth','Seventh','Eighth','Ninth','Tenth','Eleventh','Twelfth','Thirteenth','Fourteenth','Fifteenth','Sixteenth','Seventeenth','Eighteenth','Nineteenth','Twentieth'];
				const textToCopy = `First: |Second: |Third:`;
				navigator.clipboard.writeText(textToCopy)
				  .then(() => {
				  	$('#copyUrl').html('URL');
				  	$('#copyPhrases').html('<em>Copied Phrases</em>');
				  	$('#getWebhook').html('Code');
				  })
				  .catch((error) => {
				  	$('#copyUrl').html('URL');
				  	$('#copyPhrases').html('Copy Phrases Failed');
				  	$('#getWebhook').html('Code');
				  })
			});
			$('#copyUrl').on('click',function() {
				const textToCopy = document.getElementById("ga_client_url").value;
				navigator.clipboard.writeText(textToCopy)
				  .then(() => {
				  	$('#copyUrl').html('<em>Copied URL</em>');
				  	$('#copyPhrases').html('Phrases');
				  	$('#getWebhook').html('Code');
				  })
				  .catch((error) => {
				  	$('#copyUrl').html('Copy Failed');
				  	$('#copyPhrases').html('Phrases');
				  	$('#getWebhook').html('Code');
				  })
			});
			$('.ad').on('click',function() {
				const textToCopy = $(this).data('url');
				navigator.clipboard.writeText(textToCopy)
				  .then(() => {
				  	$('.fa-clipboard').toggleClass('fa-clipboard fa-credit-card')
				  	$(this).find('i').toggleClass('fa-credit-card fa-clipboard');
				  })
				  .catch((error) => {
				  	alert('Redirecting to URL');
				  	window.location = textToCopy;
				  })
			});
			$('#createNew').on('click',function() {
				window.location.replace('<?=$ga_vertical?>');										
			});
			$('#refresh-brand-2').on('click', function(){
		        document.getElementById('brand-refresh-2').classList.add('fa-spin');
		        setTimeout(function(){document.getElementById('brand-refresh-2').classList.remove('fa-spin');}, 500);
				$("#ga_brand_name_1").val(ga_tiles[0].subtitle);
				$("#ga_brand_name_2").val(ga_tiles[0].subtitle);
			});
			$('#refresh-id').on('click', function(){
		        document.getElementById('id-refresh').classList.add('fa-spin');
		        setTimeout(function(){document.getElementById('id-refresh').classList.remove('fa-spin');}, 500);
				$("#ga_client_id").val(ga_tiles[0].client_id);
			});
			$('#refresh-owners').on('click', function(){
		        document.getElementById('owners-refresh').classList.add('fa-spin');
		        setTimeout(function(){document.getElementById('owners-refresh').classList.remove('fa-spin');}, 500);
				$("#ga_owners").val(ga_tiles[0].owners);
			});
			$('#refresh-title').on('click', function(){
		        document.getElementById('title-refresh').classList.add('fa-spin');
		        setTimeout(function(){document.getElementById('title-refresh').classList.remove('fa-spin');}, 500);
				$("#ga_new_title").val(ga_tiles[0].title);
				$("#ga_option_0_title").val(ga_tiles[0].title);
				$('.ga_option_0_title').text(ga_tiles[0].title);
				$('.ga_option_0_title').val(ga_tiles[0].title);	
			});
			$('#pushOrClone').on('click',function() {
				if ($("#ga_new_title").val() !== '<?=$ga_asset_file_root?>'){
					$("#ga_submit").val("create");
					$("#form").attr("target", "_self");
				} else {
					$("#ga_submit").val("publish");
				}
				$("#form").submit();										
			});
			$('#submit-brand-1').on('click', function(){
		        document.getElementById('brand-submit-1').classList.add('fa-spin');
		        setTimeout(function(){document.getElementById('brand-submit-1').classList.remove('fa-spin');}, 500);
				$('#publishNew').modal('hide');
			});
			$('#toggleSettings').on('click',function() {
				$('#settings').slideDown();
				$('#sound').hide();
			});
		});
		init();
		//var abbr = "Java Script Object Notation".split(' ').map(function(item){return item[0]}).join('');
	</script>
</body>
</html>
<?}?>
