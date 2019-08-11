<?php

namespace gov\pglu\tourism\util;

final class ApplicationConstants {

    const NOTIFICATION_KEY = "notification";
    const REST_MESSAGE_KEY = "message";
    const REPORT_TYPES = [
        'visitorCount' => 'Number of Visitors for identified Places of Interest'
    ];
    CONST INDICATOR_NUMERIC_TRUE = 1;
    CONST INDICATOR_NUMERIC_FALSE = 0;
    const DEVELOPMENT_TYPES = [
        'Major Destination', 
        'On-going Development'
    ];
    const CONTACT_TYPES = [
        'Phone',
        'Email',
        'Web',
        'Online Messaging'
    ];
}