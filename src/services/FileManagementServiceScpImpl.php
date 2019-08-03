<?php

namespace gov\pglu\tourism\service;

use gov\pglu\tourism\service\FileManagementServiceFsImpl;

class FileManagementServiceScpImpl extends FileManagementServiceFsImpl {
    
    public function uploadFile(array $contents) {
        list('UPLOAD_PATH' => $directory) = getenv();

        $filename = parent::uploadFile($contents);
        $localFile = "{$directory}/{$filename}";
        
        list('SSH_HOST' => $host, 'SSH_USER' => $username, 'SSH_KEY' => $password, 'SSH_DIRECTORY' => $directory) = getenv();
        $remoteFile = "{$directory}/{$filename}";

        $connection = ssh2_connect($host);
        ssh2_auth_password($connection, $username, $password);
        ssh2_scp_send($connection, $localFile, $remoteFile);
        ssh2_disconnect($connection);
        $connection = null;
        unset($connection);
    }

    public function downloadFile(array $contents) {
        list('file' => $file) = $contents;
        if (strlen(trim($file)) == 0) {
            return;
        }

        list('UPLOAD_PATH' => $destination) = getenv();

        list('SSH_HOST' => $host, 'SSH_USER' => $username, 'SSH_KEY' => $password, 'SSH_DIRECTORY' => $directory) = getenv();
        $remoteFile = "{$directory}/{$file}";
        $localFile = "{$destination}/{$file}";

        $connection = ssh2_connect($host);
        ssh2_auth_password($connection, $username, $password);
        ssh2_scp_recv($connection, $remoteFile, $localFile);
        ssh2_disconnect($connection);
        $connection = null;
        unset($connection);
    }
}