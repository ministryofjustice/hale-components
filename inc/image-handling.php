<?php

defined("ABSPATH") || exit();

/**
 * Add support for Apple and other new formats
 */
add_filter("upload_mimes", function ($mimes) {
	$mimes["heic"] = "image/heic";
	$mimes["heif"] = "image/heif";
	return $mimes;
});
