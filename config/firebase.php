<?php

// Define the path to the credentials file
$credentialsPath = base_path('credentials\shortsai-b68d2-07e3adafa0c4.json');

// Check if the credentials file exists
if (!file_exists($credentialsPath)) {
    // You can either throw an exception or use die to terminate the script with an error message
    die("Credentials file not found!");
}

return [
    'credentials' => [
        'path' => $credentialsPath, // Return the valid path
    ],
];
