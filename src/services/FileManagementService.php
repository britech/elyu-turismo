<?php

namespace gov\pglu\tourism\service;

interface FileManagementService {

    public function uploadFile(array $contents);
    
    public function downloadFile(array $contents);
}