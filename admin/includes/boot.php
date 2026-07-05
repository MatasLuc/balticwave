<?php
/** Common bootstrap for all admin pages. */
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/render.php';

bw_session_start();
