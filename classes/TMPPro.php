<?php

namespace TMPPro;

class TMPPro {

    protected $userName;
    protected $password;
    public $tmpToken;
    public $loginUrl;
    public $downloadXmlUrl;
    public $downloadXmlLinkText;
    public $linkToXmlFile;
    public $folderNameToSave;
    public $cookieFolder;
    public $cookieFileName;
    public $absPath;
    public $currentDateInFileName;
    public $startTime;
    public static $iframeDefaultSrc = 'https://tmppro.com/build_and_compare/';

    public function __construct(
        string $userName,
        string $password,
        string $loginUrl,
        string $downloadXmlUrl,
        string $downloadXmlLinkText,
        string $folderNameToSave,
        string $cookieFolder,
        string $cookieFileName
    )
    {
        $this->userName = $userName;
        $this->password = $password;
        $this->loginUrl = $loginUrl;
        $this->downloadXmlUrl = $downloadXmlUrl;
        $this->downloadXmlLinkText = $downloadXmlLinkText;
        $this->folderNameToSave = $folderNameToSave;
        $this->cookieFolder = $cookieFolder;
        $this->cookieFileName = $cookieFileName;
        $this->absPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..';
        $this->currentDateInFileName = str_replace(' ', '_', Date('Y-m-d H-i-s'));
        $this->startTime = microtime(true);
    }

    public function getUserName()
    {
        return $this->userName;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function compressParams($postParams)
    {
        $outputStr = '';
        foreach ($postParams as $key => $value) {
            if ($outputStr == "") $outputStr = $key . '=' . $value;
            else $outputStr = $outputStr . '&' . $key . '=' . $value . '&';
        }
        return $outputStr;
    }

    public function getUrlByCurl($url, $post = false, $postData = false, $headers = false)
    {
        $timeout = 60;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->absPath . DIRECTORY_SEPARATOR . $this->cookieFolder . DIRECTORY_SEPARATOR . $this->cookieFileName);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->absPath . DIRECTORY_SEPARATOR . $this->cookieFolder . DIRECTORY_SEPARATOR . $this->cookieFileName);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36");

        if ($post) curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($postData)) curl_setopt($ch, CURLOPT_POSTFIELDS, $this->compressParams($postData));
        if (is_array($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    public function downloadXmlFile($url, $pathToSave)
    {
        $fp = fopen($pathToSave, 'w+');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    public function cutTmpToken($html)
    {
        preg_match('/name=\"tmp_token\"\svalue=\"(.*)\"/Us', $html, $tmpTokenMatches);
        return (isset($tmpTokenMatches[1]) && $tmpTokenMatches[1] != '') ? trim($tmpTokenMatches[1]) : false;
    }

    public function checkLoginResult($html)
    {
        return (preg_match('/<h1>Welcome,\s' . $this->getUserName() . '<\/h1>/Us', $html, $loginResultMatches)) ? true : false;
    }

    public function searchForDownloadXmlLink($html)
    {
        preg_match('/<a\shref=\"(.*)\"\sclass=\"underline\">' . $this->downloadXmlLinkText . '<\/a>/Us', $html, $downloadXmlLinkMatches);
        return (isset($downloadXmlLinkMatches[1]) && $downloadXmlLinkMatches[1] != '') ? trim($downloadXmlLinkMatches[1]) : false;
    }

    public function removeOldZipAndXml()
    {
        $dir = scandir($this->absPath . DIRECTORY_SEPARATOR . $this->folderNameToSave);
        foreach ($dir as $file) {
            if ($file == '.' || $file == '..') continue;
            else if (
                strpos($file, '.zip') !== false
                || strpos($file, '.xml') !== false
            ) {
                unlink($this->absPath . DIRECTORY_SEPARATOR . $this->folderNameToSave . DIRECTORY_SEPARATOR . $file);
            }
        }
    }

    public function downloadXml()
    {
        $this->generateTmpToken();
        $this->loginOnWebsite();
        $this->getDownloadPage();
        $this->downloadXmlToFolder();
        $this->UnzipDownloadedXml();
    }

    public function generateTmpToken()
    {
       $html = $this->getUrlByCurl($this->loginUrl);
       if ($this->cutTmpToken($html)) {
           $this->tmpToken = $this->cutTmpToken($html);
       }
       else throw new \Exception('Tmp token not found.');
    }

    public function loginOnWebsite()
    {
        $html = $this->getUrlByCurl(
            $this->loginUrl,
            true,
            ['user_name' => $this->getUserName(),
            'password' => $this->getPassword(),
            'login' => '',
            'tmp_token' => $this->tmpToken]
        );

        if (!$this->checkLoginResult($html)) throw new \Exception('Unable to log in.');
    }

    public function getDownloadPage()
    {
        $downloadPage = $this->getUrlByCurl(
            $this->downloadXmlUrl,
            false,
            false,
            ['Referer: https://tmppro.com/my_tmp/product_export']
        );

        $this->linkToXmlFile = $this->searchForDownloadXmlLink($downloadPage);
        if (!$this->linkToXmlFile) throw new \Exception('Xml file download link not found.');
    }

    public function downloadXmlToFolder()
    {
        $pathToSaveZip = $this->absPath . DIRECTORY_SEPARATOR . $this->folderNameToSave . DIRECTORY_SEPARATOR . $this->currentDateInFileName . '.zip';
        $fullLinkToXmlFile = self::$iframeDefaultSrc . $this->linkToXmlFile;
        $this->downloadXmlFile($fullLinkToXmlFile, $pathToSaveZip);

        if (!file_exists($pathToSaveZip) || filesize($pathToSaveZip) == 0) throw new \Exception('Xml file has not been downloaded.');
    }

    public function UnzipDownloadedXml()
    {
        $pathToZip = $this->absPath . DIRECTORY_SEPARATOR . $this->folderNameToSave . DIRECTORY_SEPARATOR . $this->currentDateInFileName . '.zip';
        $pathToXml = $this->absPath . DIRECTORY_SEPARATOR . $this->folderNameToSave;

        $zip = new \ZipArchive;

        if ($zip->open($pathToZip) === true) {

            $zip->extractTo($pathToXml);
            $endMsg = 'File ' . $this->currentDateInFileName . '.zip' . ' downloaded in ' . round(microtime(true) - $this->startTime, 2) . ' seconds and successfully unzipped (' . $zip->getNameIndex(0) . ').';
            $zip->close();
        }
        else throw new \Exception('Error unzipping file.');

        echo $endMsg;
    }
}

?>