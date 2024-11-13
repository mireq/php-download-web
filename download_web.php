<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

ini_set('max_execution_time', '3600');
ini_set('set_time_limit', '0');
set_time_limit(3600);

$exclude = array('_test');

function addFileToTar($tar, $filePath, $archivePath) {
	global $exclude;

	if (in_array($archivePath, $exclude)) {
		return;
	}

	$current = array(
		'name' => $filePath,
		'name2' => $archivePath,
		'stat' => stat($filePath),
	);

	if(strlen($current['name2']) > 99)
	{
		$Path = substr($current['name2'],0,strpos($current['name2'],"/",strlen($current['name2']) - 100) + 1);
		$current['name2'] = substr($current['name2'],strlen($Path));
		if(strlen($Path) > 154 || strlen($current['name2']) > 99)
		{
			return;
		}
	}
	$block = pack("a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",$current['name2'],decoct($current['stat'][2]),
		sprintf("%6s ",decoct($current['stat'][4])),sprintf("%6s ",decoct($current['stat'][5])),
		sprintf("%11s ",decoct($current['stat'][7])),sprintf("%11s ",decoct($current['stat'][9])),
		"        ","0","","ustar","00","Unknown","Unknown","","",!empty($Path)? $Path : "","");

	$checksum = 0;
	for($i = 0; $i < 512; $i++)
	{
		$checksum += ord(substr($block,$i,1));
	}
	$checksum = pack("a8",sprintf("%6s ",decoct($checksum)));
	$block = substr_replace($block,$checksum,148,8);

	if($current['stat'][7] == 0)
	{
		fwrite($tar, $block);
	}
	else if($fp = @fopen($current['name'],"rb"))
	{
		fwrite($tar, $block);
		while($temp = fread($fp,1048576))
		{
			fwrite($tar, $temp);
		}
		if($current['stat'][7] % 512 > 0)
		{
			$temp = "";
			for($i = 0; $i < 512 - $current['stat'][7] % 512; $i++)
			{
				$temp .= "\0";
			}
			fwrite($tar, $temp);
		}
		fclose($fp);
	}
}



$filter = function ($file, $key, $iterator) use ($exclude) {
	if ($iterator->hasChildren() && !in_array($file->getFilename(), $exclude)) {
		return true;
	}
	return $file->isFile();
};


$directory = dirname(__FILE__);
//unlink($directory . '/web.tar');


$innerIterator = new RecursiveDirectoryIterator(
	$directory,
	RecursiveDirectoryIterator::SKIP_DOTS
);
$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator($innerIterator, $filter)
);


//$tar = fopen($directory . '/web.tar', 'wb');
$tar = fopen('php://output', 'wb');

foreach ($iterator as $file) {
	$localPath = 'web/'. str_replace($directory . '/', '', $file);
	addFileToTar($tar, $file->getPathname(), $localPath);
}

// Write two 512-byte blocks of nulls to signify end of archive
fwrite($tar, str_repeat("\0", 1024));

fclose($tar);

