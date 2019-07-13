<?php
/**
 * Config - the Module's specific Configuration.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */


return array(
    'throttle' => array(
        'lockoutTime' => 1, // In minutes.
        'maxAttempts' => 5,
    ),

    'tokens' => array(
        'verify' => array(
            'validity' => 60, // In minutes.
        ),

        'login' => array(
            'validity' => 15, // In minutes.
        ),
    ),
);
