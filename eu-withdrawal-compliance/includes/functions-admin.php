<?php
/**
 * Admin loader: pulls in the columns, metaboxes and bulk-actions submodules.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once AYUDAWP_EUW_DIR . 'includes/admin/columns.php';
require_once AYUDAWP_EUW_DIR . 'includes/admin/metaboxes.php';
require_once AYUDAWP_EUW_DIR . 'includes/admin/bulk-actions.php';
require_once AYUDAWP_EUW_DIR . 'includes/admin/export.php';
