<?php

namespace gov\pglu\tourism\service;

use Monolog\Logger;
use gov\pglu\tourism\service\FileUploadServiceDefaultImpl;

/**
 * @property Logger $logger
 */
class FileUploadServiceImgurImpl extends FileUploadServiceDefaultImpl {

    public function uploadFile(array $fileDescriptor) {
        $file = self::convertImageToBase64(parent::uploadFile($fileDescriptor));
        if (is_null($file)) {
            return null;
        }
        
        list('name' => $name) = $fileDescriptor;
        list('IMGUR_URL' => $url, 'IMGUR_ACCESS_TOKEN' => $token) = getenv();
        
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
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"]
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            $this->logger->error($error);
            return null;
        } else {
            $jsonResponse = json_decode($response, true);
            $this->logger->debug(json_encode($jsonResponse));
            list('data' => $data) = $jsonResponse;
            $this->logger->debug(json_encode($data));
            list('link' => $link) = $data;
            $this->logger->debug(json_encode($link));
            return $link;
        }
    }

    private static function convertImageToBase64($file) {
        list('UPLOAD_PATH' => $uploadDirectory) = getenv();

        $uploadedFile = "{$uploadDirectory}/{$file}";
        $image = file_get_contents($uploadedFile);
        return base64_encode($image);
    }
}