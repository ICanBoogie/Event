<?php

namespace ICanBoogie\Event;

$hooks = __NAMESPACE__ . '\Hooks::';

return [

	'prototypes' => [

		'ICanBoogie\Core::lazy_get_events' => $hooks . 'core_lazy_get_events'

	]

];