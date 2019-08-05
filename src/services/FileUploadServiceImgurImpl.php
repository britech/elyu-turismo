<?php

namespace gov\pglu\tourism\service;

use Monolog\Logger;
use gov\pglu\tourism\service\FileUploadServiceDefaultImpl;

/**
 * @property Logger $logger
 */
class FileUploadServiceImgurImpl extends FileUploadServiceDefaultImpl {

    private $logger;

    public function uploadFile(array $fileDescriptor) {
        list('image' => $image) = json_decode(parent::uploadFile($fileDescriptor), true);
        $file = self::convertImageToBase64($image);
        
        list('name' => $name) = $fileDescriptor;
        list('IMGUR_URL' => $url, 'IMGUR_CLIENT_ID' => $clientId) = getenv();
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => [
                'image' => $file,
                'title' => $name
            ],
            CURLOPT_HTTPHEADER => ["Authorization: Client-ID {$clientId}"]
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            $this->logger->error($error);
            return null;
        } else {
            $jsonResponse = json_decode($response, true);
            $this->logger->debug('Result'. json_encode($jsonResponse));
            list('data' => $data) = $jsonResponse;
            list('link' => $link, 'deletehash' => $imageId) = $data;
            return json_encode([
                'image' => $link,
                'id' => $imageId
            ]);
        }
    }

    private static function convertImageToBase64($file) {
        list('UPLOAD_PATH' => $uploadDirectory) = getenv();

        $uploadedFile = "{$uploadDirectory}/{$file}";
        $image = file_get_contents($uploadedFile);
        return base64_encode($image);
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }

    public function __get($name) {
        return $this->$name;
    }
}