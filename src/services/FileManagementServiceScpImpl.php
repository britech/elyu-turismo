<?php

namespace gov\pglu\tourism\service;

use phpseclib\Net\SFTP;
use gov\pglu\tourism\service\FileManagementServiceFsImpl;

class FileManagementServiceScpImpl extends FileManagementServiceFsImpl {
    
    public function uploadFile(array $contents) {
        list('UPLOAD_PATH' => $directory) = getenv();

        $filename = parent::uploadFile($contents);
        $localFile = "{$directory}/{$filename}";
        
        list('SSH_HOST' => $host, 'SSH_USER' => $username, 'SSH_KEY' => $password, 'SSH_DIRECTORY' => $directory) = getenv();
        $remoteFile = "{$directory}/{$filename}";

        $sftp = new SFTP($host);
        if (!$sftp->login($username, $password)) {
            throw new \Exception('Access to denied');
        }
        $sftp->put($remoteFile, $localFile);

        return $filename;
    }

    public function downloadFile(array $contents) {
        list('file' => $file) = $contents;
        if (strlen(trim($file)) == 0) {
            return;
        }

        list('SSH_HOST' => $host, 'SSH_USER' => $username, 'SSH_KEY' => $password, 
            'SSH_DIRECTORY' => $directory, 'UPLOAD_PATH' => $destination) = getenv();
        $remoteFile = "{$directory}/{$file}";
        $localFile = "{$destination}/{$file}";

        $sftp = new SFTP($host);
        if (!$sftp->login($username, $password)) {
            throw new \Exception('Access to denied');
        }
        $sftp->get($remoteFile, $localFile);
    }
}