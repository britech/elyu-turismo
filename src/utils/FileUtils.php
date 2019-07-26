<?php

namespace gov\pglu\tourism\util;

use Slim\Http\UploadedFile;

final class FileUtils {

    public static function uploadFile(UploadedFile $file, array $opts) {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return null;
        }

        list('directory' => $directory, 'id' => $id) = $opts;

        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('image_%s_%s.%0.8s', $basename, $id, $extension);
        $file->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }
}
