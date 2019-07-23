<?php

namespace gov\pglu\tourism\util;

final class ApplicationConstants {

    const NOTIFICATION_KEY = "notification";
    const REST_MESSAGE_KEY = "message";
    const REPORT_TYPES = [
        'visitorCount' => 'Number of Visitors for identified Places of Interest',
        'ratingSummary' => 'Average Rating for identified Places of Interest'
    ];
}