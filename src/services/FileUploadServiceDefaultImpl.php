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
        list('file' => $file, 'opts' => $opts) = $fileDescriptor;
        $this->logger->debug('File => '. json_encode($file));

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $this->logger->debug("State: {$file->getError()}");
            return null;
        }

        list('id' => $id) = $opts;
        list('UPLOAD_PATH' => $directory) = getenv();

        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('image_%s_%s.%0.8s', $basename, $id, $extension);
        $file->moveTo("{$directory}/{$filename}");

        return $filename;
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }
}