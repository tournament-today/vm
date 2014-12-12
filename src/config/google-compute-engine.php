<?php
return [
	'project-id' => '',

	'service-account' => [
		'key-path' => '',
		'email-address' => ''
	],

	'machine-type' => 'f1-micro',

	// ssd disk type:
	// 'disk-type' => 'pd-ssd',
	// normal disk type:
	'disk-type' => 'pd-standard',

	'disk-source' => 'https://www.googleapis.com/compute/v1/projects/ubuntu-os-cloud/global/images/ubuntu-1404-trusty-v20141031a',
	'networking-type' => 'default',
	'access-config-type' => 'ONE_TO_ONE_NAT',
	'api-url' => 'https://www.googleapis.com/compute/v1/',
	'zone' => 'europe-west1-c'

];