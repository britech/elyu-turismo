<?php

namespace gov\pglu\tourism\service;

use gov\pglu\tourism\service\FileUploadService;

class FileUploadServiceDefaultImpl implements FileUploadService {

    public function uploadFile(array $fileDescriptor) {
        list('file' => $file, 'opts' => $opts) = $fileDescriptor;

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return null;
        }

        list('id' => $id) = $opts;
        list('UPLOAD_PATH' => $directory) = getenv();

        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('image_%s_%s.%0.8s', $basename, $id, $extension);
        $file->moveTo("{$directory}/{$filename}");

        return json_encode([
            'image' => $filename
        ]);
    }
}