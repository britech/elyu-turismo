<?php

namespace gov\pglu\tourism\service;

use gov\pglu\tourism\service\FileManagementService;


class FileManagementServiceFsImpl implements FileManagementService {

    public function uploadFile(array $contents) {
        list('file' => $file, 'opts' => $opts) = $contents;

        if ($file->getError() !== UPLOAD_ERR_OK) {
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

    public function downloadFile(array $contents) {
        return;   
    }
}