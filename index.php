<?php

/*
 * Subfolder entry point.
 *
 * Serving the app through this file (rather than rewriting straight to
 * public/index.php) keeps SCRIPT_NAME at /ingo/index.php, which is what lets
 * Symfony work out that the application is based at /ingo. Rewriting directly
 * into public/ makes Laravel see the URI as /ingo/login with no base path, and
 * every route 404s. Same approach as the other subfolder apps in htdocs.
 */

require __DIR__.'/public/index.php';
