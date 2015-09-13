<?php
	require_once  __DIR__ . '/../../app/bootstrap.php';
	
	$uri = $conf->get('base_uri');
	$pos = strrpos($uri, '/');
	
	if ($pos !== false) {
		$uri = substr($uri, 0, $pos);
	}
?>
(function() {
    'use strict';
	
	angular.module('app.config', []);
	
	angular.module('app.config').constant('appConfig', {
		'baseUri': '<?php echo $uri; ?>',
		'encrypted_file_ext': '<?php echo $conf->get('encrypted_file_ext'); ?>',
		'version': 0.2
	});
})();