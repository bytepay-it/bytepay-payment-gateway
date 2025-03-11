<?php
// config.php

// Determine SIP protocol based on the site's protocol
define('BYTEPAY_SIP_PROTOCOL', is_ssl() ? 'https://' : 'http://');
define('BYTEPAY_SIP_HOST', 'www.bytepay.it');
