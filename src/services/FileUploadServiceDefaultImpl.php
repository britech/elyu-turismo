<?php

namespace gov\pglu\tourism\service;

use Monolog\Logger;
use gov\pglu\tourism\service\FileUploadService;

/**
 * @property Logger $logger
 */
class FileUploadServiceDefaultImpl implements FileUploadService {

    private $logger;

    public function uploadFile(array $fileDescriptor) {
        list('file' => $file) = $fileDescriptor;
        if (is_null($file)) {
            return null;
        }

        $this->logger->debug('File => '. json_encode($file));

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $this->logger->debug("State: {$file->getError()}");
            return null;
        }

        list('UPLOAD_PATH' => $directory) = getenv();

        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('image_%s.%0.8s', $basename, $extension);
        $file->moveTo("{$directory}/{$filename}");

        return $filename;
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }

    public function __get($name) {
        return $this->$name;
    }
}