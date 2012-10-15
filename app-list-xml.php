<?php

define('IN_MEM_FILE_SIZE', 1048576);
define('VERBOSE', true);

$archiveName = readCommandLine();
$zip = openZipFile($archiveName);
$appList = getAppListXml();


for ($i = 0; $i < $zip->numFiles; ++$i) {

    $fileStat = $zip->statIndex($i);
    $fileName = $fileStat['name'];

    // I am foolproof.
    if ('APP-LIST.xml' == $fileName) {
        message("File $archiveName already contains APP-LIST.xml. It will be replaced.");
        continue;
    }


    // Skip entries corresponding to directories without content.
    // A directory entry is defined to be one whose name ends with a '/'.
    // http://docs.oracle.com/javase/6/docs/api/java/util/zip/ZipEntry.html#isDirectory%28%29
    // Consider ending with a '\' to be also directories, just in case.

    $endsWith = $fileName[strlen($fileName) - 1];
    if ('/' == $endsWith || '\\' == $endsWith) {
        continue;
    }

    // Read small files into memory, extract big files to disk.
    $fileSize = $fileStat['size'];

    if ($fileSize < IN_MEM_FILE_SIZE) {

        // Read file contents directly into memory
        $fp = $zip->getStream($fileName);
        if (!$fp) {
            die("Can not get zip stream reader for $archiveName#$fileName\n");
        }

        $fileContents = '';
        while (!feof($fp)) {
            $fileContents .= fread($fp, 1024);
        }

        fclose($fp);

        $sha256sum = hash("sha256", $fileContents);

    } else {

        // Assume file is too big to fit in memory
        $tmpDir = tempDir();
        $zip->extractTo($tmpDir, $fileName);
        $resultingFile = $tmpDir . '/' . $fileName;
        $sha256sum = hash_file("sha256", $resultingFile);
        deleteDir($tmpDir);
    }

    $file = $appList->addChild('file');
    $file->addAttribute("sha256", $sha256sum);
    $file->addAttribute("size", $fileSize);
    $file->addAttribute("name", $fileName);

}

// PUT APP-LIST.xml
$dom = new DOMDocument;
$dom->preserveWhiteSpace = FALSE;
$dom->loadXML($appList->asXML());
$dom->formatOutput = TRUE;

message("Resulting APP-LIST.xml:");
message($dom->saveXml());

$zip->addFromString("APP-LIST.xml", $dom->saveXml());


$zip->close();

exit(0);

function readCommandLine()
{
    global $argv;

    if (is_array($argv)) {
        $commandLine = $argv;
    } else if (@is_array($_SERVER['argv'])) {
        $commandLine = $_SERVER['argv'];
    } else {
        die("Can not read command-line options.");
    }

    $usageString = <<<EOU

Usage: {$commandLine[0]} <package.app.zip>

Adds or updates APP-LIST.xml file to the specified archive. See
http://www.apsstandard.org/doc/package-format-specification-1.2/index.html
for additional details.


EOU;
    switch (sizeof($commandLine)) {
        case 1:
            echo $usageString;
            exit(0);
        case 2:
            $sourceFile = $commandLine[1];
            break;
        default:
            echo $usageString;
            exit(2);
    }

    return $sourceFile;
}

function message($message)
{
    if (VERBOSE) {
        echo $message . "\n";
    }
}

function openZipFile($archiveName)
{
    $zip = new ZipArchive();
    $returnCode = $zip->open($archiveName);
    if (true !== $returnCode) {
        die("Can not open $archiveName: $returnCode");
    }
    message("File $archiveName successfully opened");
    message("numFiles: " . $zip->numFiles);
    message("status: " . $zip->status);
    message("statusSys: " . $zip->statusSys);
    message("filename: " . $zip->filename);
    message("comment: " . $zip->comment);

    return $zip;
}

function getAppListXml()
{
    $xmlStr = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <files xmlns="http://apstandard.com/ns/1" xmlns:ns2="http://www.w3.org/2000/09/xmldsig#">
    </files>
XML;

    return new SimpleXMLElement($xmlStr);
}

function tempDir()
{
    $tmpName = tempnam(sys_get_temp_dir(), '');
    if (file_exists($tmpName)) {
        unlink($tmpName);
    }
    mkdir($tmpName);
    if (!is_dir($tmpName)) {
        die("Can not create temporary directory $tmpName");

    }
    return $tmpName;
}

function deleteDir($tmpDir)
{
    $it = new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {

        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }

    }
    rmdir($tmpDir);
}

